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

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Reads the merchant-mapped lifecycle classification on an order status row.
 *
 * Status ids are install-dependent — J2Store shipped six core statuses where J2Commerce ships
 * eight, an id-preserving migration carries the source ids verbatim, and merchants add, rename
 * and reorder their own rows — so an id can never stand in for a meaning. The classification
 * lives on the row instead, where it survives a rename and disappears with the status.
 *
 * The set describes LIFECYCLE ONLY and never payment state; payment state has its own column
 * and OrderPayGrantHelper::isPayable() reads it independently. That separation is why
 * 'delivered' is distinct from 'complete': a delivered cash-on-delivery order is still payable.
 *
 * A null type means merchant-defined with no core semantics and is a first-class state, so
 * every caller has to state its own fallback rather than assume one.
 */
final class OrderStatusHelper
{
    public const TYPE_NEW       = 'new';
    public const TYPE_OPEN      = 'open';
    public const TYPE_SHIPPED   = 'shipped';
    public const TYPE_DELIVERED = 'delivered';
    public const TYPE_COMPLETE  = 'complete';
    public const TYPE_CANCELLED = 'cancelled';
    public const TYPE_FAILED    = 'failed';
    public const TYPE_REFUNDED  = 'refunded';

    /** Type value => language key. The only place the value set is declared. */
    public const TYPES = [
        self::TYPE_NEW       => 'COM_J2COMMERCE_ORDERSTATUS_TYPE_NEW',
        self::TYPE_OPEN      => 'COM_J2COMMERCE_ORDERSTATUS_TYPE_OPEN',
        self::TYPE_SHIPPED   => 'COM_J2COMMERCE_ORDERSTATUS_TYPE_SHIPPED',
        self::TYPE_DELIVERED => 'COM_J2COMMERCE_ORDERSTATUS_TYPE_DELIVERED',
        self::TYPE_COMPLETE  => 'COM_J2COMMERCE_ORDERSTATUS_TYPE_COMPLETE',
        self::TYPE_CANCELLED => 'COM_J2COMMERCE_ORDERSTATUS_TYPE_CANCELLED',
        self::TYPE_FAILED    => 'COM_J2COMMERCE_ORDERSTATUS_TYPE_FAILED',
        self::TYPE_REFUNDED  => 'COM_J2COMMERCE_ORDERSTATUS_TYPE_REFUNDED',
    ];

    /** @var array<int, string|null>|null Status id => type, for the whole table. */
    private static ?array $map = null;

    public static function isValidType(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    public static function getType(int $statusId): ?string
    {
        return self::getMap()[$statusId] ?? null;
    }

    /** @return int[] Status ids carrying $type, empty when nothing is mapped to it. */
    public static function idsOfType(string $type): array
    {
        if (!self::isValidType($type)) {
            return [];
        }

        return array_keys(self::getMap(), $type, true);
    }

    /**
     * Options for a type select. The empty first option is the null state and is offered
     * deliberately: leaving a status unclassified has to stay reachable from the UI.
     *
     * @return object[]
     */
    public static function getTypeOptions(): array
    {
        $options = [HTMLHelper::_('select.option', '', Text::_('COM_J2COMMERCE_ORDERSTATUS_TYPE_NONE'))];

        foreach (self::TYPES as $value => $langKey) {
            $options[] = HTMLHelper::_('select.option', $value, Text::_($langKey));
        }

        return $options;
    }

    public static function getTypeLabel(?string $type): string
    {
        return Text::_(self::TYPES[$type] ?? 'COM_J2COMMERCE_ORDERSTATUS_TYPE_NONE');
    }

    /** Drops the static cache. For callers that write the column in the same request. */
    public static function clearCache(): void
    {
        self::$map = null;
    }

    /** @return array<int, string|null> */
    private static function getMap(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_orderstatus_id', 'orderstatus_type']))
            ->from($db->quoteName('#__j2commerce_orderstatuses'));

        $db->setQuery($query);

        self::$map = [];

        foreach ($db->loadObjectList() ?: [] as $row) {
            $type = (string) ($row->orderstatus_type ?? '');

            self::$map[(int) $row->j2commerce_orderstatus_id] = self::isValidType($type) ? $type : null;
        }

        return self::$map;
    }
}
