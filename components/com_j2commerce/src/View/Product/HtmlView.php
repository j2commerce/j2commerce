<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\View\Product;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use J2Commerce\Component\J2commerce\Site\View\CustomSubtemplateTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * HTML Product View class
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    use CustomSubtemplateTrait;

    /**
     * The product object (Joomla convention)
     *
     * @var  \stdClass|null
     *
     * @since  6.0.0
     */
    protected $item;

    /**
     * The product object (alias for $this->item)
     *
     * @var  \stdClass|null
     *
     * @since  6.0.0
     */
    public $product;

    /**
     * The page parameters
     *
     * @var    \Joomla\Registry\Registry|null
     *
     * @since  6.0.0
     */
    public $params = null;

    /**
     * Should the print button be displayed or not?
     *
     * @var   boolean
     *
     * @since  6.0.0
     */
    protected $print = false;

    /**
     * The model state
     *
     * @var   \Joomla\Registry\Registry
     *
     * @since  6.0.0
     */
    protected $state;

    /**
     * The user object
     *
     * @var   \Joomla\CMS\User\User|null
     */
    protected $user = null;

    /**
     * The rendering context string (e.g. "j2commerce.site.products.detail")
     *
     * @var    string
     *
     * @since  6.0.0
     */
    public string $context = '';

    /**
     * The page class suffix
     *
     * @var    string
     *
     * @since  6.0.0
     */
    protected $pageclass_sfx = '';

    /**
     * Execute and display a template script.
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     */
    public function display($tpl = null): void
    {
        $app  = Factory::getApplication();
        $user = $this->getCurrentUser();

        /** @var ProductModel $model */
        $model = $this->getModel();

        // Get menu item parameters
        $this->params = $app->getParams();

        // Check for errors
        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->item      = $model->getItem();
        $this->product   = $this->item;
        $this->print     = $app->getInput()->getBool('print', false);
        $this->state     = $model->getState();
        $this->user      = $user;
        $this->sublayout = $app->getParams()->get('subtemplate', '');
        $this->context   = J2CommerceHelper::utilities()->getContext('.detail');

        // Prepare document metadata (title, description, etc.) FIRST
        // This must happen before plugin output or template rendering
        $this->_prepareDocument();

        // Dispatch event to allow template plugins to render the view
        // The eventWithHtml() helper properly handles passing data to/from plugins
        // and collects the rendered HTML via the 'html' argument
        $event = J2CommerceHelper::plugin()->eventWithHtml(
            'ViewProductHtml',
            [null, &$this, $model]
        );
        $view_html = $event->getArgument('html', '');

        // If a plugin provided HTML output, display it and return
        if (!empty($view_html)) {
            echo $view_html;

            return;
        }

        // If a custom subtemplate is selected, try template override directory first
        if (!empty($this->sublayout)) {
            $this->setLayout('view');
            $customHtml = $this->renderCustomSubtemplate();

            if ($customHtml !== null) {
                echo $customHtml;

                return;
            }
        }

        // No plugin or custom override provided HTML, use default template rendering
        parent::display($tpl);
    }

    /**
     * Prepares the document with SEO metadata.
     *
     * Sets page title, meta description, meta keywords, and canonical URL
     * based on the product data and linked Joomla article.
     *
     * Priority for metadata:
     * 1. Menu item parameters (if set)
     * 2. Article metadata fields (metadesc, metakey)
     * 3. Product data fallback (product_name, product_short_desc)
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function _prepareDocument(): void
    {
        $app      = Factory::getApplication();
        $document = $this->getDocument();
        $pathway  = $app->getPathway();
        $menu     = $app->getMenu()->getActive();
        $this->params->def('page_heading', $menu ? $menu->title : '');

        // Handle print view - prevent indexing
        if ($this->print) {
            $document->setMetaData('robots', 'noindex, nofollow');

            return;
        }

        // Get article source data (contains SEO fields)
        $articleData = $this->item->source ?? null;

        // =====================
        // BREADCRUMB PATHWAY
        // =====================
        // Add breadcrumb items if not directly linked to product menu
        $menuItemMatchesProduct = false;

        // Check if active menu item points directly to this product
        if ($menu && $menu->component == 'com_j2commerce'
            && isset($menu->query['view'], $menu->query['id'])
            && $menu->query['view'] == 'product'
            && (int) $menu->query['id'] == (int) $this->item->j2commerce_product_id) {
            $menuItemMatchesProduct = true;
        }

        if (!$menuItemMatchesProduct) {
            // Build pathway: category path + product name
            $path = [];

            // Get category ID from article source
            $catid = ($articleData && !empty($articleData->catid)) ? (int) $articleData->catid : null;

            if ($catid) {
                // Build category path using Joomla's Categories API
                $categories = \Joomla\CMS\Categories\Categories::getInstance('Content');
                $category   = $categories->get($catid);

                // Determine which category to stop at based on menu
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

                // Walk up category tree until we hit menu's category or root
                while ($category !== null && $category->id != $menuCategoryId && $category->id !== 'root' && $category->id > 1) {
                    $path[] = [
                        'title' => $category->title,
                        'link'  => Route::_(RouteHelper::getCategoryRouteInContext((int) $category->id, $menu)),
                    ];
                    $category = $category->getParent();
                }

                // Reverse to get root-to-leaf order
                $path = array_reverse($path);
            }

            // Add product as final breadcrumb (no link - current page)
            $path[] = [
                'title' => $this->item->product_name ?? 'Product',
                'link'  => '',
            ];

            // Add all path items to pathway
            foreach ($path as $item) {
                $pathway->addItem($item['title'], $item['link']);
            }
        }

        // =====================
        // PAGE TITLE
        // =====================
        // For single product pages, product name should be the primary title
        // Menu page_title is only used as fallback (similar to com_content article view)
        $title = '';

        // Use product name as primary title
        if (!empty($this->item->product_name)) {
            $title = $this->item->product_name;
        }

        // Fallback to menu page_title if no product name
        if (empty($title)) {
            $title = $this->params->get('page_title', '');
        }

        // Final fallback to site name
        if (empty($title)) {
            $title = $app->get('sitename');
        } else {
            // Handle sitename in page title based on Joomla configuration
            $sitenameSetting = $app->get('sitename_pagetitles', 0);

            if ($sitenameSetting == 1) {
                // Sitename - Page Title
                $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
            } elseif ($sitenameSetting == 2) {
                // Page Title - Sitename
                $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
            }
        }

        $this->setDocumentTitle($title);

        // =====================
        // META DESCRIPTION
        // =====================
        // Priority: Menu meta_description > Article metadesc > Product short description
        $metaDesc = $this->params->get('menu-meta_description', '');

        if (empty($metaDesc) && $articleData && !empty($articleData->metadesc)) {
            $metaDesc = $articleData->metadesc;
        }

        if (empty($metaDesc) && !empty($this->item->product_short_desc)) {
            // Strip HTML tags and limit length for meta description
            $metaDesc = strip_tags($this->item->product_short_desc);
            $metaDesc = \Joomla\String\StringHelper::substr($metaDesc, 0, 160);

            // Clean up whitespace
            $metaDesc = preg_replace('/\s+/', ' ', trim($metaDesc));
        }

        if (!empty($metaDesc)) {
            $document->setDescription($metaDesc);
        }

        // =====================
        // META KEYWORDS
        // =====================
        // Priority: Menu meta_keywords > Article metakey
        $metaKey = $this->params->get('menu-meta_keywords', '');

        if (empty($metaKey) && $articleData && !empty($articleData->metakey)) {
            $metaKey = $articleData->metakey;
        }

        if (!empty($metaKey)) {
            $document->setMetaData('keywords', $metaKey);
        }

        // =====================
        // ROBOTS
        // =====================
        // Use menu parameter if set, otherwise allow indexing
        $robots = $this->params->get('robots', '');

        if (!empty($robots)) {
            $document->setMetaData('robots', $robots);
        }

        // =====================
        // CANONICAL URL
        // =====================
        // Set canonical URL to prevent duplicate content issues
        if (!empty($this->item->j2commerce_product_id)) {
            $catid = null;

            // Get category ID from article source
            if ($articleData && !empty($articleData->catid)) {
                $catid = (int) $articleData->catid;
            }

            $canonicalUrl = Route::_(
                RouteHelper::getProductRoute(
                    (int) $this->item->j2commerce_product_id,
                    $this->item->alias ?? null,
                    $catid
                ),
                true,
                Route::TLS_IGNORE,
                true  // Absolute URL
            );

            $document->addHeadLink($canonicalUrl, 'canonical');
        }

        // =====================
        // OPEN GRAPH TAGS (for social sharing)
        // =====================
        $document->setMetaData('og:title', $this->item->product_name ?? $title, 'property');
        $document->setMetaData('og:type', 'product', 'property');

        if (!empty($metaDesc)) {
            $document->setMetaData('og:description', $metaDesc, 'property');
        }

        // Set OG URL to canonical
        if (!empty($canonicalUrl)) {
            $document->setMetaData('og:url', $canonicalUrl, 'property');
        }

        // Set OG image if product has a main image
        if (!empty($this->item->main_image)) {
            $imageUrl = Uri::root() . ltrim($this->item->main_image, '/');
            $document->setMetaData('og:image', $imageUrl, 'property');
        }

        // =====================
        // TWITTER CARD
        // =====================
        $document->setMetaData('twitter:card', 'summary_large_image');
        $document->setMetaData('twitter:title', $this->item->product_name ?? $title);

        if (!empty($metaDesc)) {
            $document->setMetaData('twitter:description', $metaDesc);
        }

        if (!empty($this->item->main_image)) {
            $imageUrl = Uri::root() . ltrim($this->item->main_image, '/');
            $document->setMetaData('twitter:image', $imageUrl);
        }
    }
}
