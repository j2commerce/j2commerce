<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Service;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Api\ProductApiException;
use J2Commerce\Component\J2commerce\Administrator\Api\ProductApiSchema;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/** Product option rows and their values, for the admin AJAX tasks and the API handlers; no transaction of its own. */
final class ProductOptionService
{
    private const VALUE_ATTRS = ['product_optionvalue_price', 'product_optionvalue_prefix', 'product_optionvalue_weight',
        'product_optionvalue_weight_prefix', 'product_optionvalue_sku', 'product_optionvalue_default', 'ordering',
        'product_optionvalue_attribs', 'parent_optionvalue'];

    private const MODIFIER_COLUMNS = ['product_optionvalue_price', 'product_optionvalue_weight'];

    /** API document key → column, for the per-value modifiers. */
    private const VALUE_KEYS = ['price' => 'product_optionvalue_price', 'prefix' => 'product_optionvalue_prefix',
        'weight'                        => 'product_optionvalue_weight', 'weight_prefix' => 'product_optionvalue_weight_prefix',
        'sku'                           => 'product_optionvalue_sku', 'default' => 'product_optionvalue_default'];

    private MVCFactoryInterface $mvcFactory;

    private DatabaseInterface $db;

    public function __construct(?MVCFactoryInterface $mvcFactory = null, ?DatabaseInterface $db = null)
    {
        $this->mvcFactory = $mvcFactory ?: Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory();
        $this->db         = $db ?: Factory::getContainer()->get(DatabaseInterface::class);
    }

    /** Returns the new product option id. @throws ProductApiException  409 when the option is already attached. */
    public function attachOption(int $productId, int $optionId, ?int $ordering = null, int $required = 0, int $parentId = 0, int $isVariant = 1): int
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_product_options'))
            ->where($db->quoteName('product_id') . ' = :pid')
            ->where($db->quoteName('option_id') . ' = :oid')
            ->bind(':pid', $productId, ParameterType::INTEGER)
            ->bind(':oid', $optionId, ParameterType::INTEGER);

        if ((int) $db->setQuery($query)->loadResult() > 0) {
            throw ProductApiException::single(409, '/options', \sprintf('option %d is already attached to product %d.', $optionId, $productId));
        }

        if ($ordering === null) {
            $query = $db->getQuery(true)
                ->select('COALESCE(MAX(' . $db->quoteName('ordering') . '), 0) + 1')
                ->from($db->quoteName('#__j2commerce_product_options'))
                ->where($db->quoteName('product_id') . ' = :pid')
                ->bind(':pid', $productId, ParameterType::INTEGER);
            $ordering = (int) $db->setQuery($query)->loadResult();
        }

        $table             = $this->mvcFactory->createTable('Productoption', 'Administrator');
        $table->option_id  = $optionId;
        $table->product_id = $productId;
        $table->ordering   = $ordering;
        $table->required   = $required;
        $table->is_variant = $isVariant;
        $table->parent_id  = $parentId;

        if (!$table->check() || !$table->store()) {
            throw new \RuntimeException('Product option refused: ' . $table->getError());
        }

