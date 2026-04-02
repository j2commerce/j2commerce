<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Vendor;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

/**
 * Vendor edit view class.
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
     * Custom address fields for dynamic rendering
     *
     * @var    array
     * @since  6.0.7
     */
    public $addressCustomFields = [];

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
        if (!J2CommerceHelper::canAccess('j2commerce.viewproducts')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();

        $model = $this->getModel();
        $model->setUseExceptions(true);

        $this->form  = $model->getForm();
        $this->item  = $model->getItem();
        $this->state = $model->getState();

        // Load custom address fields for template use
        $this->addressCustomFields = $model->getAddressCustomFields();

        $this->addToolbar();

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

        $isNew      = ($this->item->j2commerce_vendor_id == 0);
        $canDo      = ContentHelper::getActions('com_j2commerce');
        $user       = Factory::getApplication()->getIdentity();
        $checkedOut = !(($this->item->checked_out ?? null) === null || ($this->item->checked_out ?? 0) == $user->id);
        $toolbar    = $this->getDocument()->getToolbar();

        // Title: "New Vendor" or "Edit Vendor"
        $title = $isNew ? Text::_('COM_J2COMMERCE_TOOLBAR_NEW') . ' ' . Text::_('COM_J2COMMERCE_VENDOR') : Text::_('COM_J2COMMERCE_TOOLBAR_EDIT') . ' ' . Text::_('COM_J2COMMERCE_VENDOR');
        ToolbarHelper::title($title, 'fas fa-solid fa-user-tag');

        // Only show save buttons when the item is not checked out by another user.
        if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'))) {
            $toolbar->apply('vendor.apply');
        }

        if ($canDo->get('core.edit.state')) {
            // Dropdown save group with configure() callback
            $saveGroup = $toolbar->dropdownButton('save-group');
            $saveGroup->configure(
                function (Toolbar $childBar) use ($canDo, $isNew, $checkedOut) {
                    if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'))) {
                        $childBar->save('vendor.save');
                    }

                    if ($canDo->get('core.create')) {
                        $childBar->save2new('vendor.save2new');
                    }

                    if (!$isNew && $canDo->get('core.create')) {
                        $childBar->save2copy('vendor.save2copy');
                    }
                }
            );
        }

        $toolbar->cancel('vendor.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
        $toolbar->divider();
        ToolbarHelper::help('Vendors', true, 'https://docs.j2commerce.com/catalog/vendors/');
    }
}
