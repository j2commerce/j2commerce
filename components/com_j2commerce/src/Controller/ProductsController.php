<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Controller;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\Registry\Registry;

class ProductsController extends BaseController
{
    public function filter(): void
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();

        // Load component language file for translated strings
        $lang = Factory::getLanguage();
        $lang->load('com_j2commerce', JPATH_SITE);

        if (!Session::checkToken('post')) {
            $this->sendJsonError(Text::_('JINVALID_TOKEN'), 403);
            return;
        }

        try {
            $session = $app->getSession();

            // Support both array format (from form) and comma-separated format (from URL)
            $manufacturerIds = $input->get('manufacturer_ids', [], 'array');
            if (empty($manufacturerIds)) {
                $brandsParam = $input->getString('brands', '');
                if (!empty($brandsParam)) {
                    $manufacturerIds = array_filter(explode(',', $brandsParam), 'is_numeric');
                }
            }

            $vendorIds = $input->get('vendor_ids', [], 'array');
            if (empty($vendorIds)) {
                $vendorsParam = $input->getString('vendors', '');
                if (!empty($vendorsParam)) {
                    $vendorIds = array_filter(explode(',', $vendorsParam), 'is_numeric');
                }
            }

            $productfilterIds = $input->get('productfilter_ids', [], 'array');
            if (empty($productfilterIds)) {
                $filtersParam = $input->getString('filters', '');
                if (!empty($filtersParam)) {
                    $filterValues = explode(',', $filtersParam);
                    // Check if values are numeric IDs or aliases
                    $numericFilters = array_filter($filterValues, 'is_numeric');
                    if (\count($numericFilters) === \count($filterValues)) {
                        // All numeric - use as IDs directly
                        $productfilterIds = $numericFilters;
                    } else {
                        // Contains aliases - resolve to IDs
                        $productfilterIds = $this->resolveFilterAliasesToIds($filterValues);
                    }
                }
            }

            $catid     = $input->getInt('filter_catid', 0);
            $tagIds    = $input->get('tag_ids', [], 'array');
            $tagMatch  = $input->getString('tag_match', 'any');
            $priceFrom = $input->getFloat('pricefrom', 0);
            $priceTo   = $input->getFloat('priceto', 0);
            $search    = $input->getString('search', '');

            // Support both full sort key and SEF-friendly sort names
            $sortby = $input->getString('sortby', '');
            if (empty($sortby)) {
                $sortParam = $input->getString('sort', '');
                if (!empty($sortParam)) {
                    // Map SEF-friendly names back to internal sort keys
                    $sortMap = [
                        'name-asc'   => 'a.title ASC',
                        'name-desc'  => 'a.title DESC',
                        'price-asc'  => 'v.price ASC',
                        'price-desc' => 'v.price DESC',
                        'newest'     => 'a.created DESC',
                        'popular'    => 'a.hits DESC',
                    ];
                    $sortby = $sortMap[$sortParam] ?? $sortParam;
                }
            }

            // Support both 'limitstart' and 'start' parameter names
            $limitstart = $input->getInt('limitstart', 0);
            if ($limitstart === 0) {
                $limitstart = $input->getInt('start', 0);
            }

            // Load menu item parameters from Itemid BEFORE model creation
            // During AJAX requests, $app->getParams() returns component defaults without Itemid context
            $itemid = $input->getInt('Itemid', 0);
            if ($itemid > 0) {
                $menu     = $app->getMenu();
                $menuItem = $menu->getItem($itemid);
                if ($menuItem && $menuItem->component === 'com_j2commerce') {
                    $params = clone $app->getParams();
                    $params->merge($menuItem->getParams());
                } else {
                    $params = $app->getParams();
                }
            } else {
                $params = $app->getParams();
            }

            $session->set('manufacturer_ids', array_map('intval', $manufacturerIds), 'j2commerce');
            $session->set('vendor_ids', array_map('intval', $vendorIds), 'j2commerce');
            $session->set('productfilter_ids', array_map('intval', $productfilterIds), 'j2commerce');

            if ($catid) {
                $input->set('catid', $catid);
            }

            // Use ProducttagsModel when filtering by tags, ProductsModel otherwise
            $tagIds = array_filter(array_map('intval', $tagIds));
            if (!empty($tagIds)) {
                $model = $this->getModel('Producttags', 'Site');
                $model->getState(); // trigger populateState before overriding
                $model->setState('filter.tag_ids', $tagIds);
                $model->setState('filter.tag_match', $tagMatch === 'all' ? 'all' : 'any');
            } else {
                $model = $this->getModel('Products', 'Site');
                $model->getState(); // trigger populateState before overriding
            }
            $model->setState('list.start', $limitstart);

            // Override list.limit with menu item's page_limit (populateState used global fallback)
            $pageLimit = (int) $params->get('page_limit', 0);
            if ($pageLimit > 0) {
                $model->setState('list.limit', $pageLimit);
            }

            if (!empty($manufacturerIds)) {
                $model->setState('filter.manufacturer_ids', array_map('intval', $manufacturerIds));
            }
            if (!empty($vendorIds)) {
                $model->setState('filter.vendor_ids', array_map('intval', $vendorIds));
            }
            if (!empty($productfilterIds)) {
                $model->setState('filter.productfilter_ids', array_map('intval', $productfilterIds));
            }
            if ($priceFrom > 0 || $priceTo > 0) {
                $model->setState('filter.price_from', $priceFrom);
                $model->setState('filter.price_to', $priceTo);
            }
            if (!empty($search)) {
                $model->setState('filter.search', $search);
            }
            if (!empty($sortby)) {
                $this->applySortOrder($model, $sortby);
            }

            $items      = $model->getItems();
            $pagination = $model->getPagination();
            $filters    = $model->getFilters($items);

            // Set subtemplate override from menu item params before rendering
            $subtemplate = $params->get('subtemplate', '');
            if (!empty($subtemplate)) {
                ProductLayoutService::setSubtemplateOverride($subtemplate);
            }

            $productsHtml   = $this->renderProducts($items, $params, $catid);
            $paginationHtml = $this->renderPagination($pagination, $catid);

            if (!empty($subtemplate)) {
                ProductLayoutService::clearSubtemplateOverride();
            }

            $response = [
                'products'   => $productsHtml,
                'pagination' => $paginationHtml,
                'total'      => $pagination->total,
                'start'      => $pagination->limitstart,
                'limit'      => $pagination->limit,
            ];

            $app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo new JsonResponse($response);
            $app->close();

        } catch (\Throwable $e) {
            // Include file/line for debugging, remove in production
            $this->sendJsonError($e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine(), 500);
        }
    }

    /**
     * Render products using the View's template system for consistent AJAX/non-AJAX output.
     */
    protected function renderProducts(array $items, Registry $params, int $catid): string
    {
        if (empty($items)) {
            return '<div class="row"><div class="col-12"><div class="alert alert-info">'
                . Text::_('COM_J2COMMERCE_NO_PRODUCTS_FOUND')
                . '</div></div></div>';
        }

        $app      = Factory::getApplication();
        $itemId   = $app->getInput()->getInt('Itemid', 0);
        $columns  = (int) $params->get('list_no_of_columns', 3);
        $colClass = 'col-md-' . (int) round(12 / $columns);

        ob_start();

        echo '<div class="j2commerce-products-row row g-4 mb-4">';

        foreach ($items as $product) {
            if (!($product->params instanceof Registry)) {
                $product->params = new Registry($product->params ?? '{}');
            }

            echo '<div class="' . $colClass . '">';
            echo ProductLayoutService::renderProductItem(
                $product,
                $params,
                ProductLayoutService::CONTEXT_LIST,
                $itemId
            );
            echo '</div>';
        }

        echo '</div>';

        return ob_get_clean();
    }

    protected function renderPagination(\Joomla\CMS\Pagination\Pagination $pagination, int $catid): string
    {
        ob_start();

        echo '<nav class="j2commerce-pagination mt-4" aria-label="' . Text::_('JLIB_HTML_PAGINATION') . '">';
        echo $pagination->getPagesLinks();
        echo '</nav>';

        return ob_get_clean();
    }

    protected function applySortOrder(ListModel $model, string $sortby): void
    {
        // Support both SEF-friendly short keys and full database column format
        // Short keys come from JavaScript updateUrl(), full format from form dropdown
        $orderMapping = [
            // SEF-friendly short format (from URL params)
            'name-asc'   => ['a.title', 'ASC'],
            'name-desc'  => ['a.title', 'DESC'],
            'price-asc'  => ['v.price', 'ASC'],
            'price-desc' => ['v.price', 'DESC'],
            'newest'     => ['a.created', 'DESC'],
            'popular'    => ['p.hits', 'DESC'],
            // Legacy underscore format
            'name_asc'   => ['a.title', 'ASC'],
            'name_desc'  => ['a.title', 'DESC'],
            'price_asc'  => ['v.price', 'ASC'],
            'price_desc' => ['v.price', 'DESC'],
            'date_asc'   => ['a.created', 'ASC'],
            'date_desc'  => ['a.created', 'DESC'],
            'ordering'   => ['a.ordering', 'ASC'],
            // Full database column format (from form dropdown)
            'a.ordering'     => ['a.ordering', 'ASC'],
            'a.title ASC'    => ['a.title', 'ASC'],
            'a.title DESC'   => ['a.title', 'DESC'],
            'v.price ASC'    => ['v.price', 'ASC'],
            'v.price DESC'   => ['v.price', 'DESC'],
            'a.created DESC' => ['a.created', 'DESC'],
            'p.hits DESC'    => ['p.hits', 'DESC'],
        ];

        if (isset($orderMapping[$sortby])) {
            [$column, $direction] = $orderMapping[$sortby];
            $model->setState('list.ordering', $column);
            $model->setState('list.direction', $direction);
        }
    }

    protected function sendJsonError(string $message, int $code): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Status', (string) $code);
        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo new JsonResponse(null, $message, true);
        $app->close();
    }

    /**
     * Convert SEF-friendly aliases (e.g., "milk-chocolate") to filter IDs.
     */
    protected function resolveFilterAliasesToIds(array $aliases): array
    {
        if (empty($aliases)) {
            return [];
        }

        $db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true);

        $query->select($db->quoteName(['j2commerce_filter_id', 'filter_name']))
            ->from($db->quoteName('#__j2commerce_filters'));

        $db->setQuery($query);
        $filters = $db->loadObjectList();

        $filterIds = [];

        foreach ($aliases as $alias) {
            $alias = trim($alias);

            // If numeric, use directly
            if (is_numeric($alias)) {
                $filterIds[] = (int) $alias;
                continue;
            }

            // Match against slugified filter names
            foreach ($filters as $filter) {
                $filterAlias = \Joomla\CMS\Filter\OutputFilter::stringURLSafe($filter->filter_name);
                if ($filterAlias === $alias) {
                    $filterIds[] = (int) $filter->j2commerce_filter_id;
                    break;
                }
            }
        }

        return array_unique($filterIds);
    }
}
