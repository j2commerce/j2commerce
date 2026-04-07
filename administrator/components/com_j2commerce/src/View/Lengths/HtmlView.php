<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Lengths;

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
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

/**
 * Lengths list view class.
 *
 * @since  6.0.2
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;
    /**
     * An array of items
     *
     * @var    array
     * @since  6.0.2
     */
    protected $items = [];

    /**
     * The pagination object
     *
     * @var    Pagination
     * @since  6.0.2
     */
    protected $pagination;

    /**
     * The model state
     *
     * @var    Registry
     * @since  6.0.2
     */
    protected $state;

    /**
     * Form object for search filters
     *
     * @var    Form|null
     * @since  6.0.2
     */
    public $filterForm;

    /**
     * The active search filters
     *
     * @var    array
     * @since  6.0.2
     */
    public $activeFilters = [];

    /**
     * Is this view a modal
     *
     * @var    boolean
     * @since  6.0.2
     */
    protected $isModal = false;

    /**
     * Is this view an Empty State?
     *
     * @var   boolean
     * @since 6.0.2
     */
    private $isEmptyState = false;

    /**
     * Display the view.
     *
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     *
     * @since   6.0.2
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
     * @since   6.0.2
     */
    protected function addToolbar(): void
    {
        $canDo   = ContentHelper::getActions('com_j2commerce');
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_J2COMMERCE_LENGTHS'), 'fa-solid fa-ruler-combined');

        if ($canDo->get('core.create')) {
            $toolbar->addNew('length.add');
        }

        if (!$this->isEmptyState) {
            if ($canDo->get('core.edit.state')) {
                $dropdown = $toolbar->dropdownButton('status-group')
                    ->text('JTOOLBAR_CHANGE_STATUS')
                    ->toggleSplit(false)
                    ->icon('icon-ellipsis-h')
                    ->buttonClass('btn btn-action')
                    ->listCheck(true);

                $childBar = $dropdown->getChildToolbar();
                $childBar->publish('lengths.publish')->listCheck(true);
                $childBar->unpublish('lengths.unpublish')->listCheck(true);

                if ($this->state->get('filter.enabled') != -2) {
                    $childBar->trash('lengths.trash')->listCheck(true);
                }
            }
        }

        if ($canDo->get('core.delete') && ($this->state->get('filter.enabled') == -2)) {
            $toolbar->delete('lengths.delete', 'JTOOLBAR_DELETE_FROM_TRASH')->listCheck(true);
        }

        if (!$this->isEmptyState && $canDo->get('core.edit')) {
            $syncUrl = 'index.php?option=com_j2commerce&task=lengths.syncValues&' . Session::getFormToken() . '=1';
            $toolbar->linkButton('sync-values', 'COM_J2COMMERCE_TOOLBAR_SYNC_VALUES')
                ->url($syncUrl)
                ->icon('fa-solid fa-arrows-rotate')
                ->buttonClass('btn btn-info');
        }

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            ToolbarHelper::preferences('com_j2commerce');
        }

        ToolbarHelper::help('Lengths', true, 'https://docs.j2commerce.com/v6/localisation/lengths');
    }
}
