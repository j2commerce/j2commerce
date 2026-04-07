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

use DVDoug\BoxPacker\Item;
use DVDoug\BoxPacker\Rotation;

\defined('_JEXEC') or die;

class ShippableItem implements Item
{
    public function __construct(
        private readonly string $description,
        private readonly int $width,
        private readonly int $length,
        private readonly int $depth,
        private readonly int $weight,
        private readonly Rotation $rotation,
        private readonly float $value = 0.0,
    ) {
    }

    public function getDescription(): string
    {
        return $this->description;
    }
    public function getWidth(): int
    {
        return $this->width;
    }
    public function getLength(): int
    {
        return $this->length;
    }
    public function getDepth(): int
    {
        return $this->depth;
    }
    public function getWeight(): int
    {
        return $this->weight;
    }
    public function getAllowedRotation(): Rotation
    {
        return $this->rotation;
    }
    public function getValue(): float
    {
        return $this->value;
    }
}
