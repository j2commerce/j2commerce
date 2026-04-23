<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Service;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\Reader\JoomlaSourceReader;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\Reader\SourceDatabaseReaderInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;

/**
 * Migrates Joomla core tables from the configured source database into this site.
 * Runs ONLY in source-connection mode B/C (or mode A for dev same-install).
 *
 * Tier ordering:
 *   9  — Access Control   (assets, usergroups, viewlevels)
 *   10 — Users            (users, user_usergroup_map, user_profiles, user_notes)
 *   11 — Content          (categories, content, content_frontpage, tags, contentitem_tag_map, fields*)
 *   12 — Workflows        (workflows, workflow_stages, workflow_transitions, workflow_associations)
 *
 * Source PKs are NOT preserved — every insert gets a fresh auto-generated id and the
 * old→new pair is written into IdmapRepository. Subsequent tier inserts translate every FK
 * through that map before writing. After each tier that touches a nested-set tree
 * (#__assets, #__categories, #__tags) we rebuild the tree via the component's MVC factory.
 */
class J2CoreMigrator
{
    private const ADAPTER = 'j2core';

    public const TIERS = [
        9 => [
            'name'   => 'Access Control',
            'tables' => ['assets', 'usergroups', 'viewlevels'],
        ],
        10 => [
            'name'   => 'Users',
            'tables' => ['users', 'user_usergroup_map', 'user_profiles', 'user_notes'],
        ],
        11 => [
            'name'   => 'Content',
            'tables' => ['categories', 'content', 'content_frontpage', 'tags', 'contentitem_tag_map', 'fields', 'fields_values', 'fields_groups'],
        ],
        12 => [
            'name'   => 'Workflows',
            'tables' => ['workflows', 'workflow_stages', 'workflow_transitions', 'workflow_associations'],
        ],
    ];

    private SourceDatabaseReaderInterface $sourceReader;
    private IdmapRepository $idMap;

    public function __construct(
        private DatabaseInterface $db,
        private MigrationLogger $logger,
        ?SourceDatabaseReaderInterface $sourceReader = null
    ) {
        $this->sourceReader = $sourceReader ?? new JoomlaSourceReader($db);
        $this->idMap        = new IdmapRepository($db);
    }

    public function audit(): array
    {
        $tiers = [];

        foreach (self::TIERS as $tier => $info) {
            $tables = [];

            foreach ($info['tables'] as $bareTable) {
                $tables[$bareTable] = [
                    'source_count' => $this->sourceCount($bareTable),
                    'target_count' => $this->targetCount($bareTable),
                    'target_table' => $bareTable,
                ];
            }

            $tiers[$tier] = ['name' => $info['name'], 'tables' => $tables];
        }

        return ['tiers' => $tiers];
    }

