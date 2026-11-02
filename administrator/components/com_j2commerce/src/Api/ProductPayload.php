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

use Joomla\CMS\User\User;

/** The allow-listed, model-checked request a handler receives; `raw` holds the posted keys its own schema declared. */
final class ProductPayload
{
    /** Product columns the behaviors rewrite on save (CSV, filter links), so they only land through saveProduct(). */
    private const BEHAVIOR_COLUMNS = ['up_sells', 'cross_sells', 'productfilter_ids'];

    /**
     * @param array<string, mixed> $row      Product columns sent, from ProductApiSchema::ROW_COLUMNS (params/plugins already merged).
     * @param string|null          $price    Master price as ProductApiSchema::money() formats it; a float would lose digits in the store.
     * @param array<string, mixed> $images   Image fields sent, paths already checked.
     * @param array<string, mixed> $raw      Every posted key the merged schema accepts, untouched.
     * @param array<string, mixed> $stored   Product columns on patch; empty on create.
     * @param array<string, mixed> $master   Master variant columns plus `quantity` on patch; empty on create.
     */
    public function __construct(
        public readonly string $type,
        public readonly int $productId,
        public readonly array $row,
        public readonly ?string $price,
        public readonly ?string $sku,
        public readonly ?string $upc,
        public readonly ?int $quantity,
        public readonly array $images,
        public readonly array $raw,
        public readonly User $actor,
        public readonly array $stored = [],
        public readonly array $master = []
    ) {
    }

    /** Whether the request carried the key at all; `null` and `[]` count as sent. */
    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->raw);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->raw[$key] ?? $default;
    }

    /** Master variant fields sent, which the product row save has to carry. */
    public function touchesMaster(): bool
    {
        return $this->price !== null || $this->sku !== null || $this->upc !== null || $this->quantity !== null;
    }

    /** Whether the handler has to run saveProduct(): a master field, or a column only the behavior writes correctly. */
    public function needsProductSave(): bool
    {
        return $this->touchesMaster() || array_intersect_key($this->row, array_flip(self::BEHAVIOR_COLUMNS)) !== [];
    }

    /**
     * The array ProductService::saveProduct() takes: the admin form post shape, which binds the
     * product row and the master variant from one set of keys. On patch the stored columns are
     * carried so the behavior's casts (absent float fields become 0) cannot zero the master.
     *
     * @return array<string, mixed>
     */
    public function productRow(): array
    {
        // Filters are re-linked by the behavior from this key, and hits are counters: neither travels unless sent.
        $row    = array_diff_key($this->stored, ['productfilter_ids' => 1, 'hits' => 1]);
        $master = array_diff_key($this->master, array_flip(['j2commerce_variant_id', 'product_id', 'is_master', 'params',
            'created_on', 'created_by', 'modified_on', 'modified_by', 'quantity']));
        $row    = array_replace($row, $master, $this->row);

        $storedParams = json_decode((string) ($this->stored['params'] ?? ''), true);

        $row['product_type'] = $this->type;
        $row['params']       = $this->row['params'] ?? (\is_array($storedParams) ? $storedParams : []);
        $row['price']        = $this->price ?? ProductApiSchema::money($this->master['price'] ?? 0) ?? '0.00000';
        $row['sku']          = $this->sku ?? (string) ($this->master['sku'] ?? '');
        $row['upc']          = $this->upc ?? (string) ($this->master['upc'] ?? '');
        $row['quantity']     = ['quantity' => $this->quantity ?? (int) ($this->master['quantity'] ?? 0), 'product_attributes' => ''];

        if ($this->productId > 0) {
            $row['j2commerce_product_id'] = $this->productId;
        }

        return $row + $this->images;
    }
}
