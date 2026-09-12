<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\ParameterType;
use Joomla\String\StringHelper;

/**
 * Product item model class.
 *
 * @since  6.0.3
 */
class ProductModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.3
     */
    public $typeAlias = 'com_j2commerce.product';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.3
     */
    protected $text_prefix = 'COM_J2COMMERCE_PRODUCT';

    /**
     * Method to get the row form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|boolean  A Form object on success, false on failure
     *
     * @since   6.0.3
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm(
            'com_j2commerce.product',
            'product',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   6.0.3
     */
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_j2commerce.edit.product.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    /**
     * Method to get a single record with full hydrated data.
     *
     * Uses ProductHelper::getFullProduct() to load all related data including:
     * - manufacturer name
     * - product images
     * - article data (product_name, product_short_desc, product_long_desc)
     * - variants
     * - product options
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  object|boolean  Object on success, false on failure.
     *
     * @since   6.0.3
     * @since   6.0.8 Uses ProductHelper::getFullProduct() for hydration
     */
    public function getItem($pk = null)
    {
        // Get base item from parent (needed for form binding and new records)
        $item = parent::getItem($pk);

        if (!$item || empty($item->j2commerce_product_id)) {
            return $item;
        }

        // plg_system_schemaorg reads the item's `id` to reload a saved override into the
        // form; this table's key is j2commerce_product_id, so mirror it.
        $item->id = (int) $item->j2commerce_product_id;

        // Get fully hydrated product from ProductHelper
        $fullProduct = ProductHelper::getFullProduct((int) $item->j2commerce_product_id);

        if ($fullProduct) {
            // Merge hydrated data onto the base item
            // Keep base item properties for form binding compatibility
            foreach ($fullProduct as $key => $value) {
                if (!isset($item->$key)) {
                    $item->$key = $value;
                }
            }

            // Explicitly set hydrated properties (overwrite base if needed)
            $item->manufacturer          = $fullProduct->manufacturer ?? '';
            $item->product_name          = $fullProduct->product_name ?? '';
            $item->product_short_desc    = $fullProduct->product_short_desc ?? '';
            $item->product_long_desc     = $fullProduct->product_long_desc ?? '';
            $item->source                = $fullProduct->source ?? null;
            $item->main_image            = $fullProduct->main_image ?? '';
            $item->main_image_alt        = $fullProduct->main_image_alt ?? '';
            $item->thumb_image           = $fullProduct->thumb_image ?? '';
            $item->thumb_image_alt       = $fullProduct->thumb_image_alt ?? '';
            $item->additional_images     = $fullProduct->additional_images ?? '';
            $item->additional_images_alt = $fullProduct->additional_images_alt ?? '';
            $item->variants              = $fullProduct->variants ?? [];
            $item->product_options       = $fullProduct->product_options ?? [];
            $item->product_edit_url      = $fullProduct->product_edit_url ?? '';
            $item->product_view_url      = $fullProduct->product_view_url ?? '';
        }

        // Ensure JSON fields are encoded as strings for form binding
        // Hidden fields cannot accept arrays - they need string values
        if (isset($item->params) && (\is_array($item->params) || \is_object($item->params))) {
            $item->params = json_encode($item->params);
        }

        if (isset($item->plugins) && (\is_array($item->plugins) || \is_object($item->plugins))) {
            $item->plugins = json_encode($item->plugins);
        }

        return $item;
    }

    /**
     * Method to populate the state.
     *
     * CRITICAL: Override to read 'id' from URL instead of table's primary key name.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    protected function populateState(): void
    {
        $app = Factory::getApplication();

        // Read from URL param 'id', NOT from the table's column name
        $pk = $app->getInput()->getInt('id', 0);
        $this->setState($this->getName() . '.id', $pk);

        $params = ComponentHelper::getParams('com_j2commerce');
        $this->setState('params', $params);
    }

    /**
     * Prepare and sanitize the table before saving.
     *
     * @param   \Joomla\CMS\Table\Table  $table  The Table object
     *
     * @return  void
     *
     * @since   6.0.3
     */
    protected function prepareTable($table): void
    {
        $date = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();

        if (empty($table->j2commerce_product_id)) {
            // New record
            if (empty($table->created_on)) {
                $table->created_on = $date;
            }
            if (empty($table->created_by)) {
                $table->created_by = $user->id;
            }
        }

        // Always update modified
        $table->modified_on = $date;
        $table->modified_by = $user->id;
    }

    /**
     * Copy a product and the article behind it into a second, independent product.
     *
     * The article is created first because #__j2commerce_products.product_source_id needs its
     * id. Everything from there runs in one transaction — a product row with no variants is a
     * worse outcome than no product row at all.
     *
     * @param   integer  $productId  The product to copy.
     *
     * @return  integer|false  The new product id, or false with getError() set.
     *
     * @since   6.6.2
     */
    public function duplicateProduct(int $productId): int|false
    {
        $db      = $this->getDatabase();
        $product = $this->getTable();

        if (!$product->load($productId)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERROR_DUPLICATE_PRODUCT'));

            return false;
        }

        $productType = (string) $product->product_type;

        // The article cannot be created inside the transaction below. Writing its #__assets row
        // goes through Table\Nested, which issues LOCK TABLES, and MySQL commits whatever
        // transaction is open when it sees that — so a later rollback would leave the article
        // behind. It is unwound by hand in failDuplicate() instead.
        try {
            $articleId = $this->duplicateSourceArticle((int) $product->product_source_id);
        } catch (\Throwable $e) {
            return $this->failDuplicate($e, 0);
        }

        $db->transactionStart();

        try {
            $product->j2commerce_product_id = 0;
            $product->product_source_id     = $articleId;
            $product->created_on            = '';
            $product->created_by            = '';

            // A duplicate starts as a draft, and with none of the source product's traffic.
            $product->enabled = 0;
            $product->hits    = 0;

            if (!$product->check() || !$product->store()) {
                throw new \RuntimeException($product->getError());
            }

            $newProductId = (int) $product->j2commerce_product_id;

            $variantMap     = $this->copyVariants($productId, $newProductId);
            $optionMap      = $this->copyProductOptions($productId, $newProductId);
            $optionValueMap = $this->copyProductOptionValues($optionMap);

            $this->copyVariantOptionValues($variantMap, $optionValueMap);
            $this->copyVariantOwnedRows($variantMap);
            $this->copyProductOwnedRows($productId, $newProductId);
            $this->copyPriceIndex($productId, $newProductId);

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();

            return $this->failDuplicate($e, $articleId);
        }

        // Dispatched after the commit on purpose: a product-type plugin copying its own
        // product-keyed rows has to be able to see the product it is copying to. The copy is
        // already committed by this point, so a listener that throws must not take down the copy
        // it cannot undo, nor the remaining products in a bulk duplicate.
        try {
            J2CommerceHelper::plugin()->event('ProductDuplicate', [
                'source_product_id' => $productId,
                'new_product_id'    => $newProductId,
                'product_type'      => $productType,
            ]);
        } catch (\Throwable $e) {
            Log::add(
                'products.duplicate listener failed for product ' . $newProductId . ': ' . $e->getMessage(),
                Log::ERROR,
                'com_j2commerce'
            );
        }

        $this->cleanCache();

        return $newProductId;
    }

    /**
     * Log the failure, drop the article that was created for a copy that then failed, and set the
     * message the controller shows. The article has to go by hand because it was committed the
     * moment it was written — see duplicateProduct().
     *
     * @since   6.6.2
     */
    private function failDuplicate(\Throwable $e, int $articleId): false
    {
        Log::add('products.duplicate failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

        if ($articleId > 0) {
            try {
                $this->getArticleModel()->getTable()->delete($articleId);
            } catch (\Throwable $cleanup) {
                Log::add(
                    'products.duplicate could not remove article ' . $articleId . ': ' . $cleanup->getMessage(),
                    Log::ERROR,
                    'com_j2commerce'
                );
            }
        }

        $this->setError(Text::_('COM_J2COMMERCE_ERROR_DUPLICATE_PRODUCT'));

        return false;
    }

    /**
     * @since   6.6.2
     */
    private function getArticleModel(): object
    {
        return Factory::getApplication()->bootComponent('com_content')
            ->getMVCFactory()
            ->createModel('Article', 'Administrator', ['ignore_request' => true]);
    }

    /**
     * Create the article the duplicate hangs off, in the source article's own category.
     *
     * ArticleModel's save2copy title/alias handling is gated on the live request task, so it
     * never fires for products.duplicate — both have to be computed here.
     *
     * @since   6.6.2
     */
    private function duplicateSourceArticle(int $articleId): int
    {
        $articleModel = $this->getArticleModel();

        $item = $articleModel->getItem($articleId);

        if (!$item || empty($item->id)) {
            throw new \RuntimeException('Source article ' . $articleId . ' could not be loaded');
        }

        $data = (array) $item;

        foreach (
            [
                'id', 'asset_id', 'checked_out', 'checked_out_time', 'version', 'hits',
                'created', 'created_by', 'modified', 'modified_by', 'articletext',
                'associations', 'featured_up', 'featured_down',
            ] as $key
        ) {
            unset($data[$key]);
        }

        [$data['title'], $data['alias']] = $this->generateArticleTitle(
            (int) $item->catid,
            (string) $item->alias,
            (string) $item->title
        );

        // Two live listings carrying identical content is never what pressing Duplicate asked for.
        $data['state'] = 0;
        $data['tags']  = $this->getArticleTagIds($articleId);

        if (!$articleModel->save($data)) {
            throw new \RuntimeException($articleModel->getError());
        }

        return (int) $articleModel->getState('article.id');
    }

    /**
     * Core's own copy behaviour, which escalates (2) to (3) and -2 to -3 until the alias is free
     * in that category rather than stopping at a -2 that may already be taken.
     *
     * @return  array  The new title and alias.
     *
     * @since   6.6.2
     */
    private function generateArticleTitle(int $catid, string $alias, string $title): array
    {
        $db = $this->getDatabase();

        do {
            $title = StringHelper::increment($title);
            $alias = StringHelper::increment($alias, 'dash');

            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('alias') . ' = :alias')
                ->where($db->quoteName('catid') . ' = :catid')
                ->bind(':alias', $alias)
                ->bind(':catid', $catid, ParameterType::INTEGER);
        } while ((int) $db->setQuery($query)->loadResult() > 0);

        return [$title, $alias];
    }

    /**
     * @return  array  The tag ids assigned to the article.
     *
     * @since   6.6.2
     */
    private function getArticleTagIds(int $articleId): array
    {
        $db   = $this->getDatabase();
        $type = 'com_content.article';

        $query = $db->getQuery(true)
            ->select($db->quoteName('tag_id'))
            ->from($db->quoteName('#__contentitem_tag_map'))
            ->where($db->quoteName('type_alias') . ' = :type')
            ->where($db->quoteName('content_item_id') . ' = :articleId')
            ->bind(':type', $type)
            ->bind(':articleId', $articleId, ParameterType::INTEGER);

        return array_map('intval', (array) $db->setQuery($query)->loadColumn());
    }

    /**
     * @return  array  Old variant id => new variant id.
     *
     * @since   6.6.2
     */
    private function copyVariants(int $productId, int $newProductId): array
    {
        $db     = $this->getDatabase();
        $date   = Factory::getDate()->toSql();
        $userId = (int) Factory::getApplication()->getIdentity()->id;
        $map    = [];

        foreach ($this->loadChildRows('#__j2commerce_variants', 'product_id', [$productId]) as $row) {
            $oldId = (int) $row->j2commerce_variant_id;

            unset($row->j2commerce_variant_id);

            $row->product_id  = $newProductId;
            $row->created_on  = $date;
            $row->created_by  = $userId;
            $row->modified_on = $date;
            $row->modified_by = $userId;

            // Sales history belongs to the product that made the sales.
            $row->sold = 0;

            $db->insertObject('#__j2commerce_variants', $row);

            $map[$oldId] = (int) $db->insertid();
        }

        return $map;
    }

    /**
     * @return  array  Old product option id => new product option id.
     *
     * @since   6.6.2
     */
    private function copyProductOptions(int $productId, int $newProductId): array
    {
        $db      = $this->getDatabase();
        $map     = [];
        $parents = [];

        foreach ($this->loadChildRows('#__j2commerce_product_options', 'product_id', [$productId]) as $row) {
            $oldId     = (int) $row->j2commerce_productoption_id;
            $oldParent = (int) $row->parent_id;

            unset($row->j2commerce_productoption_id);

            $row->product_id = $newProductId;

            $db->insertObject('#__j2commerce_product_options', $row);

            $map[$oldId] = (int) $db->insertid();

            if ($oldParent > 0) {
                $parents[$map[$oldId]] = $oldParent;
            }
        }

        // Second pass: a nested option's parent may not have been inserted yet on the first, and
        // a parent_id left pointing into the source product is exactly the cross-link this remap
        // exists to prevent.
        foreach ($parents as $newId => $oldParent) {
            $newParent = (int) ($map[$oldParent] ?? 0);

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_product_options'))
                ->set($db->quoteName('parent_id') . ' = :parentId')
                ->where($db->quoteName('j2commerce_productoption_id') . ' = :optionId')
                ->bind(':parentId', $newParent, ParameterType::INTEGER)
                ->bind(':optionId', $newId, ParameterType::INTEGER);

            $db->setQuery($query)->execute();
        }

        return $map;
    }

    /**
     * @param   array  $optionMap  Old product option id => new product option id.
     *
     * @return  array  Old product option value id => new product option value id.
     *
     * @since   6.6.2
     */
    private function copyProductOptionValues(array $optionMap): array
    {
        $db      = $this->getDatabase();
        $map     = [];
        $parents = [];

        foreach ($this->loadChildRows('#__j2commerce_product_optionvalues', 'productoption_id', array_keys($optionMap)) as $row) {
            $oldId     = (int) $row->j2commerce_product_optionvalue_id;
            $oldParent = (string) $row->parent_optionvalue;

            unset($row->j2commerce_product_optionvalue_id);

            $row->productoption_id = (int) ($optionMap[(int) $row->productoption_id] ?? 0);

            $db->insertObject('#__j2commerce_product_optionvalues', $row);

            $map[$oldId] = (int) $db->insertid();

            if ($oldParent !== '' && $oldParent !== '0') {
                $parents[$map[$oldId]] = $oldParent;
            }
        }

        foreach ($parents as $newId => $oldParent) {
            $remapped = $this->remapIdList($oldParent, $map);

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_product_optionvalues'))
                ->set($db->quoteName('parent_optionvalue') . ' = :parent')
                ->where($db->quoteName('j2commerce_product_optionvalue_id') . ' = :valueId')
                ->bind(':parent', $remapped)
                ->bind(':valueId', $newId, ParameterType::INTEGER);

            $db->setQuery($query)->execute();
        }

        return $map;
    }

    /**
     * @since   6.6.2
     */
    private function copyVariantOptionValues(array $variantMap, array $optionValueMap): void
    {
        $db = $this->getDatabase();

        foreach ($this->loadChildRows('#__j2commerce_product_variant_optionvalues', 'variant_id', array_keys($variantMap)) as $row) {
            $row->product_optionvalue_ids = $this->remapIdList((string) $row->product_optionvalue_ids, $optionValueMap);
            $row->variant_id              = (int) ($variantMap[(int) $row->variant_id] ?? 0);

            $db->insertObject('#__j2commerce_product_variant_optionvalues', $row);
        }
    }

    /**
     * @since   6.6.2
     */
    private function copyVariantOwnedRows(array $variantMap): void
    {
        $db     = $this->getDatabase();
        $oldIds = array_keys($variantMap);

        foreach ($this->loadChildRows('#__j2commerce_product_prices', 'variant_id', $oldIds) as $row) {
            unset($row->j2commerce_productprice_id);

            $row->variant_id = (int) ($variantMap[(int) $row->variant_id] ?? 0);

            $db->insertObject('#__j2commerce_product_prices', $row);
        }

        foreach ($this->loadChildRows('#__j2commerce_productquantities', 'variant_id', $oldIds) as $row) {
            unset($row->j2commerce_productquantity_id);

            $row->variant_id = (int) ($variantMap[(int) $row->variant_id] ?? 0);

            // Stock carries over; on_hold is a live reservation against the source product and
            // sold is its history, and neither describes a product that has never been sold.
            $row->on_hold = 0;
            $row->sold    = 0;

            $db->insertObject('#__j2commerce_productquantities', $row);
        }
    }

    /**
     * @since   6.6.2
     */
    private function copyProductOwnedRows(int $productId, int $newProductId): void
    {
        $db = $this->getDatabase();

        foreach ($this->loadChildRows('#__j2commerce_productimages', 'product_id', [$productId]) as $row) {
            unset($row->j2commerce_productimage_id);

            $row->product_id = $newProductId;

            $db->insertObject('#__j2commerce_productimages', $row);
        }

        foreach ($this->loadChildRows('#__j2commerce_productfiles', 'product_id', [$productId]) as $row) {
            unset($row->j2commerce_productfile_id);

            $row->product_id = $newProductId;

            // Both products address the same stored file; download_total counts deliveries the
            // source product made.
            $row->download_total = 0;

            $db->insertObject('#__j2commerce_productfiles', $row);
        }

        // filter_id is a lookup unaffected by either remap, so the row only changes owner.
        foreach ($this->loadChildRows('#__j2commerce_product_filters', 'product_id', [$productId]) as $row) {
            $row->product_id = $newProductId;

            $db->insertObject('#__j2commerce_product_filters', $row);
        }
    }

    /**
     * Derive the duplicate's price index from its own copied variants rather than copying the
     * source's cached figures, which are only ever as fresh as its last save. Simple products
     * carry no index row at all, so the duplicate of one gets none either.
     *
     * @since   6.6.2
     */
    private function copyPriceIndex(int $productId, int $newProductId): void
    {
        if (empty($this->loadChildRows('#__j2commerce_productprice_index', 'product_id', [$productId]))) {
            return;
        }

        $db     = $this->getDatabase();
        $master = 0;

        $query = $db->getQuery(true)
            ->select(
                [
                    'MIN(' . $db->quoteName('price') . ') AS ' . $db->quoteName('min_price'),
                    'MAX(' . $db->quoteName('price') . ') AS ' . $db->quoteName('max_price'),
                ]
            )
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->where($db->quoteName('is_master') . ' = :master')
            ->where($db->quoteName('price') . ' > 0')
            ->bind(':productId', $newProductId, ParameterType::INTEGER)
            ->bind(':master', $master, ParameterType::INTEGER);

        $prices = $db->setQuery($query)->loadObject();

        // insertObject() takes its object by reference, so this cannot be an inline literal.
        $index = (object) [
            'product_id' => $newProductId,
            'min_price'  => $prices->min_price ?? 0,
            'max_price'  => $prices->max_price ?? 0,
        ];

        $db->insertObject('#__j2commerce_productprice_index', $index);
    }

    /**
     * @review-suppress j2c-performance -- #2350: a row-copy routine has to carry every column,
     * and naming them here would silently drop any column added to these tables later.
     *
     * @return  array  The matching rows, whole.
     *
     * @since   6.6.2
     */
    private function loadChildRows(string $table, string $column, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName($table))
            ->whereIn($db->quoteName($column), $ids, ParameterType::INTEGER);

        return (array) $db->setQuery($query)->loadObjectList();
    }

    /**
     * Rewrite a comma-separated id list through an old => new map. An id with no mapping is
     * dropped rather than carried over: a leftover id addresses the source product's row, which
     * is the cross-link these maps exist to prevent.
     *
     * @since   6.6.2
     */
    private function remapIdList(string $list, array $map): string
    {
        $remapped = [];

        foreach (explode(',', $list) as $id) {
            $id = (int) trim($id);

            if (isset($map[$id])) {
                $remapped[] = $map[$id];
            }
        }

        return implode(',', $remapped);
    }
}
