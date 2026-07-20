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
use Joomla\CMS\Filesystem\Path;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Overwrites the DB-stored `body`/`body_json` of the fixed set of core email
 * and invoice/print templates with the content of their currently-installed
 * `.html` presets under layouts/templates/. Skips rows an admin deleted or
 * repurposed so a customised row is never clobbered.
 */
class CoreTemplateSyncHelper
{
    private const EMAIL_TEMPLATES = [
        1 => ['email_type' => 'transactional', 'receiver_type' => 'customer', 'orderstatus_id' => '1', 'file' => 'email/confirmed/modern.html'],
        2 => ['email_type' => 'transactional', 'receiver_type' => 'customer', 'orderstatus_id' => '7', 'file' => 'email/shipped/modern.html'],
        3 => ['email_type' => 'transactional', 'receiver_type' => 'customer', 'orderstatus_id' => '6', 'file' => 'email/cancelled/modern.html'],
        4 => ['email_type' => 'transactional', 'receiver_type' => 'admin', 'orderstatus_id' => '1', 'file' => 'email/confirmed/admin.html'],
    ];

    private const INVOICE_TEMPLATES = [
        1 => ['invoice_type' => 'packingslip', 'title' => 'Packing Slip', 'file' => 'packingslip/modern.html'],
        2 => ['invoice_type' => 'invoice', 'title' => 'Invoice', 'file' => 'invoice/modern.html'],
        3 => ['invoice_type' => 'receipt', 'title' => 'Receipt (Thermal)', 'file' => 'receipt/thermal.html'],
        4 => ['invoice_type' => 'receipt', 'title' => 'Receipt (Full Page)', 'file' => 'receipt/modern.html'],
    ];

    public function syncEmailTemplates(): array
    {
        return $this->syncTemplates(
            '#__j2commerce_emailtemplates',
            'j2commerce_emailtemplate_id',
            self::EMAIL_TEMPLATES,
            ['email_type', 'receiver_type', 'orderstatus_id', 'body_source'],
            static fn (object $row, array $expected): bool => (string) $row->email_type === $expected['email_type']
                && (string) $row->receiver_type === $expected['receiver_type']
                && (string) $row->orderstatus_id === $expected['orderstatus_id']
        );
    }

    public function syncInvoiceTemplates(): array
    {
        return $this->syncTemplates(
            '#__j2commerce_invoicetemplates',
            'j2commerce_invoicetemplate_id',
            self::INVOICE_TEMPLATES,
            ['invoice_type', 'title', 'body_source'],
            static fn (object $row, array $expected): bool => (string) $row->invoice_type === $expected['invoice_type']
                && (string) $row->title === $expected['title']
        );
    }

    /** @param  callable(object, array): bool  $matchesIdentity */
    private function syncTemplates(string $table, string $pkColumn, array $registry, array $columns, callable $matchesIdentity): array
    {
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $results = [];

        foreach ($registry as $id => $expected) {
            $query = $db->getQuery(true)
                ->select($db->quoteName(array_merge([$pkColumn], $columns)))
                ->from($db->quoteName($table))
                ->where($db->quoteName($pkColumn) . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER);
            $db->setQuery($query);
            $row = $db->loadObject();

            if (!$row) {
                $results[] = ['id' => $id, 'status' => 'skipped_missing'];
                continue;
            }

            if (!$matchesIdentity($row, $expected)) {
                $results[] = ['id' => $id, 'status' => 'skipped_mismatch'];
                continue;
            }

            $filePath = Path::clean(JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts/templates/' . $expected['file']);

            if (!is_readable($filePath)) {
                $results[] = ['id' => $id, 'status' => 'skipped_file_missing'];
                continue;
            }

            $content  = (string) file_get_contents($filePath);
            $bodyJson = '';

            $update = $db->getQuery(true)
                ->update($db->quoteName($table))
                ->set($db->quoteName('body') . ' = :body')
                ->set($db->quoteName('body_json') . ' = :bodyJson')
                ->where($db->quoteName($pkColumn) . ' = :updateId')
                ->bind(':body', $content)
                ->bind(':bodyJson', $bodyJson)
                ->bind(':updateId', $id, ParameterType::INTEGER);
            $db->setQuery($update);
            $db->execute();

            $results[] = ['id' => $id, 'status' => 'updated', 'file' => $expected['file']];
        }

        return $results;
    }
}
