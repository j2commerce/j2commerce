<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Shippingmethods;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;

/**
 * View class for a list of shipping methods.
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    /**
     * An array of items
     *
     * @var  array
     */
    protected $items;

    /**
     * The pagination object
     *
     * @var  \Joomla\CMS\Pagination\Pagination
     */
    protected $pagination;

    /**
     * The model state
     *
     * @var  \Joomla\CMS\Object\CMSObject
     */
    protected $state;

    /**
     * Form object for search filters
     *
     * @var  \Joomla\CMS\Form\Form
     */
    public $filterForm;

    /**
     * The active search filters
     *
     * @var  array
     */
    public $activeFilters;

    /**
     * Is this view an Empty State
     *
     * @var  boolean
     * @since  6.0.0
     */
    private $isEmptyState = false;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function display($tpl = null): void
    {
        if (!J2CommerceHelper::canAccess('j2commerce.viewsetup')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();
        $this->loadShippingPluginLanguages();

        $this->navbar = $this->getNavbar();

        $this->shippingCards = $this->getShippingCards();

        $model               = $this->getModel();
        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        if (!\count($this->items) && $this->isEmptyState = $model->getIsEmptyState()) {
            $this->setLayout('emptystate');
        }

        // Check for errors.
        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Get navbar configuration
     *
     * @return string
     */
    protected function getNavbar(): string
    {
        $displayData = [
            'items'  => MenuHelper::getMenuItems(),
            'active' => MenuHelper::getActiveView(),
        ];

        return LayoutHelper::render('navbar.default', $displayData, JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    }

    /**
     * Get shipping cards configuration
     *
     * @return string
     */
    protected function getShippingCards(): string
    {
        $displayData = [];

        return LayoutHelper::render('shipping.default', $displayData, JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function addToolbar()
    {
        $canDo = ContentHelper::getActions('com_j2commerce', 'component');

        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_J2COMMERCE_SHIPPING_METHODS_TITLE'), 'fa-solid fa-truck-plane');

        if ($canDo->get('core.edit.state')) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('fa fa-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            $childBar->publish('shippingmethods.publish')->listCheck(true);
            $childBar->unpublish('shippingmethods.unpublish')->listCheck(true);
            $childBar->checkin('shippingmethods.checkin')->listCheck(true);
        }

        if ($this->getCurrentUser()->authorise('core.manage', 'com_installer')) {
            $toolbar->link(
                'COM_J2COMMERCE_TOOLBAR_INSTALL_SHIPPING',
                Uri::base() . 'index.php?option=com_installer&view=install'
            )
                ->icon('fa-solid fa-download')
                ->buttonClass('btn btn-primary');
        }

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            $toolbar->preferences('com_j2commerce');
        }

        $toolbar->help('Shipping_Methods', true, 'https://docs.j2commerce.com/v6/shipping-methods');
    }

    protected function loadShippingPluginLanguages(): void
    {
        $lang = Factory::getApplication()->getLanguage();
        $db   = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select($db->quoteName('element'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('j2commerce'))
            ->where($db->quoteName('element') . ' LIKE ' . $db->quote('shipping_%'));
        $db->setQuery($query);

        foreach ($db->loadColumn() ?: [] as $element) {
            $lang->load('plg_j2commerce_' . $element, JPATH_ADMINISTRATOR)
                || $lang->load('plg_j2commerce_' . $element, JPATH_PLUGINS . '/j2commerce/' . $element);
        }
    }
}
