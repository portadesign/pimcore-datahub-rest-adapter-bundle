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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\Handler;

use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Index\IndexPersistenceService;
use CIHub\Bundle\SimpleRESTAdapterBundle\Installer;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\RebuildIndexElementMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\RebuildUpdateIndexElementMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use CIHub\Bundle\SimpleRESTAdapterBundle\Utils\WorkspaceSorter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Pimcore\Db;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Folder;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Tool\SettingsStore;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class RebuildIndexElementMessageHandler
{
    private const CONDITION_DISTINCT = 'distinct';
    private const CONDITION_INCLUSIVE = 'inclusive';
    private const CONDITION_EXCLUSIVE = 'exclusive';
    public const CHUNK_SIZE = 100;

    public const TYPE_ASSET = 'asset';
    public const TYPE_OBJECT = 'object';

    public function __construct(
        private MessageBusInterface $messageBus,
        private IndexManager $indexManager,
        private IndexPersistenceService $indexPersistenceService,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(RebuildIndexElementMessage $rebuildIndexElementMessage): void
    {
        SettingsStore::set(Installer::RUN_HASH, $hash = uniqid('run', true), 'string', Installer::REBUILD_SCOPE);
        SettingsStore::set(Installer::RUN_DONE_COUNT, 0, 'int', Installer::REBUILD_SCOPE);

        $this->cleanAliases($rebuildIndexElementMessage);

        $todo = 0;

        if ($rebuildIndexElementMessage->configReader->isAssetIndexingEnabled()) {
            $this->rebuildType($rebuildIndexElementMessage, self::TYPE_ASSET, $hash, $todo);
        }
        if ($rebuildIndexElementMessage->configReader->isObjectIndexingEnabled()) {
            $this->rebuildType($rebuildIndexElementMessage, self::TYPE_OBJECT, $hash, $todo);
        }

        SettingsStore::set(Installer::RUN_TODO_COUNT, $todo, 'int', Installer::REBUILD_SCOPE);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function rebuildType(
        RebuildIndexElementMessage $rebuildIndexElementMessage, string $type, string $hash, int &$todo): void
    {
        $configReader = $rebuildIndexElementMessage->configReader;
        $workspace = WorkspaceSorter::sort($configReader->getWorkspace($type));

        if ([] === $workspace) {
            return;
        }

        [$conditions, $params] = $this->buildConditions(
            $workspace,
            self::TYPE_ASSET === $type ? 'filename' : 'key',
            'path',
        );

        if (! isset($conditions[self::CONDITION_INCLUSIVE]) || [] === $conditions[self::CONDITION_INCLUSIVE] || [] === $params) {
            return;
        }

        $ids = $this->fetchIdsFromDatabaseTable(
            "{$type}s",
            'id',
            $conditions,
            $params,
        );

        foreach ($ids as $id) {
            $this->messageBus->dispatch(
                new RebuildUpdateIndexElementMessage(
                    (int) $id,
                    $type,
                    $rebuildIndexElementMessage->name,
                    $hash,
                    $configReader,
                ),
            );

            $element = self::TYPE_ASSET === $type ? Asset::getById((int) $id) : DataObject::getById((int) $id);
            $parent = $element ? $element->getParent() : null;

            $this->enqueueParentFolders(
                $parent,
                self::TYPE_ASSET === $type ? Folder::class : DataObject\Folder::class,
                $type,
                $rebuildIndexElementMessage->name,
                $hash,
                $todo,
                $configReader,
            );
            ++$todo;
        }
    }

    /**
     * @param array<int, array> $workspace
     *
     * @return array{0: array<string, array<int, string>>, 1: array<string, string>}
     */
    private function buildConditions(array $workspace, string $keyColumn, string $pathColumn): array
    {
        $conditions = [];
        $params = [];

        if ([] === $workspace) {
            return [$conditions, $params];
        }

        foreach ($workspace as $item) {
            $read = $item['read'] ?? null;
            $path = $item['cpath'] ?? null;

            if (null === $read || null === $path || '' === $path) {
                continue;
            }

            $pathParts = explode('/', (string) $path);

            // If not root folder, add distinct conditions.
            if (\count($pathParts) > 2 || '' !== $pathParts[1]) {
                $this->addDistinctConditions($pathParts, $keyColumn, $pathColumn, $conditions, $params);
            }

            // Always add the ex-/inclusive conditions.
            $pathIndex = uniqid('path_', false);
            $conditions[$read ? self::CONDITION_INCLUSIVE : self::CONDITION_EXCLUSIVE][] = sprintf(
                '`%s` %s :%s',
                $pathColumn,
                $read ? 'LIKE' : 'NOT LIKE',
                $pathIndex,
            );
            $params[$pathIndex] = rtrim((string) $path, '/') . '/%';
        }

        return [$conditions, $params];
    }

    /**
     * @param array<int, string>       $pathParts
     * @param array<string, string[]>  $conditions
     * @param array<string, string>    $params
     */
    private function addDistinctConditions(
        array $pathParts,
        string $keyColumn,
        string $pathColumn,
        array &$conditions,
        array &$params,
    ): void {
        $keyIndex = uniqid('key_', false);
        $keyPathIndex = uniqid('key_path_', false);
        $keyParam = array_pop($pathParts);
        $keyPathParam = implode('/', $pathParts) . '/';

        if (! \in_array((string) $keyParam, $params, true)) {
            $conditions[self::CONDITION_DISTINCT][] = sprintf(
                '(`%s` = :%s AND `%s` = :%s)',
                $keyColumn,
                $keyIndex,
                $pathColumn,
                $keyPathIndex,
            );

            $params[$keyIndex] = (string) $keyParam;
            $params[$keyPathIndex] = $keyPathParam;
        }

        // Add parent folders to distinct conditions as well.
        if (\count($pathParts) > 1) {
            $this->addDistinctConditions($pathParts, $keyColumn, $pathColumn, $conditions, $params);
        }
    }

    /**
     * Runs the database query and returns found IDs.
     *
     * @param array<string, array<int, string>> $conditions
     * @param array<string, string>             $params
     *
     * @return array<int, int>
     */
    private function fetchIdsFromDatabaseTable(
        string $from,
        string $select,
        array $conditions,
        array $params,
    ): array {
        $qb = $this->getDb()
            ->createQueryBuilder()
            ->select($select)
            ->from($from)
            ->where(implode(' OR ', $conditions[self::CONDITION_INCLUSIVE] ?? []))
            ->setParameters($params);

        if (isset($conditions[self::CONDITION_DISTINCT])) {
            $qb->orWhere(implode(' OR ', $conditions[self::CONDITION_DISTINCT]));
        }

        if (isset($conditions[self::CONDITION_EXCLUSIVE])) {
            $qb->andWhere(implode(' AND ', $conditions[self::CONDITION_EXCLUSIVE]));
        }

        try {
            $statement = $qb->executeQuery();
            /** @var array<int, int> $ids */
            $ids = array_map('intval', $statement->fetchFirstColumn());
        } catch (DBALException $e) {
            $ids = [];
        }

        return $ids;
    }

    private function enqueueParentFolders(
        ?ElementInterface $element,
        string $folderClass,
        string $type,
        string $name,
        string $hash,
        int &$todo,
        ConfigReader $configReader,
    ): void {
        while ($element instanceof $folderClass && 1 !== $element->getId()) {
            $this->messageBus->dispatch(new RebuildUpdateIndexElementMessage($element->getId(), $type, $name, $hash, $configReader));
            $element = $element->getParent();
            ++$todo;
        }
    }

    protected function getDb(): Connection
    {
        return Db::get();
    }

    /**
     * @throws \Elastic\Elasticsearch\Exception\ClientResponseException
     * @throws \Elastic\Elasticsearch\Exception\MissingParameterException
     * @throws \Elastic\Elasticsearch\Exception\ServerResponseException
     */
    public function cleanAliases(RebuildIndexElementMessage $rebuildIndexElementMessage): void
    {
        $indices = $this->indexManager->getAllIndexNames($rebuildIndexElementMessage->configReader);
        foreach ($indices as $alias) {
            $index = $this->indexManager->findIndexNameByAlias($alias);
            $newIndexName = $this->getNewIndexName($index);
            if ($this->indexPersistenceService->indexExists($newIndexName)) {
                $this->indexPersistenceService->deleteIndex($newIndexName);
            }
            $mapping = $this->indexPersistenceService->getMapping($index)[$index]['mappings'];
            $this->indexPersistenceService->createIndex($newIndexName, $mapping);
        }
    }

    public function getNewIndexName(string $index): string
    {
        return str_ends_with($index, '-odd') ? str_replace('-odd', '', $index) . '-even' : str_replace('-even', '', $index) . '-odd';
    }
}
