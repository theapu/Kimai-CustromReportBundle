<?php

/*
 * This file is part of the Kimai CustomReportBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\CustomReportBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class CustomReportExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @param array $configs
     * @param ContainerBuilder $container
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }


    public function prepend(ContainerBuilder $container)
    {
        $container->prependExtensionConfig('kimai', [
            'permissions' => [
                'roles' => [
                      'ROLE_SUPER_ADMIN' => [
                        'view_custom_report',
                    ],
                      'ROLE_ADMIN' => [
                        'view_custom_report',
                    ],
                      'ROLE_TEAMLEAD' => [
                        'view_custom_report',
                    ],
//                      'ROLE_USER' => [
//                        'view_custom_report',
//                    ],
                ],
            ],
        ]);
    }
}
