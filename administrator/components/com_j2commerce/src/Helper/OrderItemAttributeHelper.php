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
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class OrderItemAttributeHelper
{
    /** Types that represent bundle/boxbuilder child products (not standard option selections). */
    private const PRODUCT_CHILD_TYPES = ['bundleproduct', 'boxbuilderproduct', 'bundle', 'boxbuilder'];

    /** Types to skip entirely (metadata, not displayable). */
    private const SKIP_TYPES = ['boxbuilder_selections'];

    /**
     * Parse raw orderitem_attributes column into normalized attribute objects.
     *
     * Handles 3 stored formats: JSON array, pre-resolved base64 (bundle/boxbuilder),
     * and legacy option-ID base64 (variable/configurable).
     */
    public static function parseRawAttributes(string $raw, int $productId = 0): array
    {
        if ($raw === '') {
            return [];
        }

        // Try JSON first (new format from CartOrder::saveOrderItems)
        $json = json_decode($raw);
        if (\is_array($json)) {
            return $json;
        }

        // Try base64 + serialize (legacy pre-resolved format)
        $decoded = @base64_decode($raw, true);
        if ($decoded === false) {
            return [];
        }

        $unserialized = @unserialize($decoded);
        if (!\is_array($unserialized) || empty($unserialized)) {
            return [];
        }

        // Detect pre-resolved format (bundle/boxbuilder): array of associative arrays with 'name' key
        $firstValue = reset($unserialized);
        if (\is_array($firstValue) && isset($firstValue['name'])) {
            return self::resolvePreResolvedAttributes($unserialized);
        }

        // Simple format (variable/configurable): {optionId => "valueId"}
        return self::resolveSimpleOptionAttributes($unserialized, $productId);
    }

    /**
     * Group attributes by type and deduplicate bundle/boxbuilder entries with quantity counts.
     *
     * @return array<int, array{type: string, items: array}>
     */
    public static function groupAndDeduplicate(array $attributes): array
    {
        if (empty($attributes)) {
            return [];
        }

        $groups     = [];
        $childItems = [];

        foreach ($attributes as $attr) {
            $type  = $attr->orderitemattribute_type ?? 'select';
            $name  = $attr->orderitemattribute_name ?? '';
            $value = $attr->orderitemattribute_value ?? '';

            if (\in_array($type, self::SKIP_TYPES, true)) {
                continue;
            }

            if ($name === '' && $value === '') {
                continue;
            }

            if (\in_array($type, self::PRODUCT_CHILD_TYPES, true)) {
                // Deduplicate by name for child product types
                $key = $name;
                if (!isset($childItems[$key])) {
                    $childItems[$key] = ['name' => $name, 'value' => $value, 'qty' => 1, 'type' => $type];
                } else {
                    $childItems[$key]['qty']++;
                }
            } else {
                $groups[] = ['type' => $type, 'name' => $name, 'value' => $value];
            }
        }

        $result = [];

        // Add deduplicated child products as a single group
        if (!empty($childItems)) {
            $result[] = [
                'type'  => 'product_children',
                'items' => array_values($childItems),
            ];
        }

        // Add standard attributes (each as its own entry in a 'standard' group)
        if (!empty($groups)) {
            $result[] = [
                'type'  => 'standard',
                'items' => $groups,
            ];
        }

        return $result;
    }

    /** Format attributes as HTML string for email context. */
    public static function formatForEmail(array $attributes): string
    {
        $grouped = self::groupAndDeduplicate($attributes);
        if (empty($grouped)) {
            return '';
        }

        $parts = [];

        foreach ($grouped as $group) {
            foreach ($group['items'] as $item) {
                if ($group['type'] === 'product_children') {
                    $qty   = (int) ($item['qty'] ?? 1);
                    $label = $qty > 1
                        ? '(' . $qty . ') ' . htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8')
                        : htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
                    $parts[] = $label;
                } else {
                    $name    = htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8');
                    $value   = htmlspecialchars($item['value'] ?? '', ENT_QUOTES, 'UTF-8');
                    $parts[] = $value !== '' ? $name . ': ' . $value : $name;
                }
            }
        }

        return implode('<br>', $parts);
    }

    private static function resolvePreResolvedAttributes(array $options): array
    {
        $attributes = [];

        foreach ($options as $option) {
            if (!\is_array($option)) {
                continue;
            }

            $type = $option['type'] ?? '';

            if (\in_array($type, self::SKIP_TYPES, true)) {
                continue;
            }

            $name  = $option['name'] ?? '';
            $value = $option['option_value'] ?? '';

            if ($name === '' && $value === '') {
                continue;
            }

            $attributes[] = (object) [
                'orderitemattribute_name'  => $name,
                'orderitemattribute_value' => $value,
                'orderitemattribute_type'  => $type,
                'orderitemattribute_price' => (float) ($option['price'] ?? 0),
            ];
        }

        return $attributes;
    }

    private static function resolveSimpleOptionAttributes(array $options, int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $db         = Factory::getContainer()->get(DatabaseInterface::class);
        $attributes = [];

        foreach ($options as $optionId => $optionValue) {
            if (empty($optionValue)) {
                continue;
            }

            $optId = (int) $optionId;
            $query = $db->getQuery(true)
                ->select([$db->quoteName('o.option_name'), $db->quoteName('o.type')])
                ->from($db->quoteName('#__j2commerce_product_options', 'po'))
                ->join(
                    'LEFT',
                    $db->quoteName('#__j2commerce_options', 'o'),
                    $db->quoteName('o.j2commerce_option_id') . ' = ' . $db->quoteName('po.option_id')
                )
                ->where($db->quoteName('po.j2commerce_productoption_id') . ' = :optionId')
                ->bind(':optionId', $optId, ParameterType::INTEGER);

            $db->setQuery($query);
            $optionInfo = $db->loadObject();

            if (!$optionInfo) {
                continue;
            }

            $optionName = Text::_($optionInfo->option_name ?? '');
            $optionType = $optionInfo->type ?? 'select';

            if (\is_array($optionValue)) {
                $valueNames = [];
                foreach ($optionValue as $valueId) {
                    $name = self::resolveOptionValueName($db, (int) $valueId);
                    if ($name !== '') {
                        $valueNames[] = $name;
                    }
                }
                $displayValue = implode(', ', $valueNames);
            } elseif (\in_array($optionType, ['select', 'radio', 'checkbox'], true)) {
                $displayValue = self::resolveOptionValueName($db, (int) $optionValue);
            } else {
                $displayValue = (string) $optionValue;
            }

            $attributes[] = (object) [
                'orderitemattribute_name'  => $optionName,
                'orderitemattribute_value' => $displayValue,
                'orderitemattribute_type'  => $optionType,
            ];
        }

        return $attributes;
    }

    private static function resolveOptionValueName(DatabaseInterface $db, int $valueId): string
    {
        if ($valueId <= 0) {
            return '';
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('ov.optionvalue_name'))
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_optionvalues', 'ov'),
                $db->quoteName('pov.optionvalue_id') . ' = ' . $db->quoteName('ov.j2commerce_optionvalue_id')
            )
            ->where($db->quoteName('pov.j2commerce_product_optionvalue_id') . ' = :valueId')
            ->bind(':valueId', $valueId, ParameterType::INTEGER);

        $db->setQuery($query);

        return Text::_($db->loadResult() ?: '');
    }
}
