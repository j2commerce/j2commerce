<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\Event\Event;

/**
 * Voucher item controller class.
 *
 * Handles single-item operations: edit, save, apply, cancel.
 * For bulk operations (publish, unpublish, delete, batch), see VouchersController.
 *
 * @since  6.0.6
 */
class VoucherController extends FormController
{
    protected $option      = 'com_j2commerce';
    protected $view_item   = 'voucher';
    protected $view_list   = 'vouchers';
    protected $text_prefix = 'COM_J2COMMERCE_VOUCHER';

    /**
     * The primary key name - MUST match the Table class primary key!
     * Maps URL 'id' parameter to actual table column 'j2commerce_voucher_id'.
     *
     * @var    string
     * @since  6.0.6
     */
    protected $key = 'j2commerce_voucher_id';

    // edit/save/cancel override $urlVar/$key: URLs use 'id', table PK is j2commerce_voucher_id.

    public function edit($key = null, $urlVar = 'id')
    {
        return parent::edit($key, $urlVar);
    }

    public function save($key = null, $urlVar = 'id')
    {
        return parent::save($key, $urlVar);
    }

    public function cancel($key = 'id')
    {
        return parent::cancel($key);
    }

    /**
     * Apply a manual balance adjustment (credit/debit/correction) to a voucher.
     *
     * @return  void
     *
     * @since   6.5.0
     */
    public function adjust(): void
    {
        $this->checkToken();

        $user = Factory::getApplication()->getIdentity();

        $id  = $this->input->getInt('id', 0);
        $url = Route::_('index.php?option=com_j2commerce&view=voucher&layout=history&id=' . $id, false);

        if ($user->guest || (int) $user->id === 0 || !$user->authorise('core.edit', 'com_j2commerce')) {
            $this->setRedirect($url, Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');

            return;
        }

        if (!$id) {
            $this->setRedirect($url, Text::_('COM_J2COMMERCE_VOUCHER_SEND_NO_VOUCHER'), 'error');

            return;
        }

        $adjustmentType = $this->input->getWord('adjustment_type', '');
        $amount         = $this->input->getFloat('amount', 0.0);
        $reason         = $this->input->getString('reason', '');
        $note           = $this->input->getString('note', '') ?: null;
        $orderId        = $this->input->getString('order_id', '') ?: null;

        /** @var \J2Commerce\Component\J2commerce\Administrator\Model\VoucherModel $model */
        $model = $this->getModel();

        try {
            $newBalance = $model->adjustBalance($id, $adjustmentType, $amount, $reason, $note, $orderId);
            $this->logBalanceAdjustment($id, $adjustmentType, $amount, $reason, $newBalance);
            $this->setRedirect($url, Text::sprintf('COM_J2COMMERCE_VOUCHER_BALANCE_ADJUSTED', CurrencyHelper::format($newBalance)));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->setRedirect($url, $e->getMessage(), 'error');
        } catch (\Throwable $e) {
            Log::add('Voucher balance adjustment failed for voucher ' . $id . ': ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
            $this->setRedirect($url, Text::_('COM_J2COMMERCE_VOUCHER_ADJUSTMENT_FAILED'), 'error');
        }
    }

    /**
     * Mirrors a balance adjustment to the User Actions Log. The ledger table remains the
     * system of record; this is a read-friendly visibility trail only.
     *
     * @since   6.5.0
     */
    private function logBalanceAdjustment(int $id, string $type, float $amount, string $reason, float $newBalance): void
    {
        $delta = match ($type) {
            'credit'     => abs($amount),
            'debit'      => -abs($amount),
            'correction' => $amount,
        };

        PluginHelper::importPlugin('actionlog');
        Factory::getApplication()->getDispatcher()->dispatch(
            'onJ2CommerceVoucherBalanceAdjusted',
            new Event('onJ2CommerceVoucherBalanceAdjusted', [$id, $type, $delta, $newBalance, $reason])
        );
    }
}
