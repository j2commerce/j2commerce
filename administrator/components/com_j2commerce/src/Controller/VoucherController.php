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

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

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

    /**
     * Override edit to use 'id' as URL parameter.
     * Required because Table uses j2commerce_voucher_id but URLs use standard 'id'.
     */
    public function edit($key = null, $urlVar = 'id')
    {
        return parent::edit($key, $urlVar);
    }

    /**
     * Override save to use 'id' as URL parameter.
     */
    public function save($key = null, $urlVar = 'id')
    {
        return parent::save($key, $urlVar);
    }

    /**
     * Override cancel to use 'id' as URL parameter.
     */
    public function cancel($key = 'id')
    {
        return parent::cancel($key);
    }

    /**
     * Send a voucher email to the recipient.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    public function send(): void
    {
        $this->checkToken();

        $id  = $this->input->getInt('id', 0);
        $url = Route::_('index.php?option=com_j2commerce&view=voucher&layout=edit&id=' . $id, false);

        if (!$id) {
            $this->setRedirect($url, Text::_('COM_J2COMMERCE_VOUCHER_SEND_NO_VOUCHER'), 'error');

            return;
        }

        /** @var \J2Commerce\Component\J2commerce\Administrator\Model\VoucherModel $model */
        $model = $this->getModel();

        if ($model->send($id)) {
            $this->setRedirect($url, Text::_('COM_J2COMMERCE_VOUCHER_SEND_SUCCESS'));
        } else {
            $this->setRedirect($url, Text::_('COM_J2COMMERCE_VOUCHER_SEND_FAILED'), 'error');
        }
    }
}
