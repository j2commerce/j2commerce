<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class OrderTransactionTable extends Table
{
    private const VALID_TYPES = ['DEBIT', 'CAPTURE', 'AUTH', 'REVERSAL', 'VOID'];

    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_j2commerce.ordertransaction';
        parent::__construct('#__j2commerce_order_transactions', 'j2commerce_order_transaction_id', $db);
    }

    /** @throws \UnexpectedValueException */
    public function check(): bool
    {
        parent::check();

        if (empty($this->type)) {
            $this->type = 'DEBIT';
        }

        if (!\in_array($this->type, self::VALID_TYPES, true)) {
            throw new \UnexpectedValueException(\sprintf('Invalid order transaction type "%s".', $this->type));
        }

        if (empty($this->state)) {
            $this->state = 'succeeded';
        }

        if (empty($this->created_at)) {
            $this->created_at = Factory::getDate()->toSql();
        }

        if (!isset($this->created_by) || $this->created_by === '') {
            $this->created_by = 0;
        }

        if (!isset($this->parent_txn_id)) {
            $this->parent_txn_id = '';
        }

        if (!isset($this->plugin)) {
            $this->plugin = '';
        }

        if (!isset($this->currency_code)) {
            $this->currency_code = '';
        }

        if (!isset($this->amount) || $this->amount === '') {
            $this->amount = 0;
        }

        return true;
    }
}
