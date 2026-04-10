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
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\ParameterType;

class J2htmlHelper
{
    /**
     * Cached badge style preference ('atum' or 'j2commerce').
     */
    private static ?string $badgeStyle = null;

    /**
     * Transforms a badge CSS class string to match the configured
     * badge style. When set to "atum" the helper strips the `text-`
     * prefix from `text-bg-*` classes, leaving plain `bg-*`. When set
     * to "j2commerce" the classes are returned unchanged.
     */
    public static function badgeClass(string $classes): string
    {
        if (self::$badgeStyle === null) {
            $params           = ComponentHelper::getParams('com_j2commerce');
            self::$badgeStyle = (string) $params->get('badge_style', 'atum');
        }

        if (self::$badgeStyle === 'atum') {
            return preg_replace('/\btext-bg-/', 'bg-', $classes);
        }

        return $classes;
    }

    public static function getOrderStatusHtml(int $id): string
    {
        if ($id <= 0) {
            return '';
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select([$db->quoteName('orderstatus_name'), $db->quoteName('orderstatus_cssclass')])
            ->from($db->quoteName('#__j2commerce_orderstatuses'))
            ->where($db->quoteName('j2commerce_orderstatus_id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query);
        $item = $db->loadObject();

        if (!$item) {
            return '';
        }

        $cssClass = $item->orderstatus_cssclass;

        if ($cssClass === 'badge-important') {
            $cssClass = 'badge text-bg-dark';
        }

        $cssClass = self::badgeClass($cssClass);

        return '<span class="' . htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8') . '">'
            . Text::_($item->orderstatus_name) . '</span>';
    }

    public static function getUserGroupName($id): string
    {
        if (empty($id) || !is_numeric($id)) {
            return '';
        }

        $id = (int) $id;

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('title'))
            ->from($db->quoteName('#__usergroups'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadResult() ?: '';
    }
}
