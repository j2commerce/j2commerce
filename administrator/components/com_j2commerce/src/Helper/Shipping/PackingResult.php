<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper\Shipping;

\defined('_JEXEC') or die;

class PackingResult
{
    /**
     * @param  PackedBoxResult[]  $boxes
     * @param  array              $unpacked  Items that did not fit (each has description, length, width, height, weight)
     * @param  string             $method    'box_packing' or 'per_item'
     */
    public function __construct(
        public readonly array $boxes,
        public readonly array $unpacked = [],
        public readonly string $method = 'box_packing',
    ) {
    }

    public function getTotalWeight(): float
    {
        $total = 0.0;
        foreach ($this->boxes as $box) {
            $total += $box->totalWeight;
        }
        return $total;
    }

    public function getBoxCount(): int
    {
        return \count($this->boxes);
    }

    public function hasUnpackedItems(): bool
    {
        return !empty($this->unpacked);
    }
}
