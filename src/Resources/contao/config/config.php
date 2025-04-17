<?php

declare(strict_types=1);

/*
 * @copyright  trilobit GmbH
 * @author     trilobit GmbH <https://github.com/trilobit-gmbh>
 * @license    LGPL-3.0-or-later
 */

use Trilobit\RefreshBundle\Maintenance\RefreshMaintenance;

$GLOBALS['TL_MAINTENANCE'] = array_merge(
    [RefreshMaintenance::class],
    $GLOBALS['TL_MAINTENANCE']
);
