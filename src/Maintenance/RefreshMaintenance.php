<?php

declare(strict_types=1);

/*
 * @copyright  trilobit GmbH
 * @author     trilobit GmbH <https://github.com/trilobit-gmbh>
 * @license    LGPL-3.0-or-later
 */

namespace Trilobit\RefreshBundle\Maintenance;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Date;
use Contao\Environment;
use Contao\Input;
use Contao\MaintenanceModuleInterface;
use Contao\Message;
use Contao\SelectMenu;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Process\Process;

class RefreshMaintenance implements MaintenanceModuleInterface
{
    private bool $valid = true;
    private string $name = 'task';
    private $microtask;

    public function __construct(
        ContaoFramework $framework,
        LoggerInterface $logger,
    ) {
        $this->logger = $logger;
        $this->framework = $framework;
        $this->framework->initialize();

        $this->container = System::getContainer();
        $this->microtask = $this->container->getParameter('trilobit_refresh')['config'];
        $this->rootDir = $this->container->getParameter('kernel.project_dir');
    }

    public function isActive(): bool
    {
        return 'refreshtarget' === Input::get('act') && !empty(Input::get($this->name)) && $this->valid;
    }

    /**
     * @throws \JsonException
     */
    public function run(): ?string
    {
        try {
            $driver = $this->container->get('lexik_maintenance.driver.factory')->getDriver();
            $blnMaintenance = $driver->isExists();
        } catch (\Exception $e) {
            $blnMaintenance = false;
        }

        Controller::loadLanguageFile('tl_maintenance');

        $this->taskWidget = null;
        if (!empty($this->microtask)) {
            if (($task = Input::get($this->name)) && !empty($task)) {
                Input::setPost($this->name, $task);
            }

            $this->taskWidget = $this->generateWidget();
        }

        /** @var BackendTemplate|object $template */
        $template = new BackendTemplate('be_refreshtarget');

        $template->isMaintenance = $blnMaintenance;
        $template->maintenance = $GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['maintenance'];
        $template->isActive = $this->isActive();
        $template->active = $GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['active'];

        $template->headline = $GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['headline'];
        $template->description = $GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['description'];

        if (empty($this->microtask) || empty($this->microtask['environments'])) {
            $template->description .= '<br><br>'.$GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['error'].'<br><br>';
        }

        $template->message = Message::generateUnwrapped(__CLASS__);

        $template->action = StringUtil::ampersand(Environment::get('request'));
        $template->widget = $this->taskWidget;
        $template->info = $GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['environments'][1];
        $template->submit = $GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['submit'];
        $template->confirm = htmlspecialchars(json_encode($GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['confirm'], \JSON_THROW_ON_ERROR));

        if (!$this->isActive()) {
            return $template->parse();
        }

        $template->isRunning = true;

        $queue = [$this->taskWidget->value];

        $steps = \count($this->microtask['environments'][$this->taskWidget->value]['steps']);
        $length = \strlen((string) $steps);
        $date = Date::parse('Y-m-d');

        foreach ($this->microtask['environments'][$this->taskWidget->value]['steps'] as $key => $value) {
            $description = array_key_first($value);

            // $process = new Process([$shellCommandLine]);
            $process = Process::fromShellCommandline($this->replaceSimpleTokens($value[$description]));
            $process->run();

            file_put_contents(
                $this->rootDir.'/var/logs/refreshtarget-'.$this->taskWidget->value.'-'.$date.'.log',
                '['.Date::parse('Y-m-d').'T'.Date::parse('H:i:s').'.000000+00:00]'
                .' request.INFO: '.$description.'.'
                .' {"response":"'.trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n", '', '\\'], [' ', ' ', '', '\\'], $process->getOutput()))).'"}'
                ."\n",
                \FILE_APPEND
            );

            $process->wait();

            $queue[] = '<span class="small">#'.\sprintf('%0'.$length.'d', ++$key).'</span> → '.$description;

            $this->logger->log(
                LogLevel::INFO,
                $this->taskWidget->value.': ('.\sprintf('%0'.$length.'d', $key).'/'.$steps.') '.$description,
                ['contao' => new ContaoContext(__METHOD__, 'REFRESH_TARGET')]
            );
        }

        Message::addConfirmation($GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['done'].'</p><p class="tl_info">'.implode('<br>', $queue).'</p>', __CLASS__);

        $this->logger->log(
            LogLevel::INFO,
            'Refresh done',
            ['contao' => new ContaoContext(__METHOD__, 'REPOSITORY')]
        );

        $this->redirectToMaintenance();
    }

    private function generateWidget(): Widget
    {
        $widget = new SelectMenu();
        $widget->id = $this->name;
        $widget->name = $this->name;
        $widget->label = $GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['environments'][0];
        $widget->setInputCallback($this->getInputCallback($this->name));

        $options = [['value' => '', 'label' => '-', 'default' => true]];

        foreach ($this->microtask['environments'] as $value => $label) {
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

    private function replaceSimpleTokens(string $cmd = ''): string
    {
        $environment = $this->microtask['environments'][$this->taskWidget->value];

        preg_match_all('/##(.*?)##/', $cmd, $matches);

        foreach ($matches[0] as $key => $search) {
            $key = $matches[1][$key];

            if (2 === strpos($search, 'exclude.database.tables')) {
                $db_name = null;

                if (1 === preg_match('/^##(.*?)\|(source|target)\.(.*?)##$/', $search, $match)) {
                    $db_name = $environment[$match[2]][$match[3]];
                }

                if (null === $db_name) {
                    Message::addError('Unknow database for "exclude.database.tables"', __CLASS__);
                    $this->redirectToMaintenance();
                }

                $result = array_map(
                    static function($item) use ($db_name) {
                        return '--ignore-table='.$db_name.'.'.$item;
                    },
                    $environment['exclude']['database']['tables']
                );
                $replace = implode(' ', $result);
            } elseif (1 === preg_match('/^##(source|target)\.(.*?)##$/', $search, $match)) {
                $replace = $environment[$match[1]][$match[2]];
            } elseif (!empty($environment[$key])) {
                $replace = $environment[$key];
            } else {
                $replace = $this->microtask[$key];
            }

            if (null === $replace) {
                Message::addError('Unknow simpletoken "'.$key.'"', __CLASS__);
                $this->redirectToMaintenance();
            }

            $cmd = str_replace($search, $replace, $cmd);
        }

        if (preg_match('/##(.*?)##/', $cmd, $match)) {
            $cmd = $this->replaceSimpleTokens($cmd);
        }

        return str_replace('  ', ' ', $cmd);
    }

    private function redirectToMaintenance(): void
    {
        Backend::redirect(Environment::get('base').'contao?do='.Input::get('do'), 301);
    }
}
