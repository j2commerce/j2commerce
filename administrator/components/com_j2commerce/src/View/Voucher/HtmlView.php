<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\View\Voucher;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

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
    protected $form;

    /**
     * The active item
     *
     * @var  object
     */
    protected $item;

    /**
     * The model state
     *
     * @var  object
     */
    protected $state;

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

        $model = $this->getModel();
        $this->state = $model->getState();
        $this->item = $model->getItem();
        $this->form = $model->getForm();

        // Get layout to determine what data to load
        $layout = Factory::getApplication()->getInput()->get('layout', 'edit');

        // If this is the history layout, get voucher usage history
        if ($layout === 'history') {
            $this->orders = $model->getVoucherHistory();
        }

        // Check for errors.
        if (is_array($errors = $model->getErrors()) && \count($errors)) {
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
        $checkedOut = !(\is_null($this->item->checked_out) || $this->item->checked_out == $user->id);
        $canDo      = ContentHelper::getActions('com_j2commerce', 'voucher', $this->item->j2commerce_voucher_id ?? 0);

        $layout = Factory::getApplication()->getInput()->get('layout', 'history');
        $isEditLayout = ($layout === 'edit');
        $isHistoryLayout = ($layout === 'history');

        // Get the toolbar object instance
        $toolbar = Factory::getApplication()->getDocument()->getToolbar();

        if ($isNew) {
            ToolbarHelper::title(Text::_('COM_J2COMMERCE_VOUCHER_NEW'), 'fa-solid fa-money-check');
        } else {
            if ($isEditLayout) {
                ToolbarHelper::title(Text::sprintf('COM_J2COMMERCE_VOUCHER_EDIT', $this->item->j2commerce_voucher_id), 'fa-solid fa-money-check');
            } else {
                ToolbarHelper::title(Text::sprintf('COM_J2COMMERCE_VOUCHER_HISTORY_CODE', $this->item->voucher_code), 'fa-solid fa-money-check');
            }
        }

        if ($isNew) {

            // For new records, check the edit permissions.
            if ($canDo->get('core.create')) {
                $toolbar->apply('voucher.apply');

                $saveGroup = $toolbar->dropdownButton('save-group');
                $childBar = $saveGroup->getChildToolbar();
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
                $childBar = $saveGroup->getChildToolbar();

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

                // Add "Send Voucher"
                $toolbar->standardButton('voucher-send')
                    ->text('COM_J2COMMERCE_VOUCHER_SEND_VOUCHER')
                    ->task('voucher.send')
                    ->icon('icon-envelope');


            } else {
                $toolbar->linkButton('voucherback')
                    ->text('JTOOLBAR_BACK')
                    ->url('index.php?option=com_j2commerce&view=voucher&layout=edit&id=' . $this->item->j2commerce_voucher_id)
                    ->icon('icon-arrow-left');
            }
        }

        $toolbar->divider();
        $toolbar->help('Voucher', false, 'https://docs.j2commerce.com/sales/vouchers');
    }
}
