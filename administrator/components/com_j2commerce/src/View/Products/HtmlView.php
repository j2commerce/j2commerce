<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Products;

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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Products list view class.
 *
 * @since  6.0.3
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;
    /**
     * The list of products
     *
     * @var  array
     * @since  6.0.3
     */
    protected $items;

    /**
     * The pagination object
     *
     * @var  \Joomla\CMS\Pagination\Pagination
     * @since  6.0.3
     */
    protected $pagination;

    /**
     * The model state
     *
     * @var  \Joomla\Registry\Registry
     * @since  6.0.3
     */
    protected $state;

    /**
     * Form object for filters
     *
     * @var  \Joomla\CMS\Form\Form
     * @since  6.0.3
     */
    public $filterForm;

    /**
     * The active search filters
     *
     * @var  array
     * @since  6.0.3
     */
    public $activeFilters;

    /**
     * Is this view an Empty State?
     *
     * @var   boolean
     * @since 6.0.6
     */
    private $isEmptyState = false;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function display($tpl = null): void
    {
        if (!J2CommerceHelper::canAccess('j2commerce.viewproducts')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();
        $app    = Factory::getApplication();
        $layout = $app->getInput()->getCmd('layout', 'default');
        $tmpl   = $app->getInput()->getCmd('tmpl', '');

        // Only add navbar for non-modal views
        if ($layout !== 'modal' && $tmpl !== 'component') {
            $this->navbar = $this->getNavbar();
        }

        $model = $this->getModel();

        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        if ((!\is_array($this->items) || !\count($this->items)) && $this->isEmptyState = $model->getIsEmptyState()) {
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
     * @since   6.0.3
     */
    protected function addToolbar(): void
    {
        $canDo   = ContentHelper::getActions('com_j2commerce');
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_J2COMMERCE_PRODUCTS'), 'fa-solid fa-tags');

        if ($canDo->get('core.create')) {
            // Redirect to com_content article creation since J2Commerce products are attached to articles
            // Include return URL so user comes back to J2Commerce products list after saving/canceling
            $return        = urlencode(base64_encode((string) Uri::getInstance()));
            $newArticleUrl = Route::_('index.php?option=com_content&view=article&layout=edit&return=' . $return, false);
            $toolbar->linkButton('new', 'JTOOLBAR_NEW')
                ->url($newArticleUrl)
                ->icon('icon-plus');
        }

        if ($canDo->get('core.edit.state')) {
            $dropdown = $toolbar->dropdownButton('status-group', 'JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            $childBar->publish('products.publish')->listCheck(true);
            $childBar->unpublish('products.unpublish')->listCheck(true);
        }

        if ($canDo->get('core.delete')) {
            $dropdown = $toolbar->dropdownButton('delete-group', 'JTOOLBAR_DELETE')
                ->toggleSplit(false)
                ->icon('icon-times')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            $childBar->delete('products.delete')
                ->text('COM_J2COMMERCE_TOOLBAR_DELETE_PRODUCT')
                ->message('COM_J2COMMERCE_CONFIRM_DELETE_PRODUCT')
                ->listCheck(true);

            $childBar->delete('products.deleteWithArticles')
                ->text('COM_J2COMMERCE_TOOLBAR_DELETE_PRODUCT_AND_ARTICLE')
                ->message('COM_J2COMMERCE_CONFIRM_DELETE_PRODUCT_AND_ARTICLE')
                ->listCheck(true);
        }

        if (!$this->isEmptyState) {
            // Advanced Pricing link
            $toolbar->linkButton('advancedpricing')
                ->text('COM_J2COMMERCE_TOOLBAR_ADVANCED_PRICING')
                ->url('index.php?option=com_j2commerce&view=advancedpricing')
                ->icon('fa-solid fa-tags');
        }

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            $toolbar->preferences('com_j2commerce');
        }

        $toolbar->help('Products');
    }
}
