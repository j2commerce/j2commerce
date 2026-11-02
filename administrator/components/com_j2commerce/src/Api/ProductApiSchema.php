<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Api;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;

/** Keys a product type accepts through the API; `type`, with the class that declares apiSchema(), is how ProductService::apiHandler() tells a real handler from an inherited one. */
final class ProductApiSchema
{
    /** Product columns every type accepts; author and date columns are stamped by ProductTable::store(). */
    public const ROW_COLUMNS = ['visibility', 'has_options', 'addtocart_text', 'enabled', 'taxprofile_id',
        'manufacturer_id', 'vendor_id', 'up_sells', 'cross_sells', 'main_tag', 'productfilter_ids', 'params', 'plugins'];

    public const IMAGE_FIELDS = ['main_image', 'main_image_alt', 'thumb_image', 'thumb_image_alt'];

    /** #__j2commerce_variants.price is decimal(15,5). */
    public const PRICE_MAX = 9999999999.99999;

    public const PRICE_SCALE = 5;

    /** #__j2commerce_product_optionvalues.product_optionvalue_price and _weight are decimal(15,8). */
    public const MODIFIER_MAX = 9999999.99999999;

    public const MODIFIER_SCALE = 8;

    /** Longest list (ids, option values, variants, picks) one request may carry. */
    public const MAX_LIST = 500;

    /** `cross_sells`, `up_sells` and `productfilter_ids` are stored as CSVs in varchar(255) columns. */
    public const CSV_COLUMN_LENGTH = 255;

    /** Product columns stored as an id CSV, with what an id must be. */
    private const CSV_COLUMNS = [
        'up_sells'          => 'up-sell product ids',
        'cross_sells'       => 'cross-sell product ids',
        'productfilter_ids' => 'filter ids',
    ];

    /** @param array<string, array{type: string, required: bool, on: string, description: string}> $accepts */
    public function __construct(public readonly string $type, public readonly array $accepts = [])
    {
    }

    /**
     * A finite number from 0 to `$max` as the column-scale string, or null when it is not one.
     *
     * The string is the write: Table::store() and insertObject() cast a float with ini `precision`
     * (14 digits), which turns 1234567890.12345 into 1234567890.1235 and 9999999999.99999 into
     * 10000000000, past the column. The bound is checked on the rounded value, so a number that
     * rounds past `$max` is refused too.
     */
    public static function money(mixed $value, int $scale = self::PRICE_SCALE, float $max = self::PRICE_MAX): ?string
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0) {
            return null;
        }

        $formatted = number_format((float) $value, $scale, '.', '');

        return (float) $formatted <= $max ? $formatted : null;
    }

    /** A stored decimal: zero (a stored "0.00000" too) stays int 0 for `empty()` readers; else the scaled string. */
    public static function decimal(mixed $value, int $scale = self::PRICE_SCALE): string|int
    {
        return !is_numeric($value) || (float) $value == 0.0 ? 0 : number_format((float) $value, $scale, '.', '');
    }

    /** @return array{type: string, required: bool, on: string, description: string} */
    public static function key(string $type, bool $required = false, string $on = 'both', string $description = ''): array
    {
        return ['type' => $type, 'required' => $required, 'on' => $on, 'description' => $description];
    }

    /** The keys the model handles itself, before the handler runs. */
    public static function shared(): self
    {
        $skuRule = 'An API-only rule: a SKU sent (master or variant) must not be on any other variant of the store, the variants of the same '
            . 'request included; a master keeping its stored SKU is never refused';
        $accepts = [
            'product_source'    => self::key('string', false, 'create', 'com_content only'),
            'product_source_id' => self::key('int', true, 'create', 'Existing article id with no product'),
            'product_type'      => self::key('string', true, 'create', 'Required on create, one of the creatable types; cannot be changed on patch'),
            'price'             => self::key('number', false, 'both', 'Master variant price'),
            'sku'               => self::key('string', false, 'both', 'Master variant SKU, 255 max. ' . $skuRule),
            'upc'               => self::key('string', false, 'both', 'Master variant UPC, 255 max'),
            'quantity'          => self::key('int', false, 'both', 'Master variant stock; refused on a type whose stock is per variant'),
        ];

        foreach (self::ROW_COLUMNS as $column) {
            $accepts[$column] = self::key($column === 'params' || $column === 'plugins' ? 'object' : 'scalar', false, 'both', 'Product column');
        }

        foreach (self::CSV_COLUMNS as $column => $what) {
            $accepts[$column] = self::key('list|string', false, 'both', \sprintf('List or CSV of %s, %d characters at most as a CSV. ', $what, self::CSV_COLUMN_LENGTH)
                . ($column === 'productfilter_ids'
                    ? 'Every id must be an existing filter; a filter of a tag-sourced group only when the product already carries it'
                    : 'An id already stored on the product round-trips; a new one must be an existing product with products.enabled = 1'));
        }

        foreach (self::IMAGE_FIELDS as $field) {
            $accepts[$field] = self::key('string', false, 'both', str_ends_with($field, '_alt') ? 'Alt text' : 'Path under a store image folder');
        }

        return new self('', $accepts);
    }

    /** The other schema's entries win on a shared key; its type replaces this one's. */
    public function merge(self $other): self
    {
        return new self($other->type !== '' ? $other->type : $this->type, array_replace($this->accepts, $other->accepts));
    }

    /** @return list<string>  Keys accepted on `create` or `patch`. */
    public function keys(string $on): array
    {
        $keys = [];

        foreach ($this->accepts as $key => $spec) {
            if ($spec['on'] === 'both' || $spec['on'] === $on) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** A path under one of the store image folders (the part after # is ignored); never relative, scheme-bearing or quoted. */
    public static function isLocalImage(string $value): bool
    {
        $path = explode('#', $value, 2)[0];

        if (str_contains($path, '..') || str_contains($path, ':') || preg_match('/["\'<>\\\\`\x00-\x1F\x7F]/', $value)) {
            return false;
        }

        foreach (ConfigHelper::getImageDirectoryPaths() as $root) {
            $root = trim($root, '/');

            if ($root !== '' && str_starts_with($path, $root . '/')) {
                return true;
            }
        }

        return false;
    }
}
