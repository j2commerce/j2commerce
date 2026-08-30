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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;

/**
 * One rendering path for every date the component prints, so an invoice, a packing slip,
 * a receipt and an order email cannot disagree about what a date looks like.
 */
class DateHelper
{
    /** Day-of-week keys indexed by PHP's `w`. */
    private const DAY_KEYS = ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];

    /** Month keys indexed by PHP's `n` - 1. */
    private const MONTH_KEYS = [
        'JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE',
        'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER',
    ];

    /**
     * Printed documents carry the store's timezone, not the reader's: an invoice that
     * changes date depending on who opens it is not the same document twice.
     */
    public static function timezone(): \DateTimeZone
    {
        $offset = (string) Factory::getApplication()->getConfig()->get('offset', 'UTC');

        try {
            return new \DateTimeZone($offset !== '' ? $offset : 'UTC');
        } catch (\Exception) {
            return new \DateTimeZone('UTC');
        }
    }

    /**
     * A `DATE_FORMAT_*` value is a language key so each translation supplies its own ordering,
     * the way `HTMLHelper::_('date')` resolves `DATE_FORMAT_LC1`. Anything else is taken as a
     * literal PHP format string, which is what every existing configuration holds. The prefix
     * test is what keeps the two apart: no PHP format string starts with those characters.
     */
    public static function resolveFormat(?string $value = null, ?Language $language = null): string
    {
        $value = trim((string) ($value ?? ComponentHelper::getParams('com_j2commerce')->get('date_format', 'Y-m-d')));

        if ($value === '') {
            return 'Y-m-d';
        }

        if (!str_starts_with($value, 'DATE_FORMAT_')) {
            return $value;
        }

        $resolved = $language !== null ? $language->_($value) : Text::_($value);

        // An unknown key resolves to itself, which would print the key across the document.
        return $resolved !== $value ? $resolved : 'Y-m-d';
    }

    /**
     * Renders in the store's timezone, with the day and month names taken from `$language`
     * rather than whichever language the request happens to be running in — the rest of the
     * order email already answers to the order's own language, and the date belongs with it.
     */
    public static function format(mixed $input = 'now', ?string $format = null, ?Language $language = null): string
    {
        $date = Factory::getDate($input ?: 'now');
        $date->setTimezone(self::timezone());

        $format = self::resolveFormat($format, $language);

        // Same substitution core performs, held one step longer so the names can come from
        // $language: the sentinels go in before formatting and are swapped out after, with
        // Date's own translation switched off so it cannot resolve them against Text::_().
        $format = preg_replace('/(^|[^\\\])D/', '\1' . Date::DAY_ABBR, $format);
        $format = preg_replace('/(^|[^\\\])l/', '\1' . Date::DAY_NAME, $format);
        $format = preg_replace('/(^|[^\\\])M/', '\1' . Date::MONTH_ABBR, $format);
        $format = preg_replace('/(^|[^\\\])F/', '\1' . Date::MONTH_NAME, $format);

        $rendered = $date->format($format, true, false);

        if (!str_contains($rendered, "\x02")) {
            return $rendered;
        }

        $day   = self::DAY_KEYS[(int) $date->format('w', true, false)] ?? '';
        $month = self::MONTH_KEYS[((int) $date->format('n', true, false)) - 1] ?? '';

        return strtr($rendered, [
            Date::DAY_ABBR   => self::translate(substr($day, 0, 3), $language),
            Date::DAY_NAME   => self::translate($day, $language),
            Date::MONTH_ABBR => self::translate($month === '' ? '' : $month . '_SHORT', $language),
            Date::MONTH_NAME => self::translate($month, $language),
        ]);
    }

    /** The current year on the same path, so it cannot drift from the dates beside it. */
    public static function currentYear(?Language $language = null): string
    {
        return self::format('now', 'Y', $language);
    }

    private static function translate(string $key, ?Language $language = null): string
    {
        if ($key === '') {
            return '';
        }

        return $language !== null ? $language->_($key) : Text::_($key);
    }
}
