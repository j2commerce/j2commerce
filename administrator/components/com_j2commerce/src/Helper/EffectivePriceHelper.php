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
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * The price a product listing card shows, as one SQL expression, so price sorting, the price-range
 * filter and the slider bounds all agree with the card. Mirrors ProductHelper::getPrice() for the
 * variant the card prices: the default child variant for variant-family types, the master otherwise.
 */
final class EffectivePriceHelper
{
    /** Types whose card prices the default child variant (ProductHelper::getDefaultVariant()). */
    private const DEFAULT_VARIANT_TYPES = ['variable', 'flexivariable', 'variablesubscriptionproduct'];

    private static ?\WeakMap $expressions = null;

    /**
     * Joins the priced variant onto a query that already has `p` (#__j2commerce_products) and `v`
     * (its master variant) and returns the expression. Repeat calls on the same query reuse the joins.
     */
    public static function expression(QueryInterface $query, DatabaseInterface $db, User $user): string
    {
        self::$expressions ??= new \WeakMap();

        if (isset(self::$expressions[$query])) {
            return self::$expressions[$query];
        }

        $types = implode(',', array_map(static fn (string $type): string => $db->quote($type), self::DEFAULT_VARIANT_TYPES));

        // Variable drops children with no option mapping before it picks the default; flagged
        // isdefault_variant wins, else the lowest id, the order VariantsModel lists children in.
        $mapped = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__j2commerce_product_variant_optionvalues', 'eppvo'))
            ->where($db->quoteName('eppvo.variant_id') . ' = ' . $db->quoteName('epd.j2commerce_variant_id'))
            ->where($db->quoteName('eppvo.product_optionvalue_ids') . ' <> ' . $db->quote(''));

        $defaultVariant = $db->getQuery(true)
            ->select([
                $db->quoteName('epd.product_id'),
                'COALESCE(MIN(CASE WHEN ' . $db->quoteName('epd.isdefault_variant') . ' = 1 THEN ' . $db->quoteName('epd.j2commerce_variant_id') . ' END), '
                    . 'MIN(' . $db->quoteName('epd.j2commerce_variant_id') . ')) AS ' . $db->quoteName('variant_id'),
            ])
            ->from($db->quoteName('#__j2commerce_variants', 'epd'))
            ->join('INNER', $db->quoteName('#__j2commerce_products', 'epp'), $db->quoteName('epp.j2commerce_product_id') . ' = ' . $db->quoteName('epd.product_id'))
            ->where($db->quoteName('epd.is_master') . ' = 0')
            ->where($db->quoteName('epp.product_type') . ' IN (' . $types . ')')
            ->where('NOT (' . $db->quoteName('epp.product_type') . ' = ' . $db->quote('variable')
                . ' AND ' . $db->quoteName('epp.has_options') . ' = 1 AND NOT EXISTS (' . $mapped . '))')
            ->group($db->quoteName('epd.product_id'));

        $query->join('LEFT', '(' . $defaultVariant . ') AS ' . $db->quoteName('ep_default'), $db->quoteName('ep_default.product_id') . ' = ' . $db->quoteName('p.j2commerce_product_id'));
        $query->join(
            'LEFT',
            $db->quoteName('#__j2commerce_variants', 'ep_variant'),
            $db->quoteName('ep_variant.j2commerce_variant_id') . ' = COALESCE(' . $db->quoteName('ep_default.variant_id') . ', ' . $db->quoteName('v.j2commerce_variant_id') . ')'
        );

        // Display quantity: the variant's own minimum sale quantity when it restricts one.
        $qty = 'CASE WHEN ' . $db->quoteName('ep_variant.quantity_restriction') . ' = 1'
            . ' AND COALESCE(' . $db->quoteName('ep_variant.use_store_config_min_sale_qty') . ', 0) = 0'
            . ' AND ' . $db->quoteName('ep_variant.min_sale_qty') . ' > 0'
            . ' THEN FLOOR(' . $db->quoteName('ep_variant.min_sale_qty') . ') ELSE 1 END';

        // getPrice() always counts the public group alongside the visitor's own.
        $groups   = implode(',', array_unique([...array_map('intval', $user->getAuthorisedGroups()), 1]));
        $now      = $db->quote(Factory::getDate()->toSql());
        $nullDate = $db->quote($db->getNullDate());

        $rule = $db->getQuery(true)
            ->select('MIN(' . $db->quoteName('epr.price') . ')')
            ->from($db->quoteName('#__j2commerce_product_prices', 'epr'))
            ->where($db->quoteName('epr.variant_id') . ' = ' . $db->quoteName('ep_variant.j2commerce_variant_id'))
            ->where('(' . $db->quoteName('epr.quantity_from') . ' IS NULL OR ' . $db->quoteName('epr.quantity_from') . ' = 0 OR ' . $db->quoteName('epr.quantity_from') . ' <= ' . $qty . ')')
            ->where('(' . $db->quoteName('epr.quantity_to') . ' IS NULL OR ' . $db->quoteName('epr.quantity_to') . ' = 0 OR ' . $db->quoteName('epr.quantity_to') . ' >= ' . $qty . ')')
            ->where('(' . $db->quoteName('epr.date_from') . ' IS NULL OR ' . $db->quoteName('epr.date_from') . ' = ' . $nullDate . ' OR ' . $db->quoteName('epr.date_from') . ' <= ' . $now . ')')
            ->where('(' . $db->quoteName('epr.date_to') . ' IS NULL OR ' . $db->quoteName('epr.date_to') . ' = ' . $nullDate . ' OR ' . $db->quoteName('epr.date_to') . ' >= ' . $now . ')')
            ->where('(' . $db->quoteName('epr.customer_group_id') . ' IS NULL OR ' . $db->quoteName('epr.customer_group_id') . ' IN (' . $groups . '))');

        // An advanced price only replaces the base price when it is lower, as in getPrice().
        $base = 'COALESCE(' . $db->quoteName('ep_variant.price') . ', 0)';

        return self::$expressions[$query] = 'LEAST(' . $base . ', COALESCE((' . $rule . '), ' . $base . '))';
    }

    public static function filterRange(QueryInterface $query, DatabaseInterface $db, User $user, float $from, float $to): void
    {
        $price = self::expression($query, $db, $user);

        if ($from > 0) {
            $query->where($price . ' >= :price_from')
                ->bind(':price_from', $from, ParameterType::STRING);
        }

        if ($to > 0) {
            $query->where($price . ' <= :price_to')
                ->bind(':price_to', $to, ParameterType::STRING);
        }
    }
}
