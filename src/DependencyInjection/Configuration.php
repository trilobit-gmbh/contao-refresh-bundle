<?php

/*
 * @copyright  trilobit GmbH
 * @author     trilobit GmbH <https://github.com/trilobit-gmbh>
 * @license    LGPL-3.0-or-later
 * @link       http://github.com/trilobit-gmbh/contao-refresh-bundle
 */

namespace Trilobit\RefreshBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('trilobit_refresh');

        if (method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            $rootNode = $treeBuilder->root('trilobit_refresh');
        }

        $rootNode
            ->children()
                ->variableNode('config')
                    ->defaultValue([])
                ->end()
            ->end()
        ;

        return $treeBuilder;

        /*
        $treeBuilder = new TreeBuilder('trilobit');
        $treeBuilder
            ->getRootNode()
                ->children()
                    ->variableNode('refresh')
                ->end()
        ;

        return $treeBuilder;
        */
    }
}