        return (int) $table->j2commerce_productoption_id;
    }

    /**
     * Removes the option, its values and, for a matrix product, the child variants whose combination
     * references one of those values; nothing when one of those children is on an order.
     *
     * @param list<int>|null $dependents  dependentVariantIds(), when the caller already has them.
     * @throws ProductApiException  404 when the option is not the product's; 409 when a dependent variant is on an order.
     */
    public function detachOption(int $productId, int $productOptionId, VariantMatrixService $variants, ?array $dependents = null): void
    {
        if (!$this->findProductOption($productId, $productOptionId)) {
            throw ProductApiException::single(404, '/options/detach', \sprintf('productoption_id %d does not belong to product %d.', $productOptionId, $productId));
        }

        $variants->deleteVariants($productId, $dependents ?? $this->dependentVariantIds($productId, $productOptionId, $variants), '/options/detach');

        $db    = $this->db;
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_product_optionvalues'))
            ->where($db->quoteName('productoption_id') . ' = :poid')
            ->bind(':poid', $productOptionId, ParameterType::INTEGER);
        $db->setQuery($query)->execute();

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_product_options'))
            ->where($db->quoteName('j2commerce_productoption_id') . ' = :poid')
            ->where($db->quoteName('product_id') . ' = :pid')
            ->bind(':poid', $productOptionId, ParameterType::INTEGER)
            ->bind(':pid', $productId, ParameterType::INTEGER);
        $db->setQuery($query)->execute();
    }

    /** @return list<int>  Child variants whose combination references one of the option's values. */
    public function dependentVariantIds(int $productId, int $productOptionId, VariantMatrixService $variants): array
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_product_optionvalue_id'))
            ->from($db->quoteName('#__j2commerce_product_optionvalues'))
            ->where($db->quoteName('productoption_id') . ' = :poid')
            ->bind(':poid', $productOptionId, ParameterType::INTEGER);

        return $this->dependentsOfValues($productId, array_map('intval', $db->setQuery($query)->loadColumn()), $variants);
    }

    /**
     * @param list<int> $productOptionValueIds
     * @return list<int>  Child variants whose combination references one of the product option values.
     */
    public function dependentsOfValues(int $productId, array $productOptionValueIds, VariantMatrixService $variants): array
    {
        if ($productOptionValueIds === []) {
            return [];
        }

        $dependents = [];

        foreach ($variants->existingKeys($productId) as $variantId => $csv) {
            if (array_intersect(ProductHelper::normaliseOptionvalueIds($csv), $productOptionValueIds)) {
                $dependents[] = $variantId;
            }
        }

        return $dependents;
    }

    /**
     * Returns the new product option value id. Price and weight are written at the column's scale
     * whatever the caller passed; a matrix option's values carry no price, the child variant does.
     *
     * @param array<string, mixed> $attrs      Any of the product_optionvalues columns besides the keys.
     * @param bool|null            $isVariant  Skips the option lookup when the caller knows.
     */
    public function addOptionValue(int $productOptionId, int $optionValueId, array $attrs = [], ?bool $isVariant = null): int
    {
        $row = (object) array_replace([
            'productoption_id'                  => $productOptionId,
            'optionvalue_id'                    => $optionValueId,
            'parent_optionvalue'                => '',
            'product_optionvalue_price'         => 0,
            'product_optionvalue_prefix'        => '+',
            'product_optionvalue_weight'        => 0,
            'product_optionvalue_weight_prefix' => '+',
            'product_optionvalue_sku'           => '',
            'product_optionvalue_default'       => 0,
            'ordering'                          => 0,
            'product_optionvalue_attribs'       => '',
        ], $this->valueColumns($attrs, $isVariant ?? $this->isVariantOption($productOptionId)));

        $this->db->insertObject('#__j2commerce_product_optionvalues', $row, 'j2commerce_product_optionvalue_id');

        return (int) $row->j2commerce_product_optionvalue_id;
    }

    /** @param array<string, mixed> $attrs  Columns to overwrite, formatted and price-zeroed like addOptionValue(). */
    public function updateOptionValue(int $productOptionValueId, array $attrs, ?bool $isVariant = null): void
    {
        $attrs = array_intersect_key($attrs, array_flip(self::VALUE_ATTRS));

        if ($attrs === []) {
            return;
        }

        if (isset($attrs['product_optionvalue_price']) && $isVariant === null) {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select($db->quoteName('po.is_variant'))
                ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
                ->join('INNER', $db->quoteName('#__j2commerce_product_options', 'po') . ' ON ' . $db->quoteName('po.j2commerce_productoption_id') . ' = ' . $db->quoteName('pov.productoption_id'))
                ->where($db->quoteName('pov.j2commerce_product_optionvalue_id') . ' = :id')
                ->bind(':id', $productOptionValueId, ParameterType::INTEGER);
            $isVariant = (int) $db->setQuery($query)->loadResult() === 1;
        }

        $row                                    = (object) $this->valueColumns($attrs, (bool) $isVariant);
        $row->j2commerce_product_optionvalue_id = $productOptionValueId;
        $this->db->updateObject('#__j2commerce_product_optionvalues', $row, 'j2commerce_product_optionvalue_id');
    }

    /** @param list<int> $productOptionValueIds */
    public function deleteOptionValues(array $productOptionValueIds): void
    {
        if ($productOptionValueIds === []) {
            return;
        }

        $db    = $this->db;
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_product_optionvalues'))
            ->whereIn($db->quoteName('j2commerce_product_optionvalue_id'), $productOptionValueIds);
        $db->setQuery($query)->execute();
    }

    /** `has_options` follows the option count, as the behaviors set it from `item_options` on the admin save. */
    public function syncHasOptions(int $productId): void
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_product_options'))
            ->where($db->quoteName('product_id') . ' = :pid')
            ->bind(':pid', $productId, ParameterType::INTEGER);
        $has = (int) ((int) $db->setQuery($query)->loadResult() > 0);

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_products'))
            ->set($db->quoteName('has_options') . ' = :has')
            ->where($db->quoteName('j2commerce_product_id') . ' = :pid')
            ->bind(':has', $has, ParameterType::INTEGER)
            ->bind(':pid', $productId, ParameterType::INTEGER);
        $db->setQuery($query)->execute();
    }

    /**
     * @param list<int> $optionIds
     * @return array<int, list<int>>  option_id => its optionvalue ids from #__j2commerce_optionvalues.
     */
    public function baseValues(array $optionIds): array
    {
        $out = array_fill_keys($optionIds, []);

        if ($optionIds === []) {
            return $out;
        }

        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_optionvalue_id', 'option_id']))
            ->from($db->quoteName('#__j2commerce_optionvalues'))
            ->whereIn($db->quoteName('option_id'), $optionIds)
            ->order($db->quoteName('ordering'));

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $out[(int) $row->option_id][] = (int) $row->j2commerce_optionvalue_id;
        }

        return $out;
    }

    public function isVariantOption(int $productOptionId): bool
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName('is_variant'))
            ->from($db->quoteName('#__j2commerce_product_options'))
            ->where($db->quoteName('j2commerce_productoption_id') . ' = :poid')
            ->bind(':poid', $productOptionId, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult() === 1;
    }

    /** The product a product_options row belongs to; 0 when the row does not exist. */
    public function productIdOf(int $productOptionId): int
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName('product_id'))
            ->from($db->quoteName('#__j2commerce_product_options'))
            ->where($db->quoteName('j2commerce_productoption_id') . ' = :poid')
            ->bind(':poid', $productOptionId, ParameterType::INTEGER);

        return (int) $db->setQuery($query, 0, 1)->loadResult();
    }

    /** The product_options row when it belongs to the product. */
    public function findProductOption(int $productId, int $productOptionId): ?object
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_product_options'))
            ->where($db->quoteName('j2commerce_productoption_id') . ' = :poid')
            ->where($db->quoteName('product_id') . ' = :pid')
            ->bind(':poid', $productOptionId, ParameterType::INTEGER)
            ->bind(':pid', $productId, ParameterType::INTEGER);

        return $db->setQuery($query, 0, 1)->loadObject();
    }

    /**
     * The product's option rows with their values, read fresh (ProductHelper::getTraits() caches per request).
     *
     * @return list<object>  product_options columns + option_name, option_unique_name, type; `values`: product_optionvalues columns + optionvalue_name.
     */
    public function productOptions(int $productId): array
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['po.j2commerce_productoption_id', 'po.option_id', 'po.parent_id', 'po.product_id', 'po.ordering', 'po.required', 'po.is_variant']))
            ->select($db->quoteName(['o.option_name', 'o.option_unique_name', 'o.type']))
            ->from($db->quoteName('#__j2commerce_product_options', 'po'))
            ->join('LEFT', $db->quoteName('#__j2commerce_options', 'o') . ' ON ' . $db->quoteName('o.j2commerce_option_id') . ' = ' . $db->quoteName('po.option_id'))
            ->where($db->quoteName('po.product_id') . ' = :pid')
            ->order($db->quoteName('po.ordering') . ' ASC, ' . $db->quoteName('po.j2commerce_productoption_id') . ' ASC')
            ->bind(':pid', $productId, ParameterType::INTEGER);
        $options = $db->setQuery($query)->loadObjectList('j2commerce_productoption_id');

        foreach ($options as $option) {
            $option->values = [];
        }

        if ($options !== []) {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['pov.j2commerce_product_optionvalue_id', 'pov.productoption_id', 'pov.optionvalue_id', 'pov.parent_optionvalue',
                    'pov.product_optionvalue_price', 'pov.product_optionvalue_prefix', 'pov.product_optionvalue_weight', 'pov.product_optionvalue_weight_prefix',
                    'pov.product_optionvalue_sku', 'pov.product_optionvalue_default', 'pov.ordering', 'pov.product_optionvalue_attribs']))
                ->select($db->quoteName('ov.optionvalue_name'))
                ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
                ->join('LEFT', $db->quoteName('#__j2commerce_optionvalues', 'ov') . ' ON ' . $db->quoteName('ov.j2commerce_optionvalue_id') . ' = ' . $db->quoteName('pov.optionvalue_id'))
                ->whereIn($db->quoteName('pov.productoption_id'), array_keys($options))
                ->order($db->quoteName('pov.ordering') . ' ASC, ' . $db->quoteName('pov.j2commerce_product_optionvalue_id') . ' ASC');

            foreach ($db->setQuery($query)->loadObjectList() as $value) {
                $options[(int) $value->productoption_id]->values[] = $value;
            }
        }

        return array_values($options);
    }

    /**
     * @param list<object>|null $options  productOptions() rows, when the caller already has them.
     * @return list<object>  The options block GET /products/:id renders.
     */
    public function forApi(int $productId, ?array $options = null): array
    {
        return array_map(static fn (object $option): object => (object) [
            'productoption_id' => (int) $option->j2commerce_productoption_id,
            'option_id'        => (int) $option->option_id,
            'option_name'      => $option->option_name,
            'type'             => $option->type,
            'required'         => (int) $option->required,
            'is_variant'       => (int) $option->is_variant,
            'parent_id'        => (int) $option->parent_id,
            'values'           => array_map(static fn (object $value): object => (object) [
                'product_optionvalue_id' => (int) $value->j2commerce_product_optionvalue_id,
                'optionvalue_id'         => (int) $value->optionvalue_id,
                'optionvalue_name'       => $value->optionvalue_name,
                'price'                  => $value->product_optionvalue_price,
                'prefix'                 => $value->product_optionvalue_prefix,
                'weight'                 => $value->product_optionvalue_weight,
                'weight_prefix'          => $value->product_optionvalue_weight_prefix,
                'sku'                    => $value->product_optionvalue_sku,
                'default'                => (int) $value->product_optionvalue_default,
                'parent_optionvalue'     => $value->parent_optionvalue,
            ], $option->values),
        ], $options ?? $this->productOptions($productId));
    }

    /**
     * @param list<int> $optionIds
     * @throws ProductApiException  400 naming the first option that is not enabled.
     */
    public function assertOptionsEnabled(array $optionIds, string $pointer = '/options'): void
    {
        if ($optionIds === []) {
            return;
        }

        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_option_id'))
            ->from($db->quoteName('#__j2commerce_options'))
            ->whereIn($db->quoteName('j2commerce_option_id'), $optionIds)
            ->where($db->quoteName('enabled') . ' = 1');
        $found = array_map('intval', $db->setQuery($query)->loadColumn());

        if ($missing = array_diff($optionIds, $found)) {
            throw ProductApiException::single(400, $pointer, \sprintf('option_id %d is not an enabled option.', reset($missing)));
        }
    }

    /**
     * @param list<int> $valueIds
     * @return array<int, int>  optionvalue_id => option_id for every id given.
     * @throws ProductApiException  400 naming the first unknown value.
     */
    public function assertValuesBelong(array $valueIds, string $pointer = '/options'): array
    {
        if ($valueIds === []) {
            return [];
        }

        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_optionvalue_id', 'option_id']))
            ->from($db->quoteName('#__j2commerce_optionvalues'))
            ->whereIn($db->quoteName('j2commerce_optionvalue_id'), $valueIds);
        $owner = array_map('intval', $db->setQuery($query)->loadAssocList('j2commerce_optionvalue_id', 'option_id'));

        if ($missing = array_diff($valueIds, array_keys($owner))) {
            throw ProductApiException::single(400, $pointer, \sprintf('optionvalue_id %d does not exist.', reset($missing)));
        }

        return $owner;
    }

    /**
     * Checks the create-time `options` document and returns it normalised.
     *
     * Matrix form (`$modifiers` false): `[{option_id, values: [optionvalue ids]}]`.
     * Modifier form: `[{option_id, required?, parent_option_id?, values: [optionvalue id | {optionvalue_id, price?, prefix?,
     * weight?, weight_prefix?, sku?, default?, parent_values?}]}]`; `parent_option_id` only when `$parents` is true.
     * `parent_option_id` and `parent_values` are option / optionvalue ids: the parent is an option listed earlier in
     * the document or one already attached (`$attached`: option_id => its optionvalue ids on the product).
     *
     * @return list<array{option_id: int, required: int, parent_option_id: int, values: list<array<string, mixed>>}>
     * @throws ProductApiException
     */
    public function validateOptionsDocument(mixed $options, string $pointer, bool $modifiers, bool $parents = false, bool $required = true, array $attached = []): array
    {
        if ($options === null && !$required) {
            return [];
        }

        if (!\is_array($options) || ($required && $options === []) || !array_is_list($options)) {
            throw ProductApiException::single(400, $pointer, 'must be a list of {"option_id", "values": [...]}.');
        }

        self::assertListSize($options, $pointer);

        $out      = [];
        $optionAt = [];
        $allowed  = $modifiers ? ['option_id', 'required', 'values'] : ['option_id', 'values'];

        if ($modifiers && $parents) {
            $allowed[] = 'parent_option_id';
        }

        $total = 0;

        foreach ($options as $n => $option) {
            $entry = $this->normaliseOption($option, $pointer . '/' . $n, $allowed, $modifiers, $optionAt, $attached);
            $total += \count($entry['values']);

            if ($total > ProductApiSchema::MAX_LIST) {
                throw ProductApiException::single(400, $pointer, \sprintf('must not list more than %d values in total.', ProductApiSchema::MAX_LIST));
            }

            $optionAt[$entry['option_id']] = $n;
            $out[]                         = $entry;
        }

        $this->assertOptionsEnabled(array_keys($optionAt), $pointer);
        $this->assertDocumentValues($out, $optionAt, $attached, $pointer);

        return $out;
    }

    /**
     * One option of the create-time document, its values normalised and checked for duplicates and a single default.
     *
     * @param array<int, int> $optionAt  option_id => position of the options listed before this one.
     * @return array{option_id: int, required: int, parent_option_id: int, values: list<array<string, mixed>>}
     */
    private function normaliseOption(mixed $option, string $at, array $allowed, bool $modifiers, array $optionAt, array $attached): array
    {
        if (!\is_array($option) || array_diff_key($option, array_flip($allowed)) !== [] || !self::uint($option['option_id'] ?? null) || (int) ($option['option_id'] ?? 0) < 1) {
            throw ProductApiException::single(400, $at, 'accepts: ' . implode(', ', $allowed) . '; option_id must be a positive integer.');
        }

        $optionId = (int) $option['option_id'];

        if (isset($optionAt[$optionId])) {
            throw ProductApiException::single(400, $at, \sprintf('option %d is listed twice.', $optionId));
        }

        $values = $option['values'] ?? null;

        if (!\is_array($values) || $values === [] || !array_is_list($values)) {
            throw ProductApiException::single(400, $at . '/values', 'must be a non-empty list.');
        }

        self::assertListSize($values, $at . '/values');

        $parentId = (int) ($option['parent_option_id'] ?? 0);

        if ($parentId !== 0 && (!self::uint($option['parent_option_id']) || (!isset($optionAt[$parentId]) && !isset($attached[$parentId])))) {
            throw ProductApiException::single(400, $at . '/parent_option_id', 'must name an option listed earlier in this document or already attached to the product.');
        }

        if (\array_key_exists('required', $option) && !\in_array($option['required'], [0, 1, '0', '1'], true)) {
            throw ProductApiException::single(400, $at . '/required', 'must be 0 or 1.');
        }

        return [
            'option_id'        => $optionId,
            'required'         => (int) ($option['required'] ?? 0),
            'parent_option_id' => $parentId,
            'values'           => $this->normaliseOptionValues($values, $at, $modifiers, $parentId !== 0),
        ];
    }

    /** @return list<array<string, mixed>>  The option's values normalised, no value twice, at most one default. */
    private function normaliseOptionValues(array $values, string $at, bool $modifiers, bool $hasParent): array
    {
        $entries = [];
        $seen    = [];
        $default = 0;

        foreach ($values as $i => $value) {
            $vAt = $at . '/values/' . $i;

            if ($modifiers) {
                $entry = $this->normaliseValue($value, $vAt, $hasParent);
            } elseif (self::uint($value) && (int) $value > 0) {
                $entry = ['optionvalue_id' => (int) $value];
            } else {
                throw ProductApiException::single(400, $vAt, 'must be an optionvalue id.');
            }

            if (isset($seen[$entry['optionvalue_id']])) {
                throw ProductApiException::single(400, $vAt, \sprintf('optionvalue %d is listed twice.', $entry['optionvalue_id']));
            }

            $seen[$entry['optionvalue_id']] = true;
            $default += (int) ($entry['product_optionvalue_default'] ?? 0);
            $entries[] = $entry;
        }

        if ($default > 1) {
            throw ProductApiException::single(400, $at . '/values', 'at most one value can be the default.');
        }

        return $entries;
    }

    /** Every value exists and belongs to its option; every parent value is a listed value of the parent option. */
    private function assertDocumentValues(array $out, array $optionAt, array $attached, string $pointer): void
    {
        $allValues = [];

        foreach ($out as $option) {
            foreach ($option['values'] as $entry) {
                $allValues[] = $entry['optionvalue_id'];
            }
        }

        $owner = $this->assertValuesBelong(array_values(array_unique($allValues)), $pointer);

        foreach ($out as $n => $option) {
            $parentValues = [];

            if ($option['parent_option_id'] !== 0) {
                $parentValues = isset($optionAt[$option['parent_option_id']])
                    ? array_column($out[$optionAt[$option['parent_option_id']]]['values'], 'optionvalue_id')
                    : $attached[$option['parent_option_id']];
            }

            foreach ($option['values'] as $i => $entry) {
                if ($owner[$entry['optionvalue_id']] !== $option['option_id']) {
                    throw ProductApiException::single(400, $pointer . '/' . $n . '/values/' . $i, \sprintf('optionvalue_id %d does not belong to option %d.', $entry['optionvalue_id'], $option['option_id']));
                }

                foreach ($entry['parent_values'] ?? [] as $parentValue) {
                    if (!\in_array($parentValue, $parentValues, true)) {
                        throw ProductApiException::single(400, $pointer . '/' . $n . '/values/' . $i . '/parent_values', \sprintf('%d is not a listed value of option %d.', $parentValue, $option['parent_option_id']));
                    }
                }
            }
        }
    }

    /**
     * Writes a validated document. `product_options.parent_id` stores the parent's global option
     * id (ProductsController::resolveParentOptionValues()); `parent_optionvalue` stores the parent's
     * product_optionvalue ids, translated here from the optionvalue ids the document named, whether
     * the parent was written in this call or was already attached.
     *
     * @param list<array{option_id: int, required: int, parent_option_id: int, values: list<array<string, mixed>>}> $options
     *
     * @return array{options: array<int, int>, values: array<int, int>}  option_id => productoption_id, optionvalue_id => product_optionvalue_id.
     */
    public function writeOptions(int $productId, array $options, int $isVariant): array
    {
        $optionMap = [];
        $valueMap  = [];
        $written   = [];
        $stored    = $this->valueIdsByOption($this->productOptions($productId));

        foreach ($options as $i => $option) {
            $ordering = $i + 1 + \count($stored);
            $poId     = $this->attachOption($productId, $option['option_id'], $ordering, $option['required'], $option['parent_option_id'], $isVariant);

            $optionMap[$option['option_id']] = $poId;

            foreach ($option['values'] as $j => $entry) {
                $attrs   = array_diff_key($entry, array_flip(['optionvalue_id', 'parent_values']));
                $parents = [];

                foreach ($entry['parent_values'] ?? [] as $parentValue) {
                    $parents[] = $valueMap[$parentValue] ?? $stored[$option['parent_option_id']][$parentValue]
                        ?? throw new \RuntimeException(\sprintf('Parent value %d of option %d is not on product %d.', $parentValue, $option['parent_option_id'], $productId));
                }

                $attrs['ordering']           = $j;
                $attrs['parent_optionvalue'] = implode(',', $parents);

                $povId                              = $this->addOptionValue($poId, $entry['optionvalue_id'], $attrs, $isVariant === 1);
                $valueMap[$entry['optionvalue_id']] = $povId;
                $written[$povId]                    = $this->valueColumns($attrs, $isVariant === 1);
            }
        }

        $this->assertModifiersStored($written);

        return ['options' => $optionMap, 'values' => $valueMap];
    }

    /**
     * Reads the written values' price and weight back in one query and refuses the write when one
     * differs from what was sent, so a clamped or refused store cannot answer 200. A matrix option's
     * price is forced to 0 by addOptionValue(), so only API-formatted (string) amounts are compared.
     *
     * @param array<int, array<string, mixed>> $written  product_optionvalue_id => the columns written.
     */
    private function assertModifiersStored(array $written): void
    {
        $expected = [];

        foreach ($written as $povId => $attrs) {
            foreach (self::MODIFIER_COLUMNS as $key) {
                if (\is_string($attrs[$key] ?? null)) {
                    $expected[$povId][$key] = $attrs[$key];
                }
            }
        }

        if ($expected === []) {
            return;
        }

        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(array_merge(['j2commerce_product_optionvalue_id'], self::MODIFIER_COLUMNS)))
            ->from($db->quoteName('#__j2commerce_product_optionvalues'))
            ->whereIn($db->quoteName('j2commerce_product_optionvalue_id'), array_keys($expected));
        $stored = $db->setQuery($query)->loadAssocList('j2commerce_product_optionvalue_id');

        foreach ($expected as $povId => $attrs) {
            foreach ($attrs as $key => $sent) {
                $value = isset($stored[$povId][$key]) ? number_format((float) $stored[$povId][$key], ProductApiSchema::MODIFIER_SCALE, '.', '') : null;

                if ($value !== $sent) {
                    throw new \RuntimeException(\sprintf('Option value %d %s %s was stored as %s.', $povId, $key, $sent, $stored[$povId][$key] ?? 'no row'));
                }
            }
        }
    }

    /**
     * @param list<object> $options  productOptions() rows.
     * @return array<int, array<int, int>>  option_id => [optionvalue_id => product_optionvalue_id].
     */
    private function valueIdsByOption(array $options): array
    {
        $map = [];

        foreach ($options as $option) {
            $map[(int) $option->option_id] = [];

            foreach ($option->values as $value) {
                $map[(int) $option->option_id][(int) $value->optionvalue_id] = (int) $value->j2commerce_product_optionvalue_id;
            }
        }

        return $map;
    }

    /**
     * Checks the patch-time `options` document: `{attach: [...create entries...], detach: [productoption ids],
     * values: {upsert: [{productoption_id, optionvalue_id, ...modifiers}], delete: [product_optionvalue ids]}}`.
     *
     * @return array{attach: list<array>, detach: list<int>, upsert: list<array<string, mixed>>, delete: list<int>}
     * @throws ProductApiException
     */
    public function validateOptionsPatch(int $productId, mixed $doc, string $pointer, bool $modifiers, bool $parents = false): array
    {
        if (!\is_array($doc) || array_diff_key($doc, array_flip(['attach', 'detach', 'values'])) !== []) {
            throw ProductApiException::single(400, $pointer, 'accepts: attach, detach, values.');
        }

        $existing = $this->productOptions($productId);
        $byPoId   = [];

        foreach ($existing as $option) {
            $byPoId[(int) $option->j2commerce_productoption_id] = $option;
        }

        $detach   = self::idList($doc['detach'] ?? [], $pointer . '/detach');
        $attached = $this->attachedAfterDetach($existing, $byPoId, $detach, $pointer . '/detach', $productId);
        $attach   = $this->validateOptionsDocument($doc['attach'] ?? null, $pointer . '/attach', $modifiers, $parents, false, $attached);

        foreach ($attach as $n => $option) {
            if (isset($attached[$option['option_id']])) {
                throw ProductApiException::single(409, $pointer . '/attach/' . $n, \sprintf('option %d is already attached.', $option['option_id']));
            }
        }

        $values = $doc['values'] ?? [];

        if (!\is_array($values) || array_diff_key($values, array_flip(['upsert', 'delete'])) !== []) {
            throw ProductApiException::single(400, $pointer . '/values', 'accepts: upsert, delete.');
        }

        return [
            'attach' => $attach,
            'detach' => $detach,
            'upsert' => $this->validateUpserts($values['upsert'] ?? [], $pointer . '/values/upsert', $modifiers, $byPoId, $attached),
            'delete' => $this->validateValueDeletes($values['delete'] ?? [], $pointer . '/values/delete', $productId, $existing),
        ];
    }

    /**
     * The options kept once the detached ones are gone (detach runs first, so an option listed under
     * both is re-attached, not refused); every detached id must be the product's.
     *
     * @return array<int, list<int>>  option_id => its optionvalue ids on the product.
     */
    private function attachedAfterDetach(array $existing, array $byPoId, array $detach, string $pointer, int $productId): array
    {
        foreach ($detach as $poId) {
            if (!isset($byPoId[$poId])) {
                throw ProductApiException::single(404, $pointer, \sprintf('productoption_id %d does not belong to product %d.', $poId, $productId));
            }
        }

        $attached = [];

        foreach ($existing as $option) {
            if (!\in_array((int) $option->j2commerce_productoption_id, $detach, true)) {
                $attached[(int) $option->option_id] = array_map(static fn (object $v): int => (int) $v->optionvalue_id, $option->values);
            }
        }

        return $attached;
    }

    /**
     * @param array<int, object> $byPoId    productoption_id => productOptions() row.
     * @param array<int, list<int>> $attached  option_id => optionvalue ids kept on the product.
     * @return list<array<string, mixed>>
     */
    private function validateUpserts(mixed $upsert, string $pointer, bool $modifiers, array $byPoId, array $attached): array
    {
        if (!\is_array($upsert) || !array_is_list($upsert)) {
            throw ProductApiException::single(400, $pointer, 'must be a list.');
        }

        self::assertListSize($upsert, $pointer);

        $normalised = [];
        $valueIds   = [];

        foreach ($upsert as $n => $entry) {
            $value        = $this->normaliseUpsert($entry, $pointer . '/' . $n, $modifiers, $byPoId);
            $valueIds[]   = $value['optionvalue_id'];
            $normalised[] = $value;
        }

        $this->assertUpsertsBelong($normalised, $this->assertValuesBelong(array_values(array_unique($valueIds)), $pointer), $byPoId, $attached, $pointer);

        return $normalised;
    }

    /** @return array<string, mixed>  One upsert entry as columns plus `productoption_id` and `optionvalue_id`. */
    private function normaliseUpsert(mixed $entry, string $at, bool $modifiers, array $byPoId): array
    {
        if (!\is_array($entry) || !self::uint($entry['productoption_id'] ?? null) || !isset($byPoId[(int) $entry['productoption_id']])) {
            throw ProductApiException::single(400, $at, 'productoption_id must name one of this product\'s options.');
        }

        $poId = (int) $entry['productoption_id'];

        if ($modifiers) {
            $value = $this->normaliseValue(array_diff_key($entry, ['productoption_id' => 1]), $at, (int) $byPoId[$poId]->parent_id !== 0);
        } elseif (array_diff_key($entry, array_flip(['productoption_id', 'optionvalue_id'])) === [] && self::uint($entry['optionvalue_id'] ?? null)) {
            $value = ['optionvalue_id' => (int) $entry['optionvalue_id']];
        } else {
            throw ProductApiException::single(400, $at, 'accepts: productoption_id, optionvalue_id.');
        }

        $value['productoption_id'] = $poId;

        return $value;
    }

    /** Every upserted value belongs to its option; every parent value is one of the parent option's on this product. */
    private function assertUpsertsBelong(array $normalised, array $owner, array $byPoId, array $attached, string $pointer): void
    {
        foreach ($normalised as $n => $value) {
            $option = $byPoId[$value['productoption_id']];

            if ($owner[$value['optionvalue_id']] !== (int) $option->option_id) {
                throw ProductApiException::single(400, $pointer . '/' . $n, \sprintf('optionvalue_id %d does not belong to option %d.', $value['optionvalue_id'], (int) $option->option_id));
            }

            foreach ($value['parent_values'] ?? [] as $parentValue) {
                if (!\in_array($parentValue, $attached[(int) $option->parent_id] ?? [], true)) {
                    throw ProductApiException::single(400, $pointer . '/' . $n . '/parent_values', \sprintf('%d is not a value of the parent option %d on this product.', $parentValue, (int) $option->parent_id));
                }
            }
        }
    }

    /** @return list<int>  product_optionvalue ids to delete, each one of the product's. */
    private function validateValueDeletes(mixed $delete, string $pointer, int $productId, array $existing): array
    {
        $delete   = self::idList($delete, $pointer);
        $ownedIds = [];

        foreach ($existing as $option) {
            foreach ($option->values as $value) {
                $ownedIds[(int) $value->j2commerce_product_optionvalue_id] = true;
            }
        }

        foreach ($delete as $povId) {
            if (!isset($ownedIds[$povId])) {
                throw ProductApiException::single(404, $pointer, \sprintf('product_optionvalue_id %d does not belong to product %d.', $povId, $productId));
            }
        }

        return $delete;
    }

    /**
     * Applies a validated patch document. A deleted value takes the child variants built on it with
     * it, or nothing when one of them is on an order. Upserts match on (productoption_id, optionvalue_id);
     * `parent_values` are optionvalue ids of the parent option, translated to its product_optionvalue ids.
     *
     * @param array{attach: list<array>, detach: list<int>, upsert: list<array<string, mixed>>, delete: list<int>} $patch
     */
    public function applyOptionsPatch(int $productId, array $patch, int $isVariant, VariantMatrixService $variants): void
    {
        foreach ($patch['detach'] as $poId) {
            $this->detachOption($productId, $poId, $variants);
        }

        if ($patch['delete'] !== []) {
            $variants->deleteVariants($productId, $this->dependentsOfValues($productId, $patch['delete'], $variants), '/options/values/delete');
            $this->deleteOptionValues($patch['delete']);
        }

        if ($patch['attach'] !== []) {
            $this->writeOptions($productId, $patch['attach'], $isVariant);
        }

        if ($patch['upsert'] !== []) {
            $this->applyUpserts($productId, $patch['upsert'], $isVariant);
        }
    }

    /**
     * Upserts match on (productoption_id, optionvalue_id) against one fresh load of the product's
     * options; the modifiers written are read back in one query.
     *
     * @param list<array<string, mixed>> $upsert
     */
    private function applyUpserts(int $productId, array $upsert, int $isVariant): void
    {
        $options  = $this->productOptions($productId);
        $stored   = $this->valueIdsByOption($options);
        $parentOf = [];
        $byPoId   = [];

        foreach ($options as $option) {
            $poId            = (int) $option->j2commerce_productoption_id;
            $parentOf[$poId] = (int) $option->parent_id;
            $byPoId[$poId]   = $stored[(int) $option->option_id];
        }

        $written = [];

        foreach ($upsert as $value) {
            $poId = $value['productoption_id'];

            if (!isset($byPoId[$poId])) {
                throw ProductApiException::single(400, '/options/values/upsert', \sprintf('productoption %d is removed in this request.', $poId));
            }

            $attrs = array_diff_key($value, array_flip(['productoption_id', 'optionvalue_id', 'parent_values']));

            if (\array_key_exists('parent_values', $value)) {
                $parentIds = [];

                foreach ($value['parent_values'] as $id) {
                    $parentIds[] = $stored[$parentOf[$poId]][$id]
                        ?? throw ProductApiException::single(400, '/options/values/upsert', \sprintf('parent value %d is removed in this request.', $id));
                }

                $attrs['parent_optionvalue'] = implode(',', $parentIds);
            }

            $povId = $byPoId[$poId][$value['optionvalue_id']] ?? 0;

            if ($povId > 0) {
                $this->updateOptionValue($povId, $attrs, $isVariant === 1);
            } else {
                $povId = $this->addOptionValue($poId, $value['optionvalue_id'], $attrs, $isVariant === 1);
            }

            $written[$povId] = $this->valueColumns($attrs, $isVariant === 1);
        }

        $this->assertModifiersStored($written);
    }

    /** The columns of a value write: modifiers at the column scale, price zeroed on a matrix option. */
    private function valueColumns(array $attrs, bool $isVariant): array
    {
        $attrs = array_intersect_key($attrs, array_flip(self::VALUE_ATTRS));

        foreach (self::MODIFIER_COLUMNS as $key) {
            if (\array_key_exists($key, $attrs)) {
                $attrs[$key] = ProductApiSchema::decimal($attrs[$key], ProductApiSchema::MODIFIER_SCALE);
            }
        }

        if ($isVariant && \array_key_exists('product_optionvalue_price', $attrs)) {
            $attrs['product_optionvalue_price'] = 0;
        }

        return $attrs;
    }

    /**
     * One value entry of a modifier-form document, as columns. `parent_values` is kept as given
     * (optionvalue ids); the writer translates.
     *
     * @return array<string, mixed>
     */
    private function normaliseValue(mixed $value, string $pointer, bool $hasParent): array
    {
        if (self::uint($value) && (int) $value > 0) {
            return ['optionvalue_id' => (int) $value];
        }

        $keys   = array_keys(self::VALUE_KEYS);
        $keys[] = 'optionvalue_id';

        if ($hasParent) {
            $keys[] = 'parent_values';
        }

        if (!\is_array($value) || array_diff_key($value, array_flip($keys)) !== [] || !self::uint($value['optionvalue_id'] ?? null) || (int) ($value['optionvalue_id'] ?? 0) < 1) {
            throw ProductApiException::single(400, $pointer, 'accepts: ' . implode(', ', $keys) . '; optionvalue_id is required.');
        }

        $out = ['optionvalue_id' => (int) $value['optionvalue_id']] + $this->normaliseModifiers($value, $pointer);

        if (\array_key_exists('parent_values', $value)) {
            $out['parent_values'] = self::idList($value['parent_values'], $pointer . '/parent_values');
        }

        return $out;
    }

    /** @return array<string, mixed>  The modifier columns the entry sets, typed and formatted. */
    private function normaliseModifiers(array $value, string $pointer): array
    {
        $out = [];

        // Both columns are decimal(15,8); the formatted string is what reaches the row (ProductApiSchema::money()).
        foreach (['price', 'weight'] as $key) {
            if (\array_key_exists($key, $value)) {
                $out[self::VALUE_KEYS[$key]] = ProductApiSchema::money($value[$key], ProductApiSchema::MODIFIER_SCALE, ProductApiSchema::MODIFIER_MAX)
                    ?? throw ProductApiException::single(400, $pointer . '/' . $key, \sprintf('must be a number from 0 to %s.', number_format(ProductApiSchema::MODIFIER_MAX, ProductApiSchema::MODIFIER_SCALE, '.', '')));
            }
        }

        foreach (['prefix', 'weight_prefix'] as $key) {
            if (\array_key_exists($key, $value)) {
                if (!\in_array($value[$key], ['+', '-'], true)) {
                    throw ProductApiException::single(400, $pointer . '/' . $key, 'must be + or -.');
                }

                $out[self::VALUE_KEYS[$key]] = $value[$key];
            }
        }

        if (\array_key_exists('sku', $value)) {
            if (!\is_string($value['sku']) || mb_strlen($value['sku']) > 255) {
                throw ProductApiException::single(400, $pointer . '/sku', 'must be a string of 255 characters or fewer.');
            }

            $out[self::VALUE_KEYS['sku']] = trim($value['sku']);
        }

        if (\array_key_exists('default', $value)) {
            if (!\in_array($value['default'], [0, 1, '0', '1'], true)) {
                throw ProductApiException::single(400, $pointer . '/default', 'must be 0 or 1.');
            }

            $out[self::VALUE_KEYS['default']] = (int) $value['default'];
        }

        return $out;
    }

    /** @return list<int> */
    public static function idList(mixed $list, string $pointer): array
    {
        if (!\is_array($list) || !array_is_list($list)) {
            throw ProductApiException::single(400, $pointer, 'must be a list of ids.');
        }

        self::assertListSize($list, $pointer);

        $ids = [];

        foreach ($list as $value) {
            if (!self::uint($value) || (int) $value < 1) {
                throw ProductApiException::single(400, $pointer, 'must be a list of positive integer ids.');
            }

            $ids[] = (int) $value;
        }

        return array_values(array_unique($ids));
    }

    /** @throws ProductApiException  400 when the list is longer than ProductApiSchema::MAX_LIST. */
    public static function assertListSize(array $list, string $pointer): void
    {
        if (\count($list) > ProductApiSchema::MAX_LIST) {
            throw ProductApiException::single(400, $pointer, \sprintf('must not list more than %d entries.', ProductApiSchema::MAX_LIST));
        }
    }

    public static function uint(mixed $value): bool
    {
        return (\is_int($value) && $value >= 0) || (\is_string($value) && $value !== '' && ctype_digit($value));
    }
}
