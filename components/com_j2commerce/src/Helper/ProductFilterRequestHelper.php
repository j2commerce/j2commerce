<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\Database\DatabaseInterface;
use Joomla\Input\Input;

/**
 * Single source of truth for parsing product-list filter state from an HTTP request.
 *
 * Reads:
 *   - ?filters=group:alias,group:alias    (SEF tokens — composite or bare aliases, or numeric IDs)
 *   - ?productfilter_ids[]=N              (legacy array form)
 *   - ?manufacturer_ids[]=N  | ?brands=1,2
 *   - ?vendor_ids[]=N        | ?vendors=1,2
 *   - ?tag_ids[]=N           | ?tag_match=any|all
 *   - ?pricefrom=N           | ?priceto=N
 *
 * Called from BOTH:
 *   - ProductsModel::populateState()  (every page load — fixes cold-paste of filtered URL)
 *   - ProductsController::filter()    (AJAX sidebar)
 *
 * Alias→ID catalog is loaded once per request and cached statically.
 */
class ProductFilterRequestHelper
{
    private static ?array $aliasCatalog = null;

    public static function resolveFromRequest(?Input $input = null): array
    {
        $input ??= Factory::getApplication()->getInput();

        return [
            'manufacturer_ids'  => self::readIdList($input, 'manufacturer_ids', 'brands'),
            'vendor_ids'        => self::readIdList($input, 'vendor_ids', 'vendors'),
            'productfilter_ids' => self::readProductFilterIds($input),
            'tag_ids'           => array_values(array_filter(array_map('intval', $input->get('tag_ids', [], 'array')))),
            'tag_match'         => $input->getString('tag_match', 'any') === 'all' ? 'all' : 'any',
            'price_from'        => $input->getFloat('pricefrom', 0.0),
            'price_to'          => $input->getFloat('priceto', 0.0),
        ];
    }

    public static function resolveAliasesToIds(array $tokens): array
    {
        if (empty($tokens)) {
            return [];
        }

        $catalog = self::loadAliasCatalog();
        $ids     = [];

        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }
            if (is_numeric($token)) {
                $ids[] = (int) $token;
                continue;
            }

            // Composite token "groupAlias:filterAlias" — disambiguates same filter name across groups.
            if (str_contains($token, ':')) {
                [$groupAlias, $filterAlias] = explode(':', $token, 2);
                foreach ($catalog as $row) {
                    if ($row['group_alias'] === $groupAlias && $row['filter_alias'] === $filterAlias) {
                        $ids[] = $row['id'];
                        break;
                    }
                }
                continue;
            }

            foreach ($catalog as $row) {
                if ($row['filter_alias'] === $token) {
                    $ids[] = $row['id'];
                    break;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public static function clearAliasCache(): void
    {
        self::$aliasCatalog = null;
    }

    private static function readIdList(Input $input, string $arrayKey, string $commaKey): array
    {
        $ids = $input->get($arrayKey, [], 'array');
        if (empty($ids)) {
            $comma = $input->getString($commaKey, '');
            if ($comma !== '') {
                $ids = array_filter(explode(',', $comma), 'is_numeric');
            }
        }
        return array_values(array_filter(array_map('intval', $ids)));
    }

    private static function readProductFilterIds(Input $input): array
    {
        $ids = $input->get('productfilter_ids', [], 'array');
        if (!empty($ids)) {
            return array_values(array_filter(array_map('intval', $ids)));
        }

        $filtersParam = $input->getString('filters', '');
        if ($filtersParam === '') {
            return [];
        }

        $tokens = array_values(array_filter(array_map('trim', explode(',', $filtersParam)), 'strlen'));
        if (empty($tokens)) {
            return [];
        }

        $allNumeric = true;
        foreach ($tokens as $t) {
            if (!is_numeric($t)) {
                $allNumeric = false;
                break;
            }
        }
        if ($allNumeric) {
            return array_values(array_filter(array_map('intval', $tokens)));
        }

        return self::resolveAliasesToIds($tokens);
    }

    private static function loadAliasCatalog(): array
    {
        if (self::$aliasCatalog !== null) {
            return self::$aliasCatalog;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['f.j2commerce_filter_id', 'f.filter_name', 'g.group_name']))
            ->from($db->quoteName('#__j2commerce_filters', 'f'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_filtergroups', 'g')
                . ' ON ' . $db->quoteName('g.j2commerce_filtergroup_id')
                . ' = ' . $db->quoteName('f.group_id')
            );
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $catalog = [];
        foreach ($rows as $row) {
            $catalog[] = [
                'id'           => (int) $row->j2commerce_filter_id,
                'filter_alias' => OutputFilter::stringURLSafe((string) ($row->filter_name ?? '')),
                'group_alias'  => OutputFilter::stringURLSafe((string) ($row->group_name ?? '')),
            ];
        }

        return self::$aliasCatalog = $catalog;
    }
}
