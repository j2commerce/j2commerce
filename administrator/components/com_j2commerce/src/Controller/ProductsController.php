<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\Route;
use Joomla\Database\ParameterType;
use Joomla\Input\Input;
use Joomla\Utilities\ArrayHelper;

/**
 * Products list controller class.
 *
 * @since  6.0.3
 */
class ProductsController extends AdminController
{
    /**
     * The prefix to use with controller messages.
     *
     * IMPORTANT: Use general 'COM_J2COMMERCE' prefix so bulk action messages
     * like N_ITEMS_PUBLISHED use shared language strings, not view-specific ones.
     *
     * @var    string
     * @since  6.0.3
     */
    protected $text_prefix = 'COM_J2COMMERCE';

    /**
     * Constructor.
     *
     * @param   array                     $config   An optional associative array of configuration settings.
     * @param   MVCFactoryInterface|null  $factory  The factory.
     * @param   CMSApplication|null       $app      The Application for the dispatcher
     * @param   Input|null                $input    Input
     *
     * @since   6.0.3
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);
    }

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  The array of possible config values. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
     *
     * @since   6.0.3
     */
    public function getModel($name = 'Product', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Delete selected products AND trash their linked Joomla articles.
     *
     * @return  void
     *
     * @since   6.1.4
     */
    public function deleteWithArticles(): void
    {
        $this->checkToken();

        $cids = (array) $this->input->get('cid', [], 'int');
        $cids = ArrayHelper::toInteger($cids);

        if (empty($cids)) {
            $this->app->enqueueMessage(Text::_('JGLOBAL_NO_ITEM_SELECTED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=products', false));

            return;
        }

        $table  = $this->getModel()->getTable();
        $errors = 0;

        foreach ($cids as $productId) {
            // Load product to get the linked article ID before deleting
            if (!$table->load($productId)) {
                $errors++;
                continue;
            }

            $articleId     = (int) ($table->product_source_id ?? 0);
            $productSource = $table->product_source ?? 'com_content';

            // Delete the product (cascades child records via ProductTable::delete)
            if (!$table->delete($productId)) {
                $this->app->enqueueMessage($table->getError(), 'error');
                $errors++;
                continue;
            }

            // Trash the linked article via ArticleModel::publish() to respect workflows
            if ($productSource === 'com_content' && $articleId > 0) {
                try {
                    /** @var \Joomla\Component\Content\Administrator\Model\ArticleModel $articleModel */
                    $articleModel = $this->app->bootComponent('com_content')
                        ->getMVCFactory()
                        ->createModel('Article', 'Administrator', ['ignore_request' => true]);

                    $pks = [$articleId];
                    if (!$articleModel->publish($pks, -2)) {
                        throw new \RuntimeException($articleModel->getError());
                    }
                } catch (\Exception $e) {
                    $this->app->enqueueMessage(
                        Text::sprintf('COM_J2COMMERCE_ERROR_TRASH_ARTICLE', $articleId, $e->getMessage()),
                        'warning'
                    );
                }
            }
        }

        $deleted = \count($cids) - $errors;

        if ($deleted > 0) {
            $this->app->enqueueMessage(Text::plural('COM_J2COMMERCE_N_ITEMS_DELETED_WITH_ARTICLES', $deleted), 'success');
        }

        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=products', false));
    }

    /**
     * Search product filters for autocomplete.
     *
     * Returns JSON array of filters matching the search term.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function searchproductfilters(): void
    {
        $app = Factory::getApplication();
        $q   = $app->getInput()->post->getString('q', '');

        // Get database and search for filters
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query->select([
            $db->quoteName('f.j2commerce_filter_id'),
            $db->quoteName('f.filter_name'),
            $db->quoteName('fg.group_name'),
        ])
            ->from($db->quoteName('#__j2commerce_filters', 'f'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_filtergroups', 'fg') .
                ' ON ' . $db->quoteName('f.group_id') . ' = ' . $db->quoteName('fg.j2commerce_filtergroup_id')
            );

        if (!empty($q)) {
            $search = '%' . $db->escape($q, true) . '%';
            $query->where(
                '(' . $db->quoteName('f.filter_name') . ' LIKE ' . $db->quote($search) .
                ' OR ' . $db->quoteName('fg.group_name') . ' LIKE ' . $db->quote($search) . ')'
            );
        }

        $query->order($db->quoteName('fg.group_name') . ' ASC')
            ->order($db->quoteName('f.filter_name') . ' ASC')
            ->setLimit(20);

        $db->setQuery($query);
        $results = $db->loadObjectList();

        echo json_encode($results ?: []);
        $app->close();
    }

    /**
     * Delete a product filter association.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function deleteproductfilter(): void
    {
        $app = Factory::getApplication();

        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            echo json_encode(['success' => false, 'msg' => Text::_('JINVALID_TOKEN')]);
            $app->close();

            return;
        }

        $filterId  = $app->getInput()->post->getInt('filter_id', 0);
        $productId = $app->getInput()->post->getInt('product_id', 0);

        $success = false;
        $msg     = Text::_('COM_J2COMMERCE_PRODUCT_FILTER_DELETE_ERROR');

        if ($filterId && $productId) {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);

            $query->delete($db->quoteName('#__j2commerce_product_filters'))
                ->where($db->quoteName('filter_id') . ' = :filter_id')
                ->where($db->quoteName('product_id') . ' = :product_id')
                ->bind(':filter_id', $filterId, ParameterType::INTEGER)
                ->bind(':product_id', $productId, ParameterType::INTEGER);

            $db->setQuery($query);

            try {
                $db->execute();
                $affected = $db->getAffectedRows();

                if ($affected > 0) {
                    $success = true;
                    $msg     = Text::_('COM_J2COMMERCE_PRODUCT_FILTER_DELETE_SUCCESSFUL');
                }
            } catch (\Exception $e) {
                $msg = $e->getMessage();
            }
        }

        echo json_encode([
            'success' => $success,
            'msg'     => $msg,
        ]);

        $app->close();
    }

    /**
     * Get paginated product filter list via AJAX.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function getProductFilterListAjax(): void
    {
        $app        = Factory::getApplication();
        $productId  = $app->getInput()->post->getInt('product_id', 0);
        $limitstart = $app->getInput()->post->getInt('limitstart', 0);
        $limit      = $app->getInput()->post->getInt('limit', 20);
        $formPrefix = $app->getInput()->post->getString('form_prefix', '[attribs][j2commerce]');

        $html = '';

        if ($productId) {
            // Get product with filters using ProductHelper
            $productModel = $this->getModel('Product');
            $product      = $productModel->getItem($productId);

            if ($product) {
                // Get paginated product filters
                $db    = Factory::getContainer()->get('DatabaseDriver');
                $query = $db->getQuery(true);

                $query->select([
                    $db->quoteName('pf.filter_id'),
                    $db->quoteName('f.filter_name'),
                    $db->quoteName('fg.group_name'),
                ])
                    ->from($db->quoteName('#__j2commerce_product_filters', 'pf'))
                    ->leftJoin(
                        $db->quoteName('#__j2commerce_filters', 'f') .
                        ' ON ' . $db->quoteName('pf.filter_id') . ' = ' . $db->quoteName('f.j2commerce_filter_id')
                    )
                    ->leftJoin(
                        $db->quoteName('#__j2commerce_filtergroups', 'fg') .
                        ' ON ' . $db->quoteName('f.group_id') . ' = ' . $db->quoteName('fg.j2commerce_filtergroup_id')
                    )
                    ->where($db->quoteName('pf.product_id') . ' = :product_id')
                    ->bind(':product_id', $productId, ParameterType::INTEGER)
                    ->order($db->quoteName('fg.group_name') . ' ASC')
                    ->order($db->quoteName('f.filter_name') . ' ASC')
                    ->setLimit($limit, $limitstart);

                $db->setQuery($query);
                $filters = $db->loadObjectList();

                // Create the product object for the layout
                $product->product_filters = $filters;
                $product->form_prefix     = $formPrefix;

                // Render using the layout
                $layout = new FileLayout('form_ajax_avfilter', JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');
                $html   = $layout->render(['product' => $product]);
            }
        }

        echo json_encode(['html' => $html]);
        $app->close();
    }

    /**
     * Search products for related products autocomplete (upsells/cross-sells).
     *
     * Returns JSON array of products matching the search term,
     * excluding the current product being edited.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function getRelatedProducts(): void
    {
        $app       = Factory::getApplication();
        $q         = $app->getInput()->post->getString('q', '');
        $productId = $app->getInput()->post->getInt('product_id', 0);

        $products = [];

        if (!empty($q)) {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);

            // Search for products by article title (product_name) or SKU
            $query->select([
                $db->quoteName('p.j2commerce_product_id'),
                $db->quoteName('p.product_source_id'),
                $db->quoteName('c.title') . ' AS ' . $db->quoteName('product_name'),
                $db->quoteName('v.sku'),
            ])
                ->from($db->quoteName('#__j2commerce_products', 'p'))
                ->leftJoin(
                    $db->quoteName('#__content', 'c') .
                    ' ON ' . $db->quoteName('p.product_source_id') . ' = ' . $db->quoteName('c.id')
                )
                ->leftJoin(
                    $db->quoteName('#__j2commerce_variants', 'v') .
                    ' ON ' . $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('v.product_id') .
                    ' AND ' . $db->quoteName('v.is_master') . ' = 1'
                )
                ->where($db->quoteName('p.enabled') . ' = 1');

            // Exclude current product
            if ($productId > 0) {
                $query->where($db->quoteName('p.j2commerce_product_id') . ' != :product_id')
                    ->bind(':product_id', $productId, ParameterType::INTEGER);
            }

            // Search by product name or SKU
            $search = '%' . $db->escape($q, true) . '%';
            $query->where(
                '(' . $db->quoteName('c.title') . ' LIKE ' . $db->quote($search) .
                ' OR ' . $db->quoteName('v.sku') . ' LIKE ' . $db->quote($search) . ')'
            );

            $query->order($db->quoteName('c.title') . ' ASC')
                ->setLimit(20);

            $db->setQuery($query);
            $products = $db->loadObjectList();

            foreach ($products as $product) {
                if (empty($product->product_name)) {
                    $product->product_name = !empty($product->sku)
                        ? $product->sku
                        : Text::_('COM_J2COMMERCE_PRODUCT_ID') . ' ' . $product->j2commerce_product_id;
                }
            }
        }

        echo json_encode(['products' => $products ?: []]);
        $app->close();
    }

    public function getBoxBuilderProducts(): void
    {
        $app = Factory::getApplication();
        $q   = $app->getInput()->post->getString('q', '');

        $products = [];

        if (\strlen($q) >= 2) {
            $db     = Factory::getContainer()->get('DatabaseDriver');
            $search = '%' . $db->escape($q, true) . '%';
            $types  = ['simple', 'configurable', 'downloadable'];

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('p.j2commerce_product_id'),
                    $db->quoteName('c.title', 'product_name'),
                    $db->quoteName('v.sku', 'product_sku'),
                ])
                ->from($db->quoteName('#__j2commerce_products', 'p'))
                ->leftJoin(
                    $db->quoteName('#__content', 'c')
                    . ' ON ' . $db->quoteName('p.product_source_id') . ' = ' . $db->quoteName('c.id')
                )
                ->leftJoin(
                    $db->quoteName('#__j2commerce_variants', 'v')
                    . ' ON ' . $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('v.product_id')
                    . ' AND ' . $db->quoteName('v.is_master') . ' = 1'
                )
                ->where($db->quoteName('p.enabled') . ' = 1')
                ->where($db->quoteName('p.product_type') . ' IN (' . implode(',', array_map([$db, 'quote'], $types)) . ')')
                ->where(
                    '(' . $db->quoteName('c.title') . ' LIKE ' . $db->quote($search)
                    . ' OR ' . $db->quoteName('v.sku') . ' LIKE ' . $db->quote($search) . ')'
                )
                ->order($db->quoteName('c.title') . ' ASC')
                ->setLimit(20);

            $db->setQuery($query);
            $products = $db->loadObjectList() ?: [];

            foreach ($products as $product) {
                if (empty($product->product_name)) {
                    $product->product_name = $product->product_sku ?: ('ID ' . $product->j2commerce_product_id);
                }
            }
        }

        echo json_encode(['products' => $products]);
        $app->close();
    }

    /**
     * Change the product type.
     *
     * This method handles changing a product's type. When the type changes,
     * the product's child tables are deleted and the product is reloaded
     * with the new type's structure.
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function changeProductType(): void
    {
        $app = Factory::getApplication();

        // Check for request forgeries
        $this->checkToken();

        $productId = $app->getInput()->post->getInt('product_id', 0);
        $newType   = $app->getInput()->post->getString('product_type', '');

        $json = ['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_CHANGE_PRODUCT_TYPE')];

        // Validate input
        if (empty($productId)) {
            $json['message'] = Text::_('COM_J2COMMERCE_ERROR_INVALID_PRODUCT_ID');
            echo json_encode($json);
            $app->close();
        }

        // Get the current product
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('*')
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('j2commerce_product_id') . ' = :product_id')
            ->bind(':product_id', $productId, ParameterType::INTEGER);
        $db->setQuery($query);
        $product = $db->loadObject();

        if (empty($product)) {
            $json['message'] = Text::_('COM_J2COMMERCE_ERROR_PRODUCT_NOT_FOUND');
            echo json_encode($json);
            $app->close();
        }

        // Check if type is changing
        if ($product->product_type === $newType) {
            $json['success'] = true;
            $json['message'] = Text::_('COM_J2COMMERCE_PRODUCT_TYPE_UNCHANGED');
            echo json_encode($json);
            $app->close();
        }

        // Allow plugins to run their events
        $pluginResponse = J2CommerceHelper::plugin()->eventWithArray('ChangeProductType', [$productId, $newType]);

        // If plugin response has success, use it; otherwise delete and continue
        if (!empty($pluginResponse) && isset($pluginResponse['success'])) {
            $json = $pluginResponse;
        } else {
            // Delete the existing product (this removes child table data)
            $table = $this->getModel()->getTable('Product');
            if (!$table->delete($productId)) {
                $json['message'] = Text::_('COM_J2COMMERCE_ERROR_DELETE_PRODUCT');
                echo json_encode($json);
                $app->close();
            }

            $json['success'] = true;
            $json['message'] = Text::_('COM_J2COMMERCE_PRODUCT_TYPE_CHANGED_SUCCESS');
        }

        echo json_encode($json);
        $app->close();
    }

    /**
     * Publish or unpublish the source articles for selected products.
     *
     * This overrides the parent publish method to update the linked content articles
     * rather than the J2Commerce products table.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function publish(): void
    {
        // Check for request forgeries
        $this->checkToken();

        // Get items to publish from the request.
        $cid   = (array) $this->input->get('cid', [], 'int');
        $data  = ['publish' => 1, 'unpublish' => 0, 'archive' => 2, 'trash' => -2];
        $task  = $this->getTask();
        $value = ArrayHelper::getValue($data, $task, 0, 'int');

        // Remove zero values resulting from input filter
        $cid = array_filter($cid);

        if (empty($cid)) {
            $this->app->getLogger()->warning(Text::_($this->text_prefix . '_NO_ITEM_SELECTED'), ['category' => 'jerror']);
        } else {
            // Get the article IDs for the selected products
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);

            $query->select($db->quoteName('product_source_id'))
                ->from($db->quoteName('#__j2commerce_products'))
                ->whereIn($db->quoteName('j2commerce_product_id'), $cid);

            $db->setQuery($query);
            $articleIds = $db->loadColumn();

            // Filter out null/empty article IDs
            $articleIds = array_filter($articleIds);

            if (!empty($articleIds)) {
                // Update the content table directly
                $user      = $this->app->getIdentity();
                $canChange = $user->authorise('core.edit.state', 'com_content');

                if ($canChange) {
                    try {
                        $updateQuery = $db->getQuery(true);
                        $updateQuery->update($db->quoteName('#__content'))
                            ->set($db->quoteName('state') . ' = :state')
                            ->whereIn($db->quoteName('id'), $articleIds)
                            ->bind(':state', $value, ParameterType::INTEGER);

                        $db->setQuery($updateQuery);
                        $db->execute();

                        $ntext = null;
                        if ($value === 1) {
                            $ntext = $this->text_prefix . '_N_ITEMS_PUBLISHED';
                        } elseif ($value === 0) {
                            $ntext = $this->text_prefix . '_N_ITEMS_UNPUBLISHED';
                        } elseif ($value === 2) {
                            $ntext = $this->text_prefix . '_N_ITEMS_ARCHIVED';
                        } else {
                            $ntext = $this->text_prefix . '_N_ITEMS_TRASHED';
                        }

                        if (\count($cid)) {
                            $this->setMessage(Text::plural($ntext, \count($cid)));
                        }
                    } catch (\Exception $e) {
                        $this->setMessage($e->getMessage(), 'error');
                    }
                } else {
                    $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), CMSWebApplicationInterface::MSG_ERROR);
                }
            } else {
                $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_NO_ARTICLE_LINKED'), CMSWebApplicationInterface::MSG_WARNING);
            }
        }

        $this->setRedirect(
            Route::_(
                'index.php?option=' . $this->option . '&view=' . $this->view_list,
                false
            )
        );
    }

    /**
     * Check in the source articles for selected products.
     *
     * This overrides the parent checkin method to check in the linked content articles
     * rather than the J2Commerce products table.
     *
     * @return  bool  True on success
     *
     * @since   6.0.3
     */
    public function checkin(): bool
    {
        // Check for request forgeries.
        $this->checkToken();

        $ids = (array) $this->input->get('cid', [], 'int');

        // Remove zero values resulting from input filter
        $ids = array_filter($ids);

        if (empty($ids)) {
            $this->setRedirect(
                Route::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false),
                Text::_($this->text_prefix . '_NO_ITEM_SELECTED'),
                'warning'
            );
            return false;
        }

        // Get the article IDs for the selected products
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query->select($db->quoteName('product_source_id'))
            ->from($db->quoteName('#__j2commerce_products'))
            ->whereIn($db->quoteName('j2commerce_product_id'), $ids);

        $db->setQuery($query);
        $articleIds = $db->loadColumn();

        // Filter out null/empty article IDs
        $articleIds = array_filter($articleIds);

        if (empty($articleIds)) {
            $this->setRedirect(
                Route::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false),
                Text::_('COM_J2COMMERCE_NO_ARTICLE_LINKED'),
                'warning'
            );
            return false;
        }

