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
use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/** Child variants of a matrix (variable) or pick-list (flexivariable) product; the caller owns the transaction. */
final class VariantMatrixService
{
    /** Keys of a create-time or patch-time `variants` entry every matrix type accepts. */
    public const ENTRY_KEYS = ['sku', 'upc', 'price', 'quantity', 'isdefault_variant', 'params'];

    private const VARIANT_TABLES = ['#__j2commerce_productquantities', '#__j2commerce_product_prices', '#__j2commerce_product_variant_optionvalues'];

    private MVCFactoryInterface $mvcFactory;

    private DatabaseInterface $db;

    public function __construct(?MVCFactoryInterface $mvcFactory = null, ?DatabaseInterface $db = null)
    {
        $this->mvcFactory = $mvcFactory ?: Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory();
        $this->db         = $db ?: Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * The one definition of a bare child row; `$overrides` lets a caller keep the column defaults
     * its editor has always written. A validated (string) price is bound as it is, a float at the
     * column's scale: insertObject() casts a float at ini precision and loses the fifth decimal.
     *
     * @param array<string, mixed> $overrides
     */
    public function childVariantRow(int $productId, string $sku, string|float $price, int $isDefault, string $upc = '', array $overrides = []): object
    {
        $now    = Factory::getDate()->toSql();
        $userId = (int) (Factory::getApplication()->getIdentity()?->id ?? 0);

        return (object) array_replace([
            'product_id'                    => $productId,
            'sku'                           => $sku,
            'upc'                           => $upc,
            'price'                         => \is_string($price) ? $price : number_format($price, ProductApiSchema::PRICE_SCALE, '.', ''),
            'pricing_calculator'            => 'standard',
            'shipping'                      => 0,
            'length'                        => 0,
            'width'                         => 0,
            'height'                        => 0,
            'length_class_id'               => ConfigHelper::getDefaultLengthClassId(),
            'weight'                        => 0,
            'weight_class_id'               => ConfigHelper::getDefaultWeightClassId(),
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
            'isdefault_variant'             => $isDefault,
            'is_master'                     => 0,
            'created_on'                    => $now,
            'created_by'                    => $userId,
            'modified_on'                   => $now,
            'modified_by'                   => $userId,
        ], $overrides);
    }

    /** The master SKU, else `PROD-<id>`; an empty master SKU never yields a bare `-V1`. */
    public function baseSku(int $productId, ?object $master = null): string
    {
        $sku = trim((string) (($master ?? $this->masterVariant($productId))?->sku ?? ''));

        return $sku !== '' ? $sku : 'PROD-' . $productId;
    }

    /** Variant columns plus `quantity`, or null when the product has no master. */
    public function masterVariant(int $productId): ?object
    {
        $db    = $this->db;
        $query = $this->variantQuery()
            ->where($db->quoteName('v.product_id') . ' = :pid')
            ->where($db->quoteName('v.is_master') . ' = 1')
            ->bind(':pid', $productId, ParameterType::INTEGER);

        return $db->setQuery($query, 0, 1)->loadObject();
    }

    /** Variant columns plus `quantity` and `j2commerce_productquantity_id`, when the child belongs to the product. */
    public function childVariant(int $productId, int $variantId): ?object
    {
        return $this->childVariants($productId, [$variantId])[$variantId] ?? null;
    }

    /**
     * @param list<int> $variantIds
     * @return array<int, object>  variant_id => columns plus `quantity` and `j2commerce_productquantity_id`, for the ids that are the product's children.
     */
    public function childVariants(int $productId, array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $db    = $this->db;
        $query = $this->variantQuery()
            ->where($db->quoteName('v.product_id') . ' = :pid')
            ->where($db->quoteName('v.is_master') . ' = 0')
            ->whereIn($db->quoteName('v.j2commerce_variant_id'), $variantIds)
            ->bind(':pid', $productId, ParameterType::INTEGER);

        $rows = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $rows[(int) $row->j2commerce_variant_id] = $row;
        }

        return $rows;
    }

    /** @return list<int>  Non-master variant ids of the product. */
    public function variantIds(int $productId): array
    {
        return array_keys($this->existingKeys($productId, false));
    }

    public function childVariantCount(int $productId): int
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :pid')
            ->where($db->quoteName('is_master') . ' = 0')
            ->bind(':pid', $productId, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult();
    }

