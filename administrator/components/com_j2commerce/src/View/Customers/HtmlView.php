<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Customers;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

/**
 * Customers list view class.
 *
 * @since  6.0.7
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;
    /**
     * Array of customer items.
     *
     * @var    array
     * @since  6.0.7
     */
    protected $items;

    /**
     * Pagination object.
     *
     * @var    \Joomla\CMS\Pagination\Pagination
     * @since  6.0.7
     */
    protected $pagination;

    /**
     * Model state object.
     *
     * @var    \Joomla\Registry\Registry
     * @since  6.0.7
     */
    protected $state;

    /**
     * Form object for filters.
     *
     * @var    \Joomla\CMS\Form\Form
     * @since  6.0.7
     */
    public $filterForm;

    /**
     * Array of active filters.
     *
     * @var    array
     * @since  6.0.7
     */
    public $activeFilters;

    /**
     * Is this view an empty state.
     *
     * @var    bool
     * @since  6.1.3
     */
    private $isEmptyState = false;


    /**
     * Display the view.
     *
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    public function display($tpl = null): void
    {
        if (!J2CommerceHelper::canAccess('j2commerce.vieworders')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();

        $this->navbar = $this->getNavbar();

        $model = $this->getModel();

        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        $this->isEmptyState = empty($this->items)
            && trim((string) $this->state->get('filter.search', '')) === ''
            && (string) $this->state->get('filter.country_id', '') === '';

        if ($this->isEmptyState) {
            $this->setLayout('emptystate');
        }

        // Check for errors
        if (\count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function getNavbar(): string
    {
        $displayData = [
            'items' => MenuHelper::getMenuItems(),
            'active' => MenuHelper::getActiveView()
        ];

        return LayoutHelper::render('navbar.default', $displayData, JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    protected function addToolbar(): void
    {
        $canDo = ContentHelper::getActions('com_j2commerce');
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_J2COMMERCE_CUSTOMERS'), 'users');

        if ($canDo->get('core.create')) {
            $toolbar->addNew('customer.add');
        }

        if ($canDo->get('core.delete')) {
            $toolbar->delete('customers.delete')
                ->text('JTOOLBAR_DELETE')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
        }

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            $toolbar->preferences('com_j2commerce');
        }

        ToolbarHelper::help('Customers', true, 'https://docs.j2commerce.com/v6/sales/customers');
    }
}
