<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Voucher ledger list model — redemptions (orderdiscounts) UNION ALL manual balance
 * adjustments (voucheradjustments), normalized to one row shape and searchable/sortable/
 * paginated exactly like a core ListModel list, powering the table on the voucher
 * History screen.
 *
 * @since  6.5.0
 */
class VoucherhistoryModel extends ListModel
{
    private const TYPES = ['redemption', 'credit', 'debit', 'correction'];

    private int $voucherId;

    public function __construct($config = [])
    {
        $this->voucherId = Factory::getApplication()->getInput()->getInt('id', 0);

        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'type', 'amount', 'running_balance', 'reference', 'actor', 'reason', 'created_on',
            ];
        }

        parent::__construct($config);

        // Distinct context per voucher — filter/sort/page state for voucher #3's history
        // must not bleed into voucher #5's history.
        $this->context = $this->option . '.voucherhistory.' . $this->voucherId;
    }

    protected function populateState($ordering = 'created_on', $direction = 'DESC'): void
    {
        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->voucherId;
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.type');
        $id .= ':' . $this->getState('filter.amount_from');
        $id .= ':' . $this->getState('filter.amount_to');

        return parent::getStoreId($id);
    }

    private function getVoucherValue(): float
    {
        $db    = $this->getDatabase();
        $id    = $this->voucherId;
        $query = $db->getQuery(true)
            ->select($db->quoteName('voucher_value'))
            ->from($db->quoteName('#__j2commerce_vouchers'))
            ->where($db->quoteName('j2commerce_voucher_id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query);

        return (float) ($db->loadResult() ?? 0.0);
    }

    /**
     * Redemption rows (orderdiscounts) UNION ALL adjustment rows (voucheradjustments),
     * normalized to one column shape. Built as raw SQL and embedded as a FROM-subquery
     * string in getListQuery() — bind() on these inner pieces would NOT propagate to the
     * outer query's execution, so voucherId is embedded as a validated (int) cast and the
     * 'voucher' discount_type constant via $db->quote(), matching the established pattern
     * in VouchersModel::getListQuery().
     */
    private function getLedgerSourceSql(): string
    {
        $db = $this->getDatabase();
        $id = $this->voucherId;

        $redemptions = $db->getQuery(true)
            ->select([
                $db->quote('redemption') . ' AS ' . $db->quoteName('type'),
                '(' . $db->quoteName('od.discount_amount') . ' + ' . $db->quoteName('od.discount_tax') . ') AS ' . $db->quoteName('amount'),
                '-(' . $db->quoteName('od.discount_amount') . ' + ' . $db->quoteName('od.discount_tax') . ') AS ' . $db->quoteName('signed_amount'),
                $db->quoteName('od.order_id') . ' AS ' . $db->quoteName('reference'),
                $db->quoteName('o.j2commerce_order_id') . ' AS ' . $db->quoteName('order_pk'),
                $db->quoteName('o.user_email') . ' AS ' . $db->quoteName('actor'),
                'NULL AS ' . $db->quoteName('reason'),
                'NULL AS ' . $db->quoteName('note'),
                $db->quoteName('o.created_on') . ' AS ' . $db->quoteName('created_on'),
                '0 AS ' . $db->quoteName('type_rank'),
                $db->quoteName('od.j2commerce_orderdiscount_id') . ' AS ' . $db->quoteName('row_id'),
            ])
            ->from($db->quoteName('#__j2commerce_orderdiscounts', 'od'))
            ->leftJoin($db->quoteName('#__j2commerce_orders', 'o') . ' ON ' . $db->quoteName('od.order_id') . ' = ' . $db->quoteName('o.order_id'))
            ->where($db->quoteName('od.discount_type') . ' = ' . $db->quote('voucher'))
            ->where($db->quoteName('od.discount_entity_id') . ' = ' . (int) $id)
            ->where('(' . $db->quoteName('o.order_state_id') . ' IS NULL OR ' . $db->quoteName('o.order_state_id') . ' != 5)');

        $adjustments = $db->getQuery(true)
            ->select([
                $db->quoteName('a.adjustment_type') . ' AS ' . $db->quoteName('type'),
                $db->quoteName('a.amount') . ' AS ' . $db->quoteName('amount'),
                'CASE ' . $db->quoteName('a.adjustment_type')
                    . " WHEN 'credit' THEN " . $db->quoteName('a.amount')
                    . " WHEN 'debit' THEN -" . $db->quoteName('a.amount')
                    . ' ELSE (' . $db->quoteName('a.balance_after') . ' - ' . $db->quoteName('a.balance_before') . ')'
                    . ' END AS ' . $db->quoteName('signed_amount'),
                $db->quoteName('a.order_id') . ' AS ' . $db->quoteName('reference'),
                'NULL AS ' . $db->quoteName('order_pk'),
                'COALESCE(' . $db->quoteName('u.name') . ", '') AS " . $db->quoteName('actor'),
                $db->quoteName('a.reason') . ' AS ' . $db->quoteName('reason'),
                $db->quoteName('a.note') . ' AS ' . $db->quoteName('note'),
                $db->quoteName('a.created_on') . ' AS ' . $db->quoteName('created_on'),
                '1 AS ' . $db->quoteName('type_rank'),
                $db->quoteName('a.j2commerce_voucheradjustment_id') . ' AS ' . $db->quoteName('row_id'),
            ])
            ->from($db->quoteName('#__j2commerce_voucheradjustments', 'a'))
            ->leftJoin($db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('a.created_by'))
            ->where($db->quoteName('a.j2commerce_voucher_id') . ' = ' . (int) $id);

        return (string) $redemptions . ' UNION ALL ' . (string) $adjustments;
    }

    /**
     * Wraps the UNION with a running-balance window function computed over the FULL
     * chronological history for this voucher — running_balance must reflect the true
     * balance as of that transaction regardless of what the outer query later filters to.
     */
    private function getWindowedLedgerSql(): string
    {
        $db           = $this->getDatabase();
        $voucherValue = number_format($this->getVoucherValue(), 5, '.', '');

        return 'SELECT ' . $db->quoteName('u') . '.*, ('
            . $voucherValue . ' + SUM(' . $db->quoteName('u.signed_amount') . ') OVER ('
            . 'ORDER BY ' . $db->quoteName('u.created_on') . ' ASC, ' . $db->quoteName('u.type_rank') . ' ASC, ' . $db->quoteName('u.row_id') . ' ASC'
            . ' ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW'
            . ')) AS ' . $db->quoteName('running_balance')
            . ' FROM (' . $this->getLedgerSourceSql() . ') AS ' . $db->quoteName('u');
    }

    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true);
        $query->select($db->quoteName('ledger') . '.*')
            ->from('(' . $this->getWindowedLedgerSql() . ') AS ' . $db->quoteName('ledger'));

        $type = (string) $this->getState('filter.type');

        if (\in_array($type, self::TYPES, true)) {
            $query->where($db->quoteName('ledger.type') . ' = :type')
                ->bind(':type', $type);
        }

        $amountFrom = $this->getState('filter.amount_from');

        if ($amountFrom !== null && $amountFrom !== '') {
            $amountFromVal = (float) $amountFrom;
            $query->where($db->quoteName('ledger.amount') . ' >= :amountFrom')
                ->bind(':amountFrom', $amountFromVal);
        }

        $amountTo = $this->getState('filter.amount_to');

        if ($amountTo !== null && $amountTo !== '') {
            $amountToVal = (float) $amountTo;
            $query->where($db->quoteName('ledger.amount') . ' <= :amountTo')
                ->bind(':amountTo', $amountToVal);
        }

        $search = (string) $this->getState('filter.search');

        if ($search !== '') {
            $search = '%' . str_replace(' ', '%', trim($search)) . '%';
            $query->where(
                '(' .
                $db->quoteName('ledger.reference') . ' LIKE :search1 OR ' .
                $db->quoteName('ledger.actor') . ' LIKE :search2 OR ' .
                $db->quoteName('ledger.reason') . ' LIKE :search3 OR ' .
                $db->quoteName('ledger.note') . ' LIKE :search4' .
                ')'
            )
                ->bind(':search1', $search)
                ->bind(':search2', $search)
                ->bind(':search3', $search)
                ->bind(':search4', $search);
        }

        $orderCol = (string) $this->state->get('list.ordering', 'created_on');
        $orderDir = strtoupper((string) $this->state->get('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        if (!\in_array($orderCol, $this->filter_fields, true)) {
            $orderCol = 'created_on';
        }

        $query->order($db->quoteName($orderCol) . ' ' . $orderDir);

        return $query;
    }
}
