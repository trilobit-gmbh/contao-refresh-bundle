<?php

declare(strict_types=1);

/*
 * @copyright  trilobit GmbH
 * @author     trilobit GmbH <https://github.com/trilobit-gmbh>
 * @license    LGPL-3.0-or-later
 */

namespace Trilobit\RefreshBundle;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Trilobit\RefreshBundle\DependencyInjection\RefreshExtension;

/**
 * Configures the trilobit refresh bundle.
 *
 * @author trilobit GmbH <https://github.com/trilobit-gmbh>
 */
class TrilobitRefreshBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new RefreshExtension();
    }
}
