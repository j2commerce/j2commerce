<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\View\Producttags;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\View\CustomSubtemplateTrait;
use Joomla\CMS\Categories\CategoryNode;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Registry\Registry;

/**
 * HTML Tagged Products list view class for site frontend.
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    use CustomSubtemplateTrait;

    /**
     * The model state
     *
     * @var    Registry
     * @since  6.0.0
     */
    protected $state;

    /**
     * Product items data
     *
     * @var    array
     * @since  6.0.0
     */
    protected $items = [];

    /**
     * Product items (alias for template plugin compatibility)
     *
     * @var    array
     * @since  6.0.0
     */
    public $products = [];

    /**
     * The pagination object
     *
     * @var    Pagination|null
     * @since  6.0.0
     */
    protected $pagination;

    /**
     * The page parameters (from menu item)
     *
     * @var   Registry
     * @since 6.0.0
     */
    public $params;

    /**
     * The parent category node
     *
     * @var   CategoryNode|null
     * @since 6.0.0
     */
    public $parent;

    /**
     * Current product being rendered in item template
     *
     * @var   object|null
     * @since 6.0.0
     */
    public $product;

    /**
     * Current product link for template rendering
     *
     * @var   string
     * @since 6.0.0
     */
    public $product_link = '';

    /**
     * Number of columns for grid layout
     *
     * @var   int
     * @since 6.0.0
     */
    protected $columns   = 3;

    /**
     * Current user
     *
     * @var   \Joomla\CMS\User\User|null
     * @since 6.0.0
     */
    protected $user;

    /**
     * Filter data for sidebar filters (categories, price, brands, etc.)
     *
     * @var   array|null
     * @since 6.0.3
     */
    public $filters;

    /**
     * Current category filter value
     *
     * @var   string|int|null
     * @since 6.0.3
     */
    public $filter_catid;

    /**
     * Active menu item
     *
     * @var   object|null
     * @since 6.0.3
     */
    public $active_menu;

    /**
     * Current tags value
     *
     * @var   string|int|null
     * @since 6.0.3
     */
    public array $tag_ids    = [];

    /**
     * Current matching of tags
     *
     * @var   string|int|null
     * @since 6.0.3
     */
    public string $tag_match = 'any';

    /**
     * Display the view.
     *
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function display($tpl = null): void
    {
        $app   = Factory::getApplication();
        $model = $this->getModel();

        // Get menu item parameters
        $this->params        = $app->getParams();

        // Load data from model
        $this->state         = $model->getState();
        $this->items         = $model->getItems();
        $this->products   = $this->items; // Alias for template plugin compatibility
        $this->parent        = $model->getParent();
        $this->pagination    = $model->getPagination();
        $this->user          = $this->getCurrentUser();

        // Load filter data for sidebar
        $this->filters       = $model->getFilters($this->items);

        // Get the active menu item and current category filter
        $this->active_menu   = $app->getMenu()->getActive();
        $this->filter_catid  = '';
        $this->tag_ids       = $model->getState('filter.tag_ids', []);
        $this->tag_match     = $model->getState('filter.tag_match', 'any');

        // Check for errors
        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        // Get display settings from params
        $this->columns   = (int) $this->params->get('list_no_of_columns', 3);
        $this->sublayout = $this->params->get('subtemplate', '');

        // Dispatch event to allow template plugins to render the view
        // The eventWithHtml() helper properly handles passing data to/from plugins
        // and collects the rendered HTML via the 'html' argument
        $event    = J2CommerceHelper::plugin()->eventWithHtml('ViewProductListTagHtml', [null, &$this, $model]);
        $viewHtml = $event->getArgument('html', '');

        // If a plugin provided HTML output, still prepare document first
        // This ensures breadcrumbs, page title, and meta tags are set even when
        // a template plugin handles the actual HTML rendering
        if (!empty($viewHtml)) {
            $this->_prepareDocument();
            echo $viewHtml;

            return;
        }

        // If a custom subtemplate is selected, try template override directory first
        if (!empty($this->sublayout)) {
            $customHtml = $this->renderCustomSubtemplate();

            if ($customHtml !== null) {
                $this->_prepareDocument();
                echo $customHtml;

                return;
            }
        }

        // No plugin or custom override provided HTML, use default template rendering
        $this->_prepareDocument();

        parent::display($tpl);
    }

    /**
     * Prepares the document.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function _prepareDocument(): void
    {
        $app  = Factory::getApplication();
        $menu = $app->getMenu()->getActive();

        // Set page heading from menu item or default
        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', Text::_('COM_J2COMMERCE_PRODUCTTAGS_VIEW_DEFAULT_TITLE'));
        }

        // Set document title
        $title = $this->params->get('page_title', '');
        if (empty($title)) {
            $title = $app->get('sitename');
        } elseif ($app->get('sitename_pagetitles', 0) == 1) {
            $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
        }

        $this->setDocumentTitle($title);

        // Set meta description
        if ($this->params->get('menu-meta_description')) {
            $this->getDocument()->setDescription($this->params->get('menu-meta_description'));
        }

        // Set meta keywords
        if ($this->params->get('menu-meta_keywords')) {
            $this->getDocument()->setMetaData('keywords', $this->params->get('menu-meta_keywords'));
        }

        // Set robots
        if ($this->params->get('robots')) {
            $this->getDocument()->setMetaData('robots', $this->params->get('robots'));
        }

        // Add custom CSS from menu item
        $customCss = $this->params->get('custom_css', '');
        if (!empty($customCss)) {
            $this->getDocument()->getWebAssetManager()->addInlineStyle($customCss);
        }
    }

    /**
     * Get Bootstrap column class based on number of columns.
     *
     * @return  string  Bootstrap column class
     *
     * @since   6.0.0
     */
    public function getColumnClass(): string
    {
        return match ($this->columns) {
            1       => 'col-12',
            2       => 'col-12 col-md-6',
            3       => 'col-12 col-md-6 col-lg-4',
            4       => 'col-12 col-md-6 col-lg-3',
            6       => 'col-12 col-md-4 col-lg-2',
            default => 'col-12 col-md-6 col-lg-4',
        };
    }
}
