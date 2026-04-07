<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\View\Products;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use J2Commerce\Component\J2commerce\Site\View\CustomSubtemplateTrait;
use Joomla\CMS\Categories\CategoryNode;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

/**
 * HTML Products list view class for site frontend.
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
    protected $columns = 3;

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
        $this->params = $app->getParams();

        // Load data from model
        $this->state      = $model->getState();
        $this->items      = $model->getItems();
        $this->products   = $this->items; // Alias for template plugin compatibility
        $this->parent     = $model->getParent();
        $this->pagination = $model->getPagination();
        $this->user       = $this->getCurrentUser();

        // Load filter data for sidebar
        $this->filters = $model->getFilters($this->items);

        // Get the active menu item and current category filter
        $this->active_menu  = $app->getMenu()->getActive();
        $catids             = $this->state->get('filter.catids', []);
        $this->filter_catid = !empty($catids) ? reset($catids) : '';

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
        $event = J2CommerceHelper::plugin()->eventWithHtml(
            'ViewProductListHtml',
            [null, &$this, $model]
        );
        $view_html = $event->getArgument('html', '');

        // If a plugin provided HTML output, still prepare document first
        // This ensures breadcrumbs, page title, and meta tags are set even when
        // a template plugin handles the actual HTML rendering
        if (!empty($view_html)) {
            $this->_prepareDocument();
            echo $view_html;

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
        $app     = Factory::getApplication();
        $pathway = $app->getPathway();
        $menu    = $app->getMenu()->getActive();

        // Set page heading from menu item or default
        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', Text::_('COM_J2COMMERCE_PRODUCTS'));
        }

        // =====================
        // BREADCRUMB PATHWAY
        // =====================
        // Add breadcrumb for category if we're filtering by category
        // Note: Model uses 'filter.catids' (plural array), extract first category
        $catids = $this->state->get('filter.catids', []);
        $catid  = !empty($catids) ? (int) reset($catids) : 0;

        // Fallback: check input directly if state doesn't have catid
        // This handles edge cases where the router sets catid but state wasn't populated
        if (!$catid) {
            $inputCatid = $app->getInput()->getInt('catid', 0);
            if ($inputCatid) {
                $catid = $inputCatid;
            }
        }

        if ($catid > 0) {
            // Check if menu already points to this category
            $menuCategoryId = 0;
            if ($menu && $menu->component == 'com_j2commerce') {
                if (isset($menu->query['view'])) {
                    if ($menu->query['view'] == 'categories' && isset($menu->query['id'])) {
                        $menuCategoryId = (int) $menu->query['id'];
                    } elseif ($menu->query['view'] == 'products' && isset($menu->query['catid'])) {
                        $menuCategoryId = (int) $menu->query['catid'];
                    }
                }
            }

            // Only add breadcrumb if menu doesn't already point to this category
            if ($catid != $menuCategoryId) {
                $categories = \Joomla\CMS\Categories\Categories::getInstance('Content');
                $category   = $categories->get($catid);

                if ($category) {
                    // Build path from current category up to menu's category
                    $path = [];

                    $current = $category;
                    while ($current !== null && $current->id != $menuCategoryId && $current->id !== 'root' && $current->id > 1) {
                        $path[] = [
                            'title' => $current->title,
                            'id'    => (int) $current->id,
                        ];
                        $current = $current->getParent();
                    }

                    // Reverse to get root-to-leaf order (ancestor -> descendant)
                    $path = array_reverse($path);

                    // Add to pathway - all except last get links, last is current page (no link)
                    $pathCount = \count($path);
                    foreach ($path as $index => $item) {
                        $isLast = ($index === $pathCount - 1);
                        $link   = $isLast ? '' : Route::_(RouteHelper::getCategoryRouteInContext($item['id'], $menu));
                        $pathway->addItem($item['title'], $link);
                    }
                }
            }
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

    public function getPagination(): \Joomla\CMS\Pagination\Pagination
    {
        return $this->pagination;
    }
}
