<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\Path;

/**
 * Overwrites the DB-stored `body`/`body_json` of the fixed set of core email
 * and invoice/print templates with the content of their currently-installed
 * `.html` presets under layouts/templates/, or recreates the row from the
 * registry defaults when a merchant has deleted it. A row an admin
 * repurposed (identity fields no longer match) is skipped so it is never
 * clobbered, and a duplicated or imported row -- which repeats every identity
 * field of the row it came from -- is excluded by its own `core_key`.
 */
class CoreTemplateSyncHelper
{
    /**
     * What a row that is nobody's core template is stamped with.
     *
     * It has to be a non-empty string rather than NULL: DatabaseDriver::insertObject() skips
     * null properties outright, so a NULL never reaches the INSERT and the column would fall
     * back to its '' default -- the one value the identity tiers below treat as adoptable.
     */
    public const MERCHANT_KEY = 'custom';

    // orderstatus_id is install-dependent (J2Store shipped 6 core statuses, J2Commerce ships 8,
    // and the migrator preserves source ids), so the registry carries the status NAME and it is
    // resolved to this install's actual id at runtime — see resolveOrderStatusIds().
    private const EMAIL_TEMPLATES = [
        1 => [
            'email_type'       => 'transactional',
            'receiver_type'    => 'customer',
            'orderstatus_name' => 'J2COMMERCE_CONFIRMED',
            'group_id'         => '*',
            'paymentmethod'    => '*',
            'subject'          => '[LANG:COM_J2COMMERCE_EMAIL_THANKS_FOR_YOUR_ORDER]',
            'file'             => 'email/confirmed/modern.html',
            'core_key'         => 'email.confirmed.customer',
        ],
        2 => [
            'email_type'       => 'transactional',
            'receiver_type'    => 'customer',
            'orderstatus_name' => 'J2COMMERCE_SHIPPED',
            'group_id'         => '*',
            'paymentmethod'    => '*',
            'subject'          => '[LANG:COM_J2COMMERCE_EMAIL_GOOD_NEWS_YOUR_ORDER_ITS_WAY]',
            'file'             => 'email/shipped/modern.html',
            'core_key'         => 'email.shipped.customer',
        ],
        3 => [
            'email_type'       => 'transactional',
            'receiver_type'    => 'customer',
            'orderstatus_name' => 'J2COMMERCE_CANCELLED',
            'group_id'         => '*',
            'paymentmethod'    => '*',
            'subject'          => '[LANG:COM_J2COMMERCE_EMAIL_ORDER_CANCELLATION_CONFIRMED]',
            'file'             => 'email/cancelled/modern.html',
            'core_key'         => 'email.cancelled.customer',
        ],
        4 => [
            'email_type'       => 'transactional',
            'receiver_type'    => 'admin',
            'orderstatus_name' => 'J2COMMERCE_CONFIRMED',
            'group_id'         => '*',
            'paymentmethod'    => '*',
            'subject'          => '[LANG:COM_J2COMMERCE_EMAIL_NEW_ORDER]',
            'file'             => 'email/confirmed/admin.html',
            'core_key'         => 'email.confirmed.admin',
        ],
    ];

    private const INVOICE_TEMPLATES = [
        1 => [
            'invoice_type'   => 'packingslip',
            'title'          => 'Packing Slip',
            'orderstatus_id' => '*',
            'group_id'       => '1',
            'paymentmethod'  => '*',
            'file'           => 'packingslip/modern.html',
            'core_key'       => 'invoice.packingslip',
        ],
        2 => [
            'invoice_type'   => 'invoice',
            'title'          => 'Invoice',
            'orderstatus_id' => '*',
            'group_id'       => '1',
            'paymentmethod'  => '*',
            'file'           => 'invoice/modern.html',
            'core_key'       => 'invoice.invoice',
        ],
        3 => [
            'invoice_type'   => 'receipt',
            'title'          => 'Receipt (Thermal)',
            'orderstatus_id' => '*',
            'group_id'       => '1',
            'paymentmethod'  => '*',
            'file'           => 'receipt/thermal.html',
            'core_key'       => 'invoice.receipt.thermal',
        ],
        4 => [
            'invoice_type'   => 'receipt',
            'title'          => 'Receipt (Full Page)',
            'orderstatus_id' => '*',
            'group_id'       => '1',
            'paymentmethod'  => '*',
            'file'           => 'receipt/modern.html',
            'core_key'       => 'invoice.receipt.full',
        ],
    ];

