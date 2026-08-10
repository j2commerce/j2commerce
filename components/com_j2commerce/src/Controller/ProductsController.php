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

use J2Commerce\Component\J2commerce\Administrator\Controller\ProductsController as AdminProductsController;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Helper\ProductFilterRequestHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Site Products controller. Extends admin so all variant AJAX methods are inherited.
 * Overrides execute() to gate every inherited admin task behind the vendor/editor
 * check; only the site's own public tasks are exempt.
 *
 * @since  6.2.3
 */
class ProductsController extends AdminProductsController
{
    /**
     * Tasks this controller serves to anyone. Every other task on this class is
     * inherited from the admin controller and must clear the vendor/editor gate.
     * Deny by default: an allow-list cannot silently reopen when the admin
     * controller gains a public method.
     */
    private const PUBLIC_SITE_TASKS = [
        'filter',
    ];

    /**
     * Methods AdminController and the admin Products controller provide for managing the list
     * itself. They act on the catalogue rather than on one shopper's request, no storefront
     * surface calls any of them, and the administrator client reaches them through their own
     * screen — so this class does not serve them at all.
     *
     * Matched against the method the task map resolves to, the way WriteAccessTrait matches,
     * so the aliases core registers — unpublish, archive, trash, orderup, unfeatured — are
     * covered without naming each one.
     */
    private const ADMIN_ONLY_METHODS = [
        'batch',
        'featured',
        'delete',
        'publish',
        'reorder',
        'saveorder',
        'saveOrderAjax',
        'checkin',
        'runTransition',
    ];

    /**
     * The public tasks read; without this they would inherit the admin controller's list and
     * WriteAccessTrait would demand j2commerce.editproducts from every shopper.
     */
    protected function readTasks(): array
    {
        return array_merge(parent::readTasks(), self::PUBLIC_SITE_TASKS);
    }

    public function execute($task)
    {
        // Lower-cased to match BaseController::execute(), which lower-cases the task
        // before resolving it against $taskMap. Comparing the raw string here would
        // let a differently-cased task skip this gate and still dispatch.
        $lower = strtolower((string) $task);

        // The storefront calls none of the inherited list-management methods and the administrator
        // reaches them through its own screen, so this client declines them outright rather than
        // resolving a permission for them.
        $resolved = $this->taskMap[$lower] ?? ($this->taskMap['__default'] ?? null);

        if ($resolved !== null && \in_array($resolved, self::ADMIN_ONLY_METHODS, true)) {
            $this->sendVariantJsonError(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'));

            return false;
        }

        if (!\in_array($lower, self::PUBLIC_SITE_TASKS, true) && !$this->authorizeVariantAjax()) {
            $this->sendVariantJsonError(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'));
            return false;
        }

        return parent::execute($task);
    }

    /**
     * Site-only: AJAX product list filter for the frontend product listing view.
     */
    public function filter(): void
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();

        $lang = Factory::getLanguage();
        $lang->load('com_j2commerce', JPATH_SITE);

        if (!Session::checkToken('post')) {
            $this->sendJsonError(Text::_('JINVALID_TOKEN'), 403);
            return;
        }

        try {
            $session = $app->getSession();

            // Single source of truth for filter parsing — same helper used by ProductsModel::populateState().
            $filterState      = ProductFilterRequestHelper::resolveFromRequest($input);
            $manufacturerIds  = $filterState['manufacturer_ids'];
            $vendorIds        = $filterState['vendor_ids'];
            $productfilterIds = $filterState['productfilter_ids'];
            $tagIds           = array_filter(array_map('intval', $filterState['tag_ids']));
            $tagMatch         = $filterState['tag_match'];
            $priceFrom        = $filterState['price_from'];
            $priceTo          = $filterState['price_to'];

            $catid  = $input->getInt('filter_catid', 0);
            $search = $input->getString('search', '');

            $sortby = $input->getString('sortby', '');
            if (empty($sortby)) {
                $sortParam = $input->getString('sort', '');
                if (!empty($sortParam)) {
                    $sortMap = [
                        'name-asc'   => 'a.title ASC',
                        'name-desc'  => 'a.title DESC',
                        'price-asc'  => 'v.price ASC',
                        'price-desc' => 'v.price DESC',
                        'newest'     => 'a.created DESC',
                        'popular'    => 'p.hits DESC',
                    ];
                    $sortby = $sortMap[$sortParam] ?? $sortParam;
                }
            }

            $limitstart = $input->getInt('limitstart', 0);
            if ($limitstart === 0) {
                $limitstart = $input->getInt('start', 0);
            }

            $itemid = $input->getInt('Itemid', 0);
            if ($itemid > 0) {
                $menu     = $app->getMenu();
                $menuItem = $menu->getItem($itemid);
                if ($menuItem && $menuItem->component === 'com_j2commerce') {
                    $params = clone $app->getParams();
                    $params->merge($menuItem->getParams());

                    // Backstop for a layout that renders no filter_catid carrier (a template
                    // override predating the wrapper attribute): re-derive the category from
                    // the menu item, the same way ProductsModel::populateState() does.
                    if ($catid === 0 && empty($tagIds) && ($menuItem->query['view'] ?? '') === 'products') {
                        $catid = (int) ($menuItem->query['catid'] ?? 0);

                        if (!$catid && !empty($menuItem->link)) {
                            parse_str(parse_url($menuItem->link, PHP_URL_QUERY) ?: '', $linkQuery);
                            $catid = (int) ($linkQuery['catid'] ?? 0);
                        }
                    }
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

            if (!empty($tagIds)) {
                $model = $this->getModel('Producttags', 'Site');
                $model->getState();
                $model->setState('filter.tag_ids', $tagIds);
                $model->setState('filter.tag_match', $tagMatch === 'all' ? 'all' : 'any');
            } else {
                $model = $this->getModel('Products', 'Site');
                $model->getState();
            }

            // Seed model state with menu/app params — HtmlView::display() does
            // this normally, but this AJAX endpoint bypasses the view chain.
            // Without it, ProductsModel::getFilters() fatals on null $params.
            $model->setState('params', $params);

            // Seed category filter directly. populateState() reads catid from
            // Input/active-menu, neither of which is reliably populated on this
            // AJAX path — so $input->set('catid', ...) above is not enough.
            if ($catid > 0) {
                $model->setState('filter.catids', [$catid]);
            }

            $model->setState('list.start', $limitstart);

            $pageLimit = (int) $params->get('page_limit', 0);
            if ($pageLimit > 0) {
                $model->setState('list.limit', $pageLimit);
            }

            // Seed the sidebar filter state explicitly. This AJAX task bypasses the normal
            // view chain, and the model's populateState() does not run via this controller
            // path, so manufacturer / vendor / product-filter / price selections must be set
            // here (same as tag_ids / search / sort above) or they are silently ignored.
            $model->setState('filter.manufacturer_ids', array_map('intval', $manufacturerIds));
            $model->setState('filter.vendor_ids', array_map('intval', $vendorIds));
            $model->setState('filter.productfilter_ids', array_map('intval', $productfilterIds));
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
                // Without this the sidebar keeps the counts of whatever the last full page
                // render produced, which stop describing the listing on the first tick.
                'filterCounts' => $this->filterCounts($model),
            ];

            $app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo new JsonResponse($response);
            $app->close();

        } catch (\Throwable $e) {
            // The exception text stays server-side; the caller gets a generic error.
            Log::add(
                'Product filter AJAX failed: ' . $e->getMessage()
                    . ' at ' . $e->getFile() . ':' . $e->getLine(),
                Log::ERROR,
                'com_j2commerce'
            );

            $this->sendJsonError(Text::_('COM_J2COMMERCE_ERR_GENERIC'), 500);
        }
    }

    /**
     * ACL gate for variant AJAX calls: admin / active vendor / article-author.
     * CSRF token validation remains inside each individual admin AJAX method.
     */
    private function authorizeVariantAjax(): bool
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || $user->guest) {
            return false;
        }

        if ($user->authorise('core.edit', 'com_j2commerce')) {
            return true;
        }

        $db     = Factory::getContainer()->get(DatabaseInterface::class);
        $userId = (int) $user->id;
        $query  = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_vendors'))
            ->where($db->quoteName('j2commerce_user_id') . ' = :userId')
            ->where($db->quoteName('enabled') . ' = 1')
            ->bind(':userId', $userId, ParameterType::INTEGER);
        $db->setQuery($query);

        if (((int) $db->loadResult()) > 0) {
            return true;
        }

        if ($user->authorise('core.edit', 'com_content') || $user->authorise('core.edit.own', 'com_content')) {
            return true;
        }

        return false;
    }

