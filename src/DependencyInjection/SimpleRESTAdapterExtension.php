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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\DependencyInjection;

use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\EndpointAndIndexesConfigurator;
use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Index\IndexPersistenceService;
use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Index\IndexQueryService;
use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Mapping\AssetMapping;
use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Mapping\DataObjectMapping;
use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Mapping\FolderMapping;
use CIHub\Bundle\SimpleRESTAdapterBundle\Extractor\LabelExtractor;
use CIHub\Bundle\SimpleRESTAdapterBundle\Extractor\LabelExtractorInterface;
use CIHub\Bundle\SimpleRESTAdapterBundle\Helper\AssetHelper;
use CIHub\Bundle\SimpleRESTAdapterBundle\Helper\UploadHelper;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\AuthManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Provider\AssetProvider;
use CIHub\Bundle\SimpleRESTAdapterBundle\Provider\DataObjectProvider;
use CIHub\Bundle\SimpleRESTAdapterBundle\Repository\DataHubConfigurationRepository;
use CIHub\Bundle\SimpleRESTAdapterBundle\Transformer\FilterFieldNameTransformer;
use CIHub\Bundle\SimpleRESTAdapterBundle\Transformer\FilterFieldNameTransformerInterface;
use Nelmio\ApiDocBundle\Render\RenderOpenApi;
use Pimcore\Bundle\ElasticsearchClientBundle\DependencyInjection\PimcoreElasticsearchClientExtension;
use Pimcore\Config;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Routing\RouterInterface;

final class SimpleRESTAdapterExtension extends Extension implements PrependExtensionInterface
{
    private array $ciHubConfig = [];

    public function getAlias(): string
    {
        return 'datahub_rest_adapter';
    }

    /**
     * @throws \Exception
     */
    public function prepend(ContainerBuilder $containerBuilder): void
    {
        $bundles = $containerBuilder->getParameter('kernel.bundles');

        if (isset($bundles['PimcoreCIHubAdapterBundle'])) {
            $this->ciHubConfig = $containerBuilder->getExtensionConfig('ci_hub_adapter');
        }

        $phpFileLoader = new PhpFileLoader($containerBuilder, new FileLocator(__DIR__.'/../Resources/config'));
        $phpFileLoader->load('config.php');
        if ($containerBuilder->hasExtension('doctrine_migrations')) {
            $phpFileLoader->load('doctrine_migrations.php');
        }
    }

    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $containerBuilder): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->registerConfiguration($containerBuilder, $config);

        $phpFileLoader = new PhpFileLoader($containerBuilder, new FileLocator(__DIR__.'/../Resources/config'));
        $phpFileLoader->load('services.php');

        $definition = new Definition(IndexManager::class);
        $definition->setArgument('$indexNamePrefix', $config['index_name_prefix']);
        $definition->setArgument('$indexPersistenceService', new Reference(IndexPersistenceService::class));

        $containerBuilder->addDefinitions([IndexManager::class => $definition]);

        $definition = new Definition(LabelExtractor::class);
        $definition->setArgument('$indexManager', new Reference(IndexManager::class));

        $containerBuilder->addDefinitions([LabelExtractor::class => $definition]);

        $definition = new Definition(AssetHelper::class);
        $definition->setArgument('$authManager', new Reference(AuthManager::class));

        $containerBuilder->addDefinitions([AssetHelper::class => $definition]);

        $definition = new Definition(UploadHelper::class);
        $definition->setArgument('$pimcoreConfig', new Reference(Config::class));
        $definition->setArgument('$router', new Reference(RouterInterface::class));
        $definition->setArgument('$authManager', new Reference(AuthManager::class));

        $containerBuilder->addDefinitions([UploadHelper::class => $definition]);

        $containerBuilder->setAlias(LabelExtractorInterface::class, LabelExtractor::class);
        $containerBuilder->setAlias(RenderOpenApi::class, 'nelmio_api_doc.render_docs');

        $definition = new Definition(DataHubConfigurationRepository::class);
        $containerBuilder->addDefinitions([DataHubConfigurationRepository::class => $definition]);

        $definition = new Definition(AuthManager::class);
        $definition->setArgument('$requestStack', new Reference(RequestStack::class));

        $containerBuilder->addDefinitions([AuthManager::class => $definition]);

        $definition = new Definition(IndexPersistenceService::class);
        $definition->setArgument('$client', new Reference(PimcoreElasticsearchClientExtension::CLIENT_SERVICE_PREFIX.$config['es_client_name']));
        $definition->setArgument('$dataHubConfigurationRepository', new Reference(DataHubConfigurationRepository::class));
        $definition->setArgument('$assetProvider', new Reference($config['asset_provider'] ?? AssetProvider::class));
        $definition->setArgument('$dataObjectProvider', new Reference($config['data_object_provider'] ?? DataObjectProvider::class));
        $definition->setArgument('$indexSettings', $config['index_settings']);

        $containerBuilder->addDefinitions([IndexPersistenceService::class => $definition]);

        $definition = new Definition(IndexQueryService::class);
        $definition->setArgument('$client', new Reference(PimcoreElasticsearchClientExtension::CLIENT_SERVICE_PREFIX.$config['es_client_name']));
        $definition->setArgument('$indexNamePrefix', $config['index_name_prefix']);

        $containerBuilder->addDefinitions([IndexQueryService::class => $definition]);

        $definition = new Definition(AssetMapping::class);
        $containerBuilder->addDefinitions([AssetMapping::class => $definition]);
        $definition = new Definition(DataObjectMapping::class);
        $containerBuilder->addDefinitions([DataObjectMapping::class => $definition]);
        $definition = new Definition(FolderMapping::class);
        $containerBuilder->addDefinitions([FolderMapping::class => $definition]);

        $definition = new Definition(EndpointAndIndexesConfigurator::class);
        $definition->setArgument('$indexManager', new Reference(IndexManager::class));
        $definition->setArgument('$messageBus', new Reference('messenger.default_bus'));
        $definition->setArgument('$assetMapping', new Reference(AssetMapping::class));
        $definition->setArgument('$dataObjectMapping', new Reference(DataObjectMapping::class));
        $definition->setArgument('$folderMapping', new Reference(FolderMapping::class));
        $containerBuilder->addDefinitions([EndpointAndIndexesConfigurator::class => $definition]);

        $definition = new Definition($config['filter_field_name_transformer'] ?? FilterFieldNameTransformer::class);
        $containerBuilder->addDefinitions([FilterFieldNameTransformerInterface::class => $definition]);
    }

    /**
     * Registers the configuration as parameters to the container.
     *
     * @param array<string, string|array> $config
     */
    private function registerConfiguration(ContainerBuilder $containerBuilder, array $config): void
    {
        if ([] !== $this->ciHubConfig) {
            $config = array_merge($config, ...$this->ciHubConfig);
        }

        $containerBuilder->setParameter('datahub_rest_adapter.index_name_prefix', $config['index_name_prefix']);
        $containerBuilder->setParameter('datahub_rest_adapter.index_settings', $config['index_settings']);
        $containerBuilder->setParameter('datahub_rest_adapter.default_preview_thumbnail', $config['default_preview_thumbnail'] ?? []);
    }
}
