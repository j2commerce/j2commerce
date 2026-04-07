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

class PackedBoxResult
{
    public function __construct(
        public readonly string $reference,
        public readonly float $outerLength,
        public readonly float $outerWidth,
        public readonly float $outerHeight,
        public readonly float $totalWeight,
        public readonly float $itemWeight,
        public readonly float $boxWeight,
        public readonly float $totalValue,
        public readonly float $volumeUtilisation,
        public readonly array $items = [],
        public readonly string $visualisationUrl = '',
    ) {
    }
}