    private function sendVariantJsonError(string $message): void
    {
        $app = Factory::getApplication();
        $app->getDocument()->setMimeEncoding('application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        $app->close();
    }

    /**
     * How many products each sidebar filter value still reaches, keyed by filter ID.
     *
     * The value list itself is a superset the full page render already emitted, so only the
     * counts travel: the sidebar updates in place and keeps its accordion and focus state.
     *
     * @return array<int, int>
     */
    protected function filterCounts(ListModel $model): array
    {
        if (!method_exists($model, 'getProductFilterFacets')) {
            return [];
        }

        $counts = [];

        foreach ($model->getProductFilterFacets() as $group) {
            foreach ($group['filters'] as $filter) {
                $counts[(int) $filter->filter_id] = (int) $filter->product_count;
            }
        }

        return $counts;
    }

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

        // Let subtemplate plugins render the grid in their own framework
        // (uk-grid for UIkit, etc.). Fall back to Bootstrap markup below
        // when no plugin claims the event.
        $pluginHtml = J2CommerceHelper::plugin()->eventWithHtml(
            'RenderAjaxProductListGrid',
            [$items, $params, $itemId]
        )->getArgument('html', '');

        if ($pluginHtml !== '') {
            return $pluginHtml;
        }

        ob_start();

        echo '<div class="j2commerce-products-row row g-4 mb-4">';

        foreach ($items as $product) {
            if (!($product->params instanceof Registry)) {
                $product->params = new Registry($product->params ?? '{}');
            }

            $itemHtml = ProductLayoutService::renderProductItem(
                $product,
                $params,
                ProductLayoutService::CONTEXT_LIST,
                $itemId
            );

            // A product type with no registered layout renders nothing — skip the wrapper too.
            if (trim($itemHtml) === '') {
                continue;
            }

            echo '<div class="' . $colClass . '">';
            echo $itemHtml;
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
        $orderMapping = [
            'name-asc'       => ['a.title', 'ASC'],
            'name-desc'      => ['a.title', 'DESC'],
            'price-asc'      => ['v.price', 'ASC'],
            'price-desc'     => ['v.price', 'DESC'],
            'newest'         => ['a.created', 'DESC'],
            'popular'        => ['p.hits', 'DESC'],
            'name_asc'       => ['a.title', 'ASC'],
            'name_desc'      => ['a.title', 'DESC'],
            'price_asc'      => ['v.price', 'ASC'],
            'price_desc'     => ['v.price', 'DESC'],
            'date_asc'       => ['a.created', 'ASC'],
            'date_desc'      => ['a.created', 'DESC'],
            'ordering'       => ['a.ordering', 'ASC'],
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

}