    public function syncEmailTemplates(): array
    {
        $names     = array_values(array_unique(array_column(self::EMAIL_TEMPLATES, 'orderstatus_name')));
        $statusIds = $this->resolveOrderStatusIds($names);

        $skipped  = [];
        $registry = [];

        foreach (self::EMAIL_TEMPLATES as $id => $expected) {
            $name = $expected['orderstatus_name'];

            if (!isset($statusIds[$name])) {
                // An unmapped status name must never fall back to a literal id or '*' — that
                // would risk matching (or inserting into) the wrong order status.
                $skipped[] = ['id' => $id, 'status' => 'skipped_status_unmapped'];
                continue;
            }

            $registry[$id] = $expected + ['orderstatus_id' => $statusIds[$name]];
        }

        $synced = $this->syncTemplates(
            '#__j2commerce_emailtemplates',
            'j2commerce_emailtemplate_id',
            $registry,
            function (DatabaseInterface $db, array $expected): array {
                $emailType       = $expected['email_type'];
                $emailTypeLegacy = '';
                $orderstatus     = $expected['orderstatus_id'];
                $receiver        = $expected['receiver_type'];
                $wildcard        = '*';

                // A legacy row stores receiver_type='*' for BOTH the customer and admin
                // variant of the same order status, so one such row is ambiguous between
                // two registry entries and can only ever be claimed by one of them. Trying
                // the exact receiver_type first (tier 1) keeps a store that already has a
                // real admin/customer row from having it stolen by the other entry; the
                // '*' row (tier 2) is only a fallback for entries with no exact match.
                $exact = $db->getQuery(true)
                    ->select($db->quoteName('j2commerce_emailtemplate_id'))
                    ->from($db->quoteName('#__j2commerce_emailtemplates'))
                    // Accept the legacy '' email_type (old core default) so pre-existing rows are recognized, not duplicated.
                    ->where($db->quoteName('email_type') . ' IN (:emailType, :emailTypeLegacy)')
                    ->where($db->quoteName('orderstatus_id') . ' = :orderstatusId')
                    ->where($db->quoteName('receiver_type') . ' = :receiverType')
                    ->order($db->quoteName('j2commerce_emailtemplate_id') . ' ASC')
                    ->bind(':emailType', $emailType)
                    ->bind(':emailTypeLegacy', $emailTypeLegacy)
                    ->bind(':orderstatusId', $orderstatus)
                    ->bind(':receiverType', $receiver);

                $wildcardFallback = $db->getQuery(true)
                    ->select($db->quoteName('j2commerce_emailtemplate_id'))
                    ->from($db->quoteName('#__j2commerce_emailtemplates'))
                    ->where($db->quoteName('email_type') . ' IN (:emailType2, :emailTypeLegacy2)')
                    ->where($db->quoteName('orderstatus_id') . ' = :orderstatusId2')
                    ->where($db->quoteName('receiver_type') . ' = :receiverWildcard')
                    ->order($db->quoteName('j2commerce_emailtemplate_id') . ' ASC')
                    ->bind(':emailType2', $emailType)
                    ->bind(':emailTypeLegacy2', $emailTypeLegacy)
                    ->bind(':orderstatusId2', $orderstatus)
                    ->bind(':receiverWildcard', $wildcard);

                return [$exact, $wildcardFallback];
            },
            static fn (array $expected, string $content, string $bodyJson, ?string $subject, int $id): array => [
                'email_type'       => $expected['email_type'],
                'receiver_type'    => $expected['receiver_type'],
                'orderstatus_id'   => $expected['orderstatus_id'],
                'group_id'         => $expected['group_id'],
                'paymentmethod'    => $expected['paymentmethod'],
                'subject'          => $subject ?? $expected['subject'],
                'body'             => $content,
                'body_json'        => $bodyJson,
                'body_source'      => 'visual',
                'body_source_file' => '',
                'language'         => '*',
                'enabled'          => 1,
                'ordering'         => $id,
                'core_key'         => $expected['core_key'],
            ]
        );

        return array_merge($skipped, $synced);
    }

