<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Voucher;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Voucher View
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    /**
     * The \Joomla\CMS\Form\Form object
     *
     * @var  \Joomla\CMS\Form\Form
     */
    public $form;

    /**
     * The active item
     *
     * @var  object
     */
    public $item;

    /**
     * The model state
     *
     * @var  object
     */
    public $state;

    /** History layout: ledger rows, list machinery and KPI totals. */
    public array $ledger = [];
    public $ledgerPagination;
    public $ledgerState;
    public $filterForm;
    public array $activeFilters    = [];
    public float $redeemedTotal    = 0.0;
    public float $adjustmentsNet   = 0.0;
    public float $remainingBalance = 0.0;
    public bool $ledgerIsEmpty     = true;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @throws  \Exception
     */
    public function display($tpl = null)
    {
        if (!J2CommerceHelper::canAccess('j2commerce.vieworders')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();

        $model       = $this->getModel();
        $this->state = $model->getState();
        $this->item  = $model->getItem();
        $this->form  = $model->getForm();

        // Get layout to determine what data to load
        $layout = Factory::getApplication()->getInput()->get('layout', 'edit');

        // If this is the history layout, get the KPI summary + the filterable/sortable/
        // paginated ledger table (a real ListModel so it behaves like every other list view)
        if ($layout === 'history') {
            $voucherId = (int) ($this->item->j2commerce_voucher_id ?? 0);

            // Full unfiltered ledger — KPI totals must reflect the true balance, not
            // whatever the table below happens to be filtered/paginated to.
            $fullLedger = $model->getLedger($voucherId);

            $this->redeemedTotal  = 0.0;
            $this->adjustmentsNet = 0.0;

            foreach ($fullLedger as $row) {
                if ($row->type === 'redemption') {
                    $this->redeemedTotal += $row->amount;
                } else {
                    $this->adjustmentsNet += $row->signed_amount;
                }
            }

            $this->remainingBalance = $model->getRemainingBalance($voucherId);
            $this->ledgerIsEmpty    = empty($fullLedger);

            /** @var \J2Commerce\Component\J2commerce\Administrator\Model\VoucherhistoryModel $historyModel */
            $historyModel = Factory::getApplication()
                ->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createModel('Voucherhistory', 'Administrator', ['ignore_request' => false]);

            $this->ledger           = $historyModel->getItems();
            $this->ledgerPagination = $historyModel->getPagination();
            $this->ledgerState      = $historyModel->getState();

            // Property names below are read directly by the core joomla.searchtools.default
            // layout off $view->filterForm / $view->activeFilters — must not be renamed.
            $this->filterForm    = $historyModel->getFilterForm();
            $this->activeFilters = $historyModel->getActiveFilters();

            HTMLHelper::_('bootstrap.modal');
        }

        // Check for errors.
        if (\is_array($errors = $model->getErrors()) && \count($errors)) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @throws  \Exception
     *
     * @since   6.0.0
     */
    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $user       = Factory::getApplication()->getIdentity();
        $isNew      = (empty($this->item->j2commerce_voucher_id) || $this->item->j2commerce_voucher_id == 0);
        $checkedOut = !empty($this->item->checked_out) && (int) $this->item->checked_out !== (int) $user->id;
        $canDo      = ContentHelper::getActions('com_j2commerce', 'voucher', $this->item->j2commerce_voucher_id ?? 0);

        $layout          = Factory::getApplication()->getInput()->get('layout', 'history');
        $isEditLayout    = ($layout === 'edit');
        $isHistoryLayout = ($layout === 'history');

        // Get the toolbar object instance
        $toolbar = Factory::getApplication()->getDocument()->getToolbar();

        if ($isNew) {
            ToolbarHelper::title(Text::_('COM_J2COMMERCE_VOUCHER_NEW'), 'fa-solid fa-money-check');
        } else {
            if ($isEditLayout) {
                ToolbarHelper::title(Text::sprintf('COM_J2COMMERCE_VOUCHER_EDIT', $this->item->j2commerce_voucher_id), 'fa-solid fa-money-check');
            } else {
                ToolbarHelper::title(Text::_('COM_J2COMMERCE_VOUCHER_HISTORY') . ': ' . $this->item->voucher_code, 'fa-solid fa-money-check');
            }
        }

        if ($isNew) {

            // For new records, check the edit permissions.
            if ($canDo->get('core.create')) {
                $toolbar->apply('voucher.apply');

                $saveGroup = $toolbar->dropdownButton('save-group');
                $childBar  = $saveGroup->getChildToolbar();
                $childBar->save('voucher.save');
                $childBar->save2new('voucher.save2new');
            }

            $toolbar->cancel('voucher.cancel');
        } else {
            if ($isEditLayout) {
                // Since it's an existing record, check the edit permissions, or fall back to edit own if the owner.
                $itemEditable = $canDo->get('core.edit') || ($canDo->get('core.edit.own') && $this->item->created_by == $user->id);

                // Can't save the record if it's checked out and editable
                if (!$checkedOut && $itemEditable) {
                    $toolbar->apply('voucher.apply');
                }

                $saveGroup = $toolbar->dropdownButton('save-group');
                $childBar  = $saveGroup->getChildToolbar();

                if (!$checkedOut && $itemEditable) {
                    $childBar->save('voucher.save');

                    if ($canDo->get('core.create')) {
                        $childBar->save2new('voucher.save2new');
                    }
                }

                if ($canDo->get('core.create')) {
                    $childBar->save2copy('voucher.save2copy');
                }
                $toolbar->cancel('voucher.cancel', 'JTOOLBAR_CLOSE');

                // Add "View Voucher History"
                $toolbar->linkButton('voucher-history')
                    ->text('COM_J2COMMERCE_VOUCHER_VIEW_HISTORY')
                    ->url('index.php?option=com_j2commerce&view=voucher&layout=history&id=' . $this->item->j2commerce_voucher_id)
                    ->icon('icon-list');

                // "Send Voucher" moved to plg_j2commerce_app_giftcertificate — it injects its
                // own button here via onAfterDispatch() and sends through the Central Email
                // Hub's giftcertificate template instead of this record's raw ad-hoc fields.
            } else {
                $toolbar->linkButton('voucherback')
                    ->text('JTOOLBAR_BACK')
                    ->url('index.php?option=com_j2commerce&view=voucher&layout=edit&id=' . $this->item->j2commerce_voucher_id)
                    ->icon('icon-arrow-left');

                if ($canDo->get('core.edit')) {
                    $toolbar->standardButton('voucher-adjust-balance')
                        ->text('COM_J2COMMERCE_VOUCHER_ADJUST_BALANCE')
                        ->icon('icon-fa-solid fa-money-check')
                        ->buttonClass('btn btn-primary')
                        ->onclick('')
                        ->attributes(['data-bs-toggle' => 'modal', 'data-bs-target' => '#adjustBalanceModal']);
                }
            }
        }

        $toolbar->divider();
        $toolbar->help(Text::_('COM_J2COMMERCE_VOUCHER'), false, 'https://docs.j2commerce.com/v6/sales/vouchers/');
    }
}
