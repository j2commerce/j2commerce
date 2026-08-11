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

use J2Commerce\Component\J2commerce\Administrator\Helper\CsvHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;

/**
 * Vouchers list controller class.
 *
 * @since  6.0.6
 */
class VouchersController extends AdminController
{
    use WriteAccessTrait;

    protected string $writeAction = 'j2commerce.editorders';

    /** exportCsv reads rather than writes, so it names the read actions itself instead. */
    protected function readTasks(): array
    {
        return [
            'display',
            'exportCsv',
        ];
    }

    /**
     * The prefix to use with controller messages.
     * Uses general prefix so bulk action messages use shared language strings.
     *
     * @var    string
     * @since  6.0.6
     */
    protected $text_prefix = 'COM_J2COMMERCE';

    public function getModel($name = 'Voucher', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Streams the currently-filtered voucher list as CSV, reusing the list model's own
     * populated searchtools filter state.
     *
     * @since   6.5.0
     */
    public function exportCsv(): void
    {
        $this->checkToken('get');

        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        // Reading every voucher at once carries the codes and their remaining balances, so it
        // answers to the same pair the order export does rather than to the component floor.
        if (
            $user->guest
            || (int) $user->id === 0
            || !J2CommerceHelper::canAccess('j2commerce.vieworders')
            || !J2CommerceHelper::canAccess('j2commerce.exportorders')
        ) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        /** @var \J2Commerce\Component\J2commerce\Administrator\Model\VouchersModel $model */
        $model = $app->bootComponent('com_j2commerce')->getMVCFactory()->createModel('Vouchers', 'Administrator');
        $model->setState('list.limit', 0);

        $items = $model->getItems();

        $app->setHeader('Content-Type', 'text/csv; charset=utf-8');
        $app->setHeader('Content-Disposition', 'attachment; filename="vouchers-' . date('Y-m-d') . '.csv"');
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        $app->setHeader('Pragma', 'no-cache');
        $app->setHeader('Expires', '0');
        $app->sendHeaders();

        $fp = fopen('php://output', 'w');

        fputcsv($fp, [
            Text::_('JGLOBAL_FIELD_ID'),
            Text::_('COM_J2COMMERCE_HEADING_VOUCHER_CODE'),
            Text::_('COM_J2COMMERCE_HEADING_RECIPIENT'),
            Text::_('COM_J2COMMERCE_HEADING_VALUE'),
            Text::_('COM_J2COMMERCE_VOUCHER_SUMMARY_REDEEMED'),
            Text::_('COM_J2COMMERCE_HEADING_REMAINING_BALANCE'),
            Text::_('COM_J2COMMERCE_HEADING_VOUCHER_STATUS'),
            Text::_('COM_J2COMMERCE_HEADING_VALID_FROM'),
            Text::_('COM_J2COMMERCE_HEADING_VALID_TO'),
            Text::_('COM_J2COMMERCE_FIELD_CREATED_ON'),
        ]);

        foreach ($items as $item) {
            fputcsv($fp, array_map(CsvHelper::sanitizeCell(...), [
                $item->j2commerce_voucher_id,
                $item->voucher_code,
                $item->email_to ?: $item->recipient_name,
                number_format((float) $item->voucher_value, 2, '.', ''),
                number_format((float) $item->redeemed_total, 2, '.', ''),
                number_format((float) $item->remaining_balance, 2, '.', ''),
                $item->derived_status,
                $item->valid_from ?: '',
                $item->valid_to ?: '',
                $item->created_on,
            ]));
        }

        fclose($fp);
        $app->close();
    }

}
