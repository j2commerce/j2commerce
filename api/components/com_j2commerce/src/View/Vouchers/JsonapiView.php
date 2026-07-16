<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\View\Vouchers;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Api\View\J2CommerceJsonapiView;

class JsonapiView extends J2CommerceJsonapiView
{
    protected string $pkField = 'j2commerce_voucher_id';

    protected $fieldsToRenderItem = [
        'j2commerce_voucher_id',
        'order_id',
        'email_to',
        'voucher_code',
        'voucher_type',
        'subject',
        'voucher_value',
        'valid_from',
        'valid_to',
        'enabled',
        'created_on',
        'remaining_balance',
        'uses_count',
        'derived_status',
    ];

    protected $fieldsToRenderList = [
        'j2commerce_voucher_id',
        'voucher_code',
        'voucher_type',
        'email_to',
        'voucher_value',
        'enabled',
        'created_on',
        'remaining_balance',
        'uses_count',
        'derived_status',
    ];

    protected $relationship = [];

    /**
     * Enriches each voucher with D1 derived-balance fields (remaining_balance, uses_count,
     * derived_status) so API consumers never re-implement the ledger math client-side.
     *
     * List rows arrive from VouchersModel::getListQuery(), which already computes all three
     * fields in one aggregated query — those are reused as-is to avoid N+1 per-row queries.
     * Only single-item display (VoucherModel::getItem(), no aggregates) computes them here.
     */
    protected function prepareItem($item)
    {
        $item = parent::prepareItem($item);

        if (!isset($item->{$this->pkField})) {
            return $item;
        }

        $id = (int) $item->{$this->pkField};

        /** @var \J2Commerce\Component\J2commerce\Administrator\Model\VoucherModel $model */
        $model = $this->getModel();

        $item->remaining_balance = isset($item->remaining_balance)
            ? (float) $item->remaining_balance
            : $model->getRemainingBalance($id);

        $item->uses_count = isset($item->uses_count)
            ? (int) $item->uses_count
            : \count(array_filter(
                $model->getLedger($id),
                static fn (object $row): bool => $row->type === 'redemption'
            ));

        if (empty($item->derived_status)) {
            $item->derived_status = $this->deriveStatus($item, $item->remaining_balance);
        }

        return $item;
    }

    private function deriveStatus(object $item, float $remaining): string
    {
        $now = time();

        return match (true) {
            !$item->enabled => 'disabled',
            !empty($item->valid_to) && strtotime((string) $item->valid_to) < $now => 'expired',
            !empty($item->valid_from) && strtotime((string) $item->valid_from) > $now => 'not_yet_valid',
            $remaining <= 0 => 'depleted',
            default => 'active',
        };
    }
}
