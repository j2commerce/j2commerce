<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Context;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;

/** Currency formatter bound to one order's currency, so a stored order is shown in the currency it was placed in rather than the visitor's current one. */
final class OrderCurrency
{
    public function __construct(private readonly string $code, private readonly float $rate)
    {
    }

    public function format(float $number, string $currencyCode = '', float $exchangeRate = 0.0, bool $format = true): string
    {
        return CurrencyHelper::format(
            $number,
            $currencyCode !== '' ? $currencyCode : $this->code,
            $exchangeRate > 0 ? $exchangeRate : $this->rate,
            $format
        );
    }
}
