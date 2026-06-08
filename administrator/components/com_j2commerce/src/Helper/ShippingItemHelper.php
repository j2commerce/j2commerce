<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Helper for expanding cart/order items into shipping packages.
 *
 * Shipping plugins that want to honour multi-package configurations (e.g. a
 * glass panel shipped separately from hardware) should call expandPackages()
 * instead of reading $item->weight directly. Plugins that do not call this
 * helper continue to work unchanged via the legacy flat $item->weight path.
 */
final class ShippingItemHelper
{
    /**
     * Return shipping packages for an arbitrary cart or order item.
     *
     * If $item->shipping_packages is set (populated by the Guided Builder plugin
     * via the AfterGetCartItems event), return it directly. Otherwise build a
     * single-package fallback from the item's flat weight and qty, preserving
     * today's behaviour for every product type that does not emit packages.
     *
     * @param object $item  A cart or order item object.
     *
     * @return array<int, object{id: string, weight: float, length: float, width: float, height: float, qty: int}>
     */
    public static function expandPackages(object $item): array
    {
        if (!empty($item->shipping_packages) && \is_array($item->shipping_packages)) {
            return $item->shipping_packages;
        }

        // Fallback: build a single-package array from the item's flat weight.
        $weight = (float) ($item->weight ?? $item->orderitem_weight ?? 0);
        $qty    = (int)   ($item->product_qty ?? $item->orderitem_quantity ?? 1);

        return [
            (object) [
                'id'     => 'default',
                'label'  => '',
                'weight' => $weight,
                'length' => 0.0,
                'width'  => 0.0,
                'height' => 0.0,
                'qty'    => $qty,
                'items'  => [],
            ],
        ];
    }
}
