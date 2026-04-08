<?php

/**
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @license    https://choosealicense.com/licenses/gpl-3.0/ GNU General Public License v3.0
 * @copyright  Copyright (c) 2023 Brand Oriented sp. z o.o. (https://brandoriented.pl)
 * @copyright  Copyright (c) 2021 CI HUB GmbH (https://ci-hub.com)
 */
declare(strict_types=1);
use Composer\InstalledVersions;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('nelmio_api_doc', [
        'documentation' => [
            'components' => [
                'securitySchemes' => [
                    'Bearer' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
            ],
            'openapi' => '3.0.0',
            'security' => [
                [
                    'Bearer' => [
                    ],
                ],
            ],
            'info' => [
                'title' => 'Pimcore DataHub REST Adapter',
                'description' => 'Endpoints provided by the REST Adapter Bundle.',
                'version' => InstalledVersions::getPrettyVersion('portadesign/pimcore-datahub-rest-adapter-bundle'),
                'license' => [
                    'name' => 'GPL 3.0',
                    'url' => 'https://www.gnu.org/licenses/gpl-3.0.html',
                ],
            ],
        ],
        'areas' => [
            'disable_default_routes' => true,
            'default' => [
                'path_patterns' => ['^/api'],
            ],
            'ci_hub' => [
                'path_patterns' => [
                    '^/datahub/rest/{config}(?!/doc$)',
                ],
            ],
        ],
    ]);
};
