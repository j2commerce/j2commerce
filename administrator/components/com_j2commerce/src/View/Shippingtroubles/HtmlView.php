<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\View\Shippingtroubles;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

/**
 * Shipping Troubles View
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    /**
     * Current step in the troubleshooter
     *
     * @var  string
     */
    protected $step;

    /**
     * Diagnostic data
     *
     * @var  array
     */
    protected $diagnostics;

    /**
     * An array of items
     *
     * @var  array
     */
    protected $items;

    /**
     * Products shipping status data
     *
     * @var  array
     */
    protected $products;

    /**
     * The model state
     *
     * @var   \Joomla\CMS\Object\CMSObject
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
     * Summary statistics
     *
     * @var  array
     */
    protected $summaryStats;

    /**
     * Pagination object
     *
     * @var  \Joomla\CMS\Pagination\Pagination
     */
    protected $pagination;

    /**
     * Products shipping statistics
     *
     * @var  array
     */
    protected $productsStats;

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
        if (!J2CommerceHelper::canAccess('j2commerce.viewsetup')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();

        $this->navbar = $this->getNavbar();

        $app = Factory::getApplication();
        $input = $app->getInput();

        // Get the current step from the URL
        $this->step = $input->get('step', 'welcome', 'cmd');

        $model = $this->getModel('Shippingtroubles');

        // Load data based on the current step
        switch ($this->step) {
            case 'shipping':
                $this->diagnostics = $model->getShippingMethodsDiagnostic();
                $tpl = 'shipping';
                break;

            case 'products':
                // Use the new ListModel methods
                $this->items = $model->getItems();
                $this->pagination = $model->getPagination();
                $this->state = $model->getState();
                $this->filterForm = $model->getFilterForm();
                $this->activeFilters = $model->getActiveFilters();
                $this->productsStats = $model->getProductsShippingStatistics();

                // Keep backward compatibility
                $this->products = $this->items;
                $tpl = 'shipping_product';
                break;

            default:
                // Welcome step - load summary stats
                $this->summaryStats = $model->getSummaryStats();
                $tpl = null; // Will use default.php
                break;
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
     * @since   6.0.0
     */
    protected function addToolbar()
    {
        $canDo = ContentHelper::getActions('com_j2commerce');
        $user  = Factory::getApplication()->getIdentity();

        // Set title based on current step
        switch ($this->step) {
            case 'shipping':
                ToolbarHelper::title(
                    Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_METHODS_TITLE'),
                    'troubleshooter fa-solid fa-truck-medical'
                );
                break;

            case 'products':
                ToolbarHelper::title(
                    Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_PRODUCTS_TITLE'),
                    'troubleshooter fa-solid fa-truck-medical'
                );
                break;

            default:
                ToolbarHelper::title(
                    Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER'),
                    'troubleshooter fa-solid fa-truck-medical'
                );
                break;
        }

        // Add help button
        $toolbar = Factory::getApplication()->getDocument()->getToolbar();

        // Add preferences for admin users
        if ($user->authorise('core.admin', 'com_j2commerce') || $user->authorise('core.options', 'com_j2commerce')) {
            $toolbar->preferences('com_j2commerce');
        }

        $toolbar->help('Shipping_Troubleshooter', true, 'https://docs.j2commerce.com/troubleshooting-guide/troubleshooting-shipping-methods');
    }

    /**
     * Get the current step
     *
     * @return  string
     *
     * @since   6.0.0
     */
    public function getStep()
    {
        return $this->step;
    }

    /**
     * Get diagnostic data
     *
     * @return  array
     *
     * @since   6.0.0
     */
    public function getDiagnostics()
    {
        return $this->diagnostics ?: [];
    }

    /**
     * Get products data
     *
     * @return  array
     *
     * @since   6.0.0
     */
    public function getProducts()
    {
        return $this->products ?: [];
    }

    /**
     * Get items data
     *
     * @return  array
     *
     * @since   6.0.0
     */
    public function getItems()
    {
        return $this->items ?: [];
    }

    /**
     * Get the model state
     *
     * @return  \Joomla\CMS\Object\CMSObject
     *
     * @since   6.0.0
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Get the filter form
     *
     * @return  \Joomla\CMS\Form\Form
     *
     * @since   6.0.0
     */
    public function getFilterForm()
    {
        return $this->filterForm;
    }

    /**
     * Get the active filters
     *
     * @return  array
     *
     * @since   6.0.0
     */
    public function getActiveFilters()
    {
        return $this->activeFilters ?: [];
    }

    /**
     * Get summary statistics
     *
     * @return  array
     *
     * @since   6.0.0
     */
    public function getSummaryStats()
    {
        return $this->summaryStats ?: [];
    }

    /**
     * Get pagination object
     *
     * @return  \Joomla\CMS\Pagination\Pagination|null
     *
     * @since   6.0.0
     */
    public function getPagination()
    {
        return $this->pagination;
    }

    /**
     * Get products shipping statistics
     *
     * @return  array
     *
     * @since   6.0.0
     */
    public function getProductsStats()
    {
        return $this->productsStats ?: [];
    }
}
