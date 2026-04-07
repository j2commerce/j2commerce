<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Currencies;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

/**
 * Currencies list view class.
 *
 * @since  6.0.4
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    /**
     * An array of items
     *
     * @var    array
     * @since  6.0.4
     */
    protected $items = [];

    /**
     * The pagination object
     *
     * @var    Pagination
     * @since  6.0.4
     */
    protected $pagination;

    /**
     * The model state
     *
     * @var    Registry
     * @since  6.0.4
     */
    protected $state;

    /**
     * Form object for search filters
     *
     * @var    Form|null
     * @since  6.0.4
     */
    public $filterForm;

    /**
     * The active search filters
     *
     * @var    array
     * @since  6.0.4
     */
    public $activeFilters = [];

    /**
     * Is this view a modal
     *
     * @var    boolean
     * @since  6.0.4
     */
    protected $isModal = false;

    /**
     * Is this view an Empty State?
     *
     * @var   boolean
     * @since 6.0.4
     */
    private $isEmptyState = false;

    /**
     * Display the view.
     *
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     *
     * @since   6.0.4
     */
    public function display($tpl = null): void
    {
        $this->loadAdminAssets();

        $this->navbar = $this->getNavbar();

        $model = $this->getModel();
        $model->setUseExceptions(true);

        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        if (!\count($this->items) && $this->isEmptyState = $model->getIsEmptyState()) {
            $this->setLayout('emptystate');
        }

        // Check if modal
        $this->isModal = Factory::getApplication()->getInput()->get('layout') === 'modal';

        if (!$this->isModal) {
            $this->addToolbar();
        }

        parent::display($tpl);
    }

    protected function getNavbar(): string
    {
        $displayData = [
            'items'  => MenuHelper::getMenuItems(),
            'active' => MenuHelper::getActiveView(),
        ];

        return LayoutHelper::render('navbar.default', $displayData, JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   6.0.4
     */
    protected function addToolbar(): void
    {
        $canDo   = ContentHelper::getActions('com_j2commerce');
        $user    = Factory::getApplication()->getIdentity();
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_J2COMMERCE_CURRENCIES'), 'fas fa-solid fa-dollar fa-dollar-sign');

        if ($canDo->get('core.create')) {
            $toolbar->addNew('currency.add');
        }

        if (!$this->isEmptyState) {
            if ($canDo->get('core.edit.state')) {
                // Dropdown button group for status changes
                $dropdown = $toolbar->dropdownButton('status-group')
                    ->text('JTOOLBAR_CHANGE_STATUS')
                    ->toggleSplit(false)
                    ->icon('icon-ellipsis-h')
                    ->buttonClass('btn btn-action')
                    ->listCheck(true);

                $childBar = $dropdown->getChildToolbar();
                $childBar->publish('currencies.publish')->listCheck(true);
                $childBar->unpublish('currencies.unpublish')->listCheck(true);

                if ($this->state->get('filter.enabled') != -2) {
                    $childBar->trash('currencies.trash')->listCheck(true);
                }
            }
        }

        if ($canDo->get('core.delete') && ($this->state->get('filter.enabled') == -2)) {
            $toolbar->delete('currencies.delete', 'JTOOLBAR_DELETE_FROM_TRASH')->listCheck(true);
        }

        if ($canDo->get('core.manage') && PluginHelper::isEnabled('j2commerce', 'app_currencyupdater')) {
            $db             = Factory::getContainer()->get('DatabaseDriver');
            $publishedCount = (int) $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_currencies'))
                    ->where($db->quoteName('enabled') . ' = 1')
            )->loadResult();

            if ($publishedCount > 1) {
                $toolbar->standardButton('updaterates', 'COM_J2COMMERCE_TOOLBAR_UPDATE_RATES', 'currencies.updateRates')
                    ->icon('fa-solid fa-arrows-rotate')
                    ->listCheck(false);
            }
        }

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            ToolbarHelper::preferences('com_j2commerce');
        }

        ToolbarHelper::help('Currencies', true, 'https://docs.j2commerce.com/v6/localisation/currencies');
    }
}
