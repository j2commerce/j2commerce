<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\InventoryHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

/** The variants of one order line's product, listed for the order editor's Update Variant picker. */
class OrdervariantsModel extends ListModel
{
    private object|false|null $line = null;

    private ?array $variantOptions = null;

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] ??= ['search', 'stock', 'variant_options', 'v.sku', 'v.price', 'stock_quantity'];

        parent::__construct($config, $factory);
    }

    /** The order line being edited, or null when it does not exist or is not a variable product type. */
    public function getOrderLine(): ?object
    {
        if ($this->line === null) {
            $itemId = (int) $this->getState('orderitem_id');
            $db     = $this->getDatabase();
            $query  = $db->getQuery(true)
                ->select($db->quoteName([
                    'oi.j2commerce_orderitem_id',
                    'oi.product_id',
                    'oi.product_type',
                    'oi.variant_id',
                    'oi.orderitem_name',
                    'oi.orderitem_quantity',
                    'o.currency_code',
                ]))
                ->from($db->quoteName('#__j2commerce_orderitems', 'oi'))
                ->join(
                    'INNER',
                    $db->quoteName('#__j2commerce_orders', 'o')
                    . ' ON ' . $db->quoteName('o.order_id') . ' = ' . $db->quoteName('oi.order_id')
                )
                ->where($db->quoteName('oi.j2commerce_orderitem_id') . ' = :itemId')
                ->bind(':itemId', $itemId, ParameterType::INTEGER);
            $db->setQuery($query);
            $line = $db->loadObject();

            $this->line = $line && \in_array((string) $line->product_type, ProductHelper::getVariableProductTypes(), true) ? $line : false;
        }

        return $this->line ?: null;
    }

    /**
     * The options that define the line product's variants, in option order, each with the values
     * its variants use.
     *
     * @return  array<int, object>  Keyed by productoption id: {id, label, values: [product optionvalue id => label]}
     */
    public function getVariantOptions(): array
    {
        if ($this->variantOptions !== null) {
            return $this->variantOptions;
        }

        $this->variantOptions = [];
        $line                 = $this->getOrderLine();

        if ($line === null) {
            return [];
        }

        $db        = $this->getDatabase();
        $productId = (int) $line->product_id;

        $used = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__j2commerce_variants', 'uv'))
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_product_variant_optionvalues', 'upvo')
                . ' ON ' . $db->quoteName('upvo.variant_id') . ' = ' . $db->quoteName('uv.j2commerce_variant_id')
            )
            ->where($db->quoteName('uv.product_id') . ' = ' . $db->quoteName('po.product_id'))
            ->where('FIND_IN_SET(' . $db->quoteName('pov.j2commerce_product_optionvalue_id') . ', ' . $db->quoteName('upvo.product_optionvalue_ids') . ') > 0');

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('po.j2commerce_productoption_id', 'option_id'),
                $db->quoteName('o.option_name'),
                $db->quoteName('pov.j2commerce_product_optionvalue_id', 'value_id'),
                $db->quoteName('ov.optionvalue_name'),
            ])
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_product_options', 'po')
                . ' ON ' . $db->quoteName('po.j2commerce_productoption_id') . ' = ' . $db->quoteName('pov.productoption_id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_options', 'o')
                . ' ON ' . $db->quoteName('o.j2commerce_option_id') . ' = ' . $db->quoteName('po.option_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_optionvalues', 'ov')
                . ' ON ' . $db->quoteName('ov.j2commerce_optionvalue_id') . ' = ' . $db->quoteName('pov.optionvalue_id')
            )
            ->where($db->quoteName('po.product_id') . ' = :productId')
            ->where('EXISTS (' . $used . ')')
            ->order([
                $db->quoteName('po.ordering') . ' ASC',
                $db->quoteName('po.j2commerce_productoption_id') . ' ASC',
                $db->quoteName('pov.ordering') . ' ASC',
                $db->quoteName('pov.j2commerce_product_optionvalue_id') . ' ASC',
            ])
            ->bind(':productId', $productId, ParameterType::INTEGER);
        $db->setQuery($query);

        foreach ($db->loadObjectList() ?: [] as $row) {
            $optionId = (int) $row->option_id;

            $this->variantOptions[$optionId] ??= (object) [
                'id'     => $optionId,
                'label'  => J2htmlHelper::translateKey((string) $row->option_name),
                'values' => [],
            ];
            $this->variantOptions[$optionId]->values[(int) $row->value_id] = J2htmlHelper::translateKey((string) ($row->optionvalue_name ?? ''));
        }

        return $this->variantOptions;
    }

    /** Adds one list filter per variant option (Size, Color, ...) to the static search/stock filters. */
    public function getFilterForm($data = [], $loadData = true): ?Form
    {
        $form = parent::getFilterForm($data, $loadData);

        if (!$form) {
            return null;
        }

        foreach ($this->getVariantOptions() as $option) {
            $field = new \SimpleXMLElement('<field/>');
            $field->addAttribute('name', 'option_' . $option->id);
            $field->addAttribute('type', 'list');
            // Searchtools prints a filter label without escaping it.
            $field->addAttribute('label', htmlspecialchars($option->label, ENT_QUOTES, 'UTF-8'));
            $field->addAttribute('class', 'js-select-submit-on-change');

            $placeholder = $field->addChild('option', htmlspecialchars(Text::sprintf('COM_J2COMMERCE_FILTER_OPTION_SELECT', $option->label), ENT_XML1));
            $placeholder->addAttribute('value', '');

            foreach ($option->values as $valueId => $valueLabel) {
                $choice = $field->addChild('option', htmlspecialchars($valueLabel, ENT_XML1));
                $choice->addAttribute('value', (string) $valueId);
            }

            $form->setField($field, 'filter');
        }

        return $form;
    }

    public function getActiveFilters(): array
    {
        $active = parent::getActiveFilters();

        foreach (array_keys($this->getVariantOptions()) as $optionId) {
            $value = (int) $this->getState('filter.option_' . $optionId);

            if ($value > 0) {
                $active['option_' . $optionId] = $value;
            }
        }

        return $active;
    }

    /** Each row also carries options_label, is_current, manages_stock, available and can_fulfil. */
    public function getItems()
    {
        $items = parent::getItems();

        if (!\is_array($items)) {
            return $items;
        }

        $line     = $this->getOrderLine();
        $qty      = max(1, (int) ($line->orderitem_quantity ?? 1));
        $labels   = [];
        $position = [];

        foreach ($this->getVariantOptions() as $option) {
            foreach ($option->values as $valueId => $label) {
                $labels[$valueId]   = $label;
                $position[$valueId] = \count($position);
            }
        }

        foreach ($items as $item) {
            $ids = array_values(array_filter(
                array_map('intval', explode(',', (string) ($item->product_optionvalue_ids ?? ''))),
                static fn (int $id): bool => isset($labels[$id])
            ));
            usort($ids, static fn (int $a, int $b): int => $position[$a] <=> $position[$b]);

            $item->options_label = implode(', ', array_map(static fn (int $id): string => $labels[$id], $ids));
            $item->is_current    = (int) $item->j2commerce_variant_id === (int) ($line->variant_id ?? 0);
            $item->manages_stock = InventoryHelper::isManagingStock($item);
            $item->available     = max(0, (int) $item->stock_quantity - (int) $item->stock_on_hold);
            $item->can_fulfil    = !InventoryHelper::isMarkedOutOfStock($item)
                && (!$item->manages_stock || InventoryHelper::isBackorderAllowed($item) || $item->available >= $qty);
        }

        return $items;
    }

    protected function populateState($ordering = 'v.sku', $direction = 'ASC'): void
    {
        $orderitemId = Factory::getApplication()->getInput()->getInt('orderitem_id', 0);

        $this->setState('orderitem_id', $orderitemId);

        // Filters are remembered per line, so an option filter never follows the editor onto another product.
        $this->context .= '.' . $orderitemId;

        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('orderitem_id')
            . ':' . $this->getState('filter.search')
            . ':' . $this->getState('filter.stock');

        foreach (array_keys($this->getVariantOptions()) as $optionId) {
            $id .= ':' . (int) $this->getState('filter.option_' . $optionId);
        }

        return parent::getStoreId($id);
    }

    protected function getListQuery()
    {
        $db        = $this->getDatabase();
        $query     = $db->getQuery(true);
        $productId = (int) ($this->getOrderLine()->product_id ?? 0);

        $optionsLabel = $db->getQuery(true)
            ->select(
                'GROUP_CONCAT(' . $db->quoteName('lov.optionvalue_name')
                . ' ORDER BY ' . $db->quoteName('lpo.ordering') . ', ' . $db->quoteName('lpo.j2commerce_productoption_id')
                . ' SEPARATOR ' . $db->quote(', ') . ')'
            )
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'lpov'))
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_product_options', 'lpo')
                . ' ON ' . $db->quoteName('lpo.j2commerce_productoption_id') . ' = ' . $db->quoteName('lpov.productoption_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_optionvalues', 'lov')
                . ' ON ' . $db->quoteName('lov.j2commerce_optionvalue_id') . ' = ' . $db->quoteName('lpov.optionvalue_id')
            )
            ->where('FIND_IN_SET(' . $db->quoteName('lpov.j2commerce_product_optionvalue_id') . ', ' . $db->quoteName('pvo.product_optionvalue_ids') . ') > 0');

        $available = 'COALESCE(' . $db->quoteName('pq.quantity') . ', 0) - COALESCE(' . $db->quoteName('pq.on_hold') . ', 0)';

        $query->select([
            $db->quoteName('v.j2commerce_variant_id'),
            $db->quoteName('v.sku'),
            $db->quoteName('v.price'),
            $db->quoteName('v.manage_stock'),
            $db->quoteName('v.allow_backorder'),
            $db->quoteName('v.availability'),
            $db->quoteName('pvo.product_optionvalue_ids'),
            'COALESCE(' . $db->quoteName('pq.quantity') . ', 0) AS ' . $db->quoteName('stock_quantity'),
            'COALESCE(' . $db->quoteName('pq.on_hold') . ', 0) AS ' . $db->quoteName('stock_on_hold'),
            '(' . $optionsLabel . ') AS ' . $db->quoteName('variant_options'),
        ])
            ->from($db->quoteName('#__j2commerce_variants', 'v'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_productquantities', 'pq')
                . ' ON ' . $db->quoteName('pq.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_product_variant_optionvalues', 'pvo')
                . ' ON ' . $db->quoteName('pvo.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id')
            )
            // Scoped to the line's own product on the server; no request value widens it.
            ->where($db->quoteName('v.product_id') . ' = :productId')
            // Master rows of variable products are placeholders, not sellable variants.
            ->where('COALESCE(' . $db->quoteName('v.is_master') . ', 0) = 0')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            $skuLike     = '%' . $search . '%';
            $optionsLike = $skuLike;

            $query->where('(' . $db->quoteName('v.sku') . ' LIKE :skuLike OR (' . $optionsLabel . ') LIKE :optionsLike)')
                ->bind(':skuLike', $skuLike)
                ->bind(':optionsLike', $optionsLike);
        }

        match ((string) $this->getState('filter.stock')) {
            'in'    => $query->where('(COALESCE(' . $db->quoteName('v.manage_stock') . ', 0) <> 1 OR ' . $available . ' > 0)'),
            'out'   => $query->where('(COALESCE(' . $db->quoteName('v.manage_stock') . ', 0) = 1 AND ' . $available . ' <= 0)'),
            default => null,
        };

        $chosen = [];

        foreach ($this->getVariantOptions() as $optionId => $option) {
            $valueId = (int) $this->getState('filter.option_' . $optionId);

            if (!isset($option->values[$valueId])) {
                continue;
            }

            // bind() takes a reference, so each value gets its own array slot rather than the loop variable.
            $chosen[$optionId] = (string) $valueId;
            $query->where('FIND_IN_SET(:optionValue' . $optionId . ', ' . $db->quoteName('pvo.product_optionvalue_ids') . ') > 0')
                ->bind(':optionValue' . $optionId, $chosen[$optionId]);
        }

        $ordering  = (string) $this->getState('list.ordering', 'v.sku');
        $direction = strtoupper((string) $this->getState('list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        if (!\in_array($ordering, ['variant_options', 'v.sku', 'v.price', 'stock_quantity'], true)) {
            $ordering = 'v.sku';
        }

        return $query->order($db->quoteName($ordering) . ' ' . $direction);
    }
}
