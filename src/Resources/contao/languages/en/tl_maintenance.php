<?php

declare(strict_types=1);

/*
 * @copyright  trilobit GmbH
 * @author     trilobit GmbH <https://github.com/trilobit-gmbh>
 * @license    LGPL-3.0-or-later
 * @link       http://github.com/trilobit-gmbh/contao-refresh-bundle
 */

$GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['headline'] = 'Refresh';
$GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['description'] = 'Here you can update the content of the selected target system. The Contao Manager is required (<a href="https://contao.org/de/download.html"><u>https://contao.org/de/download.html</u></a>).';

$GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['maintenance'] = 'The refresh is not available in maintenance mode.';
$GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['active'] = 'A refresh process is already running. Please try again later.';

$GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['submit'] = 'Start refresh';
$GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['confirm'] = 'Should the refresh really be executed?';
$GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['done'] = 'Refresh complete';

$GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['error'] = 'No task has been defined.';

$GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['environments'][0] = 'Task';
$GLOBALS['TL_LANG']['tl_maintenance']['refreshtarget']['environments'][1] = 'Please select a refresh task.';
