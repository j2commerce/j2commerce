<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\View\Categories;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use J2Commerce\Component\J2commerce\Site\View\CustomSubtemplateTrait;
use Joomla\CMS\Categories\CategoryNode;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

/**
 * HTML Categories list view class for site frontend.
 *
 * Displays product categories from com_content with optional subcategories
 * and product counts.
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    use CustomSubtemplateTrait;

    /**
     * The page parameters (from menu item).
     *
     * @var   Registry
     * @since 6.0.0
     */
    public $params;

    /**
     * Category items data.
     *
     * @var   array
     * @since 6.0.0
     */
    public $items = [];

    /**
     * The parent category node.
     *
     * @var   CategoryNode|null
     * @since 6.0.0
     */
    public $parent;

    /**
     * Number of columns for grid layout.
     *
     * @var   int
     * @since 6.0.0
     */
    public $columns = 4;

    /**
     * Current category item being rendered in template.
     *
     * @var   object|null
     * @since 6.0.0
     */
    public $category;

    /**
     * Products in root category.
     *
     * @var   array
     * @since 6.0.0
     */
    public $products = [];

    /**
     * Number of columns for product grid layout.
     *
     * @var   int
     * @since 6.0.0
     */
    public $productColumns = 4;

    /**
     * Current product item being rendered in template.
     *
     * @var   object|null
     * @since 6.0.0
     */
    public $product;

    public array $trendingProducts = [];

    public string $displayMode = 'products';

    public function display($tpl = null): void
    {
        $app   = Factory::getApplication();
        $model = $this->getModel();

        $this->params   = $app->getParams();
        $this->items    = $model->getItems();
        $this->parent   = $model->getParent();
        $this->products = $model->getProducts();

        // Override menu item params with category-level params when set
        // Only affects the Product Categories view — Products, ProductTags, and single Product
        // views never use this HtmlView, so their menu item settings remain untouched
        if ($this->parent) {
            $catParams = $this->parent->getParams();

            $overrideKeys = [
                'category_view_type',
                'subtemplate_categories',
                'subtemplate_products',
                'subtemplate',
                'categoriestemplate',
                'show_category_root_title',
                'show_subcategories',
                'subcategory_levels',
                'show_category_description',
                'show_category_image',
                'show_product_count',
                'category_columns',
                'show_empty_categories',
                'subcategory_display_mode',
                'popular_product_count',
                'popular_display_type',
                'popular_grid_columns',
                'popular_slides_per_view',
                'popular_space_between',
                'popular_autoplay',
                'popular_autoplay_delay',
                'popular_loop',
                'popular_navigation',
                'popular_pagination',
            ];

            foreach ($overrideKeys as $key) {
                $value = $catParams->get($key, '');
                if ($value !== '' && $value !== null) {
                    $this->params->set($key, $value);
                }
            }
        }

        // Resolve effective subtemplate based on category_view_type and categoriestemplate
        // Resolution order:
        // - categories mode: subtemplate_categories (cat-level) → categoriestemplate (menu-level)
        // - products mode: subtemplate_products (cat-level) → subtemplate (menu-level)
        // - empty (use menu item setting): categoriestemplate (menu-level, since the menu item IS a categories view)
        $categoryViewType = $this->params->get('category_view_type', '');
        if ($categoryViewType === 'categories') {
            $resolved = $this->params->get('subtemplate_categories', '')
                ?: $this->params->get('categoriestemplate', '');
            $this->params->set('subtemplate', $resolved);
        } elseif ($categoryViewType === 'products') {
            $resolved = $this->params->get('subtemplate_products', '');
            if ($resolved !== '') {
                $this->params->set('subtemplate', $resolved);
            }
        } else {
            // No category-level override — use menu item's categoriestemplate as default
            $resolved = $this->params->get('categoriestemplate', '');
            if ($resolved !== '') {
                $this->params->set('subtemplate', $resolved);
            }
        }

        $this->columns        = (int) $this->params->get('category_columns', 3);
        $this->productColumns = (int) $this->params->get('list_no_of_columns', 3);
        $this->sublayout      = $this->params->get('subtemplate', '');
        $this->displayMode    = $this->params->get('subcategory_display_mode', 'products');

        // Load trending products from ALL child categories combined
        if ($this->displayMode === 'categories_popular') {
            $limit    = (int) $this->params->get('popular_product_count', 12);
            $parentId = $this->parent ? (int) $this->parent->id : 0;

            if ($parentId) {
                $this->trendingProducts = $model->getPopularProducts($parentId, $limit);
            }
        }

        // Dispatch event to allow template plugins to render the view
        $event = J2CommerceHelper::plugin()->eventWithHtml(
            'ViewCategoryListHtml',
            [null, &$this, $model]
        );
        $view_html = $event->getArgument('html', '');

        $this->prepareDocument();

        if (!empty($view_html)) {
            echo $view_html;

            return;
        }

        // If a custom subtemplate is selected, try template override directory first
        if (!empty($this->sublayout)) {
            $customHtml = $this->renderCustomSubtemplate();

            if ($customHtml !== null) {
                echo $customHtml;

                return;
            }
        }

        parent::display($tpl);
    }

    /**
     * Prepares the document.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function prepareDocument(): void
    {
        $app = Factory::getApplication();

        // Set page heading: use category title when viewing a child category, menu title at root
        $menu            = $app->getMenu()->getActive();
        $menuParentId    = $menu ? (int) ($menu->query['id'] ?? 0) : 0;
        $currentParentId = $this->parent ? (int) $this->parent->id : 0;

        if ($this->parent && $currentParentId !== $menuParentId) {
            $this->params->set('page_heading', $this->parent->title);
        } elseif ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', Text::_('COM_J2COMMERCE_CATEGORIES'));
        }

        // Set document title
        $title = $this->parent && $currentParentId !== $menuParentId
            ? $this->parent->title
            : $this->params->get('page_title', '');
        if (empty($title)) {
            $title = $app->get('sitename');
        } elseif ($app->get('sitename_pagetitles', 0) == 1) {
            $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
        }

        $this->setDocumentTitle($title);

        // Add breadcrumb items for ancestor categories between menu root and current parent
        if ($this->parent && $currentParentId !== $menuParentId) {
            $pathway    = $app->getPathway();
            $activeMenu = $menu;

            // Build ancestor chain from current parent up to (but not including) the menu's root category
            $ancestors = [];
            $node      = $this->parent;

            while ($node && (int) $node->id !== $menuParentId && (int) $node->id > 1) {
                $ancestors[] = $node;
                $node        = $node->getParent();
            }

            // Add ancestors in top-down order
            foreach (array_reverse($ancestors) as $ancestor) {
                $pathway->addItem(
                    $ancestor->title,
                    Route::_(RouteHelper::getCategoryRouteInContext((int) $ancestor->id, $activeMenu))
                );
            }
        }

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

    /**
     * Get Bootstrap column class for products based on number of columns.
     *
     * @return  string  Bootstrap column class
     *
     * @since   6.0.0
     */
    public function getProductColumnClass(): string
    {
        return match ($this->productColumns) {
            1       => 'col-12',
            2       => 'col-12 col-md-6',
            3       => 'col-12 col-md-6 col-lg-4',
            4       => 'col-12 col-md-6 col-lg-3',
            6       => 'col-12 col-md-4 col-lg-2',
            default => 'col-12 col-md-6 col-lg-4',
        };
    }

    public function getPopularColumnClass(): string
    {
        $cols = (int) $this->params->get('popular_grid_columns', 4);
        return match ($cols) {
            2       => 'col-12 col-md-6',
            3       => 'col-12 col-md-6 col-lg-4',
            4       => 'col-12 col-md-6 col-lg-3',
            6       => 'col-12 col-md-4 col-lg-2',
            default => 'col-12 col-md-6 col-lg-3',
        };
    }
}
