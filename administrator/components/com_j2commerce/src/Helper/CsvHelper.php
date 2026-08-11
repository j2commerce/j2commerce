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

\defined('_JEXEC') or die;

/**
 * Shared cell handling for every CSV export the component writes.
 *
 * @since  6.0.0
 */
class CsvHelper
{
    /**
     * Prefixes a tab to cells opening with =, +, - or @ so a spreadsheet
     * application shows the stored text instead of evaluating it as a formula.
     */
    public static function sanitizeCell(mixed $value): string
    {
        $value = (string) $value;

        if ($value !== '' && \in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "\t" . $value;
        }

        return $value;
    }
}
