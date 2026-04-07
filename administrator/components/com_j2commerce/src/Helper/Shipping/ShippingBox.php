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

use DVDoug\BoxPacker\Box;

\defined('_JEXEC') or die;

class ShippingBox implements Box
{
    public function __construct(
        private readonly string $reference,
        private readonly int $outerWidth,
        private readonly int $outerLength,
        private readonly int $outerDepth,
        private readonly int $emptyWeight,
        private readonly int $innerWidth,
        private readonly int $innerLength,
        private readonly int $innerDepth,
        private readonly int $maxWeight,
    ) {
    }

    public function getReference(): string
    {
        return $this->reference;
    }
    public function getOuterWidth(): int
    {
        return $this->outerWidth;
    }
    public function getOuterLength(): int
    {
        return $this->outerLength;
    }
    public function getOuterDepth(): int
    {
        return $this->outerDepth;
    }
    public function getEmptyWeight(): int
    {
        return $this->emptyWeight;
    }
    public function getInnerWidth(): int
    {
        return $this->innerWidth;
    }
    public function getInnerLength(): int
    {
        return $this->innerLength;
    }
    public function getInnerDepth(): int
    {
        return $this->innerDepth;
    }
    public function getMaxWeight(): int
    {
        return $this->maxWeight;
    }
}