        // Check in the content articles using direct database update
        $user             = $this->app->getIdentity();
        $userId           = $user->id;
        $canManageCheckin = $user->authorise('core.manage', 'com_checkin');

        // Get articles that are currently checked out and can be checked in by this user
        $query = $db->getQuery(true);
        $query->select([$db->quoteName('id'), $db->quoteName('checked_out')])
            ->from($db->quoteName('#__content'))
            ->whereIn($db->quoteName('id'), $articleIds)
            ->where($db->quoteName('checked_out') . ' > 0');

        $db->setQuery($query);
        $checkedOutArticles = $db->loadObjectList();

        $articlesToCheckin = [];

        foreach ($checkedOutArticles as $article) {
            // User can check in if they have manage permission or they checked it out
            if ($canManageCheckin || $article->checked_out == $userId) {
                $articlesToCheckin[] = (int) $article->id;
            }
        }

        $checkedIn = 0;

        if (!empty($articlesToCheckin)) {
            // Update the content table to check in the articles
            $updateQuery = $db->getQuery(true);
            $updateQuery->update($db->quoteName('#__content'))
                ->set($db->quoteName('checked_out') . ' = NULL')
                ->set($db->quoteName('checked_out_time') . ' = NULL')
                ->whereIn($db->quoteName('id'), $articlesToCheckin);

            $db->setQuery($updateQuery);
            $db->execute();

            $checkedIn = \count($articlesToCheckin);
        }

        if ($checkedIn > 0) {
            $this->setMessage(Text::plural($this->text_prefix . '_N_ITEMS_CHECKED_IN', $checkedIn));
        } else {
            $this->setMessage(Text::_('COM_J2COMMERCE_NO_ITEMS_CHECKED_IN'), 'notice');
        }

