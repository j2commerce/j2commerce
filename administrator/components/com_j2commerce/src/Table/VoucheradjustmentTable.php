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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Voucher balance adjustment ledger — append-only, no update()/delete() surface.
 *
 * @since  6.5.0
 */
class VoucheradjustmentTable extends Table
{
    private const ADJUSTMENT_TYPES = ['credit', 'debit', 'correction'];

    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__j2commerce_voucheradjustments', 'j2commerce_voucheradjustment_id', $db);
    }

    public function check(): bool
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }

        if (empty($this->j2commerce_voucher_id)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_VOUCHER')));
            return false;
        }

        if (!\in_array($this->adjustment_type, self::ADJUSTMENT_TYPES, true)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_ADJUSTMENT_TYPE_INVALID'));
            return false;
        }

        if ((float) $this->amount <= 0) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_ADJUSTMENT_AMOUNT_INVALID'));
            return false;
        }

        if (empty($this->reason)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_ADJUSTMENT_REASON')));
            return false;
        }

        if (empty($this->created_on)) {
            $this->created_on = Factory::getDate()->toSql();
        }

        if (empty($this->created_by)) {
            $this->created_by = (int) Factory::getApplication()->getIdentity()->id;
        }

        if (!isset($this->ip_address) || $this->ip_address === '') {
            $this->ip_address = Factory::getApplication()->input->server->getString('REMOTE_ADDR', '');
        }

        return true;
    }
}
