<?php

declare(strict_types=1);

/*
 * @copyright  trilobit GmbH
 * @author     trilobit GmbH <https://github.com/trilobit-gmbh>
 * @license    LGPL-3.0-or-later
 * @link       http://github.com/trilobit-gmbh/contao-refresh-bundle
 */

namespace Trilobit\RefreshBundle\Maintenance;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Controller;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Environment;
use Contao\Input;
use Contao\Message;
use Contao\SelectMenu;
use Contao\System;
use Contao\Widget;
use Psr\Log\LogLevel;
use Symfony\Component\Process\Process;

class RefreshMaintenance extends Backend implements \executable
{
    public function isActive(): bool
    {
        return 'tl_refreshtarget' === Input::post('FORM_SUBMIT') && !empty(Input::post('environments'));
    }

    public function run(): ?string
    {
        Controller::loadLanguageFile('tl_maintenance');

        $config = System::getContainer()->getParameter('trilobit_refresh')['config'];

        $logger = System::getContainer()->get('monolog.logger.contao');

        // Hide the crawler in maintenance mode (see #1379)
        try {
            $driver = System::getContainer()->get('lexik_maintenance.driver.factory')->getDriver();
            $blnMaintenance = $driver->isExists();
        } catch (\Exception $e) {
            $blnMaintenance = false;
        }

        $widget = null;
        if (!empty($config) && !empty($config['environments'])) {
            $widget = $this->generateWidget($config);
        }

        /** @var BackendTemplate|object $template */
        $template = new BackendTemplate('be_refreshtarget');

        $template->isMaintenance = $blnMaintenance;
        $template->maintenance = $GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['maintenance'];
        $template->isActive = $this->isActive();
        $template->active = $GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['active'];

        $template->headline = $GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['headline'];
        $template->description = $GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['description'];
        if (empty($config) || empty($config['environments'])) {
            $template->description .= '<br><br>'.$GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['error'].'<br><br>';
        }

        $template->message = Message::generateUnwrapped(__CLASS__);

        $template->action = ampersand(Environment::get('request'));
        $template->widget = $widget;
        $template->info = $GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['environments'][1];
        $template->submit = $GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['submit'];
        $template->confirm = htmlspecialchars(json_encode($GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['confirm']));

        if (!$this->isActive()) {
            return $template->parse();
        }

        $environment = $config['environments'][Input::post('environments')];

        $queue = [Input::post('environments')];

        echo '<pre>';

        foreach ($environment['steps'] as $count => $step) {
            $description = array_key_first($step);

            $shellCommandLine = $step[$description];
            $shellCommandLine = $this->replaceSimpleTokens($shellCommandLine, $environment, $config);

            $process = new Process([$shellCommandLine]);
            $process = Process::fromShellCommandline($shellCommandLine);
            $process->run();

            $process->wait();

            $successful = $process->isSuccessful();

            $queue[] = '→ '.$description.' [#'.++$count.' / '. 1 !== $successful ? $successful : 'OK' .']';

            $logger->log(
                LogLevel::INFO,
                $description,
                ['contao' => new ContaoContext(__METHOD__, 'REFRESH_TARGET')]
            );
        }

        Message::addConfirmation($GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['done'].'</p><p class="tl_info">'.implode('<br>', $queue).'</p>', __CLASS__);
        $logger->log(
            LogLevel::INFO,
            'Refresh done',
            ['contao' => new ContaoContext(__METHOD__, 'REPOSITORY')]
        );

        $this->reload();

        return null;
    }

    private function replaceSimpleTokens(string $cmd = '', array $environment = [], array $config = [])
    {
        preg_match_all('/##(.*?)##/', $cmd, $matches);

        foreach ($matches[0] as $key => $search) {
            $key = $matches[1][$key];

            if (2 === strpos($search, 'exclude.database.tables')) {
                $db_name = null;

                if (1 === preg_match('/^##(.*?)\|(source|target)\.(.*?)##$/', $search, $match)) {
                    $db_name = $environment[$match[2]][$match[3]];
                    //$search = '##'.$match[1].'##';
                }

                if (null === $db_name) {
                    Message::addError('unknow database for "exclude.database.tables"', __CLASS__);
                    $this->reload();
                }

                $result = array_map(
                    (static function($item) use ($db_name) {
                        return '--ignore-table='.$db_name.'.'.$item;
                    }),
                    $environment['exclude']['database']['tables']
                );
                $replace = implode(' ', $result);
            } elseif (1 === preg_match('/^##(source|target)\.(.*?)##$/', $search, $match)) {
                $replace = $environment[$match[1]][$match[2]];
            } elseif (!empty($environment[$key])) {
                $replace = $environment[$key];
            } else {
                $replace = $config[$key];
            }

            if (null === $replace) {
                Message::addError('unknow simpletoken "'.$key.'"', __CLASS__);
                $this->reload();
            }

            $cmd = str_replace($search, $replace, $cmd);
        }

        if (preg_match('/##(.*?)##/', $cmd, $match)) {
            $cmd = $this->replaceSimpleTokens($cmd, $environment, $config);
        }

        return str_replace('  ', ' ', $cmd);
    }

    private function generateWidget(array $config = []): Widget
    {
        $name = 'environments';

        $widget = new SelectMenu();
        $widget->id = $name;
        $widget->name = $name;
        $widget->label = $GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['environments'][0];
        $widget->setInputCallback($this->getInputCallback($name));

        $options = [['value' => '', 'label' => '-', 'default' => true]];

        foreach ($config['environments'] as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label['name'],
            ];
        }

        $widget->options = $options;

        if ($this->isActive()) {
            $widget->validate();

            if ($widget->hasErrors()) {
                $this->valid = false;
            }
        }

        return $widget;
    }

    private function getInputCallback(string $name): \Closure
    {
        return static function() use ($name) {
            return Input::get($name);
        };
    }
}
