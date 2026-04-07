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
 * Vouchers list model class.
 *
 * @since  6.0.6
 */
class VouchersModel extends ListModel
{
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

        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.enabled');

        return parent::getStoreId($id);
    }

    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select(
            $db->quoteName([
                'a.j2commerce_voucher_id',
                'a.order_id',
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

        $query->from($db->quoteName('#__j2commerce_vouchers', 'a'));

        // Filter by enabled state
        $enabled = $this->getState('filter.enabled');

        if (is_numeric($enabled)) {
            $enabled = (int) $enabled;
            $query->where($db->quoteName('a.enabled') . ' = :enabled')
                ->bind(':enabled', $enabled, ParameterType::INTEGER);
        } elseif ($enabled === '') {
            $query->where($db->quoteName('a.enabled') . ' IN (0, 1)');
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

        // Add ordering clause
        $orderCol  = $this->state->get('list.ordering', 'a.ordering');
        $orderDir  = $this->state->get('list.direction', 'ASC');
        $ordering  = $db->escape($orderCol) . ' ' . $db->escape($orderDir);

        $query->order($ordering);

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