    /** @return array<int, string>  variant_id => normalised combination CSV ('' when the child has no mapping). */
    public function existingKeys(int $productId, bool $mappedOnly = false): array
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['v.j2commerce_variant_id', 'm.product_optionvalue_ids']))
            ->from($db->quoteName('#__j2commerce_variants', 'v'))
            ->join($mappedOnly ? 'INNER' : 'LEFT', $db->quoteName('#__j2commerce_product_variant_optionvalues', 'm') . ' ON ' . $db->quoteName('m.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id'))
            ->where($db->quoteName('v.product_id') . ' = :pid')
            ->where($db->quoteName('v.is_master') . ' = 0')
            ->order($db->quoteName('v.j2commerce_variant_id'))
            ->bind(':pid', $productId, ParameterType::INTEGER);

        $keys = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $keys[(int) $row->j2commerce_variant_id] = ProductHelper::normaliseOptionvalueKey((string) ($row->product_optionvalue_ids ?? ''));
        }

        return $keys;
    }

    /**
     * @param list<int> $variantIds
     * @return list<int>  The given ids that appear on an order line.
     */
    public function orderedVariantIds(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('variant_id'))
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->whereIn($db->quoteName('variant_id'), $variantIds);

        return array_map('intval', $db->setQuery($query)->loadColumn());
    }

    /**
     * Removes every child of the product with its stock, price rules and mapping, or nothing: one
     * child on an order refuses the whole set. Returns the number removed.
     *
     * @param bool $refreshIndex  False when a generate() follows, which refreshes once itself.
     * @throws ProductApiException  409 naming the ordered children.
     */
    public function deleteChildren(int $productId, bool $refreshIndex = true): int
    {
        $ids = $this->variantIds($productId);

        if ($ids === []) {
            return 0;
        }

        if ($ordered = $this->orderedVariantIds($ids)) {
            throw ProductApiException::single(409, '/variants/delete', \sprintf('variants %s appear on orders and cannot be deleted.', implode(', ', $ordered)));
        }

        $this->purge($productId, $ids);

        if ($refreshIndex) {
            $this->refreshPriceIndex($productId);
        }

        return \count($ids);
    }

    /**
     * Removes the children named with everything keyed to them (stock, price rules, mapping, and a
     * flexivariable child's own pick values), or nothing: one child on an order refuses the whole
     * set. Ids that are not the product's children are ignored. Returns the ids removed.
     *
     * @param list<int> $variantIds
     * @param bool      $refreshIndex  False when a save that refreshes the price index follows.
     * @return list<int>
     * @throws ProductApiException  409 at `$pointer` naming the ordered children.
     */
    public function deleteVariants(int $productId, array $variantIds, string $pointer = '/variants/delete', bool $refreshIndex = true): array
    {
        $owned = array_keys($this->childVariants($productId, array_values(array_unique(array_map('intval', $variantIds)))));

        if ($owned === []) {
            return [];
        }

        if ($ordered = $this->orderedVariantIds($owned)) {
            throw ProductApiException::single(409, $pointer, \sprintf('variants %s appear on orders and cannot be deleted.', implode(', ', $ordered)));
        }

        $this->purge($productId, $owned);
        $this->ensureDefault($productId);

        if ($refreshIndex) {
            $this->refreshPriceIndex($productId);
        }

        return $owned;
    }

    /** @throws ProductApiException  404 when the id is not a child of the product; 409 when it is on an order. */
    public function deleteVariant(int $productId, int $variantId): void
    {
        if ($this->deleteVariants($productId, [$variantId]) === []) {
            throw ProductApiException::single(404, '/variants/delete', \sprintf('variant %d is not a child variant of product %d.', $variantId, $productId));
        }
    }

    /**
     * The deletes of a set of children already known to be the product's and off every order. A
     * flexivariable child's product_optionvalues rows (one per pick) go with it, except the ones
     * another child of the product still maps: a product converted from variable shares them.
     *
     * @param list<int> $variantIds
     */
    private function purge(int $productId, array $variantIds): void
    {
        $db = $this->db;

        if ($this->productType($productId) === 'flexivariable') {
            $remaining = array_values(array_diff(array_keys($this->existingKeys($productId, true)), $variantIds));
            $povIds    = array_values(array_diff($this->mappedValueIds($variantIds), $this->mappedValueIds($remaining)));

            if ($povIds !== []) {
                $query = $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_product_optionvalues'))
                    ->whereIn($db->quoteName('j2commerce_product_optionvalue_id'), $povIds);
                $db->setQuery($query)->execute();
            }
        }

        foreach (self::VARIANT_TABLES as $table) {
            $query = $db->getQuery(true)
                ->delete($db->quoteName($table))
                ->whereIn($db->quoteName('variant_id'), $variantIds);
            $db->setQuery($query)->execute();
        }

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_variants'))
            ->whereIn($db->quoteName('j2commerce_variant_id'), $variantIds)
            ->where($db->quoteName('product_id') . ' = :pid')
            ->where($db->quoteName('is_master') . ' = 0')
            ->bind(':pid', $productId, ParameterType::INTEGER);
        $db->setQuery($query)->execute();
    }

    /**
     * @param list<int> $variantIds
     * @return list<int>  Every product_optionvalues id in the mapping CSVs of the children named, once.
     */
    private function mappedValueIds(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName('product_optionvalue_ids'))
            ->from($db->quoteName('#__j2commerce_product_variant_optionvalues'))
            ->whereIn($db->quoteName('variant_id'), $variantIds);
        $parts = [];

        foreach ($db->setQuery($query)->loadColumn() as $csv) {
            $parts[] = ProductHelper::normaliseOptionvalueIds((string) $csv);
        }

        return array_values(array_unique(array_merge(...$parts)));
    }

    private function productType(int $productId): string
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName('product_type'))
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('j2commerce_product_id') . ' = :pid')
            ->bind(':pid', $productId, ParameterType::INTEGER);

        return (string) $db->setQuery($query)->loadResult();
    }

    /**
     * Creates child variants and refreshes the price index once.
     *
     * With `$requested` null: the admin generator, every combination of the product's options at
     * the master price plus the value modifiers, existing combinations skipped, no stock rows (the
     * form save that follows writes those).
     *
     * With `$requested`: exactly the named combinations, each a full row with its stock row, so no
     * second product save is needed. Entries: `{product_optionvalue_ids: list<int>, sku, upc, price
     * (validated string), quantity?, isdefault_variant, params, pointer?}`; `quantity` sent turns stock
     * management on for the child, `shipping` follows the master, and `pointer` is where an error
     * about the entry is reported (`/variants/<n>` when absent).
     *
     * @param list<string> $reservedSkus  SKUs the same write gives other children (renames applied after the adds); a generated SKU avoids them.
     * @return array{created: int, skipped: int, variant_ids: list<int>}
     * @throws ProductApiException  409 when a requested combination already exists.
     */
    public function generate(int $productId, ?array $requested = null, array $reservedSkus = []): array
    {
        $byKey   = array_flip($this->existingKeys($productId, true));
        $master  = $this->masterVariant($productId);
        $entries = $requested === null ? $this->cartesianEntries($productId, $master) : $this->requestedEntries($productId, $requested, $byKey, $reservedSkus, $master);
        $default = $requested === null && !$this->hasDefault($productId) ? 0 : null;
        $result  = $this->insertEntries($productId, $entries, $byKey, $master, $requested !== null, $default);

        if ($default > 0) {
            $this->setDefault($productId, $default);
        }

        $this->ensureDefault($productId);
        $this->refreshPriceIndex($productId);

        return $result;
    }

    /**
     * The insert loop: an existing combination is skipped, a failed insert is skipped on the admin
     * path and rethrown on the API path, and the API path's prices are read back once.
     *
     * @param array<string, int> $byKey    combination key => variant id of the existing children.
     * @param int|null           $default  0 when the first child created is to be the default; set to the id that was.
     * @return array{created: int, skipped: int, variant_ids: list<int>}
     */
    private function insertEntries(int $productId, array $entries, array $byKey, ?object $master, bool $requested, ?int &$default): array
    {
        $result = ['created' => 0, 'skipped' => 0, 'variant_ids' => []];
        $prices = [];

        foreach ($entries as $entry) {
            if ($entry['key'] === '') {
                continue;
            }

            if (isset($byKey[$entry['key']])) {
                $result['skipped']++;

                continue;
            }

            $isDefault = $requested ? (int) ($entry['isdefault_variant'] ?? 0) : (int) ($default === 0 && $result['created'] === 0);

            try {
                $variantId = $this->createChild($productId, $entry, $isDefault, $master, $requested);
            } catch (\Throwable $e) {
                if ($requested) {
                    throw $e;
                }

                $result['skipped']++;

                continue;
            }

            if ($requested) {
                $prices[$variantId] = $entry['price'];
            }

            if ($isDefault === 1) {
                $default = $variantId;
            }

            $byKey[$entry['key']]    = $variantId;
            $result['variant_ids'][] = $variantId;
            $result['created']++;
        }

        $this->assertPricesStored($prices);

        return $result;
    }

    /** One child from a generator entry with its mapping and, on the API path, params and stock row; returns the id. */
    private function createChild(int $productId, array $entry, int $isDefault, ?object $master, bool $requested): int
    {
        $row = $this->childVariantRow($productId, (string) $entry['sku'], $entry['price'], $isDefault, (string) ($entry['upc'] ?? ''), [
            'shipping'     => (int) ($master?->shipping ?? 0),
            'manage_stock' => (int) isset($entry['quantity']),
        ]);

        if ($requested) {
            $row->params = json_encode($entry['params'] ?? [], JSON_THROW_ON_ERROR);
        }

        $variantId = $this->insertChild($row, $entry['key']);

        if ($requested) {
            $this->writeQuantity($variantId, (int) ($entry['quantity'] ?? 0));
        }

        return $variantId;
    }

    /**
     * The requested entries with their combination key and, where no SKU was given, `<base>-V<n>`
     * with the smallest n free on the product: its stored children (a renamed child still holds
     * its old SKU until the update after the adds), the request's own SKUs and `$reservedSkus`.
     *
     * The explicit SKUs were checked with the document, against the store less the children the
     * same write renames or deletes; only the generated ones are checked here, store-wide, as a
     * backstop for a base shared with another product.
     *
     * @param array<string, int> $existing      combination key => variant id, for the 409.
     * @param list<string>       $reservedSkus
     */
    private function requestedEntries(int $productId, array $requested, array $existing, array $reservedSkus, ?object $master): array
    {
        $base      = $this->baseSku($productId, $master);
        $taken     = $this->takenSuffixes($productId, $base, array_merge(array_column($requested, 'sku'), $reservedSkus));
        $generated = [];

        foreach ($requested as $n => $entry) {
            $pointer              = (string) ($entry['pointer'] ?? '/variants/' . $n);
            $requested[$n]['key'] = ProductHelper::normaliseOptionvalueKey($entry['product_optionvalue_ids']);

            if (isset($existing[$requested[$n]['key']])) {
                throw ProductApiException::single(409, $pointer, 'a variant with this option combination already exists.');
            }

            if (($entry['sku'] ?? '') !== '') {
                continue;
            }

            $suffix = 1;

            while (isset($taken[$suffix])) {
                $suffix++;
            }

            $taken[$suffix]                   = true;
            $requested[$n]['sku']             = $base . '-V' . $suffix;
            $generated[$pointer . '/sku']     = $requested[$n]['sku'];
        }

        $this->assertSkusFree($generated);

        return $requested;
    }

    /**
     * @param list<string> $skus  SKUs of the request itself.
     * @return array<int, true>  The n of every `<base>-V<n>` among the product's stored children and `$skus`, keyed by n.
     */
    private function takenSuffixes(int $productId, string $base, array $skus): array
    {
        $db      = $this->db;
        $pattern = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $base) . '-V%';
        $query   = $db->getQuery(true)
            ->select($db->quoteName('sku'))
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :pid')
            ->where($db->quoteName('sku') . ' LIKE :pattern')
            ->bind(':pid', $productId, ParameterType::INTEGER)
            ->bind(':pattern', $pattern);
        $regex = '/^' . preg_quote($base, '/') . '-V(\d+)$/i';
        $taken = [];

        foreach (array_merge($db->setQuery($query)->loadColumn(), $skus) as $sku) {
            if (preg_match($regex, (string) $sku, $m)) {
                $taken[(int) $m[1]] = true;
            }
        }

        return $taken;
    }

    /** Inserts the row and its combination mapping; returns the new variant id. */
    private function insertChild(object $row, string $key): int
    {
        $this->db->insertObject('#__j2commerce_variants', $row, 'j2commerce_variant_id');
        $variantId = (int) $row->j2commerce_variant_id;

        $mapping = (object) ['variant_id' => $variantId, 'product_optionvalue_ids' => $key];
        $this->db->insertObject('#__j2commerce_product_variant_optionvalues', $mapping);

        return $variantId;
    }

    /**
     * One flexivariable child from hand-picked values, the admin "Add Variant" shape: a fresh
     * product_optionvalues row per pick, the variant (`Flexi_<id>` unless a SKU is given), its
     * stock row and its mapping. Returns the new variant id.
     *
     * @param array<int, int>      $picks         productoption_id => optionvalue_id; a 0 value means "any".
     * @param array<string, mixed> $fields        price (validated string), upc, quantity, isdefault_variant, params for the API path.
     * @param bool                 $refreshIndex  False inside a loop that refreshes once after it.
     * @param object|null          $master        The product's master variant when the caller already holds it.
     * @param array<int, string>|null $prices     A loop's collector: variant_id => price written, read back by the caller once with assertPricesStored(); null reads this child back at once.
     */
    public function addCombination(int $productId, array $picks, ?string $sku = null, array $fields = [], bool $refreshIndex = true, ?object $master = null, ?array &$prices = null): int
    {
        $db  = $this->db;
        $row = $this->pickRow($productId, $fields, $master);

        if ($sku !== null && $sku !== '') {
            $row->sku = $sku;
        }

        $variantId = $this->insertChild($row, ProductHelper::normaliseOptionvalueKey($this->insertPickValues($picks)));

        if ($row->sku === '') {
            $newSku = 'Flexi_' . $variantId;
            $query  = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_variants'))
                ->set($db->quoteName('sku') . ' = :sku')
                ->where($db->quoteName('j2commerce_variant_id') . ' = :vid')
                ->bind(':sku', $newSku)
                ->bind(':vid', $variantId, ParameterType::INTEGER);
            $db->setQuery($query)->execute();
        }

        $this->writeQuantity($variantId, (int) ($fields['quantity'] ?? 0));

        if ($fields !== []) {
            if ($prices === null) {
                $this->assertPriceStored($variantId, $row->price);
            } else {
                $prices[$variantId] = $row->price;
            }
        }

        if ((int) $row->isdefault_variant === 1) {
            $this->setDefault($productId, $variantId);
        }

        if ($refreshIndex) {
            $this->refreshPriceIndex($productId);
        }

        return $variantId;
    }

    /** The flexivariable child row, with the column defaults the admin editor has always written. */
    private function pickRow(int $productId, array $fields, ?object $master): object
    {
        $master ??= $this->masterVariant($productId);
        $row   = $this->childVariantRow($productId, '', $fields['price'] ?? 0.0, (int) ($fields['isdefault_variant'] ?? 0), (string) ($fields['upc'] ?? ''), [
            'params'                        => json_encode(['variant_main_image' => ''] + ($fields['params'] ?? []), JSON_THROW_ON_ERROR),
            'shipping'                      => (int) ($master?->shipping ?? 0),
            'manage_stock'                  => (int) isset($fields['quantity']),
            'length'                        => '0.00000',
            'width'                         => '0.00000',
            'height'                        => '0.00000',
            'weight'                        => '0.00000',
            'min_sale_qty'                  => '0.00000',
            'max_sale_qty'                  => '0.00000',
            'notify_qty'                    => '0.00000',
            'use_store_config_min_sale_qty' => 0,
            'use_store_config_max_sale_qty' => 0,
            'use_store_config_notify_qty'   => 0,
        ]);
        unset($row->min_out_qty, $row->use_store_config_min_out_qty, $row->sold);

        return $row;
    }

    /**
     * @param array<int, int> $picks  productoption_id => optionvalue_id.
     * @return list<int>  The new product_optionvalue ids, one per pick.
     */
    private function insertPickValues(array $picks): array
    {
        $valueIds = [];

        foreach ($picks as $productOptionId => $optionValueId) {
            $value = (object) [
                'productoption_id'                  => (int) $productOptionId,
                'optionvalue_id'                    => (int) $optionValueId,
                'parent_optionvalue'                => '',
                'product_optionvalue_price'         => 0,
                'product_optionvalue_prefix'        => '+',
                'product_optionvalue_weight'        => 0,
                'product_optionvalue_weight_prefix' => '+',
                'product_optionvalue_sku'           => '',
                'product_optionvalue_default'       => 0,
                'ordering'                          => 0,
                'product_optionvalue_attribs'       => '{}',
            ];
            $this->db->insertObject('#__j2commerce_product_optionvalues', $value, 'j2commerce_product_optionvalue_id');
            $valueIds[] = (int) $value->j2commerce_product_optionvalue_id;
        }

        return $valueIds;
    }

    /**
     * Refuses a SKU already on another variant of the store, or repeated within `$skus`. Empty SKUs
     * are ignored; the store check is the column's collation, the in-document check foldSku().
     *
     * @param array<int|string, string> $skus  A string key is the JSON pointer reported for that SKU; an int key n reports `/variants/n/sku`.
     * @param list<int> $excludeVariantIds     Variants whose stored SKU does not count (being replaced or deleted by the same write).
     * @throws ProductApiException  400 at the pointer of the SKU.
     */
    public function assertSkusFree(array $skus, array $excludeVariantIds = []): void
    {
        $seen = [];

        foreach ($skus as $key => $sku) {
            $sku = trim((string) $sku);

            if ($sku === '') {
                continue;
            }

            $at     = \is_string($key) ? $key : '/variants/' . $key . '/sku';
            $folded = self::foldSku($sku);

            if (isset($seen[$folded])) {
                throw ProductApiException::single(400, $at, \sprintf('repeats the SKU of %s.', $seen[$folded]['at']));
            }

            $seen[$folded] = ['at' => $at, 'sku' => $sku];
        }

        if ($seen === []) {
            return;
        }

        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_variant_id', 'sku']))
            ->from($db->quoteName('#__j2commerce_variants'))
            ->whereIn($db->quoteName('sku'), array_column($seen, 'sku'), ParameterType::STRING);
        $excluded = array_flip(array_map('intval', $excludeVariantIds));

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $variantId = (int) $row->j2commerce_variant_id;

            if (isset($excluded[$variantId])) {
                continue;
            }

            $at = $seen[self::foldSku((string) $row->sku)]['at'] ?? $this->skuPointer($seen, $variantId);

            if ($at !== null) {
                throw ProductApiException::single(400, $at, \sprintf('the SKU is already used by variant %d.', $variantId));
            }

            // The row matched a SKU of the request and no longer does (changed between the two queries): every pointer is reported.
            throw new ProductApiException(400, array_map(
                static fn (array $entry): array => ['pointer' => $entry['at'], 'detail' => \sprintf('one of the SKUs sent is already used by variant %d.', $variantId)],
                array_values($seen)
            ));
        }
    }

    /**
     * Lower-cased with the combining marks dropped: the case and accent folding of a `_ci` collation,
     * for the in-document check and for mapping a store hit back to its pointer. The collation's
     * further equivalences (expansions such as ß = ss) are resolved by skuPointer().
     */
    private static function foldSku(string $sku): string
    {
        $sku = mb_strtolower($sku);

        // ext-intl is optional in Joomla 6; without it accent folding is skipped and skuPointer() falls back to the DB collation.
        // DELETE WHEN: ext-intl is a hard Joomla requirement.
        if (class_exists(\Normalizer::class)) {
            $sku = preg_replace('/\p{Mn}+/u', '', (string) \Normalizer::normalize($sku, \Normalizer::FORM_D)) ?? $sku;
        }

        return $sku;
    }

    /**
     * The pointer of the request SKU the column's collation equates with the variant's, asked of the
     * database; null when none does.
     *
     * @param array<string, array{at: string, sku: string}> $seen
     */
    private function skuPointer(array $seen, int $variantId): ?string
    {
        $db = $this->db;

        foreach ($seen as $entry) {
            $sku   = $entry['sku'];
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__j2commerce_variants'))
                ->where($db->quoteName('j2commerce_variant_id') . ' = :vid')
                ->where($db->quoteName('sku') . ' = :sku')
                ->bind(':vid', $variantId, ParameterType::INTEGER)
                ->bind(':sku', $sku);

            if ((int) $db->setQuery($query)->loadResult() > 0) {
                return $entry['at'];
            }
        }

        return null;
    }

    /**
     * One check for the whole patch. An update that leaves a variant's stored SKU as it is passes
     * even when that SKU is duplicated elsewhere; a variant whose SKU is being replaced, or that is
     * being deleted, no longer holds its old SKU.
     *
     * @param array<string, array<string, mixed>> $updates    pointer of the sku => normalised update entry (with `sku` and `id`).
     * @param list<array<string, mixed>>          $adds       Normalised adds, in the order of `$positions`.
     * @param list<int>                           $positions  Upsert index of each add.
     * @param list<int>                           $delete
     */
    private function assertPatchSkus(int $productId, array $updates, array $adds, array $positions, array $delete, string $pointer): void
    {
        $current = $updates === [] ? [] : $this->childVariants($productId, array_values(array_map(static fn (array $e): int => $e['id'], $updates)));
        $skus    = [];
        $exclude = $delete;

        foreach ($updates as $at => $entry) {
            if (trim((string) ($current[$entry['id']]->sku ?? '')) === $entry['sku']) {
                continue;
            }

            $skus[$at] = $entry['sku'];
            $exclude[] = $entry['id'];
        }

        foreach ($adds as $i => $entry) {
            $skus[$pointer . '/upsert/' . $positions[$i] . '/sku'] = (string) ($entry['sku'] ?? '');
        }

        $this->assertSkusFree($skus, $exclude);
    }

    /**
     * The create-time store check of a validated document's SKUs: the entries' SKUs against the
     * store and each other. The adds of a patch are checked by validateVariantsPatch() instead.
     *
     * @param list<array<string, mixed>> $entries  Normalised create-time entries; their position is the pointer index.
     * @throws ProductApiException  400 at `<pointer>/<n>/sku`.
     */
    public function assertEntrySkus(array $entries, string $pointer): void
    {
        $skus = [];

        foreach ($entries as $n => $entry) {
            $skus[$pointer . '/' . $n . '/sku'] = (string) ($entry['sku'] ?? '');
        }

        $this->assertSkusFree($skus);
    }

    /** @throws \RuntimeException  Rolled back and reported by the model's transaction. */
    public function assertPriceStored(int $variantId, string $price): void
    {
        $this->assertPricesStored([$variantId => $price]);
    }

    /**
     * Reads the prices back in one query and refuses the write when a row does not carry the value
     * sent, so a store the driver clamped (non-strict sql_mode) or a table that returned false
     * quietly cannot answer 200.
     *
     * @param array<int, string> $prices  variant_id => the formatted price written.
     * @throws \RuntimeException
     */
    public function assertPricesStored(array $prices): void
    {
        if ($prices === []) {
            return;
        }

        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_variant_id', 'price']))
            ->from($db->quoteName('#__j2commerce_variants'))
            ->whereIn($db->quoteName('j2commerce_variant_id'), array_keys($prices));
        $stored = $db->setQuery($query)->loadAssocList('j2commerce_variant_id', 'price');

        foreach ($prices as $variantId => $price) {
            if (!isset($stored[$variantId]) || number_format((float) $stored[$variantId], ProductApiSchema::PRICE_SCALE, '.', '') !== $price) {
                throw new \RuntimeException(\sprintf('Variant %d price %s was stored as %s.', $variantId, $price, $stored[$variantId] ?? 'no row'));
            }
        }
    }

    /** Makes one child the default and clears the flag on its siblings and the master. */
    public function setDefault(int $productId, int $variantId): void
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_variants'))
            ->set($db->quoteName('isdefault_variant') . ' = CASE WHEN ' . $db->quoteName('j2commerce_variant_id') . ' = :vid THEN 1 ELSE 0 END')
            ->where($db->quoteName('product_id') . ' = :pid')
            ->bind(':vid', $variantId, ParameterType::INTEGER)
            ->bind(':pid', $productId, ParameterType::INTEGER);
        $db->setQuery($query)->execute();
    }

    /** Exactly one default while children exist: the lowest id is promoted when none carries the flag. */
    public function ensureDefault(int $productId): void
    {
        if ($this->hasDefault($productId)) {
            return;
        }

        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('MIN(' . $db->quoteName('j2commerce_variant_id') . ')')
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :pid')
            ->where($db->quoteName('is_master') . ' = 0')
            ->bind(':pid', $productId, ParameterType::INTEGER);

        if ($first = (int) $db->setQuery($query)->loadResult()) {
            $this->setDefault($productId, $first);
        }
    }

    /** The product's price index row: min/max over priced non-master children, one aggregate query. */
    public function refreshPriceIndex(int $productId): void
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('MIN(' . $db->quoteName('price') . ') AS min_price, MAX(' . $db->quoteName('price') . ') AS max_price')
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :pid')
            ->where($db->quoteName('is_master') . ' = 0')
            ->where($db->quoteName('price') . ' > 0')
            ->bind(':pid', $productId, ParameterType::INTEGER);
        $range = $db->setQuery($query)->loadAssoc() ?: [];

        $values = (object) [
            'product_id' => $productId,
            'min_price'  => $range['min_price'] ?? 0,
            'max_price'  => $range['max_price'] ?? 0,
        ];

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_productprice_index'))
            ->where($db->quoteName('product_id') . ' = :pid')
            ->bind(':pid', $productId, ParameterType::INTEGER);

        if ((int) $db->setQuery($query)->loadResult() > 0) {
            $db->updateObject('#__j2commerce_productprice_index', $values, 'product_id');
        } else {
            $db->insertObject('#__j2commerce_productprice_index', $values);
        }
    }

    /**
     * The children as GET renders them.
     *
     * @param list<object>|null $options  ProductOptionService::productOptions() rows, when the caller already has them.
     * @return list<object>  {j2commerce_variant_id, sku, upc, price, quantity, isdefault_variant, params, options: [{productoption_id, option_id, product_optionvalue_id, optionvalue_id}]}
     */
    public function variantsOf(int $productId, ?array $options = null): array
    {
        $db    = $this->db;
        $query = $this->variantQuery()
            ->select($db->quoteName('m.product_optionvalue_ids'))
            ->join('LEFT', $db->quoteName('#__j2commerce_product_variant_optionvalues', 'm') . ' ON ' . $db->quoteName('m.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id'))
            ->where($db->quoteName('v.product_id') . ' = :pid')
            ->where($db->quoteName('v.is_master') . ' = 0')
            ->order($db->quoteName('v.j2commerce_variant_id'))
            ->bind(':pid', $productId, ParameterType::INTEGER);
        $rows = $db->setQuery($query)->loadObjectList();

        if ($rows === []) {
            return [];
        }

        $values = $this->valueIndex($options ?? (new ProductOptionService($this->mvcFactory, $this->db))->productOptions($productId));
        $out    = [];

        foreach ($rows as $row) {
            $picked = [];

            foreach (ProductHelper::normaliseOptionvalueIds((string) ($row->product_optionvalue_ids ?? '')) as $id) {
                if (isset($values[$id])) {
                    $picked[] = $values[$id];
                }
            }

            $params = json_decode((string) $row->params, false);

            $out[] = (object) [
                'j2commerce_variant_id' => (int) $row->j2commerce_variant_id,
                'sku'                   => $row->sku,
                'upc'                   => $row->upc,
                'price'                 => $row->price,
                'quantity'              => (int) ($row->quantity ?? 0),
                'isdefault_variant'     => (int) $row->isdefault_variant,
                'params'                => \is_object($params) ? $params : new \stdClass(),
                'options'               => $picked,
            ];
        }

        return $out;
    }

    /**
     * @param list<object> $options  ProductOptionService::productOptions() rows.
     * @return array<int, object>  product_optionvalue_id => the option/value ids GET renders for a pick.
     */
    private function valueIndex(array $options): array
    {
        $values = [];

        foreach ($options as $option) {
            foreach ($option->values as $value) {
                $values[(int) $value->j2commerce_product_optionvalue_id] = (object) [
                    'productoption_id'       => (int) $option->j2commerce_productoption_id,
                    'option_id'              => (int) $option->option_id,
                    'product_optionvalue_id' => (int) $value->j2commerce_product_optionvalue_id,
                    'optionvalue_id'         => (int) $value->optionvalue_id,
                ];
            }
        }

        return $values;
    }

    /**
     * Checks a create-time matrix `variants` document against the normalised options document.
     * Each entry names one value per option: `{options: [optionvalue ids], sku?, upc?, price, quantity?, isdefault_variant?, params?}`
     * plus the `$extraKeys` the type's schema declares (validated by the handler). The SKUs are not
     * checked against the store here: the create path calls assertEntrySkus() on the result.
     *
     * @param list<array{option_id: int, values: list<array{optionvalue_id: int}>}> $options
     *
     * @return list<array<string, mixed>>  Entries with `options` as a sorted list of optionvalue ids.
     * @throws ProductApiException
     */
    public function validateMatrixDocument(array $options, mixed $variants, string $pointer, array $extraKeys = [], array $positions = []): array
    {
        if (!\is_array($variants) || $variants === [] || !array_is_list($variants)) {
            throw ProductApiException::single(400, $pointer, 'must be a non-empty list of {"options": [one optionvalue id per option], "price", ...}.');
        }

        ProductOptionService::assertListSize($variants, $pointer);

        $valueOption = [];

        foreach ($options as $option) {
            foreach ($option['values'] as $entry) {
                $valueOption[$entry['optionvalue_id']] = $option['option_id'];
            }
        }

        $keys = array_merge(['options'], self::ENTRY_KEYS, $extraKeys);
        $seen = [];
        $out  = [];

        foreach ($variants as $n => $variant) {
            $at = $pointer . '/' . ($positions[$n] ?? $n);

            if (!\is_array($variant) || array_diff_key($variant, array_flip($keys)) !== []) {
                throw ProductApiException::single(400, $at, 'accepts: ' . implode(', ', $keys) . '.');
            }

            $pick = $this->matrixPick($variant, $at, $valueOption, \count($options));
            $key  = implode(',', $pick);

            if (isset($seen[$key])) {
                throw ProductApiException::single(400, $at, \sprintf('repeats the option combination of %s/%s.', $pointer, $seen[$key]));
            }

            $seen[$key]       = $positions[$n] ?? $n;
            $entry            = $this->normaliseEntry($variant, $at, true);
            $entry['options'] = $pick;
            $out[]            = $entry;
        }

        $this->applyDefaultRule($out, $pointer);

        return $out;
    }

    /**
     * @param array<int, int> $valueOption  optionvalue_id => option_id of the listed values.
     * @return list<int>  The entry's optionvalue ids, one listed value per option, sorted.
     */
    private function matrixPick(array $variant, string $at, array $valueOption, int $optionCount): array
    {
        $pick     = ProductOptionService::idList($variant['options'] ?? null, $at . '/options');
        $byOption = [];

        foreach ($pick as $valueId) {
            $byOption[$valueOption[$valueId] ?? 0] = $valueId;
        }

        if (isset($byOption[0]) || \count($byOption) !== $optionCount || \count($pick) !== $optionCount) {
            throw ProductApiException::single(400, $at . '/options', 'must name one listed value of each option.');
        }

        sort($pick);

        return $pick;
    }

    /**
     * Checks a create-time flexivariable `variants` document: entries are picks, not a matrix.
     * `{picks: {"<option_id>": optionvalue_id}, sku?, upc?, price, quantity?, isdefault_variant?, params?}`.
     * The SKUs are not checked against the store here: the create path calls assertEntrySkus() on the result.
     *
     * @param list<array{option_id: int, values: list<array{optionvalue_id: int}>}> $options
     *
     * @return list<array<string, mixed>>  Entries with `picks` as option_id => optionvalue_id.
     */
    public function validatePicksDocument(array $options, mixed $variants, string $pointer, array $extraKeys = [], array $positions = []): array
    {
        if (!\is_array($variants) || $variants === [] || !array_is_list($variants)) {
            throw ProductApiException::single(400, $pointer, 'must be a non-empty list of {"picks": {"<option_id>": optionvalue_id}, "price", ...}.');
        }

        ProductOptionService::assertListSize($variants, $pointer);

        $allowed = [];

        foreach ($options as $option) {
            $allowed[$option['option_id']] = array_column($option['values'], 'optionvalue_id');
        }

        $keys  = array_merge(['picks'], self::ENTRY_KEYS, $extraKeys);
        $seen  = [];
        $out   = [];
        $total = 0;

        foreach ($variants as $n => $variant) {
            $at = $pointer . '/' . ($positions[$n] ?? $n);

            if (!\is_array($variant) || array_diff_key($variant, array_flip($keys)) !== [] || !\is_array($variant['picks'] ?? null) || $variant['picks'] === []) {
                throw ProductApiException::single(400, $at, 'accepts: ' . implode(', ', $keys) . '; picks is required.');
            }

            $total += \count($variant['picks']);

            if ($total > ProductApiSchema::MAX_LIST) {
                throw ProductApiException::single(400, $at . '/picks', \sprintf('the picks of all entries must not add up to more than %d.', ProductApiSchema::MAX_LIST));
            }

            $picks = $this->picksOf($variant['picks'], $at, $allowed);
            $key   = json_encode($picks, JSON_THROW_ON_ERROR);

            if (isset($seen[$key])) {
                throw ProductApiException::single(400, $at, \sprintf('repeats the picks of %s/%s.', $pointer, $seen[$key]));
            }

            $seen[$key]     = $positions[$n] ?? $n;
            $entry          = $this->normaliseEntry($variant, $at, true);
            $entry['picks'] = $picks;
            $out[]          = $entry;
        }

        $this->applyDefaultRule($out, $pointer);

        return $out;
    }

    /**
     * @param array<int, list<int>> $allowed  option_id => the optionvalue ids a pick may name.
     * @return array<int, int>  option_id => optionvalue_id, sorted by option; 0 is the admin editor's "Any <option>" pick.
     */
    private function picksOf(array $picks, string $at, array $allowed): array
    {
        $out = [];

        foreach ($picks as $optionId => $valueId) {
            if (!ProductOptionService::uint($optionId) || !isset($allowed[(int) $optionId]) || !ProductOptionService::uint($valueId)
                || ((int) $valueId !== 0 && !\in_array((int) $valueId, $allowed[(int) $optionId], true))) {
                throw ProductApiException::single(400, $at . '/picks', \sprintf('"%s" must be a listed option with one of its listed values, or 0 for any.', (string) $optionId));
            }

            $out[(int) $optionId] = (int) $valueId;
        }

        ksort($out);

        return $out;
    }

    /**
     * Checks a patch-time `variants` document: `{upsert: [{id, ...} | {options|picks, ...}], delete: [ids]}`.
     * An entry with an id changes that child and takes no `options`/`picks`; one without adds a child.
     *
     * @param string $mode  'matrix' (entries without id carry `options`) or 'picks'.
     *
     * @return array{update: list<array<string, mixed>>, add: list<array<string, mixed>>, delete: list<int>}
     */
    public function validateVariantsPatch(int $productId, mixed $doc, string $pointer, string $mode, array $extraKeys = []): array
    {
        [$upsert, $delete, $owned] = $this->patchParts($productId, $doc, $pointer);

        $update     = [];
        $add        = [];
        $skuUpdates = [];
        $pickKey    = $mode === 'picks' ? 'picks' : 'options';
        $addKeys    = array_merge([$pickKey], self::ENTRY_KEYS, $extraKeys);
        $idKeys     = array_merge(['id'], self::ENTRY_KEYS, $extraKeys);

        foreach ($upsert as $n => $entry) {
            $at = $pointer . '/upsert/' . $n;

            if (!\is_array($entry)) {
                throw ProductApiException::single(400, $at, 'accepts: ' . implode(', ', array_unique(array_merge($idKeys, $addKeys))) . '.');
            }

            if (\array_key_exists('id', $entry)) {
                $normalised = $this->validateUpdateEntry($entry, $at, $owned, $delete, $idKeys, $pickKey);
                $update[]   = $normalised;

                if (isset($normalised['sku'])) {
                    $skuUpdates[$at . '/sku'] = $normalised;
                }
            } else {
                $add[$n] = $entry;
            }
        }

        $positions = array_keys($add);
        $add       = $this->validateAdds($productId, $add, $pointer, $mode, $extraKeys);

        $this->assertPatchSkus($productId, $skuUpdates, $add, $positions, $delete, $pointer);

        $defaults = \count(array_filter(array_merge($update, $add), static fn (array $e): bool => ($e['isdefault_variant'] ?? 0) === 1));

        if ($defaults > 1) {
            throw ProductApiException::single(400, $pointer . '/upsert', 'only one entry can set isdefault_variant to 1.');
        }

        return ['update' => $update, 'add' => $add, 'delete' => $delete];
    }

    /**
     * The document's two lists, the delete ids checked against the product's children.
     *
     * @return array{0: list<mixed>, 1: list<int>, 2: array<int, true>}  upsert entries, delete ids, child ids as keys.
     */
    private function patchParts(int $productId, mixed $doc, string $pointer): array
    {
        if (!\is_array($doc) || array_diff_key($doc, array_flip(['upsert', 'delete'])) !== []) {
            throw ProductApiException::single(400, $pointer, 'accepts: upsert, delete.');
        }

        $upsert = $doc['upsert'] ?? [];
        $delete = ProductOptionService::idList($doc['delete'] ?? [], $pointer . '/delete');

        if (!\is_array($upsert) || !array_is_list($upsert)) {
            throw ProductApiException::single(400, $pointer . '/upsert', 'must be a list.');
        }

        ProductOptionService::assertListSize($upsert, $pointer . '/upsert');

        $owned = array_flip($this->variantIds($productId));

        foreach ($delete as $id) {
            if (!isset($owned[$id])) {
                throw ProductApiException::single(404, $pointer . '/delete', \sprintf('variant %d is not a child variant of product %d.', $id, $productId));
            }
        }

        return [$upsert, $delete, $owned];
    }

    /**
     * @param array<int, true> $owned  The product's child ids as keys.
     * @return array<string, mixed>  The normalised changes plus `id`.
     */
    private function validateUpdateEntry(array $entry, string $at, array $owned, array $delete, array $idKeys, string $pickKey): array
    {
        if (array_diff_key($entry, array_flip($idKeys)) !== []) {
            throw ProductApiException::single(400, $at, 'an entry with an id accepts: ' . implode(', ', $idKeys) . '; ' . $pickKey . ' cannot change on an existing variant.');
        }

        if (!ProductOptionService::uint($entry['id']) || !isset($owned[(int) $entry['id']])) {
            throw ProductApiException::single(404, $at . '/id', 'must be a child variant id of this product.');
        }

        if (\in_array((int) $entry['id'], $delete, true)) {
            throw ProductApiException::single(400, $at . '/id', 'is also listed under delete.');
        }

        $normalised       = $this->normaliseEntry(array_diff_key($entry, ['id' => 1]), $at, false);
        $normalised['id'] = (int) $entry['id'];

        return $normalised;
    }

    /**
     * The upsert entries without an id, validated as a create-time document against the product's
     * attached options (a flexivariable pick may use any value of the option; a matrix combination
     * only the values the product offers).
     *
     * @param array<int, array> $add  Caller position => raw entry.
     * @return list<array<string, mixed>>  Each with `pointer`, the entry's place in the document, for the writer's errors.
     */
    private function validateAdds(int $productId, array $add, string $pointer, string $mode, array $extraKeys): array
    {
        if ($add === []) {
            return [];
        }

        $options       = [];
        $optionService = new ProductOptionService($this->mvcFactory, $this->db);
        $attached      = $optionService->productOptions($productId);
        $universe      = $mode === 'picks' ? $optionService->baseValues(array_map(static fn (object $o): int => (int) $o->option_id, $attached)) : [];

        foreach ($attached as $option) {
            $values = $mode === 'picks'
                ? $universe[(int) $option->option_id]
                : array_map(static fn (object $v): int => (int) $v->optionvalue_id, $option->values);

            $options[] = [
                'option_id' => (int) $option->option_id,
                'values'    => array_map(static fn (int $id): array => ['optionvalue_id' => $id], $values),
            ];
        }

        if ($options === []) {
            throw ProductApiException::single(400, $pointer . '/upsert', 'the product has no options to build a variant from.');
        }

        $positions = array_keys($add);
        $validated = $mode === 'picks'
            ? $this->validatePicksDocument($options, array_values($add), $pointer . '/upsert', $extraKeys, $positions)
            : $this->validateMatrixDocument($options, array_values($add), $pointer . '/upsert', $extraKeys, $positions);

        $existingPicks = $mode === 'picks' ? array_flip($this->existingPickKeys($productId, $attached)) : [];

        // The create-time rule "first entry is the default" does not apply when adding to an existing set.
        foreach ($validated as $i => $entry) {
            $position                           = $positions[$i];
            $validated[$i]['pointer']           = $pointer . '/upsert/' . $position;
            $validated[$i]['isdefault_variant'] = (int) ($add[$position]['isdefault_variant'] ?? 0);

            if ($mode === 'picks' && isset($existingPicks[json_encode($entry['picks'], JSON_THROW_ON_ERROR)])) {
                throw ProductApiException::single(409, $validated[$i]['pointer'], 'a variant with these picks already exists.');
            }
        }

        return $validated;
    }

    /**
     * The `variable[<id>]` array Behavior\Variable::onAfterSave() and its siblings write a child from:
     * the stored row carried whole (the loop zeroes absent numeric fields) plus the changes.
     *
     * @param array<string, mixed> $changes  sku, upc, price, quantity, isdefault_variant, params (array, merged by the behavior).
     *
     * @return array<string, mixed>
     */
    public function childFormEntry(object $stored, array $changes): array
    {
        $entry = (array) $stored;

        unset($entry['product_optionvalue_ids'], $entry['modified_on'], $entry['modified_by']);

        $entry['quantity'] = [
            'quantity'                      => (int) ($changes['quantity'] ?? $stored->quantity ?? 0),
            'j2commerce_productquantity_id' => (int) ($stored->j2commerce_productquantity_id ?? 0),
        ];
        unset($entry['j2commerce_productquantity_id']);

        foreach (['sku', 'upc', 'price', 'isdefault_variant'] as $key) {
            if (\array_key_exists($key, $changes)) {
                $entry[$key] = $changes[$key];
            }
        }

        // The behavior seeds params from the stored row and merges what is sent, so only the changes travel.
        $entry['params'] = $changes['params'] ?? [];

        return $entry;
    }

    /** @return array<string, mixed>  The entry's keys as typed values; on patch only the keys sent. */
    private function normaliseEntry(array $variant, string $pointer, bool $create): array
    {
        $out = [];

        if ($create || \array_key_exists('price', $variant)) {
            $out['price'] = ProductApiSchema::money($variant['price'] ?? null)
                ?? throw ProductApiException::single(400, $pointer . '/price', \sprintf('must be a number from 0 to %s.', number_format(ProductApiSchema::PRICE_MAX, ProductApiSchema::PRICE_SCALE, '.', '')));
        }

        if (\array_key_exists('quantity', $variant)) {
            if (!ProductOptionService::uint($variant['quantity'])) {
                throw ProductApiException::single(400, $pointer . '/quantity', 'must be a whole number of 0 or more.');
            }

            $out['quantity'] = (int) $variant['quantity'];
        }

        if ($create || \array_key_exists('isdefault_variant', $variant)) {
            $default = $variant['isdefault_variant'] ?? 0;

            if (!\in_array($default, [0, 1, '0', '1'], true)) {
                throw ProductApiException::single(400, $pointer . '/isdefault_variant', 'must be 0 or 1.');
            }

            $out['isdefault_variant'] = (int) $default;
        }

        return $out + $this->normaliseEntryText($variant, $pointer, $create) + $this->normaliseEntryParams($variant, $pointer, $create);
    }

    /** @return array<string, string>  sku and upc, trimmed; on patch only the ones sent. */
    private function normaliseEntryText(array $variant, string $pointer, bool $create): array
    {
        $out = [];

        foreach (['sku', 'upc'] as $field) {
            if ($create || \array_key_exists($field, $variant)) {
                $value = $variant[$field] ?? '';

                if (!\is_string($value) || mb_strlen(trim($value)) > 255) {
                    throw ProductApiException::single(400, $pointer . '/' . $field, 'must be a string of 255 characters or fewer.');
                }

                $out[$field] = trim($value);
            }
        }

        return $out;
    }

    /** @return array{params?: array}  The params object sent; empty on create when none was. */
    private function normaliseEntryParams(array $variant, string $pointer, bool $create): array
    {
        if (!\array_key_exists('params', $variant)) {
            return $create ? ['params' => []] : [];
        }

        $params = $variant['params'];

        if (!\is_array($params) || ($params !== [] && array_is_list($params))) {
            throw ProductApiException::single(400, $pointer . '/params', 'must be an object.');
        }

        // Variant images follow the product image rule: a path under a store image folder.
        $main = $params['variant_main_image'] ?? '';

        if (!\is_string($main) || ($main !== '' && !ProductApiSchema::isLocalImage($main))) {
            throw ProductApiException::single(400, $pointer . '/params/variant_main_image', 'must be a file in one of the store image folders.');
        }

        $images = $params['variant_images'] ?? [];

        if (!\is_array($images)) {
            throw ProductApiException::single(400, $pointer . '/params/variant_images', 'must be a list of images.');
        }

        foreach ($images as $n => $image) {
            $path = \is_array($image) ? ($image['path'] ?? '') : $image;

            if (!\is_string($path) || ($path !== '' && !ProductApiSchema::isLocalImage($path))) {
                throw ProductApiException::single(400, $pointer . '/params/variant_images/' . $n, 'must be a file in one of the store image folders.');
            }
        }

        return ['params' => $params];
    }

    /** Same rule as the admin generator: the first child is the default unless one was named; two named is an error. */
    private function applyDefaultRule(array &$entries, string $pointer): void
    {
        $defaults = 0;

        foreach ($entries as $entry) {
            $defaults += $entry['isdefault_variant'];
        }

        if ($defaults > 1) {
            throw ProductApiException::single(400, $pointer, 'only one variant can be the default.');
        }

        if ($defaults === 0) {
            $entries[0]['isdefault_variant'] = 1;
        }
    }

    /** The admin generator's input: every combination of the product's option values, priced from the master. */
    private function cartesianEntries(int $productId, ?object $master): array
    {
        $optionArrays = $this->traitValues($productId);
        $base         = $this->baseSku($productId, $master);
        $basePrice    = (float) ($master?->price ?? 0);
        $entries      = [];
        $n            = 0;

        foreach (ProductHelper::getCombinations($optionArrays) as $combination) {
            $skuParts   = [];
            $adjustment = 0.0;
            $povIds     = [];

            foreach ($combination as $value) {
                if (!empty($value['sku_suffix'])) {
                    $skuParts[] = $value['sku_suffix'];
                }

                // A float sum of the modifiers: formatted at the column scale by childVariantRow().
                $price       = (float) $value['price'];
                $adjustment += $value['price_prefix'] === '-' ? -$price : $price;
                $povIds[]    = $value['product_optionvalue_id'];
            }

            $entries[] = [
                'key'   => ProductHelper::normaliseOptionvalueKey($povIds),
                'sku'   => $base . ($skuParts !== [] ? '-' . implode('-', $skuParts) : '-V' . (++$n)),
                'price' => $basePrice + $adjustment,
            ];
        }

        return $entries;
    }

    /**
     * The product's options that carry values, each as the list the generator combines; refused
     * when there are none or when they combine into more variants than one request may carry.
     *
     * @return list<list<array{product_optionvalue_id: int, sku_suffix: string, price_prefix: string, price: mixed}>>
     */
    private function traitValues(int $productId): array
    {
        $optionArrays = [];
        $count        = 1;

        foreach (ProductHelper::getTraits($productId) as $trait) {
            if (empty($trait->values)) {
                continue;
            }

            $values = [];

            foreach ($trait->values as $value) {
                $values[] = [
                    'product_optionvalue_id' => (int) $value->j2commerce_product_optionvalue_id,
                    'sku_suffix'             => $value->product_optionvalue_sku ?? '',
                    'price_prefix'           => $value->product_optionvalue_prefix ?? '+',
                    'price'                  => $value->product_optionvalue_price ?? 0,
                ];
            }

            $optionArrays[] = $values;
            $count *= \count($values);
        }

        if ($optionArrays === []) {
            throw ProductApiException::single(400, '/options', 'the product has no option values to generate variants from.');
        }

        if ($count > ProductApiSchema::MAX_LIST) {
            throw ProductApiException::single(400, '/variants', \sprintf('the options combine into %d variants; the limit is %d.', $count, ProductApiSchema::MAX_LIST));
        }

        return $optionArrays;
    }

    /**
     * @param list<object> $attached  ProductOptionService::productOptions() rows.
     * @return list<string>  One JSON key per child: its picks as option_id => optionvalue_id, sorted by option.
     */
    private function existingPickKeys(int $productId, array $attached): array
    {
        $byValueId = [];

        foreach ($attached as $option) {
            foreach ($option->values as $value) {
                $byValueId[(int) $value->j2commerce_product_optionvalue_id] = [(int) $option->option_id, (int) $value->optionvalue_id];
            }
        }

        $keys = [];

        foreach ($this->existingKeys($productId, true) as $csv) {
            $picks = [];

            foreach (ProductHelper::normaliseOptionvalueIds($csv) as $povId) {
                if (isset($byValueId[$povId])) {
                    $picks[$byValueId[$povId][0]] = $byValueId[$povId][1];
                }
            }

            ksort($picks);
            $keys[] = json_encode($picks, JSON_THROW_ON_ERROR);
        }

        return $keys;
    }

    private function hasDefault(int $productId): bool
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :pid')
            ->where($db->quoteName('is_master') . ' = 0')
            ->where($db->quoteName('isdefault_variant') . ' = 1')
            ->bind(':pid', $productId, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult() > 0;
    }

    private function writeQuantity(int $variantId, int $quantity): void
    {
        $row = (object) ['variant_id' => $variantId, 'quantity' => $quantity, 'on_hold' => 0, 'sold' => 0, 'product_attributes' => ''];
        $this->db->insertObject('#__j2commerce_productquantities', $row, 'j2commerce_productquantity_id');
    }

    private function variantQuery(): QueryInterface
    {
        $db = $this->db;

        return $db->getQuery(true)
            ->select('v.*')
            ->select($db->quoteName(['q.j2commerce_productquantity_id', 'q.quantity']))
            ->from($db->quoteName('#__j2commerce_variants', 'v'))
            ->join('LEFT', $db->quoteName('#__j2commerce_productquantities', 'q') . ' ON ' . $db->quoteName('q.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id'));
    }
}
