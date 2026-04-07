<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Schemaorg.ecommerce
 *
 * @copyright   (C) 2024-2026 J2Commerce, LLC All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Schemaorg\Ecommerce\Schema;

use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Schemaorg\Ecommerce\Event\BreadcrumbSchemaPrepareEvent;
use Joomla\Plugin\Schemaorg\Ecommerce\Helper\J2CommerceSchemaHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Breadcrumb Schema Builder
 *
 * Generates schema.org/BreadcrumbList JSON-LD structured data
 * from Joomla menu structure and J2Commerce category hierarchy.
 *
 * @since  6.0.0
 */
class BreadcrumbSchemaBuilder
{
    /**
     * J2Commerce Schema Helper instance
     *
     * @var    J2CommerceSchemaHelper
     * @since  6.0.0
     */
    private J2CommerceSchemaHelper $helper;

    /**
     * Database interface
     *
     * @var    DatabaseInterface
     * @since  6.0.0
     */
    private DatabaseInterface $db;

    /**
     * Event dispatcher for third-party integration
     *
     * @var    DispatcherInterface|null
     * @since  6.0.0
     */
    private ?DispatcherInterface $dispatcher;

    /**
     * Constructor
     *
     * @param   J2CommerceSchemaHelper       $helper      The schema helper
     * @param   DatabaseInterface         $db          Database interface
     * @param   DispatcherInterface|null  $dispatcher  Event dispatcher for hooks
     *
     * @since   6.0.0
     */
    public function __construct(
        J2CommerceSchemaHelper $helper,
        DatabaseInterface $db,
        ?DispatcherInterface $dispatcher = null
    ) {
        $this->helper     = $helper;
        $this->db         = $db;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Build BreadcrumbList schema for a product page
     *
     * @param   object    $product    The J2Commerce product object
     * @param   int|null  $articleId  The article ID if article-based product
     *
     * @return  array  The BreadcrumbList schema
     *
     * @since   6.0.0
     */
    public function buildForProduct(object $product, ?int $articleId = null): array
    {
        $items    = [];
        $position = 1;

        // Always start with Home
        $items[] = $this->createListItem('Home', Uri::root(), $position++);

        // Get category path from J2Commerce
        $categoryPath = $this->getJ2CommerceCategoryPath($product);

        foreach ($categoryPath as $category) {
            $items[] = $this->createListItem(
                $category['name'],
                $category['url'],
                $position++
            );
        }

        // If article-based, try to get Joomla category
        if ($articleId && empty($categoryPath)) {
            $joomlaCategoryPath = $this->getJoomlaCategoryPath($articleId);

            foreach ($joomlaCategoryPath as $category) {
                $items[] = $this->createListItem(
                    $category['name'],
                    $category['url'],
                    $position++
                );
            }
        }

        // Add the product itself as the final item
        $productName = $this->helper->getProductName($product);
        $productUrl  = $this->helper->getProductUrl($product);
        $items[]     = $this->createListItem($productName, $productUrl, $position);

        $schema = [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];

        // Dispatch event for third-party modifications
        return $this->dispatchBreadcrumbEvent($schema, $product, $articleId);
    }

    /**
     * Build BreadcrumbList schema for a category page
     *
     * @param   int  $categoryId  The J2Commerce category ID
     *
     * @return  array  The BreadcrumbList schema
     *
     * @since   6.0.0
     */
    public function buildForCategory(int $categoryId): array
    {
        $items    = [];
        $position = 1;

        // Always start with Home
        $items[] = $this->createListItem('Home', Uri::root(), $position++);

        // Get category hierarchy
        $categoryPath = $this->getJ2CommerceCategoryHierarchy($categoryId);

        foreach ($categoryPath as $category) {
            $items[] = $this->createListItem(
                $category['name'],
                $category['url'],
                $position++
            );
        }

        $schema = [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];

        // Dispatch event
        return $this->dispatchBreadcrumbEvent($schema, null, null, $categoryId);
    }

    /**
     * Build BreadcrumbList from Joomla pathway
     *
     * Uses the current Joomla pathway/breadcrumb module data.
     *
     * @return  array  The BreadcrumbList schema
     *
     * @since   6.0.0
     */
    public function buildFromPathway(): array
    {
        $app          = Factory::getApplication();
        $pathway      = $app->getPathway();
        $pathwayItems = $pathway->getPathway();

        $items    = [];
        $position = 1;

        // Add Home
        $items[] = $this->createListItem('Home', Uri::root(), $position++);

        // Add pathway items
        foreach ($pathwayItems as $item) {
            if (!empty($item->name)) {
                $url = !empty($item->link) ? Route::_($item->link, true, Route::TLS_IGNORE, true) : '';

                // Ensure absolute URL
                if (!empty($url) && strpos($url, 'http') !== 0) {
                    $url = Uri::root() . ltrim($url, '/');
                }

                $items[] = $this->createListItem($item->name, $url, $position++);
            }
        }

        return [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * Create a ListItem schema object
     *
     * @param   string  $name      The item name
     * @param   string  $url       The item URL
     * @param   int     $position  The position in the list
     *
     * @return  array  The ListItem schema
     *
     * @since   6.0.0
     */
    private function createListItem(string $name, string $url, int $position): array
    {
        $item = [
            '@type'    => 'ListItem',
            'position' => $position,
            'name'     => $name,
        ];

        if (!empty($url)) {
            $item['item'] = $url;
        }

        return $item;
    }

    /**
     * Get J2Commerce category path for a product
     *
     * @param   object  $product  The product object
     *
     * @return  array  Array of category data with name and url
     *
     * @since   6.0.0
     */
    private function getJ2CommerceCategoryPath(object $product): array
    {
        $path = [];

        // Check if product has category info
        if (!isset($product->catid) && !isset($product->category_id)) {
            return $path;
        }

        $categoryId = $product->catid ?? $product->category_id ?? 0;

        if ($categoryId <= 0) {
            return $path;
        }

        return $this->getJ2CommerceCategoryHierarchy((int) $categoryId);
    }

    /**
     * Get J2Commerce category hierarchy
     *
     * @param   int  $categoryId  The category ID
     *
     * @return  array  Array of category data from root to current
     *
     * @since   6.0.0
     */
    private function getJ2CommerceCategoryHierarchy(int $categoryId): array
    {
        $hierarchy = [];

        try {
            $query = $this->db->getQuery(true)
                ->select('c.id, c.title, c.lft, c.rgt')
                ->from($this->db->quoteName('#__categories', 'c'))
                ->where($this->db->quoteName('c.id') . ' = :categoryId')
                ->where($this->db->quoteName('c.extension') . ' = ' . $this->db->quote('com_content'))
                ->bind(':categoryId', $categoryId, \Joomla\Database\ParameterType::INTEGER);

            $this->db->setQuery($query);
            $category = $this->db->loadObject();

            if (!$category) {
                return $hierarchy;
            }

            $query = $this->db->getQuery(true)
                ->select('c.id, c.title')
                ->from($this->db->quoteName('#__categories', 'c'))
                ->where($this->db->quoteName('c.lft') . ' <= :lft')
                ->where($this->db->quoteName('c.rgt') . ' >= :rgt')
                ->where($this->db->quoteName('c.level') . ' > 0')
                ->where($this->db->quoteName('c.extension') . ' = ' . $this->db->quote('com_content'))
                ->order($this->db->quoteName('c.lft') . ' ASC')
                ->bind(':lft', $category->lft, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':rgt', $category->rgt, \Joomla\Database\ParameterType::INTEGER);

            $this->db->setQuery($query);
            $ancestors = $this->db->loadObjectList();

            foreach ($ancestors as $ancestor) {
                $hierarchy[] = [
                    'id'   => (int) $ancestor->id,
                    'name' => $ancestor->title,
                    'url'  => $this->buildCategoryUrl((int) $ancestor->id),
                ];
            }
        } catch (\Exception $e) {
            // Silently fail - return empty hierarchy
        }

        return $hierarchy;
    }

    /**
     * Get Joomla article category path
     *
     * @param   int  $articleId  The article ID
     *
     * @return  array  Array of category data from root to current
     *
     * @since   6.0.0
     */
    private function getJoomlaCategoryPath(int $articleId): array
    {
        $path = [];

        try {
            // Get article's category
            $query = $this->db->getQuery(true)
                ->select('a.catid')
                ->from($this->db->quoteName('#__content', 'a'))
                ->where($this->db->quoteName('a.id') . ' = :articleId')
                ->bind(':articleId', $articleId, \Joomla\Database\ParameterType::INTEGER);

            $this->db->setQuery($query);
            $catid = (int) $this->db->loadResult();

            if ($catid <= 0) {
                return $path;
            }

            // Get category and its parents
            $query = $this->db->getQuery(true)
                ->select('c.id, c.title, c.alias, c.path, c.lft, c.rgt')
                ->from($this->db->quoteName('#__categories', 'c'))
                ->where($this->db->quoteName('c.id') . ' = :catid')
                ->bind(':catid', $catid, \Joomla\Database\ParameterType::INTEGER);

            $this->db->setQuery($query);
            $category = $this->db->loadObject();

            if (!$category) {
                return $path;
            }

            // Get all ancestors including self
            $query = $this->db->getQuery(true)
                ->select('c.id, c.title, c.alias')
                ->from($this->db->quoteName('#__categories', 'c'))
                ->where($this->db->quoteName('c.lft') . ' <= :lft')
                ->where($this->db->quoteName('c.rgt') . ' >= :rgt')
                ->where($this->db->quoteName('c.level') . ' > 0')
                ->where($this->db->quoteName('c.extension') . ' = ' . $this->db->quote('com_content'))
                ->order($this->db->quoteName('c.lft') . ' ASC')
                ->bind(':lft', $category->lft, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':rgt', $category->rgt, \Joomla\Database\ParameterType::INTEGER);

            $this->db->setQuery($query);
            $ancestors = $this->db->loadObjectList();

            foreach ($ancestors as $ancestor) {
                $path[] = [
                    'id'   => (int) $ancestor->id,
                    'name' => $ancestor->title,
                    'url'  => Route::_('index.php?option=com_content&view=category&id=' . $ancestor->id, true, Route::TLS_IGNORE, true),
                ];
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return $path;
    }

    /**
     * Build a J2Commerce category URL
     *
     * @param   int  $categoryId  The category ID
     *
     * @return  string  The category URL
     *
     * @since   6.0.0
     */
    private function buildCategoryUrl(int $categoryId): string
    {
        // Use modern J2Commerce RouteHelper for category URL
        $url = Route::_(RouteHelper::getCategoryRoute((int) $categoryId));

        // Ensure absolute URL
        if (strpos($url, 'http') !== 0) {
            return Uri::root() . ltrim($url, '/');
        }

        return $url;
    }

    /**
     * Dispatch breadcrumb schema prepare event
     *
     * @param   array        $schema      The schema data
     * @param   object|null  $product     The product object
     * @param   int|null     $articleId   The article ID
     * @param   int|null     $categoryId  The category ID
     *
     * @return  array  The modified schema
     *
     * @since   6.0.0
     */
    private function dispatchBreadcrumbEvent(
        array $schema,
        ?object $product = null,
        ?int $articleId = null,
        ?int $categoryId = null
    ): array {
        if (!$this->dispatcher) {
            return $schema;
        }

        $productId = $product ? (int) ($product->j2commerce_product_id ?? 0) : null;

        $event = new BreadcrumbSchemaPrepareEvent(
            'onJ2CommerceSchemaBreadcrumbPrepare',
            [
                'subject'    => $schema,
                'productId'  => $productId,
                'categoryId' => $categoryId,
            ]
        );

        $this->dispatcher->dispatch('onJ2CommerceSchemaBreadcrumbPrepare', $event);

        return $event->getSchema();
    }
}