    public function runTier(int $tier, string $conflictMode = 'skip', array $conflictResolutions = []): array
    {
        if (!isset(self::TIERS[$tier])) {
            return ['error' => "Unknown J2Core tier: {$tier}"];
        }

        $this->logger->tierStart($tier, self::TIERS[$tier]['name']);

        try {
            $result = match ($tier) {
                9  => $this->runAccessControl($conflictMode, $conflictResolutions),
                10 => $this->runUsers($conflictMode, $conflictResolutions),
                11 => $this->runContent($conflictMode, $conflictResolutions),
                12 => $this->runWorkflows($conflictMode, $conflictResolutions),
            };

            $this->logger->tierEnd($tier, self::TIERS[$tier]['name'], (int) ($result['migrated'] ?? 0));

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error("J2Core tier {$tier} failed: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function resetTier(int $tier): array
    {
        if (!isset(self::TIERS[$tier])) {
            return ['error' => "Unknown J2Core tier: {$tier}"];
        }

        // Drop only rows for this tier's entities — each tier is independent and resetting
        // Users must not erase Content idmap rows (which share the same 'j2core' adapter).
        foreach (self::TIERS[$tier]['tables'] as $table) {
            $this->idMap->dropForAdapterAndEntity(self::ADAPTER, $table);
        }

        return ['success' => true, 'tier' => $tier];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tier 9 — Access Control
    // ──────────────────────────────────────────────────────────────────────────

    private function runAccessControl(string $conflictMode, array $resolutions): array
    {
        $migrated = 0;

        $migrated += $this->copyKeyed('usergroups', 'id', ['title']);
        $migrated += $this->copyKeyed('viewlevels', 'id', ['title']);
        $migrated += $this->copyAssets();

        return ['success' => true, 'migrated' => $migrated, 'skipped' => 0];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tier 10 — Users
    // ──────────────────────────────────────────────────────────────────────────

    private function runUsers(string $conflictMode, array $resolutions): array
    {
        $migrated = 0;

        $migrated += $this->copyUsers($conflictMode, $resolutions);

        $migrated += $this->copyRemap(
            'user_usergroup_map',
            [],
            null,
            [
                'user_id'  => 'users',
                'group_id' => 'usergroups',
            ]
        );

        $migrated += $this->copyRemap('user_profiles', [], null, ['user_id' => 'users']);

        $migrated += $this->copyRemap('user_notes', ['id'], 'id', [
            'user_id'          => 'users',
            'catid'            => 'categories',
            'created_user_id'  => 'users',
            'modified_user_id' => 'users',
            'checked_out'      => 'users',
            'asset_id'         => 'assets',
        ]);

        return ['success' => true, 'migrated' => $migrated];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tier 11 — Content
    // ──────────────────────────────────────────────────────────────────────────

    private function runContent(string $conflictMode, array $resolutions): array
    {
        $migrated = 0;

        $migrated += $this->copyCategories();
        $this->rebuildNestedTree('com_categories', 'Category');

        $migrated += $this->copyRemap('content', ['id'], 'id', [
            'catid'       => 'categories',
            'created_by'  => 'users',
            'modified_by' => 'users',
            'checked_out' => 'users',
            'asset_id'    => 'assets',
        ]);

        $migrated += $this->copyRemap('content_frontpage', [], null, ['content_id' => 'content']);

        $migrated += $this->copyTags();
        $this->rebuildNestedTree('com_tags', 'Tag');

        $migrated += $this->copyRemap('contentitem_tag_map', [], null, [
            'tag_id'          => 'tags',
            'content_item_id' => null, // per-type FK — leave as-is
        ]);

        $migrated += $this->copyRemap('fields_groups', ['id'], 'id', [
            'asset_id'        => 'assets',
            'created_user_id' => 'users',
            'modified_by'     => 'users',
            'checked_out'     => 'users',
        ]);
        $migrated += $this->copyRemap('fields', ['id'], 'id', [
            'asset_id'        => 'assets',
            'group_id'        => 'fields_groups',
            'created_user_id' => 'users',
            'modified_by'     => 'users',
            'checked_out'     => 'users',
        ]);
        $migrated += $this->copyRemap('fields_values', [], null, [
            'field_id' => 'fields',
        ]);

        return ['success' => true, 'migrated' => $migrated];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tier 12 — Workflows
    // ──────────────────────────────────────────────────────────────────────────

    private function runWorkflows(string $conflictMode, array $resolutions): array
    {
        $migrated = 0;

        $migrated += $this->copyRemap('workflows', ['id'], 'id', [
            'asset_id'        => 'assets',
            'created_user_id' => 'users',
            'modified_by'     => 'users',
            'checked_out'     => 'users',
        ]);
        $migrated += $this->copyRemap('workflow_stages', ['id'], 'id', [
            'workflow_id' => 'workflows',
            'asset_id'    => 'assets',
        ]);
        $migrated += $this->copyRemap('workflow_transitions', ['id'], 'id', [
            'workflow_id'  => 'workflows',
            'from_stage_id' => 'workflow_stages',
            'to_stage_id'   => 'workflow_stages',
            'asset_id'      => 'assets',
        ]);
        $migrated += $this->copyRemap('workflow_associations', [], null, [
            'stage_id' => 'workflow_stages',
        ]);

        return ['success' => true, 'migrated' => $migrated];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Copy helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function copyKeyed(string $table, string $pkCol, array $uniqueCols): int
    {
        $rows = $this->sourceReader->fetchBatch($table, $pkCol, 0, 100000);
        if (empty($rows)) {
            return 0;
        }

        $migrated = 0;

        foreach ($rows as $row) {
            $sourceId = (int) ($row[$pkCol] ?? 0);
            $payload  = $row;
            unset($payload[$pkCol]);

            $targetId = $this->insertRow('#__' . $table, $payload);
            if ($targetId > 0) {
                $this->putIdmap($table, $sourceId, $targetId);
                $migrated++;
            }
        }

        return $migrated;
    }

    private function copyRemap(string $table, array $pkCols, ?string $pkCol, array $remap): int
    {
        $orderBy = $pkCol ?? ($this->sourceReader->getPrimaryKey($table) ?? 'id');

        $rows = $this->sourceReader->fetchBatch($table, $orderBy, 0, 500000);
        if (empty($rows)) {
            return 0;
        }

        $migrated = 0;

        foreach ($rows as $row) {
            $sourceId = $pkCol ? (int) ($row[$pkCol] ?? 0) : 0;

            foreach ($remap as $col => $entityKey) {
                if (!array_key_exists($col, $row) || $entityKey === null) {
                    continue;
                }
                $val = (int) $row[$col];
                if ($val <= 0) {
                    continue;
                }
                $mapped = $this->getIdmap($entityKey, $val);
                if ($mapped !== null) {
                    $row[$col] = $mapped;
                }
            }

            $payload = $row;
            if ($pkCol !== null) {
                unset($payload[$pkCol]);
            }

            $targetId = $this->insertRow('#__' . $table, $payload);

            if ($targetId > 0 && $pkCol !== null) {
                $this->putIdmap($table, $sourceId, $targetId);
            }
            $migrated++;
        }

        return $migrated;
    }

    /**
     * Assets: two passes ordered by level so parent IDs are mapped before children.
     */
    private function copyAssets(): int
    {
        $rows = $this->sourceReader->fetchBatch('assets', 'level', 0, 500000);
        if (empty($rows)) {
            return 0;
        }

        usort($rows, static fn($a, $b) => (int) $a['level'] <=> (int) $b['level']);

        $migrated = 0;

        foreach ($rows as $row) {
            $sourceId = (int) $row['id'];
            $parentId = (int) ($row['parent_id'] ?? 0);
            if ($parentId > 0) {
                $row['parent_id'] = $this->getIdmap('assets', $parentId) ?? 0;
            }

            // lft/rgt will be corrected by rebuild()
            $row['lft'] = 0;
            $row['rgt'] = 0;

            $payload = $row;
            unset($payload['id']);

            $targetId = $this->insertRow('#__assets', $payload);
            if ($targetId > 0) {
                $this->putIdmap('assets', $sourceId, $targetId);
                $migrated++;
            }
        }

        $this->rebuildNestedTree('com_admin', 'Asset', true);

        return $migrated;
    }

    /**
     * Users: enforces option-(c) conflict semantics.
     *
     * Conflict resolutions keyed 'users:<source_id>':
     *   skip       — source row dropped; no idmap entry
     *   overwrite  — target row replaced by source values; idmap → target id
     *   merge      — target wins on conflict, source nulls filled in; idmap → target id
     *   use_target — idmap → existing target id so later FK tiers resolve correctly
     */
    private function copyUsers(string $conflictMode, array $resolutions): int
    {
        $rows = $this->sourceReader->fetchBatch('users', 'id', 0, 200000);
        if (empty($rows)) {
            return 0;
        }

        $migrated = 0;

        foreach ($rows as $row) {
            $sourceId = (int) $row['id'];
            $email    = (string) ($row['email'] ?? '');
            $username = (string) ($row['username'] ?? '');

            $existing = $this->findExistingUser($email, $username);

            if ($existing !== null) {
                $res = $resolutions['users:' . $sourceId] ?? $conflictMode;
                $this->applyUserResolution($res, $row, $existing, $sourceId);
            } else {
                $payload = $row;
                unset($payload['id']);
                $targetId = $this->insertRow('#__users', $payload);
                if ($targetId > 0) {
                    $this->putIdmap('users', $sourceId, $targetId);
                    $migrated++;
                }
            }
        }

        return $migrated;
    }

    private function findExistingUser(string $email, string $username): ?int
    {
        if ($email === '' && $username === '') {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__users'))
            ->where($this->db->quoteName('email') . ' = :email OR ' . $this->db->quoteName('username') . ' = :username')
            ->bind(':email', $email)
            ->bind(':username', $username);

        $id = $this->db->setQuery($query)->loadResult();

        return $id !== null ? (int) $id : null;
    }

    private function applyUserResolution(string $res, array $source, int $targetId, int $sourceId): void
    {
        match ($res) {
            'use_target' => $this->putIdmap('users', $sourceId, $targetId),

            'overwrite' => (function () use ($source, $targetId, $sourceId): void {
                $payload = $source;
                unset($payload['id']);
                $this->updateRow('#__users', 'id', $targetId, $payload);
                $this->putIdmap('users', $sourceId, $targetId);
            })(),

            'merge' => (function () use ($source, $targetId, $sourceId): void {
                $existing = $this->fetchRow('#__users', 'id', $targetId);
                $merged   = $existing;
                foreach ($source as $k => $v) {
                    if ($k === 'id') {
                        continue;
                    }
                    if (!array_key_exists($k, $existing) || $existing[$k] === null || $existing[$k] === '') {
                        $merged[$k] = $v;
                    }
                }
                unset($merged['id']);
                $this->updateRow('#__users', 'id', $targetId, $merged);
                $this->putIdmap('users', $sourceId, $targetId);
            })(),

            default => $this->logger->info("users:{$sourceId} skipped per conflict resolution"),
        };
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Nested-set copies (categories, tags)
    // ──────────────────────────────────────────────────────────────────────────

    private function copyCategories(): int
    {
        $rows = $this->sourceReader->fetchBatch('categories', 'level', 0, 500000);
        if (empty($rows)) {
            return 0;
        }

        usort($rows, static fn($a, $b) => (int) $a['level'] <=> (int) $b['level']);

        $migrated = 0;

        foreach ($rows as $row) {
            $sourceId = (int) $row['id'];

            if (($parent = (int) ($row['parent_id'] ?? 0)) > 0) {
                $row['parent_id'] = $this->getIdmap('categories', $parent) ?? 0;
            }
            if (($asset = (int) ($row['asset_id'] ?? 0)) > 0) {
                $row['asset_id'] = $this->getIdmap('assets', $asset) ?? 0;
            }
            foreach (['created_user_id', 'modified_user_id', 'checked_out'] as $uc) {
                if (array_key_exists($uc, $row) && (int) $row[$uc] > 0) {
                    $row[$uc] = $this->getIdmap('users', (int) $row[$uc]) ?? 0;
                }
            }

            $row['lft'] = 0;
            $row['rgt'] = 0;

            $payload = $row;
            unset($payload['id']);

            $targetId = $this->insertRow('#__categories', $payload);
            if ($targetId > 0) {
                $this->putIdmap('categories', $sourceId, $targetId);
                $migrated++;
            }
        }

        return $migrated;
    }

    private function copyTags(): int
    {
        $rows = $this->sourceReader->fetchBatch('tags', 'level', 0, 500000);
        if (empty($rows)) {
            return 0;
        }

        usort($rows, static fn($a, $b) => (int) $a['level'] <=> (int) $b['level']);

        $migrated = 0;

        foreach ($rows as $row) {
            $sourceId = (int) $row['id'];

            if (($parent = (int) ($row['parent_id'] ?? 0)) > 0) {
                $row['parent_id'] = $this->getIdmap('tags', $parent) ?? 0;
            }
            if (($asset = (int) ($row['asset_id'] ?? 0)) > 0) {
                $row['asset_id'] = $this->getIdmap('assets', $asset) ?? 0;
            }
            foreach (['created_user_id', 'modified_user_id', 'checked_out'] as $uc) {
                if (array_key_exists($uc, $row) && (int) $row[$uc] > 0) {
                    $row[$uc] = $this->getIdmap('users', (int) $row[$uc]) ?? 0;
                }
            }

            $row['lft'] = 0;
            $row['rgt'] = 0;

            $payload = $row;
            unset($payload['id']);

            $targetId = $this->insertRow('#__tags', $payload);
            if ($targetId > 0) {
                $this->putIdmap('tags', $sourceId, $targetId);
                $migrated++;
            }
        }

        return $migrated;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Idmap delegation — adapts IdmapRepository (adapter+table scoped) to the
    // simple entity-keyed put/get interface used internally
    // ──────────────────────────────────────────────────────────────────────────

    private function putIdmap(string $entity, int $sourceId, int $targetId): void
    {
        $this->idMap->record(self::ADAPTER, $entity, $sourceId, $targetId);
    }

    private function getIdmap(string $entity, int $sourceId): ?int
    {
        return $this->idMap->lookupTarget(self::ADAPTER, $entity, $sourceId);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Raw DB helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function insertRow(string $table, array $data): int
    {
        if (empty($data)) {
            return 0;
        }

        $cols = array_map(fn($c) => $this->db->quoteName($c), array_keys($data));
        $vals = array_map(
            fn($v) => $v === null ? 'NULL' : $this->db->quote((string) $v),
            array_values($data)
        );

        $sql = 'INSERT INTO ' . $this->db->quoteName($table)
            . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';

        try {
            $this->db->setQuery($sql)->execute();
            return (int) $this->db->insertid();
        } catch (\Throwable $e) {
            $this->logger->warning("INSERT {$table} failed: " . $e->getMessage());
            return 0;
        }
    }

    private function updateRow(string $table, string $pkCol, int $pkVal, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $set = [];
        foreach ($data as $k => $v) {
            $set[] = $this->db->quoteName($k) . ' = ' . ($v === null ? 'NULL' : $this->db->quote((string) $v));
        }

        $sql = 'UPDATE ' . $this->db->quoteName($table)
            . ' SET ' . implode(', ', $set)
            . ' WHERE ' . $this->db->quoteName($pkCol) . ' = ' . (int) $pkVal;

        $this->db->setQuery($sql)->execute();
    }

    private function fetchRow(string $table, string $pkCol, int $pkVal): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName($table))
            ->where($this->db->quoteName($pkCol) . ' = ' . (int) $pkVal);

        return $this->db->setQuery($query)->loadAssoc() ?: [];
    }

    private function sourceCount(string $bareTable): int
    {
        try {
            return (int) $this->sourceReader->count($bareTable);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function targetCount(string $bareTable): int
    {
        try {
            $query = $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__' . $bareTable));
            return (int) $this->db->setQuery($query)->loadResult();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Joomla 6 tree rebuild via MVC factory — non-deprecated replacement for JTable::rebuild().
     * com_admin/Asset for #__assets; com_categories/Category; com_tags/Tag; com_menus/Menu.
     */
    private function rebuildNestedTree(string $component, string $tableName, bool $quiet = false): void
    {
        try {
            $app     = Factory::getApplication();
            $factory = $app->bootComponent($component)->getMVCFactory();
            if (!$factory instanceof MVCFactoryInterface) {
                return;
            }
            $table = $factory->createTable($tableName, 'Administrator');
            if (method_exists($table, 'rebuild')) {
                $table->rebuild();
            }
        } catch (\Throwable $e) {
            if (!$quiet) {
                $this->logger->warning("Tree rebuild via {$component}/{$tableName} failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Public lookup used by J2Store adapter to remap user_id FKs when Tier 10 ran first.
     * Returns null if the source id was never migrated (callers should null or skip the FK).
     */
    public function translateUserId(int $sourceUserId): ?int
    {
        return $this->getIdmap('users', $sourceUserId);
    }
}