        $this->setRedirect(
            Route::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false)
        );

        return true;
    }

    /**
     * Display the product option values modal.
     *
     * Shows a modal to manage option values (price/weight modifiers) for a product option.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function setproductoptionvalues(): void
    {
        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        $productId       = $app->getInput()->getInt('product_id', 0);
        $productOptionId = $app->getInput()->getInt('productoption_id', 0);

        // Get product
        $productModel = $this->getModel('Product');
        $product      = $productModel->getItem($productId);

        if (!$product) {
            echo Text::_('COM_J2COMMERCE_PRODUCT_NOT_FOUND');
            return;
        }

        // Get product option details
        $query = $db->getQuery(true);
        $query->select([
                $db->quoteName('po.j2commerce_productoption_id'),
                $db->quoteName('po.option_id'),
                $db->quoteName('po.parent_id'),
                $db->quoteName('po.required'),
                $db->quoteName('po.is_variant'),
                $db->quoteName('po.product_id'),
            ])
            ->select([
                $db->quoteName('o.option_name'),
                $db->quoteName('o.option_unique_name'),
                $db->quoteName('o.type'),
            ])
            ->from($db->quoteName('#__j2commerce_product_options', 'po'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_options', 'o') .
                ' ON ' . $db->quoteName('po.option_id') . ' = ' . $db->quoteName('o.j2commerce_option_id')
            )
            ->where($db->quoteName('po.j2commerce_productoption_id') . ' = :poId')
            ->bind(':poId', $productOptionId, ParameterType::INTEGER);

        $db->setQuery($query);
        $productOption = $db->loadObject();

        if (!$productOption) {
            echo Text::_('COM_J2COMMERCE_PRODUCT_OPTION_NOT_FOUND');
            return;
        }

        // Get all available option values for this option
        // Note: bind() requires a variable reference, can't use object property directly
        $optionId = (int) $productOption->option_id;
        $query    = $db->getQuery(true);
        $query->select([
                $db->quoteName('j2commerce_optionvalue_id'),
                $db->quoteName('optionvalue_name'),
            ])
            ->from($db->quoteName('#__j2commerce_optionvalues'))
            ->where($db->quoteName('option_id') . ' = :optionId')
            ->order($db->quoteName('ordering') . ' ASC')
            ->bind(':optionId', $optionId, ParameterType::INTEGER);

        $db->setQuery($query);
        $optionValues = $db->loadObjectList() ?: [];

        // Get existing product option values
        $query = $db->getQuery(true);
        $query->select([
                $db->quoteName('pov.j2commerce_product_optionvalue_id'),
                $db->quoteName('pov.productoption_id'),
                $db->quoteName('pov.optionvalue_id'),
                $db->quoteName('pov.parent_optionvalue'),
                $db->quoteName('pov.product_optionvalue_price'),
                $db->quoteName('pov.product_optionvalue_prefix'),
                $db->quoteName('pov.product_optionvalue_weight'),
                $db->quoteName('pov.product_optionvalue_weight_prefix'),
                $db->quoteName('pov.product_optionvalue_sku'),
                $db->quoteName('pov.product_optionvalue_default'),
                $db->quoteName('pov.ordering'),
                $db->quoteName('pov.product_optionvalue_attribs'),
            ])
            ->select($db->quoteName('ov.optionvalue_name'))
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_optionvalues', 'ov') .
                ' ON ' . $db->quoteName('pov.optionvalue_id') . ' = ' . $db->quoteName('ov.j2commerce_optionvalue_id')
            )
            ->where($db->quoteName('pov.productoption_id') . ' = :poId')
            ->order($db->quoteName('pov.ordering') . ' ASC')
            ->bind(':poId', $productOptionId, ParameterType::INTEGER);

        $db->setQuery($query);
        $productOptionValues = $db->loadObjectList() ?: [];

        // Get parent option values if applicable
        $parentOptionValues = $this->resolveParentOptionValues($productOption);

        // Render the modal layout
        $layout = new FileLayout('productoptionvalues', JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');
        echo $layout->render([
            'product'              => $product,
            'product_option'       => $productOption,
            'product_id'           => $productId,
            'productoption_id'     => $productOptionId,
            'option_values'        => $optionValues,
            'product_optionvalues' => $productOptionValues,
            'parent_optionvalues'  => $parentOptionValues,
            'prefix'               => 'jform[poption_value]',
        ]);

        // Stop execution to prevent default display from rendering
        $app->close();
    }

    /**
     * Create a new product option value.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function createproductoptionvalue(): void
    {
        $this->checkToken();

        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        $productId         = $app->getInput()->getInt('product_id', 0);
        $productOptionId   = $app->getInput()->getInt('productoption_id', 0);
        $optionValueId     = $app->getInput()->getInt('optionvalue_id', 0);
        $price             = $app->getInput()->getFloat('product_optionvalue_price', 0.0);
        $pricePrefix       = $app->getInput()->getString('product_optionvalue_prefix', '+');
        $weight            = $app->getInput()->getFloat('product_optionvalue_weight', 0.0);
        $weightPrefix      = $app->getInput()->getString('product_optionvalue_weight_prefix', '+');
        $ordering          = $app->getInput()->getInt('ordering', 0);
        $attribs           = $app->getInput()->getString('product_optionvalue_attribs', '');
        $parentOptionValue = $app->getInput()->get('parent_optionvalue', [], 'array');

        // Check if option is a variant option (no price modifier)
        $query = $db->getQuery(true);
        $query->select($db->quoteName('is_variant'))
            ->from($db->quoteName('#__j2commerce_product_options'))
            ->where($db->quoteName('j2commerce_productoption_id') . ' = :poId')
            ->bind(':poId', $productOptionId, ParameterType::INTEGER);

        $db->setQuery($query);
        $isVariant = (int) $db->loadResult();

        // Reset price for variant options
        if ($isVariant) {
            $price = 0.0;
        }

        // Insert the new product option value
        $parentValue = !empty($parentOptionValue) ? implode(',', $parentOptionValue) : '';
        $emptyString = '';
        $zero        = 0;

        $columns = [
            'productoption_id',
            'optionvalue_id',
            'parent_optionvalue',
            'product_optionvalue_price',
            'product_optionvalue_prefix',
            'product_optionvalue_weight',
            'product_optionvalue_weight_prefix',
            'product_optionvalue_sku',
            'product_optionvalue_default',
            'ordering',
            'product_optionvalue_attribs',
        ];

        $query = $db->getQuery(true);
        $query->insert($db->quoteName('#__j2commerce_product_optionvalues'))
            ->columns($db->quoteName($columns))
            ->values(':productoption_id, :optionvalue_id, :parent_optionvalue, :price, :price_prefix, :weight, :weight_prefix, :sku, :default, :ordering, :attribs')
            ->bind(':productoption_id', $productOptionId, ParameterType::INTEGER)
            ->bind(':optionvalue_id', $optionValueId, ParameterType::INTEGER)
            ->bind(':parent_optionvalue', $parentValue)
            ->bind(':price', $price)
            ->bind(':price_prefix', $pricePrefix)
            ->bind(':weight', $weight)
            ->bind(':weight_prefix', $weightPrefix)
            ->bind(':sku', $emptyString)
            ->bind(':default', $zero, ParameterType::INTEGER)
            ->bind(':ordering', $ordering, ParameterType::INTEGER)
            ->bind(':attribs', $attribs);

        $db->setQuery($query);

        try {
            $db->execute();
            $msg     = Text::_('COM_J2COMMERCE_PRODUCT_OPTION_VALUE_CREATED');
            $msgType = 'message';
        } catch (\Exception $e) {
            $msg     = $e->getMessage();
            $msgType = 'error';
        }

        $url = 'index.php?option=com_j2commerce&view=productoptionvalues&product_id=' . $productId
            . '&productoption_id=' . $productOptionId . '&tmpl=component';

        $this->setRedirect(Route::_($url, false), $msg, $msgType);
    }

    /**
     * Save product option values (bulk update).
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function saveproductoptionvalue(): void
    {
        $this->checkToken();

        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        $productId       = $app->getInput()->getInt('product_id', 0);
        $productOptionId = $app->getInput()->getInt('productoption_id', 0);
        $poptionValues   = $app->getInput()->get('jform', [], 'array');

        if (isset($poptionValues['poption_value']) && \is_array($poptionValues['poption_value'])) {
            foreach ($poptionValues['poption_value'] as $povId => $data) {
                $povId = (int) $povId;
                if ($povId <= 0) {
                    continue;
                }

                // Prepare data
                $optionValueId = isset($data['optionvalue_id']) ? (int) $data['optionvalue_id'] : 0;
                $price         = isset($data['product_optionvalue_price']) ? (float) $data['product_optionvalue_price'] : 0.0;
                $pricePrefix   = isset($data['product_optionvalue_prefix']) ? $data['product_optionvalue_prefix'] : '+';
                $weight        = isset($data['product_optionvalue_weight']) ? (float) $data['product_optionvalue_weight'] : 0.0;
                $weightPrefix  = isset($data['product_optionvalue_weight_prefix']) ? $data['product_optionvalue_weight_prefix'] : '+';
                $ordering      = isset($data['ordering']) ? (int) $data['ordering'] : 0;
                $parentValue   = isset($data['parent_optionvalue']) && \is_array($data['parent_optionvalue'])
                    ? implode(',', $data['parent_optionvalue'])
                    : '';
                $attribs = isset($data['product_optionvalue_attribs']) ? $data['product_optionvalue_attribs'] : '';

                $query = $db->getQuery(true);
                $query->update($db->quoteName('#__j2commerce_product_optionvalues'))
                    ->set($db->quoteName('optionvalue_id') . ' = :optionvalue_id')
                    ->set($db->quoteName('parent_optionvalue') . ' = :parent_optionvalue')
                    ->set($db->quoteName('product_optionvalue_price') . ' = :price')
                    ->set($db->quoteName('product_optionvalue_prefix') . ' = :price_prefix')
                    ->set($db->quoteName('product_optionvalue_weight') . ' = :weight')
                    ->set($db->quoteName('product_optionvalue_weight_prefix') . ' = :weight_prefix')
                    ->set($db->quoteName('ordering') . ' = :ordering')
                    ->set($db->quoteName('product_optionvalue_attribs') . ' = :attribs')
                    ->where($db->quoteName('j2commerce_product_optionvalue_id') . ' = :povId')
                    ->bind(':optionvalue_id', $optionValueId, ParameterType::INTEGER)
                    ->bind(':parent_optionvalue', $parentValue)
                    ->bind(':price', $price)
                    ->bind(':price_prefix', $pricePrefix)
                    ->bind(':weight', $weight)
                    ->bind(':weight_prefix', $weightPrefix)
                    ->bind(':ordering', $ordering, ParameterType::INTEGER)
                    ->bind(':attribs', $attribs)
                    ->bind(':povId', $povId, ParameterType::INTEGER);

                $db->setQuery($query);
                $db->execute();
            }
        }

        $url = 'index.php?option=com_j2commerce&view=productoptionvalues&product_id=' . $productId
            . '&productoption_id=' . $productOptionId . '&tmpl=component';

        $this->setRedirect(Route::_($url, false), Text::_('COM_J2COMMERCE_PRODUCT_OPTION_VALUES_SAVED'), 'message');
    }

    /**
     * Delete product option values.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function deleteProductOptionvalues(): void
    {
        $this->checkToken('request');

        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        $productId       = $app->getInput()->getInt('product_id', 0);
        $productOptionId = $app->getInput()->getInt('productoption_id', 0);
        $cid             = $app->getInput()->get('cid', [], 'array');

        $cid = ArrayHelper::toInteger($cid);
        $cid = array_filter($cid);

        if (!empty($cid)) {
            $query = $db->getQuery(true);
            $query->delete($db->quoteName('#__j2commerce_product_optionvalues'))
                ->whereIn($db->quoteName('j2commerce_product_optionvalue_id'), $cid);

            $db->setQuery($query);

            try {
                $db->execute();
                $msg     = Text::plural('COM_J2COMMERCE_N_ITEMS_DELETED', \count($cid));
                $msgType = 'message';
            } catch (\Exception $e) {
                $msg     = $e->getMessage();
                $msgType = 'error';
            }
        } else {
            $msg     = Text::_('COM_J2COMMERCE_NO_ITEM_SELECTED');
            $msgType = 'warning';
        }

        $url = 'index.php?option=com_j2commerce&view=productoptionvalues&product_id=' . $productId
            . '&productoption_id=' . $productOptionId . '&tmpl=component';

        $this->setRedirect(Route::_($url, false), $msg, $msgType);
    }

    /**
     * Add all master option values to a product option.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function addAllOptionValue(): void
    {
        $this->checkToken('request');

        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        $productId       = $app->getInput()->getInt('product_id', 0);
        $productOptionId = $app->getInput()->getInt('productoption_id', 0);

        // Get the option_id from the product option
        $query = $db->getQuery(true);
        $query->select($db->quoteName('option_id'))
            ->from($db->quoteName('#__j2commerce_product_options'))
            ->where($db->quoteName('j2commerce_productoption_id') . ' = :poId')
            ->bind(':poId', $productOptionId, ParameterType::INTEGER);

        $db->setQuery($query);
        $optionId = (int) $db->loadResult();

        $success = false;

        if ($optionId) {
            // Get all option values for this option
            $query = $db->getQuery(true);
            $query->select($db->quoteName('j2commerce_optionvalue_id'))
                ->from($db->quoteName('#__j2commerce_optionvalues'))
                ->where($db->quoteName('option_id') . ' = :optionId')
                ->order($db->quoteName('ordering') . ' ASC')
                ->bind(':optionId', $optionId, ParameterType::INTEGER);

            $db->setQuery($query);
            $optionValueIds = $db->loadColumn();

            foreach ($optionValueIds as $optionValueId) {
                $optionValueId = (int) $optionValueId;

                // Check if already exists
                $checkQuery = $db->getQuery(true);
                $checkQuery->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_product_optionvalues'))
                    ->where($db->quoteName('productoption_id') . ' = :poId')
                    ->where($db->quoteName('optionvalue_id') . ' = :ovId')
                    ->bind(':poId', $productOptionId, ParameterType::INTEGER)
                    ->bind(':ovId', $optionValueId, ParameterType::INTEGER);

                $db->setQuery($checkQuery);
                $exists = (int) $db->loadResult();

                if ($exists) {
                    continue;
                }

                // Insert new product option value
                $empty   = '';
                $zero    = 0.0;
                $zeroInt = 0;
                $plus    = '+';

                $columns = [
                    'productoption_id',
                    'optionvalue_id',
                    'parent_optionvalue',
                    'product_optionvalue_price',
                    'product_optionvalue_prefix',
                    'product_optionvalue_weight',
                    'product_optionvalue_weight_prefix',
                    'product_optionvalue_sku',
                    'product_optionvalue_default',
                    'ordering',
                    'product_optionvalue_attribs',
                ];

                $insertQuery = $db->getQuery(true);
                $insertQuery->insert($db->quoteName('#__j2commerce_product_optionvalues'))
                    ->columns($db->quoteName($columns))
                    ->values(':productoption_id, :optionvalue_id, :parent, :price, :price_prefix, :weight, :weight_prefix, :sku, :default, :ordering, :attribs')
                    ->bind(':productoption_id', $productOptionId, ParameterType::INTEGER)
                    ->bind(':optionvalue_id', $optionValueId, ParameterType::INTEGER)
                    ->bind(':parent', $empty)
                    ->bind(':price', $zero)
                    ->bind(':price_prefix', $plus)
                    ->bind(':weight', $zero)
                    ->bind(':weight_prefix', $plus)
                    ->bind(':sku', $empty)
                    ->bind(':default', $zeroInt, ParameterType::INTEGER)
                    ->bind(':ordering', $zeroInt, ParameterType::INTEGER)
                    ->bind(':attribs', $empty);

                $db->setQuery($insertQuery);
                $db->execute();
            }

            $success = true;
        }

        echo json_encode(['success' => $success]);
        $app->close();
    }

    /**
     * Get product option values content via AJAX for modal injection.
     *
     * Returns HTML content suitable for direct injection into a modal body,
     * avoiding the need for iframes.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function getProductOptionValuesAjax(): void
    {
        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        $productId       = $app->getInput()->getInt('product_id', 0);
        $productOptionId = $app->getInput()->getInt('productoption_id', 0);

        $response = ['success' => false, 'html' => '', 'message' => ''];

        if (!$productId || !$productOptionId) {
            $response['message'] = Text::_('COM_J2COMMERCE_INVALID_PRODUCT_OR_OPTION');
            echo json_encode($response);
            $app->close();
            return;
        }

        // Get product
        $productModel = $this->getModel('Product');
        $product      = $productModel->getItem($productId);

        if (!$product) {
            $response['message'] = Text::_('COM_J2COMMERCE_PRODUCT_NOT_FOUND');
            echo json_encode($response);
            $app->close();
            return;
        }

        // Get product option details
        $query = $db->getQuery(true);
        $query->select([
                $db->quoteName('po.j2commerce_productoption_id'),
                $db->quoteName('po.option_id'),
                $db->quoteName('po.parent_id'),
                $db->quoteName('po.required'),
                $db->quoteName('po.is_variant'),
                $db->quoteName('po.product_id'),
            ])
            ->select([
                $db->quoteName('o.option_name'),
                $db->quoteName('o.option_unique_name'),
                $db->quoteName('o.type'),
            ])
            ->from($db->quoteName('#__j2commerce_product_options', 'po'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_options', 'o') .
                ' ON ' . $db->quoteName('po.option_id') . ' = ' . $db->quoteName('o.j2commerce_option_id')
            )
            ->where($db->quoteName('po.j2commerce_productoption_id') . ' = :poId')
            ->bind(':poId', $productOptionId, ParameterType::INTEGER);

        $db->setQuery($query);
        $productOption = $db->loadObject();

        if (!$productOption) {
            $response['message'] = Text::_('COM_J2COMMERCE_PRODUCT_OPTION_NOT_FOUND');
            echo json_encode($response);
            $app->close();
            return;
        }

        // Get all available option values for this option
        // Note: bind() requires a variable reference, can't use object property directly
        $optionId = (int) $productOption->option_id;
        $query    = $db->getQuery(true);
        $query->select([
                $db->quoteName('j2commerce_optionvalue_id'),
                $db->quoteName('optionvalue_name'),
            ])
            ->from($db->quoteName('#__j2commerce_optionvalues'))
            ->where($db->quoteName('option_id') . ' = :optionId')
            ->order($db->quoteName('ordering') . ' ASC')
            ->bind(':optionId', $optionId, ParameterType::INTEGER);

        $db->setQuery($query);
        $optionValues = $db->loadObjectList() ?: [];

        // Get existing product option values
        $query = $db->getQuery(true);
        $query->select([
                $db->quoteName('pov.j2commerce_product_optionvalue_id'),
                $db->quoteName('pov.productoption_id'),
                $db->quoteName('pov.optionvalue_id'),
                $db->quoteName('pov.parent_optionvalue'),
                $db->quoteName('pov.product_optionvalue_price'),
                $db->quoteName('pov.product_optionvalue_prefix'),
                $db->quoteName('pov.product_optionvalue_weight'),
                $db->quoteName('pov.product_optionvalue_weight_prefix'),
                $db->quoteName('pov.product_optionvalue_sku'),
                $db->quoteName('pov.product_optionvalue_default'),
                $db->quoteName('pov.ordering'),
                $db->quoteName('pov.product_optionvalue_attribs'),
            ])
            ->select($db->quoteName('ov.optionvalue_name'))
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_optionvalues', 'ov') .
                ' ON ' . $db->quoteName('pov.optionvalue_id') . ' = ' . $db->quoteName('ov.j2commerce_optionvalue_id')
            )
            ->where($db->quoteName('pov.productoption_id') . ' = :poId')
            ->order($db->quoteName('pov.ordering') . ' ASC')
            ->bind(':poId', $productOptionId, ParameterType::INTEGER);

        $db->setQuery($query);
        $productOptionValues = $db->loadObjectList() ?: [];

        // Get parent option values if applicable
        $parentOptionValues = $this->resolveParentOptionValues($productOption);

        // Render the AJAX modal layout
        $layout = new FileLayout('form_ajax_optionvalues', JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');
        $html   = $layout->render([
            'product'              => $product,
            'product_option'       => $productOption,
            'product_id'           => $productId,
            'productoption_id'     => $productOptionId,
            'option_values'        => $optionValues,
            'product_optionvalues' => $productOptionValues,
            'parent_optionvalues'  => $parentOptionValues,
            'prefix'               => 'jform[poption_value]',
        ]);

        $response['success']    = true;
        $response['html']       = $html;
        $response['optionName'] = $productOption->option_name ?? '';

        echo json_encode($response);
        $app->close();
    }

    /**
     * Create a new product option value via AJAX.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function createProductOptionValueAjax(): void
    {
        $this->checkToken('request');

        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        $productId         = $app->getInput()->getInt('product_id', 0);
        $productOptionId   = $app->getInput()->getInt('productoption_id', 0);
        $optionValueId     = $app->getInput()->getInt('optionvalue_id', 0);
        $price             = $app->getInput()->getFloat('product_optionvalue_price', 0.0);
        $pricePrefix       = $app->getInput()->getString('product_optionvalue_prefix', '+');
        $weight            = $app->getInput()->getFloat('product_optionvalue_weight', 0.0);
        $weightPrefix      = $app->getInput()->getString('product_optionvalue_weight_prefix', '+');
        $ordering          = $app->getInput()->getInt('ordering', 0);
        $attribs           = $app->getInput()->getString('product_optionvalue_attribs', '');
        $parentOptionValue = $app->getInput()->get('parent_optionvalue', [], 'array');

        $response = ['success' => false, 'message' => ''];

        // Check if option is a variant option (no price modifier)
        $query = $db->getQuery(true);
        $query->select($db->quoteName('is_variant'))
            ->from($db->quoteName('#__j2commerce_product_options'))
            ->where($db->quoteName('j2commerce_productoption_id') . ' = :poId')
            ->bind(':poId', $productOptionId, ParameterType::INTEGER);

        $db->setQuery($query);
        $isVariant = (int) $db->loadResult();

        // Reset price for variant options
        if ($isVariant) {
            $price = 0.0;
        }

        // Insert the new product option value
        $parentValue = !empty($parentOptionValue) ? implode(',', $parentOptionValue) : '';
        $emptyString = '';
        $zero        = 0;

        $columns = [
            'productoption_id',
            'optionvalue_id',
            'parent_optionvalue',
            'product_optionvalue_price',
            'product_optionvalue_prefix',
            'product_optionvalue_weight',
            'product_optionvalue_weight_prefix',
            'product_optionvalue_sku',
            'product_optionvalue_default',
            'ordering',
            'product_optionvalue_attribs',
        ];

        $query = $db->getQuery(true);
        $query->insert($db->quoteName('#__j2commerce_product_optionvalues'))
            ->columns($db->quoteName($columns))
            ->values(':productoption_id, :optionvalue_id, :parent_optionvalue, :price, :price_prefix, :weight, :weight_prefix, :sku, :default, :ordering, :attribs')
            ->bind(':productoption_id', $productOptionId, ParameterType::INTEGER)
            ->bind(':optionvalue_id', $optionValueId, ParameterType::INTEGER)
            ->bind(':parent_optionvalue', $parentValue)
            ->bind(':price', $price)
            ->bind(':price_prefix', $pricePrefix)
            ->bind(':weight', $weight)
            ->bind(':weight_prefix', $weightPrefix)
            ->bind(':sku', $emptyString)
            ->bind(':default', $zero, ParameterType::INTEGER)
            ->bind(':ordering', $ordering, ParameterType::INTEGER)
            ->bind(':attribs', $attribs);

        $db->setQuery($query);

        try {
            $db->execute();
            $response['success'] = true;
            $response['message'] = Text::_('COM_J2COMMERCE_PRODUCT_OPTION_VALUE_CREATED');
        } catch (\Exception $e) {
            $response['message'] = $e->getMessage();
        }

        echo json_encode($response);
        $app->close();
    }

    /**
     * Save product option values via AJAX (bulk update).
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function saveProductOptionValueAjax(): void
    {
        $this->checkToken('request');

        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        $poptionValues = $app->getInput()->get('jform', [], 'array');

        $response = ['success' => false, 'message' => ''];

        try {
            if (isset($poptionValues['poption_value']) && \is_array($poptionValues['poption_value'])) {
                foreach ($poptionValues['poption_value'] as $povId => $data) {
                    $povId = (int) $povId;
                    if ($povId <= 0) {
                        continue;
                    }

                    // Prepare data
                    $optionValueId = isset($data['optionvalue_id']) ? (int) $data['optionvalue_id'] : 0;
                    $price         = isset($data['product_optionvalue_price']) ? (float) $data['product_optionvalue_price'] : 0.0;
                    $pricePrefix   = isset($data['product_optionvalue_prefix']) ? $data['product_optionvalue_prefix'] : '+';
                    $weight        = isset($data['product_optionvalue_weight']) ? (float) $data['product_optionvalue_weight'] : 0.0;
                    $weightPrefix  = isset($data['product_optionvalue_weight_prefix']) ? $data['product_optionvalue_weight_prefix'] : '+';
                    $ordering      = isset($data['ordering']) ? (int) $data['ordering'] : 0;
                    $parentValue   = isset($data['parent_optionvalue']) && \is_array($data['parent_optionvalue'])
                        ? implode(',', $data['parent_optionvalue'])
                        : '';
                    $attribs = isset($data['product_optionvalue_attribs']) ? $data['product_optionvalue_attribs'] : '';

                    $query = $db->getQuery(true);
                    $query->update($db->quoteName('#__j2commerce_product_optionvalues'))
                        ->set($db->quoteName('optionvalue_id') . ' = :optionvalue_id')
                        ->set($db->quoteName('parent_optionvalue') . ' = :parent_optionvalue')
                        ->set($db->quoteName('product_optionvalue_price') . ' = :price')
                        ->set($db->quoteName('product_optionvalue_prefix') . ' = :price_prefix')
                        ->set($db->quoteName('product_optionvalue_weight') . ' = :weight')
                        ->set($db->quoteName('product_optionvalue_weight_prefix') . ' = :weight_prefix')
                        ->set($db->quoteName('ordering') . ' = :ordering')
                        ->set($db->quoteName('product_optionvalue_attribs') . ' = :attribs')
                        ->where($db->quoteName('j2commerce_product_optionvalue_id') . ' = :povId')
                        ->bind(':optionvalue_id', $optionValueId, ParameterType::INTEGER)
                        ->bind(':parent_optionvalue', $parentValue)
                        ->bind(':price', $price)
                        ->bind(':price_prefix', $pricePrefix)
                        ->bind(':weight', $weight)
                        ->bind(':weight_prefix', $weightPrefix)
                        ->bind(':ordering', $ordering, ParameterType::INTEGER)
                        ->bind(':attribs', $attribs)
                        ->bind(':povId', $povId, ParameterType::INTEGER);

                    $db->setQuery($query);
                    $db->execute();
                }
            }

            $response['success'] = true;
            $response['message'] = Text::_('COM_J2COMMERCE_PRODUCT_OPTION_VALUES_SAVED');
        } catch (\Exception $e) {
            $response['message'] = $e->getMessage();
        }

        echo json_encode($response);
        $app->close();
    }

    /**
     * Delete product option values via AJAX.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function deleteProductOptionValueAjax(): void
    {
        $this->checkToken('request');

        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        $povId = $app->getInput()->getInt('pov_id', 0);

        $response = ['success' => false, 'message' => ''];

        if ($povId) {
            $query = $db->getQuery(true);
            $query->delete($db->quoteName('#__j2commerce_product_optionvalues'))
                ->where($db->quoteName('j2commerce_product_optionvalue_id') . ' = :povId')
                ->bind(':povId', $povId, ParameterType::INTEGER);

            $db->setQuery($query);

            try {
                $db->execute();
                $response['success'] = true;
                $response['message'] = Text::_('COM_J2COMMERCE_N_ITEMS_DELETED');
            } catch (\Exception $e) {
                $response['message'] = $e->getMessage();
            }
        } else {
            $response['message'] = Text::_('COM_J2COMMERCE_NO_ITEM_SELECTED');
        }

        echo json_encode($response);
        $app->close();
    }

    /**
     * Set default product option value.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function setDefault(): void
    {
        $this->checkToken('request');

        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        $productId       = $app->getInput()->getInt('product_id', 0);
        $productOptionId = $app->getInput()->getInt('productoption_id', 0);
        $cid             = $app->getInput()->get('cid', [], 'array');

        $cid   = ArrayHelper::toInteger($cid);
        $povId = !empty($cid) ? (int) $cid[0] : 0;

        if ($povId && $productOptionId) {
            // Reset all defaults for this product option
            $query = $db->getQuery(true);
            $query->update($db->quoteName('#__j2commerce_product_optionvalues'))
                ->set($db->quoteName('product_optionvalue_default') . ' = 0')
                ->where($db->quoteName('productoption_id') . ' = :poId')
                ->bind(':poId', $productOptionId, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();

            // Set the selected one as default
            $query = $db->getQuery(true);
            $query->update($db->quoteName('#__j2commerce_product_optionvalues'))
                ->set($db->quoteName('product_optionvalue_default') . ' = 1')
                ->where($db->quoteName('j2commerce_product_optionvalue_id') . ' = :povId')
                ->bind(':povId', $povId, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();
        }

        echo json_encode(['success' => true]);
        $app->close();
    }

    /**
     * Add a flexivariable variant via AJAX.
     *
     * Creates a new variant with the specified option value combinations.
     * Returns JSON with the new variant HTML for DOM injection.
     *
     * All variant fields are initialized with proper defaults:
     * - Empty strings for varchar fields (upc, params)
     * - "0.00000" for decimal fields (price, dimensions, qty limits)
     * - Default weight/length class IDs from config
     * - Current timestamp and user for created/modified fields
     * - 0 for integer boolean fields (manage_stock, use_store_config_*, availability)
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function addVariantAjax(): void
    {
        $app = Factory::getApplication();

        $response = [
            'success'    => false,
            'message'    => '',
            'variant_id' => 0,
            'html'       => '',
            'total'      => 0,
        ];

        // CSRF token check - return JSON error instead of redirect
        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            $response['message'] = Text::_('JINVALID_TOKEN');
            echo json_encode($response);
            $app->close();
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        // Use raw input to preserve numeric values, then sanitize manually
        // The 'array' filter can sometimes have issues with numeric values
        $rawInput      = $app->getInput()->post->getArray();
        $variantCombin = isset($rawInput['variant_combin']) && \is_array($rawInput['variant_combin'])
            ? $rawInput['variant_combin']
            : [];

        $productId  = $app->getInput()->getInt('flexi_product_id', 0);
        $formPrefix = $app->getInput()->getString('form_prefix', 'jform[attribs][j2commerce]');

        if (empty($variantCombin) || empty($productId)) {
            $response['message'] = Text::_('COM_J2COMMERCE_INVALID_DATA');
            echo json_encode($response);
            $app->close();
            return;
        }

        try {
            $productOptionvalueIds = [];

            // Create product option values for the variant
            foreach ($variantCombin as $variantKey => $variantValue) {
                $productOptionvalue = new \stdClass();
                // Sanitize keys and values as integers
                $productOptionvalue->productoption_id                  = (int) $variantKey;
                $productOptionvalue->optionvalue_id                    = (int) $variantValue;
                $productOptionvalue->parent_optionvalue                = '';
                $productOptionvalue->product_optionvalue_price         = 0;
                $productOptionvalue->product_optionvalue_prefix        = '+';
                $productOptionvalue->product_optionvalue_weight        = 0;
                $productOptionvalue->product_optionvalue_weight_prefix = '+';
                $productOptionvalue->product_optionvalue_sku           = '';
                $productOptionvalue->product_optionvalue_default       = 0;
                $productOptionvalue->ordering                          = 0;
                $productOptionvalue->product_optionvalue_attribs       = '{}';

                $db->insertObject('#__j2commerce_product_optionvalues', $productOptionvalue, 'j2commerce_product_optionvalue_id');
                $productOptionvalueIds[] = $productOptionvalue->j2commerce_product_optionvalue_id;
            }

            if (!empty($productOptionvalueIds)) {
                // Get config defaults for weight and length class IDs
                $defaultWeightClassId = ConfigHelper::getDefaultWeightClassId();
                $defaultLengthClassId = ConfigHelper::getDefaultLengthClassId();

                // Get current timestamp and user
                $date   = Factory::getDate()->toSql();
                $user   = $app->getIdentity();
                $userId = $user ? $user->id : 0;

                // Create variant record with all fields properly defaulted
                $variant             = new \stdClass();
                $variant->product_id = $productId;
                $variant->is_master  = 0;

                // Varchar fields - empty strings (not null)
                $variant->sku                = '';
                $variant->upc                = '';
                $variant->params             = '{"variant_main_image":""}';
                $variant->pricing_calculator = 'standard';

                // Decimal fields - "0.00000" (not null)
                $variant->price        = '0.00000';
                $variant->length       = '0.00000';
                $variant->width        = '0.00000';
                $variant->height       = '0.00000';
                $variant->weight       = '0.00000';
                $variant->min_sale_qty = '0.00000';
                $variant->max_sale_qty = '0.00000';
                $variant->notify_qty   = '0.00000';

                // Integer fields with defaults from config
                $variant->length_class_id = $defaultLengthClassId;
                $variant->weight_class_id = $defaultWeightClassId;

                // Integer boolean fields - 0 (not null)
                $variant->shipping                      = 0;
                $variant->manage_stock                  = 0;
                $variant->quantity_restriction          = 0;
                $variant->use_store_config_min_sale_qty = 0;
                $variant->use_store_config_max_sale_qty = 0;
                $variant->use_store_config_notify_qty   = 0;
                $variant->availability                  = 0;
                $variant->allow_backorder               = 0;
                $variant->isdefault_variant             = 0;

                // Timestamp fields
                $variant->created_on  = $date;
                $variant->created_by  = $userId;
                $variant->modified_on = $date;
                $variant->modified_by = $userId;

                $db->insertObject('#__j2commerce_variants', $variant, 'j2commerce_variant_id');

                // Update SKU with variant ID (Flexi_[id] format)
                $variantId = $variant->j2commerce_variant_id;
                $newSku    = 'Flexi_' . $variantId;

                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_variants'))
                    ->set($db->quoteName('sku') . ' = :sku')
                    ->where($db->quoteName('j2commerce_variant_id') . ' = :variantId')
                    ->bind(':sku', $newSku)
                    ->bind(':variantId', $variantId, ParameterType::INTEGER);

                $db->setQuery($query);
                $db->execute();

                // Create product quantity record
                $productQuantity                     = new \stdClass();
                $productQuantity->variant_id         = $variantId;
                $productQuantity->quantity           = 0;
                $productQuantity->on_hold            = 0;
                $productQuantity->sold               = 0;
                $productQuantity->product_attributes = '';

                $db->insertObject('#__j2commerce_productquantities', $productQuantity, 'j2commerce_productquantity_id');

                // Create product variant optionvalues mapping
                $productVariantOptionValue                          = new \stdClass();
                $productVariantOptionValue->variant_id              = $variantId;
                $productVariantOptionValue->product_optionvalue_ids = implode(',', $productOptionvalueIds);

                $db->insertObject('#__j2commerce_product_variant_optionvalues', $productVariantOptionValue);

                // Get total variants count (non-master)
                // Note: We don't render individual HTML here since JS reloads the full list
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_variants'))
                    ->where($db->quoteName('product_id') . ' = :productId')
                    ->where($db->quoteName('is_master') . ' = 0')
                    ->bind(':productId', $productId, ParameterType::INTEGER);

                $db->setQuery($query);
                $total = (int) $db->loadResult();

                $response['success']    = true;
                $response['message']    = Text::_('COM_J2COMMERCE_VARIANT_ADDED');
                $response['variant_id'] = $variantId;
                $response['html']       = ''; // JS reloads full list via loadVariantList()
                $response['total']      = $total;
            }
        } catch (\Exception $e) {
            $response['message'] = $e->getMessage();
        }

        echo json_encode($response);
        $app->close();
    }

    /**
     * Delete a single variant via AJAX.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function deleteVariantAjax(): void
    {
        $app = Factory::getApplication();

        $response = [
            'success' => false,
            'message' => '',
            'total'   => 0,
        ];

        // CSRF token check - return JSON error instead of redirect
        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            $response['message'] = Text::_('JINVALID_TOKEN');
            echo json_encode($response);
            $app->close();
            return;
        }

        $variantId = $app->getInput()->getInt('variant_id', 0);
        $productId = $app->getInput()->getInt('product_id', 0);

        if ($variantId > 0 && $this->deleteSingleVariant($variantId)) {
            $response['success'] = true;
            $response['message'] = Text::_('COM_J2COMMERCE_VARIANT_DELETED');

            // Get remaining total
            if ($productId > 0) {
                $db    = Factory::getContainer()->get('DatabaseDriver');
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_variants'))
                    ->where($db->quoteName('product_id') . ' = :productId')
                    ->where($db->quoteName('is_master') . ' = 0')
                    ->bind(':productId', $productId, ParameterType::INTEGER);

                $db->setQuery($query);
                $response['total'] = (int) $db->loadResult();
            }
        } else {
            $response['message'] = Text::_('COM_J2COMMERCE_VARIANT_DELETE_ERROR');
        }

        echo json_encode($response);
        $app->close();
    }

    /**
     * Delete all non-master variants for a product via AJAX.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function deleteAllVariantsAjax(): void
    {
        $app = Factory::getApplication();

        $response = [
            'success'       => false,
            'message'       => '',
            'deleted_count' => 0,
        ];

        // CSRF token check - return JSON error instead of redirect
        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            $response['message'] = Text::_('JINVALID_TOKEN');
            echo json_encode($response);
            $app->close();
            return;
        }

        $db        = Factory::getContainer()->get('DatabaseDriver');
        $productId = $app->getInput()->getInt('product_id', 0);

        if ($productId > 0) {
            // Get all non-master variant IDs
            $query = $db->getQuery(true)
                ->select($db->quoteName('j2commerce_variant_id'))
                ->from($db->quoteName('#__j2commerce_variants'))
                ->where($db->quoteName('product_id') . ' = :productId')
                ->where($db->quoteName('is_master') . ' = 0')
                ->bind(':productId', $productId, ParameterType::INTEGER);

            $db->setQuery($query);
            $variants = $db->loadColumn();

            $deletedCount = 0;
            foreach ($variants as $variantId) {
                if ($this->deleteSingleVariant((int) $variantId)) {
                    $deletedCount++;
                }
            }

            $response['success']       = true;
            $response['message']       = Text::plural('COM_J2COMMERCE_N_VARIANTS_DELETED', $deletedCount);
            $response['deleted_count'] = $deletedCount;
        } else {
            $response['message'] = Text::_('COM_J2COMMERCE_INVALID_PRODUCT');
        }

        echo json_encode($response);
        $app->close();
    }

    /**
     * Delete selected variants via AJAX.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function deleteSelectedVariantsAjax(): void
    {
        $app = Factory::getApplication();

        $response = [
            'success'       => false,
            'message'       => '',
            'deleted_count' => 0,
            'total'         => 0,
        ];

        // CSRF token check - return JSON error instead of redirect
        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            $response['message'] = Text::_('JINVALID_TOKEN');
            echo json_encode($response);
            $app->close();
            return;
        }

        $variantIds = $app->getInput()->get('variant_ids', [], 'array');
        $productId  = $app->getInput()->getInt('product_id', 0);

        $variantIds = ArrayHelper::toInteger($variantIds);
        $variantIds = array_filter($variantIds);

        if (!empty($variantIds)) {
            $deletedCount = 0;
            foreach ($variantIds as $variantId) {
                if ($this->deleteSingleVariant((int) $variantId)) {
                    $deletedCount++;
                }
            }

            $response['success']       = true;
            $response['message']       = Text::plural('COM_J2COMMERCE_N_VARIANTS_DELETED', $deletedCount);
            $response['deleted_count'] = $deletedCount;

            // Get remaining total
            if ($productId > 0) {
                $db    = Factory::getContainer()->get('DatabaseDriver');
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_variants'))
                    ->where($db->quoteName('product_id') . ' = :productId')
                    ->where($db->quoteName('is_master') . ' = 0')
                    ->bind(':productId', $productId, ParameterType::INTEGER);

                $db->setQuery($query);
                $response['total'] = (int) $db->loadResult();
            }
        } else {
            $response['message'] = Text::_('COM_J2COMMERCE_NO_ITEM_SELECTED');
        }

        echo json_encode($response);
        $app->close();
    }

    public function generateVariantsAjax(): void
    {
        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        header('Content-Type: application/json');

        $response = ['success' => false, 'message' => '', 'total' => 0];

        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            $response['message'] = Text::_('JINVALID_TOKEN');
            echo json_encode($response);
            $app->close();
            return;
        }

        if (!$app->getIdentity()->authorise('core.edit', 'com_j2commerce')) {
            $response['message'] = Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN');
            echo json_encode($response);
            $app->close();
            return;
        }

        $productId = $app->getInput()->getInt('product_id', 0);

        if (!$productId) {
            $response['message'] = Text::_('COM_J2COMMERCE_ERROR_NO_PRODUCT_SELECTED');
            echo json_encode($response);
            $app->close();
            return;
        }

        $traits = ProductHelper::getTraits($productId);

        if (empty($traits)) {
            $response['message'] = Text::_('COM_J2COMMERCE_ERROR_NO_TRAITS_DEFINED');
            echo json_encode($response);
            $app->close();
            return;
        }

        // Build option arrays using product_optionvalue_id (for mapping table)
        $optionArrays = [];
        foreach ($traits as $trait) {
            if (!empty($trait->values)) {
                $values = [];
                foreach ($trait->values as $value) {
                    $values[] = [
                        'option_id'              => $trait->option_id,
                        'product_optionvalue_id' => $value->j2commerce_product_optionvalue_id,
                        'optionvalue_name'       => $value->optionvalue_name ?? '',
                        'sku_suffix'             => $value->product_optionvalue_sku ?? '',
                        'price_prefix'           => $value->product_optionvalue_prefix ?? '+',
                        'price'                  => $value->product_optionvalue_price ?? 0,
                    ];
                }
                $optionArrays[] = $values;
            }
        }

        if (empty($optionArrays)) {
            $response['message'] = Text::_('COM_J2COMMERCE_ERROR_NO_OPTION_VALUES');
            echo json_encode($response);
            $app->close();
            return;
        }

        $combinations         = ProductHelper::getCombinations($optionArrays);
        $masterVariant        = ProductHelper::getMasterVariant($productId);
        $baseSku              = $masterVariant->sku ?? 'PROD-' . $productId;
        $basePrice            = (float) ($masterVariant->price ?? 0);
        $date                 = \Joomla\CMS\Factory::getDate()->toSql();
        $defaultWeightClassId = ConfigHelper::getDefaultWeightClassId();
        $defaultLengthClassId = ConfigHelper::getDefaultLengthClassId();

        // Load existing mapping CSVs for duplicate detection
        $query = $db->getQuery(true)
            ->select($db->quoteName('product_optionvalue_ids'))
            ->from($db->quoteName('#__j2commerce_product_variant_optionvalues', 'pvo'))
            ->join('INNER', $db->quoteName('#__j2commerce_variants', 'v')
                . ' ON ' . $db->quoteName('v.j2commerce_variant_id') . ' = ' . $db->quoteName('pvo.variant_id'))
            ->where($db->quoteName('v.product_id') . ' = :pid')
            ->where($db->quoteName('v.is_master') . ' = 0')
            ->bind(':pid', $productId, ParameterType::INTEGER);
        $db->setQuery($query);
        $existingCsvs = $db->loadColumn() ?: [];

        // Normalize existing CSVs for comparison
        $existingNormalized = [];
        foreach ($existingCsvs as $csv) {
            $ids = explode(',', $csv);
            sort($ids);
            $existingNormalized[] = implode(',', $ids);
        }

        // Check if any existing variant is already set as default
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :piddef')
            ->where($db->quoteName('is_master') . ' = 0')
            ->where($db->quoteName('isdefault_variant') . ' = 1')
            ->bind(':piddef', $productId, ParameterType::INTEGER);
        $db->setQuery($query);
        $hasDefault = (int) $db->loadResult() > 0;

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($combinations as $combination) {
            $skuParts        = [];
            $priceAdjustment = 0.0;
            $povIds          = [];

            foreach ($combination as $optionValue) {
                if (!empty($optionValue['sku_suffix'])) {
                    $skuParts[] = $optionValue['sku_suffix'];
                }

                $price = (float) $optionValue['price'];
                $priceAdjustment += ($optionValue['price_prefix'] === '-') ? -$price : $price;

                $povIds[] = (int) $optionValue['product_optionvalue_id'];
            }

            // Skip combinations with missing/invalid option value IDs
            $povIds = array_filter($povIds, static fn (int $id): bool => $id > 0);

            if (empty($povIds)) {
                continue;
            }

            sort($povIds);
            $povIdsCsv = implode(',', $povIds);

            // Check for existing variant with same option combination
            if (\in_array($povIdsCsv, $existingNormalized, true)) {
                $skippedCount++;
                continue;
            }

            $variantSku = $baseSku . (!empty($skuParts) ? '-' . implode('-', $skuParts) : '-V' . ($createdCount + 1));

            $variantData = (object) [
                'product_id'                    => $productId,
                'sku'                           => $variantSku,
                'upc'                           => '',
                'price'                         => $basePrice + $priceAdjustment,
                'pricing_calculator'            => 'standard',
                'shipping'                      => 0,
                'length'                        => 0,
                'width'                         => 0,
                'height'                        => 0,
                'length_class_id'               => $defaultLengthClassId,
                'weight'                        => 0,
                'weight_class_id'               => $defaultWeightClassId,
                'manage_stock'                  => 0,
                'quantity_restriction'          => 0,
                'min_out_qty'                   => 0,
                'use_store_config_min_out_qty'  => 1,
                'min_sale_qty'                  => 0,
                'use_store_config_min_sale_qty' => 1,
                'max_sale_qty'                  => 0,
                'use_store_config_max_sale_qty' => 1,
                'notify_qty'                    => 0,
                'use_store_config_notify_qty'   => 1,
                'availability'                  => 1,
                'sold'                          => 0,
                'allow_backorder'               => 0,
                'isdefault_variant'             => (!$hasDefault && $createdCount === 0) ? 1 : 0,
                'is_master'                     => 0,
                'created_on'                    => $date,
                'modified_on'                   => $date,
            ];

            try {
                $db->insertObject('#__j2commerce_variants', $variantData, 'j2commerce_variant_id');
                $variantId = (int) $variantData->j2commerce_variant_id;

                // Insert into mapping table
                $mapping = (object) [
                    'variant_id'              => $variantId,
                    'product_optionvalue_ids' => $povIdsCsv,
                ];
                $db->insertObject('#__j2commerce_product_variant_optionvalues', $mapping);

                $existingNormalized[] = $povIdsCsv;
                $createdCount++;
            } catch (\Throwable $e) {
                $skippedCount++;
            }
        }

        // Count total variants
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :pid2')
            ->where($db->quoteName('is_master') . ' = 0')
            ->bind(':pid2', $productId, ParameterType::INTEGER);
        $db->setQuery($query);
        $total = (int) $db->loadResult();

        $response['success'] = true;
        $response['total']   = $total;
        $response['message'] = Text::sprintf('COM_J2COMMERCE_VARIANTS_CREATED', $createdCount);

        if ($skippedCount > 0) {
            $response['message'] .= ' ' . Text::sprintf('COM_J2COMMERCE_VARIANTS_SKIPPED', $skippedCount);
        }

        echo json_encode($response);
        $app->close();
    }

    public function regenerateVariantsAjax(): void
    {
        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        header('Content-Type: application/json');

        $response = ['success' => false, 'message' => '', 'total' => 0];

        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            $response['message'] = Text::_('JINVALID_TOKEN');
            echo json_encode($response);
            $app->close();
            return;
        }

        if (!$app->getIdentity()->authorise('core.edit', 'com_j2commerce')) {
            $response['message'] = Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN');
            echo json_encode($response);
            $app->close();
            return;
        }

        $productId = $app->getInput()->getInt('product_id', 0);

        if (!$productId) {
            $response['message'] = Text::_('COM_J2COMMERCE_ERROR_NO_PRODUCT_SELECTED');
            echo json_encode($response);
            $app->close();
            return;
        }

        // Get existing non-master variant IDs for this product
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_variant_id'))
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :pid')
            ->where($db->quoteName('is_master') . ' = 0')
            ->bind(':pid', $productId, ParameterType::INTEGER);
        $db->setQuery($query);
        $variantIds = $db->loadColumn() ?: [];

        // Delete mapping entries for these variants
        if (!empty($variantIds)) {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_product_variant_optionvalues'))
                ->whereIn($db->quoteName('variant_id'), ArrayHelper::toInteger($variantIds));
            $db->setQuery($query);
            $db->execute();
        }

        // Delete all existing non-master variants
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :pid2')
            ->where($db->quoteName('is_master') . ' = 0')
            ->bind(':pid2', $productId, ParameterType::INTEGER);
        $db->setQuery($query);
        $db->execute();

        // Rebuild from traits
        $traits = ProductHelper::getTraits($productId);

        if (empty($traits)) {
            $response['success'] = true;
            $response['message'] = Text::_('COM_J2COMMERCE_ERROR_NO_TRAITS_DEFINED');
            echo json_encode($response);
            $app->close();
            return;
        }

        $optionArrays = [];
        foreach ($traits as $trait) {
            if (!empty($trait->values)) {
                $values = [];
                foreach ($trait->values as $value) {
                    $values[] = [
                        'option_id'              => $trait->option_id,
                        'product_optionvalue_id' => $value->j2commerce_product_optionvalue_id,
                        'optionvalue_name'       => $value->optionvalue_name ?? '',
                        'sku_suffix'             => $value->product_optionvalue_sku ?? '',
                        'price_prefix'           => $value->product_optionvalue_prefix ?? '+',
                        'price'                  => $value->product_optionvalue_price ?? 0,
                    ];
                }
                $optionArrays[] = $values;
            }
        }

        if (empty($optionArrays)) {
            $response['success'] = true;
            $response['message'] = Text::_('COM_J2COMMERCE_ERROR_NO_OPTION_VALUES');
            echo json_encode($response);
            $app->close();
            return;
        }

        $combinations         = ProductHelper::getCombinations($optionArrays);
        $masterVariant        = ProductHelper::getMasterVariant($productId);
        $baseSku              = $masterVariant->sku ?? 'PROD-' . $productId;
        $basePrice            = (float) ($masterVariant->price ?? 0);
        $date                 = \Joomla\CMS\Factory::getDate()->toSql();
        $defaultWeightClassId = ConfigHelper::getDefaultWeightClassId();
        $defaultLengthClassId = ConfigHelper::getDefaultLengthClassId();

        $createdCount = 0;

        foreach ($combinations as $combination) {
            $skuParts        = [];
            $priceAdjustment = 0.0;
            $povIds          = [];

            foreach ($combination as $optionValue) {
                if (!empty($optionValue['sku_suffix'])) {
                    $skuParts[] = $optionValue['sku_suffix'];
                }

                $price = (float) $optionValue['price'];
                $priceAdjustment += ($optionValue['price_prefix'] === '-') ? -$price : $price;

                $povIds[] = (int) $optionValue['product_optionvalue_id'];
            }

            // Skip combinations with missing/invalid option value IDs
            $povIds = array_filter($povIds, static fn (int $id): bool => $id > 0);

            if (empty($povIds)) {
                continue;
            }

            sort($povIds);
            $povIdsCsv  = implode(',', $povIds);
            $variantSku = $baseSku . (!empty($skuParts) ? '-' . implode('-', $skuParts) : '-V' . ($createdCount + 1));

            $variantData = (object) [
                'product_id'                    => $productId,
                'sku'                           => $variantSku,
                'upc'                           => '',
                'price'                         => $basePrice + $priceAdjustment,
                'pricing_calculator'            => 'standard',
                'shipping'                      => 0,
                'length'                        => 0,
                'width'                         => 0,
                'height'                        => 0,
                'length_class_id'               => $defaultLengthClassId,
                'weight'                        => 0,
                'weight_class_id'               => $defaultWeightClassId,
                'manage_stock'                  => 0,
                'quantity_restriction'          => 0,
                'min_out_qty'                   => 0,
                'use_store_config_min_out_qty'  => 1,
                'min_sale_qty'                  => 0,
                'use_store_config_min_sale_qty' => 1,
                'max_sale_qty'                  => 0,
                'use_store_config_max_sale_qty' => 1,
                'notify_qty'                    => 0,
                'use_store_config_notify_qty'   => 1,
                'availability'                  => 1,
                'sold'                          => 0,
                'allow_backorder'               => 0,
                'isdefault_variant'             => ($createdCount === 0) ? 1 : 0,
                'is_master'                     => 0,
                'created_on'                    => $date,
                'modified_on'                   => $date,
            ];

            try {
                $db->insertObject('#__j2commerce_variants', $variantData, 'j2commerce_variant_id');
                $variantId = (int) $variantData->j2commerce_variant_id;

                $mapping = (object) [
                    'variant_id'              => $variantId,
                    'product_optionvalue_ids' => $povIdsCsv,
                ];
                $db->insertObject('#__j2commerce_product_variant_optionvalues', $mapping);

                $createdCount++;
            } catch (\Throwable $e) {
                // Skip on error
            }
        }

        // Count total
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :pid3')
            ->where($db->quoteName('is_master') . ' = 0')
            ->bind(':pid3', $productId, ParameterType::INTEGER);
        $db->setQuery($query);
        $total = (int) $db->loadResult();

        $response['success'] = true;
        $response['total']   = $total;
        $response['message'] = Text::sprintf('COM_J2COMMERCE_VARIANTS_REGENERATED_COUNT', $createdCount);

        echo json_encode($response);
        $app->close();
    }

    /**
     * Get variant list HTML via AJAX (for pagination).
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function getVariantListAjax(): void
    {
        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        $productId  = $app->getInput()->getInt('product_id', 0);
        $limitstart = $app->getInput()->getInt('limitstart', 0);
        $limit      = $app->getInput()->getInt('limit', 20);
        $formPrefix = $app->getInput()->getString('form_prefix', 'jform[attribs][j2commerce]');

        $response = ['html' => '', 'total' => 0];

        if ($productId > 0) {
            // Get product with full data using ProductHelper
            $product = ProductHelper::getFullProduct($productId);

            if ($product) {

                // Get paginated variants directly with limit/offset
                // Include weight/length class titles and units
                // Also include product_optionvalue_ids for variant name resolution
                $query = $db->getQuery(true);
                $query->select($db->quoteName('v') . '.*')
                    ->select([
                        $db->quoteName('pq.j2commerce_productquantity_id'),
                        $db->quoteName('pq.quantity'),
                        $db->quoteName('pq.on_hold'),
                        $db->quoteName('pq.sold', 'qty_sold'),
                        $db->quoteName('w.weight_title', 'weight_title'),
                        $db->quoteName('w.weight_unit', 'weight_unit'),
                        $db->quoteName('l.length_title', 'length_title'),
                        $db->quoteName('l.length_unit', 'length_unit'),
                        $db->quoteName('pvo.product_optionvalue_ids'),
                    ])
                    ->from($db->quoteName('#__j2commerce_variants', 'v'))
                    ->join(
                        'LEFT',
                        $db->quoteName('#__j2commerce_productquantities', 'pq'),
                        $db->quoteName('pq.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id')
                    )
                    ->join(
                        'LEFT',
                        $db->quoteName('#__j2commerce_weights', 'w'),
                        $db->quoteName('v.weight_class_id') . ' = ' . $db->quoteName('w.j2commerce_weight_id')
                    )
                    ->join(
                        'LEFT',
                        $db->quoteName('#__j2commerce_lengths', 'l'),
                        $db->quoteName('v.length_class_id') . ' = ' . $db->quoteName('l.j2commerce_length_id')
                    )
                    ->join(
                        'LEFT',
                        $db->quoteName('#__j2commerce_product_variant_optionvalues', 'pvo'),
                        $db->quoteName('pvo.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id')
                    )
                    ->where($db->quoteName('v.product_id') . ' = :productId')
                    ->where($db->quoteName('v.is_master') . ' = 0')
                    ->order($db->quoteName('v.j2commerce_variant_id') . ' ASC')
                    ->bind(':productId', $productId, ParameterType::INTEGER);

                $db->setQuery($query, $limitstart, $limit);
                $variants = $db->loadObjectList() ?: [];

                // Add variant names - store CSV IDs as variant_name_ids, human-readable as variant_name
                // This matches the pattern used in ProductHelper::getVariantsByProductId()
                foreach ($variants as $variant) {
                    // Preserve original CSV IDs for template processing
                    $variant->variant_name_ids = $variant->product_optionvalue_ids ?? '';
                    // Convert to human-readable names using the controller's method
                    $variant->variant_name = $this->getVariantName($variant->j2commerce_variant_id);
                }

                $product->variants = $variants;

                // Get weights and lengths for dropdowns
                $product->weights = $this->getWeightsList();
                $product->lengths = $this->getLengthsList();

                // Use the layout specified by the caller (variable vs flexivariable product type)
                $allowedLayouts = ['form_ajax_avoptions', 'form_ajax_flexivariableoptions'];
                $variantLayout  = $app->getInput()->getCmd('variant_layout', 'form_ajax_avoptions');
                if (!\in_array($variantLayout, $allowedLayouts, true)) {
                    $variantLayout = 'form_ajax_avoptions';
                }

                $layout           = new FileLayout($variantLayout, JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');
                $response['html'] = $layout->render([
                    'product'            => $product,
                    'variant_list'       => $variants,
                    'variant_pagination' => null,
                    'weights'            => $product->weights,
                    'lengths'            => $product->lengths,
                    'form_prefix'        => $formPrefix,
                ]);

                // Get total count
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_variants'))
                    ->where($db->quoteName('product_id') . ' = :productId')
                    ->where($db->quoteName('is_master') . ' = 0')
                    ->bind(':productId', $productId, ParameterType::INTEGER);

                $db->setQuery($query);
                $response['total'] = (int) $db->loadResult();
            }
        }

        echo json_encode($response);
        $app->close();
    }

    /**
     * Get variant name from option values.
     *
     * @param   int  $variantId  The variant ID.
     *
     * @return  string  The variant name.
     *
     * @since   6.0.3
     */
    protected function getVariantName(int $variantId): string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);
        $query->select($db->quoteName('product_optionvalue_ids'))
            ->from($db->quoteName('#__j2commerce_product_variant_optionvalues'))
            ->where($db->quoteName('variant_id') . ' = :variantId')
            ->bind(':variantId', $variantId, ParameterType::INTEGER);

        $db->setQuery($query);
        $optionValueIds = $db->loadResult();

        if (empty($optionValueIds)) {
            return '';
        }

        $ids = array_map('intval', explode(',', $optionValueIds));
        $ids = array_filter($ids);

        if (empty($ids)) {
            return '';
        }

        // Get option values with their parent option names for "Any" handling
        $query = $db->getQuery(true);
        $query->select([
                $db->quoteName('pov.optionvalue_id'),
                $db->quoteName('ov.optionvalue_name'),
                $db->quoteName('o.option_name'),
            ])
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_optionvalues', 'ov') .
                ' ON ' . $db->quoteName('pov.optionvalue_id') . ' = ' . $db->quoteName('ov.j2commerce_optionvalue_id')
            )
            ->leftJoin(
                $db->quoteName('#__j2commerce_product_options', 'po') .
                ' ON ' . $db->quoteName('pov.productoption_id') . ' = ' . $db->quoteName('po.j2commerce_productoption_id')
            )
            ->leftJoin(
                $db->quoteName('#__j2commerce_options', 'o') .
                ' ON ' . $db->quoteName('po.option_id') . ' = ' . $db->quoteName('o.j2commerce_option_id')
            )
            ->whereIn($db->quoteName('pov.j2commerce_product_optionvalue_id'), $ids);

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        $names = [];
        foreach ($rows as $row) {
            if ((int) $row->optionvalue_id === 0) {
                // "Any" option selected - show "Any [Option Name]"
                $names[] = Text::_('COM_J2COMMERCE_ANY') . ' ' . ($row->option_name ?? '');
            } elseif (!empty($row->optionvalue_name)) {
                $names[] = $row->optionvalue_name;
            }
        }

        return implode(', ', $names);
    }

    /**
     * Get weights list for dropdowns.
     *
     * @return  array  Array of weight classes.
     *
     * @since   6.0.3
     */
    protected function getWeightsList(): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);
        $query->select([
                $db->quoteName('j2commerce_weight_id', 'value'),
                $db->quoteName('weight_title', 'text'),
            ])
            ->from($db->quoteName('#__j2commerce_weights'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('weight_title') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get lengths list for dropdowns.
     *
     * @return  array  Array of length classes.
     *
     * @since   6.0.3
     */
    protected function getLengthsList(): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);
        $query->select([
                $db->quoteName('j2commerce_length_id', 'value'),
                $db->quoteName('length_title', 'text'),
            ])
            ->from($db->quoteName('#__j2commerce_lengths'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('length_title') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Set a variant as the default variant for a product via AJAX.
     *
     * Sets the specified variant's isdefault_variant to 1 and resets ALL other
     * variants for the same product to 0.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function setDefaultVariantAjax(): void
    {
        $app = Factory::getApplication();

        $response = [
            'success'    => false,
            'message'    => '',
            'variant_id' => 0,
        ];

        // CSRF token check - return JSON error instead of redirect
        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            $response['message'] = Text::_('JINVALID_TOKEN');
            echo json_encode($response);
            $app->close();
            return;
        }

        $db        = Factory::getContainer()->get('DatabaseDriver');
        $variantId = $app->getInput()->getInt('variant_id', 0);
        $productId = $app->getInput()->getInt('product_id', 0);

        if ($variantId <= 0 || $productId <= 0) {
            $response['message'] = Text::_('COM_J2COMMERCE_INVALID_DATA');
            echo json_encode($response);
            $app->close();
            return;
        }

        try {
            // First, reset ALL variants for this product to isdefault_variant = 0
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_variants'))
                ->set($db->quoteName('isdefault_variant') . ' = 0')
                ->where($db->quoteName('product_id') . ' = :productId')
                ->bind(':productId', $productId, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();

            // Then, set the specified variant as default
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_variants'))
                ->set($db->quoteName('isdefault_variant') . ' = 1')
                ->where($db->quoteName('j2commerce_variant_id') . ' = :variantId')
                ->bind(':variantId', $variantId, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();

            $response['success']    = true;
            $response['message']    = Text::_('COM_J2COMMERCE_VARIANT_SET_DEFAULT_SUCCESS');
            $response['variant_id'] = $variantId;
        } catch (\Exception $e) {
            $response['message'] = $e->getMessage();
        }

        echo json_encode($response);
        $app->close();
    }

    /**
     * Unset a variant as the default variant via AJAX.
     *
     * Sets the specified variant's isdefault_variant to 0.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function unsetDefaultVariantAjax(): void
    {
        $app = Factory::getApplication();

        $response = [
            'success'    => false,
            'message'    => '',
            'variant_id' => 0,
        ];

        // CSRF token check - return JSON error instead of redirect
        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            $response['message'] = Text::_('JINVALID_TOKEN');
            echo json_encode($response);
            $app->close();
            return;
        }

        $db        = Factory::getContainer()->get('DatabaseDriver');
        $variantId = $app->getInput()->getInt('variant_id', 0);

        if ($variantId <= 0) {
            $response['message'] = Text::_('COM_J2COMMERCE_INVALID_DATA');
            echo json_encode($response);
            $app->close();
            return;
        }

        try {
            // Set the specified variant's isdefault_variant to 0
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_variants'))
                ->set($db->quoteName('isdefault_variant') . ' = 0')
                ->where($db->quoteName('j2commerce_variant_id') . ' = :variantId')
                ->bind(':variantId', $variantId, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();

            $response['success']    = true;
            $response['message']    = Text::_('COM_J2COMMERCE_VARIANT_UNSET_DEFAULT_SUCCESS');
            $response['variant_id'] = $variantId;
        } catch (\Exception $e) {
            $response['message'] = $e->getMessage();
        }

        echo json_encode($response);
        $app->close();
    }

    /**
     * Delete a single variant and all related data.
     *
     * @param   int  $variantId  The variant ID to delete.
     *
     * @return  bool  True on success.
     *
     * @since   6.0.3
     */
    protected function deleteSingleVariant(int $variantId): bool
    {
        if ($variantId <= 0) {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            // Delete inventory
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_productquantities'))
                ->where($db->quoteName('variant_id') . ' = :variantId')
                ->bind(':variantId', $variantId, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();

            // Delete prices
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_product_prices'))
                ->where($db->quoteName('variant_id') . ' = :variantId')
                ->bind(':variantId', $variantId, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();

            // Get product optionvalue IDs
            $query = $db->getQuery(true)
                ->select($db->quoteName('product_optionvalue_ids'))
                ->from($db->quoteName('#__j2commerce_product_variant_optionvalues'))
                ->where($db->quoteName('variant_id') . ' = :variantId')
                ->bind(':variantId', $variantId, ParameterType::INTEGER);

            $db->setQuery($query);
            $productOptionvalueIds = $db->loadResult();

            if (!empty($productOptionvalueIds)) {
                // Delete product optionvalues
                $ids = array_map('intval', explode(',', $productOptionvalueIds));
                $ids = array_filter($ids);

                if (!empty($ids)) {
                    $query = $db->getQuery(true)
                        ->delete($db->quoteName('#__j2commerce_product_optionvalues'))
                        ->whereIn($db->quoteName('j2commerce_product_optionvalue_id'), $ids);

                    $db->setQuery($query);
                    $db->execute();
                }
            }

            // Delete variant optionvalues mapping
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_product_variant_optionvalues'))
                ->where($db->quoteName('variant_id') . ' = :variantId')
                ->bind(':variantId', $variantId, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();

            // Delete variant
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_variants'))
                ->where($db->quoteName('j2commerce_variant_id') . ' = :variantId')
                ->bind(':variantId', $variantId, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();

            return true;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Render HTML for a single variant item.
     *
     * @param   int     $variantId   The variant ID.
     * @param   int     $productId   The product ID.
     * @param   string  $formPrefix  The form prefix.
     *
     * @return  string  The rendered HTML.
     *
     * @since   6.0.3
     */
    protected function renderVariantHtml(int $variantId, int $productId, string $formPrefix): string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Get the variant data with all related info
        $query = $db->getQuery(true);
        $query->select('v.*')
            ->select($db->quoteName('pq.j2commerce_productquantity_id'))
            ->select($db->quoteName('pq.quantity'))
            ->from($db->quoteName('#__j2commerce_variants', 'v'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_productquantities', 'pq') .
                ' ON ' . $db->quoteName('v.j2commerce_variant_id') . ' = ' . $db->quoteName('pq.variant_id')
            )
            ->where($db->quoteName('v.j2commerce_variant_id') . ' = :variantId')
            ->bind(':variantId', $variantId, ParameterType::INTEGER);

        $db->setQuery($query);
        $variant = $db->loadObject();

        if (!$variant) {
            return '';
        }

        // Get variant name from option values
        $query = $db->getQuery(true);
        $query->select($db->quoteName('product_optionvalue_ids'))
            ->from($db->quoteName('#__j2commerce_product_variant_optionvalues'))
            ->where($db->quoteName('variant_id') . ' = :variantId')
            ->bind(':variantId', $variantId, ParameterType::INTEGER);

        $db->setQuery($query);
        $optionValueIds = $db->loadResult();

        $variantName = '';
        if (!empty($optionValueIds)) {
            $ids = array_map('intval', explode(',', $optionValueIds));
            $ids = array_filter($ids);

            if (!empty($ids)) {
                $query = $db->getQuery(true);
                $query->select($db->quoteName('ov.optionvalue_name'))
                    ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
                    ->leftJoin(
                        $db->quoteName('#__j2commerce_optionvalues', 'ov') .
                        ' ON ' . $db->quoteName('pov.optionvalue_id') . ' = ' . $db->quoteName('ov.j2commerce_optionvalue_id')
                    )
                    ->whereIn($db->quoteName('pov.j2commerce_product_optionvalue_id'), $ids);

                $db->setQuery($query);
                $names       = $db->loadColumn();
                $variantName = implode(',', $names);
            }
        }

        $variant->variant_name = $variantName;
        $variant->params       = $variant->params ?? '{}';
        $variant->product_id   = $productId;

        // Get product for layout using ProductHelper
        $product = ProductHelper::getFullProduct($productId);
        if ($product) {
            $product->weights = $this->getWeightsList();
            $product->lengths = $this->getLengthsList();
        }

        // Render the single variant item
        $layout = new FileLayout('form_ajax_flexivariableoptions_item', JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');

        return $layout->render([
            'product'     => $product,
            'variant'     => $variant,
            'weights'     => $product->weights,
            'lengths'     => $product->lengths,
            'form_prefix' => $formPrefix,
        ]);
    }

    /** Resolve parent option values — parent_id stores a global option_id, not a productoption_id */
    private function resolveParentOptionValues(object $productOption): array
    {
        if (empty($productOption->parent_id)) {
            return [];
        }

        $parentOptionId  = (int) $productOption->parent_id;
        $parentProductId = (int) $productOption->product_id;
        $db              = Factory::getContainer()->get('DatabaseDriver');
        $query           = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_productoption_id'))
            ->from($db->quoteName('#__j2commerce_product_options'))
            ->where($db->quoteName('option_id') . ' = :parentOptId')
            ->where($db->quoteName('product_id') . ' = :parentProdId')
            ->bind(':parentOptId', $parentOptionId, ParameterType::INTEGER)
            ->bind(':parentProdId', $parentProductId, ParameterType::INTEGER);
        $db->setQuery($query);
        $parentProductOptionId = (int) $db->loadResult();

        if ($parentProductOptionId > 0) {
            return ProductHelper::getProductOptionValues($parentProductOptionId);
        }

        return [];
    }

    public function addProductOptionAjax(): void
    {
        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        header('Content-Type: application/json');

        $response = ['success' => false, 'message' => ''];

        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            $response['message'] = Text::_('JINVALID_TOKEN');
            echo json_encode($response);
            $app->close();
            return;
        }

        if (!$app->getIdentity()->authorise('core.edit', 'com_j2commerce')) {
            $response['message'] = Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN');
            echo json_encode($response);
            $app->close();
            return;
        }

        $productId = $app->getInput()->getInt('product_id', 0);
        $optionId  = $app->getInput()->getInt('option_id', 0);

        if (!$productId || !$optionId) {
            $response['message'] = Text::_('COM_J2COMMERCE_INVALID_PRODUCT_OR_OPTION');
            echo json_encode($response);
            $app->close();
            return;
        }

        // Check duplicate
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_product_options'))
            ->where($db->quoteName('product_id') . ' = :pid')
            ->where($db->quoteName('option_id') . ' = :oid')
            ->bind(':pid', $productId, ParameterType::INTEGER)
            ->bind(':oid', $optionId, ParameterType::INTEGER);
        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            $response['message'] = Text::_('COM_J2COMMERCE_OPTION_ALREADY_ADDED');
            echo json_encode($response);
            $app->close();
            return;
        }

        // Get next ordering
        $query = $db->getQuery(true)
            ->select('COALESCE(MAX(' . $db->quoteName('ordering') . '), 0) + 1')
            ->from($db->quoteName('#__j2commerce_product_options'))
            ->where($db->quoteName('product_id') . ' = :pid2')
            ->bind(':pid2', $productId, ParameterType::INTEGER);
        $db->setQuery($query);
        $nextOrdering = (int) $db->loadResult();

        // Insert
        $poTable             = $this->factory->createTable('Productoption', 'Administrator');
        $poTable->option_id  = $optionId;
        $poTable->product_id = $productId;
        $poTable->ordering   = $nextOrdering;
        $poTable->required   = 0;
        $poTable->is_variant = 1;
        $poTable->parent_id  = 0;

        try {
            $poTable->store();
        } catch (\Throwable $e) {
            $response['message'] = $e->getMessage();
            echo json_encode($response);
            $app->close();
            return;
        }

        // Load option metadata
        $query = $db->getQuery(true)
            ->select($db->quoteName(['option_name', 'option_unique_name', 'type']))
            ->from($db->quoteName('#__j2commerce_options'))
            ->where($db->quoteName('j2commerce_option_id') . ' = :oid2')
            ->bind(':oid2', $optionId, ParameterType::INTEGER);
        $db->setQuery($query);
        $optionMeta = $db->loadObject();

        $response['success']            = true;
        $response['productoption_id']   = $poTable->j2commerce_productoption_id;
        $response['option_name']        = $optionMeta->option_name ?? '';
        $response['option_unique_name'] = $optionMeta->option_unique_name ?? '';
        $response['option_type']        = $optionMeta->type ?? '';
        $response['ordering']           = $nextOrdering;
        $response['product_id']         = $productId;

        echo json_encode($response);
        $app->close();
    }

    public function removeProductOptionAjax(): void
    {
        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        header('Content-Type: application/json');

        $response = ['success' => false, 'message' => ''];

        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            $response['message'] = Text::_('JINVALID_TOKEN');
            echo json_encode($response);
            $app->close();
            return;
        }

        if (!$app->getIdentity()->authorise('core.edit', 'com_j2commerce')) {
            $response['message'] = Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN');
            echo json_encode($response);
            $app->close();
            return;
        }

        $productOptionId = $app->getInput()->getInt('productoption_id', 0);

        if (!$productOptionId) {
            $response['message'] = Text::_('COM_J2COMMERCE_INVALID_PRODUCT_OR_OPTION');
            echo json_encode($response);
            $app->close();
            return;
        }

        $db->transactionStart();
        try {
            // Delete cascading option values first
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_product_optionvalues'))
                ->where($db->quoteName('productoption_id') . ' = :poId')
                ->bind(':poId', $productOptionId, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();

            // Delete the product option
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_product_options'))
                ->where($db->quoteName('j2commerce_productoption_id') . ' = :poId2')
                ->bind(':poId2', $productOptionId, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            $response['message'] = $e->getMessage();
            echo json_encode($response);
            $app->close();
            return;
        }

        $response['success'] = true;
        $response['message'] = Text::_('COM_J2COMMERCE_OPTION_REMOVED');

        echo json_encode($response);
        $app->close();
    }

    public function saveProductOptionsAjax(): void
    {
        $app = Factory::getApplication();

        $response = [
            'success'                => false,
            'message'                => '',
            'variant_add_block_html' => '',
        ];

        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            $response['message'] = Text::_('JINVALID_TOKEN');
            echo json_encode($response);
            $app->close();
            return;
        }

        $productId  = $app->getInput()->getInt('product_id', 0);
        $formPrefix = $app->getInput()->getString('form_prefix', 'jform[attribs][j2commerce]');
        $rawInput   = $app->getInput()->post->getArray();
        $options    = isset($rawInput['options']) && \is_array($rawInput['options']) ? $rawInput['options'] : [];

        if ($productId <= 0) {
            $response['message'] = Text::_('COM_J2COMMERCE_SAVE_ARTICLE_FIRST');
            echo json_encode($response);
            $app->close();
            return;
        }

        if (empty($options)) {
            $response['message'] = Text::_('COM_J2COMMERCE_INVALID_DATA');
            echo json_encode($response);
            $app->close();
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $db->transactionStart();

            foreach ($options as $opt) {
                $optionId = (int) ($opt['option_id'] ?? 0);
                $ordering = (int) ($opt['ordering'] ?? 0);

                if ($optionId <= 0) {
                    continue;
                }

                // Check if product_option already exists
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_product_options'))
                    ->where($db->quoteName('product_id') . ' = :pid')
                    ->where($db->quoteName('option_id') . ' = :oid')
                    ->bind(':pid', $productId, ParameterType::INTEGER)
                    ->bind(':oid', $optionId, ParameterType::INTEGER);

                $db->setQuery($query);

                if ((int) $db->loadResult() === 0) {
                    $record             = new \stdClass();
                    $record->product_id = $productId;
                    $record->option_id  = $optionId;
                    $record->parent_id  = 0;
                    $record->ordering   = $ordering;
                    $record->required   = 0;
                    $record->is_variant = 1;
                    $db->insertObject('#__j2commerce_product_options', $record, 'j2commerce_productoption_id');
                }
            }

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            $response['message'] = $e->getMessage();
            echo json_encode($response);
            $app->close();
            return;
        }

        // Load ALL product options (DB-saved) with their option values
        /** @var \J2Commerce\Component\J2commerce\Administrator\Model\ProductOptionsModel $optionsModel */
        $optionsModel   = $this->factory->createModel('ProductOptions', 'Administrator', ['ignore_request' => true]);
        $productOptions = $optionsModel->getOptionsByProductId($productId);

        /** @var \J2Commerce\Component\J2commerce\Administrator\Model\OptionvaluesModel $valuesModel */
        $valuesModel = $this->factory->createModel('Optionvalues', 'Administrator', ['ignore_request' => true]);

        foreach ($productOptions as $productOption) {
            $productOption->option_values = $valuesModel->getValuesByOptionId((int) $productOption->option_id);
        }

        // Render the variant_add_block HTML (option dropdowns + Add Variant button)
        $html = '<input type="hidden" name="flexi_product_id" value="' . $productId . '"/>';
        $html .= '<div class="input-group">';

        foreach ($productOptions as $productOption) {
            $html .= '<select name="variant_combin[' . $productOption->j2commerce_productoption_id . ']" class="form-select">';
            $label = substr(Text::_('COM_J2COMMERCE_ANY') . ' ' . htmlspecialchars($productOption->option_name, ENT_QUOTES, 'UTF-8'), 0, 10) . '...';
            $html .= '<option value="0">' . $label . '</option>';

            foreach ($productOption->option_values as $optionValue) {
                $html .= '<option value="' . (int) $optionValue->j2commerce_optionvalue_id . '">'
                    . htmlspecialchars($optionValue->optionvalue_name, ENT_QUOTES, 'UTF-8') . '</option>';
            }

            $html .= '</select>';
        }

        $html .= '<button type="button" onclick="addFlexiVariant(event);" class="btn btn-primary" id="addVariantBtn">';
        $html .= '<span class="fas fa-solid fa-plus me-1" aria-hidden="true"></span>' . Text::_('COM_J2COMMERCE_ADD_VARIANT');
        $html .= '</button>';
        $html .= '</div>';

        // Build options table rows HTML with real DB IDs so the form save doesn't re-insert
        $optionsHtml = '';

        foreach ($productOptions as $po) {
            $poId        = (int) $po->j2commerce_productoption_id;
            $optName     = htmlspecialchars($po->option_name ?? '', ENT_QUOTES, 'UTF-8');
            $optUnique   = htmlspecialchars($po->option_unique_name ?? '', ENT_QUOTES, 'UTF-8');
            $optType     = htmlspecialchars($po->option_type ?? '', ENT_QUOTES, 'UTF-8');
            $optOrdering = (int) ($po->ordering ?? 0);

            $optionsHtml .= '<tr id="pao_flexivar_option_' . $poId . '">';
            $optionsHtml .= '<td><div class="d-flex align-items-center">';
            $optionsHtml .= '<strong>' . $optName . '</strong>';
            $optionsHtml .= '<input type="hidden" name="' . htmlspecialchars($formPrefix, ENT_QUOTES, 'UTF-8')
                . '[item_options][' . $poId . '][j2commerce_productoption_id]" value="' . $poId . '">';
            $optionsHtml .= '<input type="hidden" name="' . htmlspecialchars($formPrefix, ENT_QUOTES, 'UTF-8')
                . '[item_options][' . $poId . '][option_id]" value="' . (int) $po->option_id . '">';
            $optionsHtml .= '<small class="ms-1">(' . $optUnique . ')</small>';
            $optionsHtml .= '</div><div><small class="text-capitalize">'
                . Text::_('COM_J2COMMERCE_OPTION_TYPE') . ': ' . $optType . '</small></div></td>';
            $optionsHtml .= '<td><input class="form-control" name="' . htmlspecialchars($formPrefix, ENT_QUOTES, 'UTF-8')
                . '[item_options][' . $poId . '][ordering]" value="' . $optOrdering . '"></td>';
            $optionsHtml .= '<td class="text-end"><span class="optionRemove btn btn-danger btn-sm" data-option-id="'
                . $poId . '" role="button" title="' . Text::_('COM_J2COMMERCE_OPTION_REMOVE') . '">';
            $optionsHtml .= '<span class="icon icon-trash"></span></span></td>';
            $optionsHtml .= '</tr>';
        }

        $response['success']                = true;
        $response['message']                = Text::_('COM_J2COMMERCE_OPTIONS_SAVED_VARIANTS_READY');
        $response['variant_add_block_html'] = $html;
        $response['options_table_html']     = $optionsHtml;

        echo json_encode($response);
        $app->close();
    }
}