    /**
     * Resolves order status names to this install's actual ids. A name that does not resolve
     * to exactly one row is omitted (never guessed) so the caller can skip it explicitly.
     */
    private function resolveOrderStatusIds(array $names): array
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_orderstatus_id', 'orderstatus_name']))
            ->from($db->quoteName('#__j2commerce_orderstatuses'))
            ->whereIn($db->quoteName('orderstatus_name'), $names, ParameterType::STRING);
        $db->setQuery($query);
        $rows = $db->loadAssocList() ?: [];

        $counts = array_count_values(array_column($rows, 'orderstatus_name'));
        $ids    = [];

        foreach ($rows as $row) {
            if ($counts[$row['orderstatus_name']] === 1) {
                $ids[$row['orderstatus_name']] = (string) (int) $row['j2commerce_orderstatus_id'];
            }
        }

        return $ids;
    }

    public function syncInvoiceTemplates(): array
    {
        return $this->syncTemplates(
            '#__j2commerce_invoicetemplates',
            'j2commerce_invoicetemplate_id',
            self::INVOICE_TEMPLATES,
            function (DatabaseInterface $db, array $expected): array {
                $invoiceType = $expected['invoice_type'];
                $title       = $expected['title'];

                $exact = $db->getQuery(true)
                    ->select($db->quoteName('j2commerce_invoicetemplate_id'))
                    ->from($db->quoteName('#__j2commerce_invoicetemplates'))
                    ->where($db->quoteName('invoice_type') . ' = :invoiceType')
                    ->where($db->quoteName('title') . ' = :title')
                    ->order($db->quoteName('j2commerce_invoicetemplate_id') . ' ASC')
                    ->bind(':invoiceType', $invoiceType)
                    ->bind(':title', $title);

                return [$exact];
            },
            static fn (array $expected, string $content, string $bodyJson, ?string $subject, int $id): array => [
                'invoice_type'     => $expected['invoice_type'],
                'title'            => $expected['title'],
                'orderstatus_id'   => $expected['orderstatus_id'],
                'group_id'         => $expected['group_id'],
                'paymentmethod'    => $expected['paymentmethod'],
                'body'             => $content,
                'body_json'        => $bodyJson,
                'body_source'      => 'visual',
                'body_source_file' => '',
                'language'         => '*',
                'enabled'          => 1,
                'ordering'         => $id,
                'core_key'         => $expected['core_key'],
            ]
        );
    }

    /**
     * @param  callable(DatabaseInterface, array): array<\Joomla\Database\QueryInterface>  $identityQueries Builds the ordered SELECT tiers (by identity fields, not PK) that find an existing row — most specific tier first, legacy-wildcard fallback last.
     * @param  callable(array, string, string, ?string, int): array<string, int|string>     $buildInsertRow  Builds the column => value map used to recreate a missing row.
     */
    private function syncTemplates(string $table, string $pkColumn, array $registry, callable $identityQueries, callable $buildInsertRow): array
    {
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $results = [];

        // A legacy row's identity fields (e.g. receiver_type='*') can be ambiguous between
        // two registry entries — both a customer and an admin entry for the same order
        // status can match the SAME row, and a store can even hold TWO such wildcard rows
        // for one status (a pre-existing email_type duplicate). Tracking claimed primary
        // keys within this run guarantees a row is never written by more than one entry.
        // Crucially, once a tier query is evaluated for an entry, EVERY row it returned —
        // not just the one selected — is marked claimed: those rows all belong to the same
        // ambiguity pool, and leaving the "spare" one open would let a later entry with a
        // different receiver_type silently annex it instead of getting its own fresh row.
        $claimed = [];

        foreach ($registry as $id => $expected) {
            $filePath = Path::clean(JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts/templates/' . $expected['file']);

            if (!is_readable($filePath)) {
                $results[] = ['id' => $id, 'status' => 'skipped_file_missing'];
                continue;
            }

            $content  = (string) file_get_contents($filePath);
            $bodyJson = '';
            $subject  = null;

            // Optional `<!--@subject: ... -->` directive at the top of the preset is the single
            // source for the row's subject; extract it and strip it from the saved body.
            if (preg_match('/<!--@subject:\s*(.*?)\s*-->[ \t]*\r?\n?/', $content, $matches)) {
                $subject = $matches[1];
                $content = preg_replace('/<!--@subject:.*?-->[ \t]*\r?\n?/', '', $content, 1);
            }

            $existingId = null;
            $coreKey    = (string) $expected['core_key'];
            $unclaimed  = '';

            // Tier 0 is the row this entry has already stamped, so an installed core template is
            // found by its own name and nothing else can answer to it. The identity tiers below
            // remain for the one legacy pass that adopts a row predating the column, and for a
            // merchant who deleted the core row outright.
            $byCoreKey = $db->getQuery(true)
                ->select($db->quoteName($pkColumn))
                ->from($db->quoteName($table))
                ->where($db->quoteName('core_key') . ' = :coreKey')
                ->order($db->quoteName($pkColumn) . ' ASC')
                ->bind(':coreKey', $coreKey);

            $tiers = $identityQueries($db, $expected);

            // A duplicate is stamped MERCHANT_KEY, and it matches every identity field of the
            // row it was copied from -- the whole reason a merchant's copy used to be a
            // candidate here. Confining the identity tiers to rows still carrying the unclaimed
            // '' takes copies out of the running for good.
            foreach ($tiers as $tierQuery) {
                $tierQuery->where($db->quoteName('core_key') . ' = :unclaimedKey')
                    ->bind(':unclaimedKey', $unclaimed);
            }

            array_unshift($tiers, $byCoreKey);

            // Never rely on unordered loadResult()/loadObject() — each tier is ordered by
            // PK ascending, and within a tier the lowest-id row not already claimed by an
            // earlier registry entry wins. Only fall to the next (less specific) tier when
            // this tier has no unclaimed candidate at all.
            foreach ($tiers as $tierQuery) {
                $db->setQuery($tierQuery);
                $candidateIds = array_map('intval', $db->loadColumn() ?: []);

                if ($existingId === null) {
                    foreach ($candidateIds as $candidateId) {
                        if (!isset($claimed[$candidateId])) {
                            $existingId = $candidateId;
                            break;
                        }
                    }
                }

                foreach ($candidateIds as $candidateId) {
                    $claimed[$candidateId] = true;
                }

                if ($existingId !== null) {
                    break;
                }
            }

            if ($existingId !== null) {
                $claimed[$existingId] = true;

                // Stamping the key on every write is what makes the legacy adoption a one-off:
                // from here on this row answers to tier 0 and the identity tiers are never
                // consulted for it again.
                $update = $db->getQuery(true)
                    ->update($db->quoteName($table))
                    ->set($db->quoteName('body') . ' = :body')
                    ->set($db->quoteName('body_json') . ' = :bodyJson')
                    ->set($db->quoteName('core_key') . ' = :coreKeyStamp')
                    ->where($db->quoteName($pkColumn) . ' = :updateId')
                    ->bind(':body', $content)
                    ->bind(':bodyJson', $bodyJson)
                    ->bind(':coreKeyStamp', $coreKey)
                    ->bind(':updateId', $existingId, ParameterType::INTEGER);

                if ($subject !== null) {
                    $update->set($db->quoteName('subject') . ' = :subject')
                        ->bind(':subject', $subject);
                }

                $db->setQuery($update);
                $db->execute();

                $results[] = ['id' => $id, 'status' => 'updated', 'file' => $expected['file']];
                continue;
            }

            $row     = $buildInsertRow($expected, $content, $bodyJson, $subject, $id);
            $insert  = $db->getQuery(true)->insert($db->quoteName($table));
            $columns = [];
            $params  = [];
            $index   = 0;

            foreach (array_keys($row) as $column) {
                $columns[]  = $db->quoteName($column);
                $paramName  = ':i' . $index++;
                $params[]   = $paramName;
                $insert->bind($paramName, $row[$column], \is_int($row[$column]) ? ParameterType::INTEGER : ParameterType::STRING);
            }

            $insert->columns($columns)->values(implode(',', $params));
            $db->setQuery($insert);
            $db->execute();

            $results[] = ['id' => $id, 'status' => 'created', 'file' => $expected['file']];
        }

        return $results;
    }
}
