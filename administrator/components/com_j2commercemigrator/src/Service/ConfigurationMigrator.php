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

use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\Reader\JoomlaSourceReader;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\Reader\SourceDatabaseReaderInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class ConfigurationMigrator
{
    /**
     * Explicit key renames applied AFTER the generic j2store→j2commerce prefix rewrite.
     *
     * Direct same-name passthroughs (no rename — listed here for documentation, handled
     * automatically by the default copy path):
     *   store_phone, addtocart_checkout_link, addtocart_button_class
     */
    private const RENAME_MAP = [
        // J2Store's original field had a typo ("untill"); J2Commerce fixed it.
        'hide_shipping_untill_address_selection' => 'hide_shipping_until_address_selection',
    ];

    private DataTransformer $transformer;

    public function __construct(
        private DatabaseInterface $db,
        private MigrationLogger $logger,
        private ?SourceDatabaseReaderInterface $sourceReader = null
    ) {
        $this->transformer  = new DataTransformer();
        $this->sourceReader ??= new JoomlaSourceReader($db);
    }

    public function setSourceReader(SourceDatabaseReaderInterface $reader): void
    {
        $this->sourceReader = $reader;
    }

    /**
     * @return array{success:bool, rows:array<int,array{key:string, source_value:string, target_value:string, changed:bool}>}|array{error:string}
     */
    public function analyze(): array
    {
        try {
            $pk  = $this->sourceReader->getPrimaryKey('j2store_configurations') ?? 'j2store_configuration_id';
            $src = $this->sourceReader->fetchBatch('j2store_configurations', $pk, 0, 10000);

            if (empty($src)) {
                return ['success' => true, 'rows' => []];
            }

            $targetParams = $this->loadExtensionParams();
            $targetLegacy = $this->loadLegacyConfigurations();
            $rows         = [];

            foreach ($src as $row) {
                $srcKey = $row['config_meta_key'] ?? '';
                if ($srcKey === '') {
                    continue;
                }

                $newKey = $this->rewriteKey($srcKey);
                $srcValue = $this->transformer->transformJsonField((string) ($row['config_meta_value'] ?? ''));
                $tgtValue = $targetParams[$newKey] ?? $targetLegacy[$newKey] ?? '';

                $rows[] = [
                    'key'          => $newKey,
                    'source_value' => (string) $srcValue,
                    'target_value' => (string) $tgtValue,
                    'changed'      => (string) $srcValue !== (string) $tgtValue,
                ];
            }

            return ['success' => true, 'rows' => $rows];
        } catch (\Throwable $e) {
            $this->logger->error('Configuration analysis failed: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string,string> $resolutions  map of config key → 'update'|'skip'|'ignore'.
     *                                           Missing key defaults to 'update' (legacy one-shot behaviour).
     */
    public function migrate(array $resolutions = []): array
    {
        $this->logger->info('Starting configuration migration');

        try {
            $pk   = $this->sourceReader->getPrimaryKey('j2store_configurations') ?? 'j2store_configuration_id';
            $rows = $this->sourceReader->fetchBatch('j2store_configurations', $pk, 0, 10000);

            if (empty($rows)) {
                return ['success' => true, 'migrated' => 0, 'message' => 'No configurations to migrate'];
            }

            $migrated  = 0;
            $skipped   = 0;
            $paramsMap = [];

            $this->db->transactionStart();

            try {
                foreach ($rows as $row) {
                    $configKey     = $row['config_meta_key'] ?? '';
                    $configValue   = $row['config_meta_value'] ?? '';
                    $configDefault = $row['config_meta_default'] ?? '';

                    $newKey = $this->rewriteKey($configKey);

                    $resolution = $resolutions[$newKey] ?? 'update';
                    if ($resolution === 'skip' || $resolution === 'ignore') {
                        $skipped++;
                        continue;
                    }

                    $newValue   = $this->transformer->transformJsonField((string) $configValue);
                    $newDefault = $this->transformer->transformJsonField((string) $configDefault);

                    $sql = 'INSERT INTO ' . $this->db->quoteName('#__j2commerce_configurations')
                        . ' (' . $this->db->quoteName('config_meta_key')
                        . ', ' . $this->db->quoteName('config_meta_value')
                        . ', ' . $this->db->quoteName('config_meta_default') . ')'
                        . ' VALUES (' . $this->db->quote($newKey)
                        . ', ' . $this->db->quote($newValue)
                        . ', ' . $this->db->quote($newDefault) . ')'
                        . ' ON DUPLICATE KEY UPDATE '
                        . $this->db->quoteName('config_meta_value') . ' = ' . $this->db->quote($newValue) . ', '
                        . $this->db->quoteName('config_meta_default') . ' = ' . $this->db->quote($newDefault);

                    $this->db->setQuery($sql)->execute();
                    $migrated++;
                    $paramsMap[$newKey] = $newValue;
                }

                $this->bridgeToExtensionParams($paramsMap);

                $this->db->transactionCommit();
            } catch (\Throwable $e) {
                $this->db->transactionRollback();
                throw $e;
            }

            $this->logger->info("Configuration migration complete: {$migrated} rows ({$skipped} skipped)");

            return ['success' => true, 'migrated' => $migrated, 'skipped' => $skipped];
        } catch (\Throwable $e) {
            $this->logger->error('Configuration migration failed: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    private function rewriteKey(string $key): string
    {
        $newKey = str_replace(
            ['com_j2store', 'j2store', 'J2STORE'],
            ['com_j2commerce', 'j2commerce', 'J2COMMERCE'],
            $key
        );

        return self::RENAME_MAP[$newKey] ?? $newKey;
    }

    private function loadExtensionParams(): array
    {
        $element  = 'com_j2commerce';
        $type     = 'component';

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('params'))
            ->from($this->db->quoteName('#__extensions'))
            ->where($this->db->quoteName('element') . ' = :element')
            ->where($this->db->quoteName('type') . ' = :type')
            ->bind(':element', $element)
            ->bind(':type', $type);

        $json = (string) $this->db->setQuery($query)->loadResult();

        return $json !== '' ? (json_decode($json, true) ?: []) : [];
    }

    private function loadLegacyConfigurations(): array
    {
        try {
            $query = $this->db->getQuery(true)
                ->select([$this->db->quoteName('config_meta_key'), $this->db->quoteName('config_meta_value')])
                ->from($this->db->quoteName('#__j2commerce_configurations'));

            $rows = $this->db->setQuery($query)->loadAssocList() ?: [];
            $out  = [];

            foreach ($rows as $r) {
                $out[(string) $r['config_meta_key']] = (string) $r['config_meta_value'];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** Merge the migrated key/value pairs into com_j2commerce's `#__extensions.params` JSON. */
    private function bridgeToExtensionParams(array $paramsMap): void
    {
        if (empty($paramsMap)) {
            return;
        }

        $element = 'com_j2commerce';
        $type    = 'component';

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('params'))
            ->from($this->db->quoteName('#__extensions'))
            ->where($this->db->quoteName('element') . ' = :element')
            ->where($this->db->quoteName('type') . ' = :type')
            ->bind(':element', $element)
            ->bind(':type', $type);

        $current = (string) $this->db->setQuery($query)->loadResult();
        $params  = $current !== '' ? (json_decode($current, true) ?: []) : [];

        foreach ($paramsMap as $key => $value) {
            $params[$key] = $value;
        }

        $json   = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $update = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__extensions'))
            ->set($this->db->quoteName('params') . ' = :params')
            ->where($this->db->quoteName('element') . ' = :element')
            ->where($this->db->quoteName('type') . ' = :type')
            ->bind(':params', $json)
            ->bind(':element', $element)
            ->bind(':type', $type);

        $this->db->setQuery($update)->execute();

        $this->logger->info(sprintf('Bridged %d config keys into #__extensions.params for com_j2commerce', count($paramsMap)));
    }
}
