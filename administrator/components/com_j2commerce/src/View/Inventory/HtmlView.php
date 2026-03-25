<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\View\Inventory;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * View class for a list of inventory items.
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
     * @var  Pagination
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
     * @var  Form
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
     * @since 4.0.0
     */
    private $isEmptyState = false;

    /**
     * Display the view.
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     */
    public function display($tpl = null): void
    {
        if (!$this->getCurrentUser()->authorise('j2commerce.viewproducts', 'com_j2commerce')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();

        $this->navbar = $this->getNavbar();

        $model = $this->getModel();
        $this->items = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->state = $model->getState();
        $this->filterForm = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        if (!\count($this->items) && $this->isEmptyState = $this->get('IsEmptyState')) {
            $this->setLayout('emptystate');
        }

        // Check for errors.
        if (\count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        // We don't need toolbar in the modal window.
        if ($this->getLayout() !== 'modal') {
            $this->addToolbar();

            // We do not need to filter by language when multilingual is disabled
            if (!Factory::getApplication()->isClient('site')) {
                unset($this->activeFilters['language']);
                if ($this->filterForm && $this->filterForm->getField('language', 'filter')) {
                    $this->filterForm->removeField('language', 'filter');
                }
            }
        } else {
            // In article associations modal we need to remove language filter if forcing a language.
            // We also need to change the category filter to show show categories with All or the forced language.
            if ($forcedLanguage = Factory::getApplication()->getInput()->get('forcedLanguage', '', 'CMD')) {
                // If the language is forced we can't allow to select the language, so transform the language selector filter into a hidden field.
                $languageXml = new \SimpleXMLElement('<field name="language" type="hidden" default="' . $forcedLanguage . '" />');
                $this->filterForm->setField($languageXml, 'filter', true);

                // Also, unset the active language filter so the search tools is not open by default with this filter.
                unset($this->activeFilters['language']);
            }
        }

        //J2CommerceHelper::addSubmenu('inventory');

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
    protected function addToolbar(): void
    {
        $canDo = J2CommerceHelper::getActions('com_j2commerce');
        $app = Factory::getApplication();

        // Get the toolbar object instance
        $toolbar = $app->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_J2COMMERCE_INVENTORY_MANAGER_TITLE'), 'fa-solid fa-barcode');

        if ($canDo->get('core.edit')) {
            $toolbar->standardButton('batch', Text::_('COM_J2COMMERCE_INVENTORY_BATCH_UPDATE'), 'inventory.batch')
                ->listCheck(true);
        }

        // Advanced Pricing link
        $toolbar->linkButton('advancedpricing')
            ->text('COM_J2COMMERCE_TOOLBAR_ADVANCED_PRICING')
            ->url('index.php?option=com_j2commerce&view=advancedpricing')
            ->icon('fas fa-tags');

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            $toolbar->preferences('com_j2commerce');
        }

        $toolbar->help('COM_J2COMMERCE_HELP_INVENTORY');
    }

    /**
     * Returns an array of fields the table can be sorted by
     *
     * @return  array  Array containing the field name to sort by as the key and display text as value
     *
     * @since   6.0.0
     */
    protected function getSortFields()
    {
        return [
            'p.j2commerce_product_id' => Text::_('COM_J2COMMERCE_INVENTORY_PRODUCT_ID'),
            'a.title' => Text::_('COM_J2COMMERCE_INVENTORY_PRODUCT_NAME'),
            'v.sku' => Text::_('COM_J2COMMERCE_INVENTORY_SKU'),
            'pq.quantity' => Text::_('COM_J2COMMERCE_INVENTORY_QUANTITY'),
            'v.manage_stock' => Text::_('COM_J2COMMERCE_INVENTORY_MANAGE_STOCK'),
            'v.availability' => Text::_('COM_J2COMMERCE_INVENTORY_STOCK_STATUS'),
        ];
    }
}
