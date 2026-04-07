<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Coupon;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

/**
 * Coupon edit view class.
 *
 * @since  6.0.6
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    /**
     * The Form object
     *
     * @var    Form|null
     * @since  6.0.6
     */
    protected $form;

    /**
     * The active item
     *
     * @var    object
     * @since  6.0.6
     */
    protected $item;

    /**
     * The model state
     *
     * @var    Registry
     * @since  6.0.6
     */
    protected $state;

    /**
     * Display the view.
     *
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    public function display($tpl = null): void
    {
        if (!J2CommerceHelper::canAccess('j2commerce.vieworders')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();

        $model = $this->getModel();
        $model->setUseExceptions(true);

        $this->form  = $model->getForm();
        $this->item  = $model->getItem();
        $this->state = $model->getState();

        $this->addToolbar();

        $layout = Factory::getApplication()->getInput()->get('layout', 'edit');

        // If this is the history layout, get coupon usage details
        if ($layout === 'history') {
            $this->orders = $model->getCouponUsageDetails();
        }

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function addToolbar(): void
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $user       = Factory::getApplication()->getIdentity();
        $isNew      = ($this->item->j2commerce_coupon_id == 0);
        $canDo      = ContentHelper::getActions('com_j2commerce');
        $checkedOut = !(($this->item->checked_out ?? null) === null || ($this->item->checked_out ?? 0) == $user->id);
        $toolbar    = $this->getDocument()->getToolbar();

        $layout          = Factory::getApplication()->getInput()->get('layout', 'history');
        $isEditLayout    = ($layout === 'edit');
        $isHistoryLayout = ($layout === 'history');

        if ($isNew) {
            ToolbarHelper::title(Text::_('COM_J2COMMERCE_TOOLBAR_NEW') . ' ' . Text::_('COM_J2COMMERCE_COUPON'), 'fa-solid fa-scissors');
        } else {
            if ($isEditLayout) {
                ToolbarHelper::title(Text::_('COM_J2COMMERCE_TOOLBAR_EDIT').': '.$this->item->coupon_name, 'fa fa-solid fa-list-alt');
            } else {
                ToolbarHelper::title(Text::_('COM_J2COMMERCE_COUPON_HISTORY').': '.$this->item->coupon_name, 'fa fa-solid fa-list-alt');
            }
        }

        if ($isNew) {

            // For new records, check the edit permissions.
            if ($canDo->get('core.create')) {
                $toolbar->apply('coupon.apply');

                $saveGroup = $toolbar->dropdownButton('save-group');
                $childBar  = $saveGroup->getChildToolbar();
                $childBar->save('coupon.save');
                $childBar->save2new('coupon.save2new');
            }

            $toolbar->cancel('coupon.cancel');
        } else {
            if ($isEditLayout) {
                // Since it's an existing record, check the edit permissions, or fall back to edit own if the owner.
                $itemEditable = $canDo->get('core.edit') || ($canDo->get(
                    'core.edit.own'
                ) && $this->item->created_by == $user->id);

                // Can't save the record if it's checked out and editable
                if (!$checkedOut && $itemEditable) {
                    $toolbar->apply('coupon.apply');
                }

                $saveGroup = $toolbar->dropdownButton('save-group');
                $childBar  = $saveGroup->getChildToolbar();

                if (!$checkedOut && $itemEditable) {
                    $childBar->save('coupon.save');

                    if ($canDo->get('core.create')) {
                        $childBar->save2new('coupon.save2new');
                    }
                }

                if ($canDo->get('core.create')) {
                    $childBar->save2copy('coupon.save2copy');
                }

                $toolbar->cancel('coupon.cancel', 'JTOOLBAR_CLOSE');

                // Add "View Voucher History"
                $toolbar->linkButton('coupon-history')
                    ->text('COM_J2COMMERCE_COUPON_VIEW_HISTORY')
                    ->url(
                        'index.php?option=com_j2commerce&view=coupon&layout=history&j2commerce_coupon_id=' . $this->item->j2commerce_coupon_id
                    )
                    ->icon('icon-list');
            } else {
                $toolbar->linkButton('couponback')
                    ->text('JTOOLBAR_BACK')
                    ->url(
                        'index.php?option=com_j2commerce&view=coupon&layout=edit&j2commerce_coupon_id=' . $this->item->j2commerce_coupon_id
                    )
                    ->icon('icon-arrow-left');
            }
        }

        $toolbar->divider();
        ToolbarHelper::help('Coupons', true, 'https://docs.j2commerce.com/v6/sales/coupons');
    }
}
