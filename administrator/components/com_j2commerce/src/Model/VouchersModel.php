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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Vouchers list model class.
 *
 * @since  6.0.6
 */
class VouchersModel extends ListModel
{
    /** D4 status precedence, reused across list/filter/CSV/customer view/API. */
    public const STATUS_CASE_SQL = "CASE"
        . " WHEN a.enabled = 0 THEN 'disabled'"
        . " WHEN a.valid_to IS NOT NULL AND a.valid_to < NOW() THEN 'expired'"
        . " WHEN a.valid_from IS NOT NULL AND a.valid_from > NOW() THEN 'not_yet_valid'"
        . " WHEN (a.voucher_value - COALESCE(red.redeemed_total, 0)"
        . " + COALESCE(adj.credit_total, 0) - COALESCE(adj.debit_total, 0)"
        . " + COALESCE(adj.correction_net, 0)) <= 0 THEN 'depleted'"
        . " ELSE 'active' END";

    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_voucher_id', 'a.j2commerce_voucher_id',
                'voucher_code', 'a.voucher_code',
                'email_to', 'a.email_to',
                'voucher_value', 'a.voucher_value',
                'enabled', 'a.enabled',
                'ordering', 'a.ordering',
                'created_on', 'a.created_on',
                'valid_from', 'a.valid_from',
                'valid_to', 'a.valid_to',
                'remaining_balance',
                'uses_count',
                'derived_status',
            ];
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = 'a.ordering', $direction = 'asc'): void
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $enabled = $this->getUserStateFromRequest($this->context . '.filter.enabled', 'filter_enabled', '', 'string');
        $this->setState('filter.enabled', $enabled);

        $status = $this->getUserStateFromRequest($this->context . '.filter.status', 'filter_status', '', 'string');
        $this->setState('filter.status', $status);

        $lowBalance = $this->getUserStateFromRequest($this->context . '.filter.low_balance', 'filter_low_balance', '', 'string');
        $this->setState('filter.low_balance', $lowBalance);

        $unassigned = $this->getUserStateFromRequest($this->context . '.filter.unassigned', 'filter_unassigned', '', 'string');
        $this->setState('filter.unassigned', $unassigned);

        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.enabled');
        $id .= ':' . $this->getState('filter.status');
        $id .= ':' . $this->getState('filter.low_balance');
        $id .= ':' . $this->getState('filter.unassigned');

        return parent::getStoreId($id);
    }

    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $remainingBalanceExpr = '(a.voucher_value - COALESCE(red.redeemed_total, 0)'
            . ' + COALESCE(adj.credit_total, 0) - COALESCE(adj.debit_total, 0)'
            . ' + COALESCE(adj.correction_net, 0))';

        $query->select(
            $db->quoteName([
                'a.j2commerce_voucher_id',
                'a.order_id',
                'a.user_id',
                'a.email_to',
                'a.voucher_code',
                'a.voucher_type',
                'a.subject',
                'a.voucher_value',
                'a.valid_from',
                'a.valid_to',
                'a.enabled',
                'a.ordering',
                'a.created_on',
                'a.created_by',
            ])
        );

        $query->select('COALESCE(red.redeemed_total, 0) AS ' . $db->quoteName('redeemed_total'));
        $query->select('COALESCE(red.uses_count, 0) AS ' . $db->quoteName('uses_count'));
        $query->select($remainingBalanceExpr . ' AS ' . $db->quoteName('remaining_balance'));
        $query->select(self::STATUS_CASE_SQL . ' AS ' . $db->quoteName('derived_status'));
        $query->select($db->quoteName('u.name', 'recipient_name'));

        $query->from($db->quoteName('#__j2commerce_vouchers', 'a'));
        $query->leftJoin($db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('a.user_id'));

        // Subquery binds do not propagate to the outer query's execution — the constant
        // discount_type value is quoted directly instead (not user input).
        $redemptions = $db->getQuery(true)
            ->select(
                $db->quoteName('od.discount_entity_id') . ', '
                . 'SUM(' . $db->quoteName('od.discount_amount') . ' + ' . $db->quoteName('od.discount_tax') . ') AS '
                . $db->quoteName('redeemed_total') . ', '
                . 'COUNT(*) AS ' . $db->quoteName('uses_count')
            )
            ->from($db->quoteName('#__j2commerce_orderdiscounts', 'od'))
            ->leftJoin($db->quoteName('#__j2commerce_orders', 'o') . ' ON ' . $db->quoteName('od.order_id') . ' = ' . $db->quoteName('o.order_id'))
            ->where($db->quoteName('od.discount_type') . ' = ' . $db->quote('voucher'))
            ->where('(' . $db->quoteName('o.order_state_id') . ' IS NULL OR ' . $db->quoteName('o.order_state_id') . ' != 5)')
            ->group($db->quoteName('od.discount_entity_id'));

        $adjustments = $db->getQuery(true)
            ->select(
                $db->quoteName('j2commerce_voucher_id') . ', '
                . 'SUM(CASE WHEN ' . $db->quoteName('adjustment_type') . " = 'credit' THEN " . $db->quoteName('amount') . ' ELSE 0 END) AS '
                . $db->quoteName('credit_total') . ', '
                . 'SUM(CASE WHEN ' . $db->quoteName('adjustment_type') . " = 'debit' THEN " . $db->quoteName('amount') . ' ELSE 0 END) AS '
                . $db->quoteName('debit_total') . ', '
                . "SUM(CASE WHEN " . $db->quoteName('adjustment_type') . " = 'correction'"
                . ' THEN (' . $db->quoteName('balance_after') . ' - ' . $db->quoteName('balance_before') . ') ELSE 0 END) AS '
                . $db->quoteName('correction_net')
            )
            ->from($db->quoteName('#__j2commerce_voucheradjustments'))
            ->group($db->quoteName('j2commerce_voucher_id'));

        $query->leftJoin('(' . (string) $redemptions . ') AS ' . $db->quoteName('red') . ' ON ' . $db->quoteName('red.discount_entity_id') . ' = ' . $db->quoteName('a.j2commerce_voucher_id'));
        $query->leftJoin('(' . (string) $adjustments . ') AS ' . $db->quoteName('adj') . ' ON ' . $db->quoteName('adj.j2commerce_voucher_id') . ' = ' . $db->quoteName('a.j2commerce_voucher_id'));

        // Filter by enabled state
        $enabled = $this->getState('filter.enabled');

        if (is_numeric($enabled)) {
            $enabled = (int) $enabled;
            $query->where($db->quoteName('a.enabled') . ' = :enabled')
                ->bind(':enabled', $enabled, ParameterType::INTEGER);
        } elseif ($enabled === '') {
            $query->where($db->quoteName('a.enabled') . ' IN (0, 1)');
        }

        // Filter by D4 derived status
        $status = (string) $this->getState('filter.status');

        if ($status !== '' && in_array($status, ['disabled', 'expired', 'not_yet_valid', 'depleted', 'active'], true)) {
            $query->having(self::STATUS_CASE_SQL . ' = :status')
                ->bind(':status', $status);
        }

        // Filter by low balance (0 < remaining < threshold% of voucher_value)
        $lowBalance = (string) $this->getState('filter.low_balance');

        if ($lowBalance === '1') {
            $lowBalancePct = (float) J2CommerceHelper::config()->get('voucher_low_balance_pct', 20);
            $query->having(
                '(' . $remainingBalanceExpr . ' > 0 AND ' . $remainingBalanceExpr . ' < (a.voucher_value * :lowBalancePct / 100))'
            )
                ->bind(':lowBalancePct', $lowBalancePct);
        }

        // Filter by unassigned (no owner, no recipient email)
        $unassigned = (string) $this->getState('filter.unassigned');

        if ($unassigned === '1') {
            $query->where(
                '(' . $db->quoteName('a.user_id') . ' IS NULL AND (' . $db->quoteName('a.email_to') . " = '' OR " . $db->quoteName('a.email_to') . ' IS NULL))'
            );
        }

        // Filter by search
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $searchId = (int) substr($search, 3);
                $query->where($db->quoteName('a.j2commerce_voucher_id') . ' = :searchId')
                    ->bind(':searchId', $searchId, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where(
                    '(' .
                    $db->quoteName('a.voucher_code') . ' LIKE :search1 OR ' .
                    $db->quoteName('a.email_to') . ' LIKE :search2' .
                    ')'
                )
                    ->bind(':search1', $search)
                    ->bind(':search2', $search);
            }
        }

        // Add ordering clause — validated against the filter_fields allow-list, never bound as identifier
        $orderCol = $this->state->get('list.ordering', 'a.ordering');
        $orderDir = strtoupper((string) $this->state->get('list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        if (!in_array($orderCol, $this->filter_fields, true)) {
            $orderCol = 'a.ordering';
        }

        $query->order($db->escape($orderCol) . ' ' . $orderDir);

        return $query;
    }

    /**
     * Get the active voucher code from cart or session.
     *
     * Retrieves the voucher code from the cart table if available,
     * otherwise falls back to the session. Also stores the voucher
     * code in session for consistency.
     *
     * @return  string  The voucher code or empty string if not found.
     *
     * @since   6.0.6
     */
    public function get_voucher(): string
    {
        $app         = Factory::getApplication();
        $session     = $app->getSession();
        $voucherCode = '';

        try {
            // Try to get cart model and check for cart_voucher
            $cartModel = $app->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createModel('Cart', 'Administrator');

            if ($cartModel) {
                $cart = $cartModel->getCart(0, false);

                if (isset($cart->cart_voucher) && !empty($cart->cart_voucher)) {
                    // Store voucher from cart to session
                    $session->set('voucher', $cart->cart_voucher, 'j2commerce');
                    $voucherCode = $cart->cart_voucher;
                } else {
                    // Fall back to session-stored voucher
                    $voucherCode = $session->get('voucher', '', 'j2commerce');
                }
            } else {
                // Fall back to session if cart model unavailable
                $voucherCode = $session->get('voucher', '', 'j2commerce');
            }
        } catch (\Exception $e) {
            // Fall back to session on any error
            $voucherCode = $session->get('voucher', '', 'j2commerce');
        }

        return $voucherCode;
    }

    /**
     * Set the voucher code in session.
     *
     * @param   string  $voucherCode  The voucher code to set.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    public function set_voucher(string $voucherCode): void
    {
        $session = Factory::getApplication()->getSession();
        $session->set('voucher', $voucherCode, 'j2commerce');
    }

    /**
     * Clear the voucher code from session.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    public function clear_voucher(): void
    {
        $session = Factory::getApplication()->getSession();
        $session->clear('voucher', 'j2commerce');
    }
}
