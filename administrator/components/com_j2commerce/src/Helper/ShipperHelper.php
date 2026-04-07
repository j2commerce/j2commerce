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

use DVDoug\BoxPacker\PackedBox;
use DVDoug\BoxPacker\Packer;
use DVDoug\BoxPacker\Rotation;
use J2Commerce\Component\J2commerce\Administrator\Helper\Shipping\PackedBoxResult;
use J2Commerce\Component\J2commerce\Administrator\Helper\Shipping\PackingResult;
use J2Commerce\Component\J2commerce\Administrator\Helper\Shipping\ShippableItem;
use J2Commerce\Component\J2commerce\Administrator\Helper\Shipping\ShippingBox;
use Joomla\CMS\Log\Log;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

class ShipperHelper
{
    private static bool $autoloaderRegistered = false;

    private const LENGTH_TO_MM = [
        'mm' => 1.0,
        'cm' => 10.0,
        'in' => 25.4,
        'm'  => 1000.0,
        'ft' => 304.8,
        'yd' => 914.4,
    ];

    private const WEIGHT_TO_G = [
        'g'  => 1.0,
        'kg' => 1000.0,
        'oz' => 28.3495,
        'lb' => 453.592,
    ];

    private static ?int $mmUnitId   = null;
    private static ?int $gramUnitId = null;

    public static function packItems(array $boxes, array $items, array $options = []): PackingResult
    {
        $shippable = self::filterShippable($items);

        if (empty($shippable)) {
            return new PackingResult(boxes: [], unpacked: [], method: 'per_item');
        }

        if (empty($boxes)) {
            return self::getPerItemPackages($shippable, $options);
        }

        if (!self::ensureBoxPackerAvailable()) {
            Log::add('BoxPacker library not available, falling back to per-item packaging', Log::WARNING, 'j2commerce.shipping');
            return self::getPerItemPackages($shippable, $options);
        }

        return self::runBoxPacker($boxes, $shippable, $options);
    }

    public static function getCustomBoxesFromParams(Registry $params, string $fieldName = 'box_list'): array
    {
        $raw = $params->get($fieldName, []);

        if (\is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }

        if (!\is_array($raw)) {
            return [];
        }

        $boxes = [];
        foreach ($raw as $box) {
            $box = (array) $box;
            if (empty($box['outer_length']) && empty($box['outer_width']) && empty($box['outer_height'])) {
                continue;
            }

            $boxes[] = self::createBox(
                name: (string) ($box['name'] ?? $box['box_name'] ?? 'Custom Box'),
                outerLength: (float) ($box['outer_length'] ?? 0),
                outerWidth: (float) ($box['outer_width'] ?? 0),
                outerHeight: (float) ($box['outer_height'] ?? 0),
                innerLength: (float) ($box['inner_length'] ?? $box['outer_length'] ?? 0),
                innerWidth: (float) ($box['inner_width'] ?? $box['outer_width'] ?? 0),
                innerHeight: (float) ($box['inner_height'] ?? $box['outer_height'] ?? 0),
                boxWeight: (float) ($box['box_weight'] ?? 0),
                maxWeight: (float) ($box['max_weight'] ?? 0),
            );
        }

        return $boxes;
    }

    public static function createBox(
        string $name,
        float $outerLength,
        float $outerWidth,
        float $outerHeight,
        float $innerLength,
        float $innerWidth,
        float $innerHeight,
        float $boxWeight = 0.0,
        float $maxWeight = 0.0,
    ): array {
        return [
            'name'         => $name,
            'outer_length' => $outerLength,
            'outer_width'  => $outerWidth,
            'outer_height' => $outerHeight,
            'inner_length' => $innerLength,
            'inner_width'  => $innerWidth,
            'inner_height' => $innerHeight,
            'box_weight'   => $boxWeight,
            'max_weight'   => $maxWeight,
        ];
    }

    public static function getPerItemPackages(array $items, array $options = []): PackingResult
    {
        $defaults = self::getDefaults($options);
        $boxes    = [];

        foreach ($items as $item) {
            $item        = (object) $item;
            $qty         = self::getItemQty($item);
            $weight      = self::getItemWeight($item, $defaults['default_weight']);
            $length      = self::getItemDimension($item, 'length', $defaults['default_length']);
            $width       = self::getItemDimension($item, 'width', $defaults['default_width']);
            $height      = self::getItemDimension($item, 'height', $defaults['default_height']);
            $description = self::getItemDescription($item);
            $price       = self::getItemPrice($item);

            for ($i = 0; $i < $qty; $i++) {
                $boxes[] = new PackedBoxResult(
                    reference: $description,
                    outerLength: $length,
                    outerWidth: $width,
                    outerHeight: $height,
                    totalWeight: $weight,
                    itemWeight: $weight,
                    boxWeight: 0.0,
                    totalValue: $price,
                    volumeUtilisation: 100.0,
                    items: [['description' => $description, 'qty' => 1]],
                );
            }
        }

        return new PackingResult(boxes: $boxes, unpacked: [], method: 'per_item');
    }

    public static function previewPacking(array $boxes, array $items, array $options = []): array
    {
        $normalizedItems = [];
        foreach ($items as $item) {
            $item              = (array) $item;
            $normalizedItems[] = (object) [
                'product_name' => $item['description'] ?? 'Item',
                'length'       => (float) ($item['length'] ?? 0),
                'width'        => (float) ($item['width'] ?? 0),
                'height'       => (float) ($item['height'] ?? 0),
                'weight'       => (float) ($item['weight'] ?? 0),
                'product_qty'  => (int) ($item['qty'] ?? 1),
                'shipping'     => 1,
                'price'        => (float) ($item['price'] ?? 0),
            ];
        }

        if (!self::ensureBoxPackerAvailable() || empty($boxes)) {
            $result = self::packItems($boxes, $normalizedItems, $options);
            return self::packingResultToArray($result);
        }

        $shippable = self::filterShippable($normalizedItems);
        if (empty($shippable)) {
            return self::packingResultToArray(new PackingResult(boxes: [], method: 'per_item'));
        }

        $weightUnitId = (int) ($options['weight_unit_id'] ?? 1);
        $lengthUnitId = (int) ($options['length_unit_id'] ?? 1);
        $defaults     = self::getDefaults($options);
        $rotation     = self::resolveRotation($options['rotation'] ?? 'best_fit');

        $packer = new Packer();
        $packer->throwOnUnpackableItem(false);

        if (isset($options['max_boxes_to_balance_weight'])) {
            $packer->setMaxBoxesToBalanceWeight((int) $options['max_boxes_to_balance_weight']);
        }

        foreach ($boxes as $boxDef) {
            $boxDef = (array) $boxDef;
            $packer->addBox(new ShippingBox(
                reference: (string) ($boxDef['name'] ?? 'Box'),
                outerWidth: self::toMm((float) $boxDef['outer_width'], $lengthUnitId),
                outerLength: self::toMm((float) $boxDef['outer_length'], $lengthUnitId),
                outerDepth: self::toMm((float) $boxDef['outer_height'], $lengthUnitId),
                emptyWeight: self::toGrams((float) ($boxDef['box_weight'] ?? 0), $weightUnitId),
                innerWidth: self::toMm((float) ($boxDef['inner_width'] ?? $boxDef['outer_width']), $lengthUnitId),
                innerLength: self::toMm((float) ($boxDef['inner_length'] ?? $boxDef['outer_length']), $lengthUnitId),
                innerDepth: self::toMm((float) ($boxDef['inner_height'] ?? $boxDef['outer_height']), $lengthUnitId),
                maxWeight: self::toGrams((float) ($boxDef['max_weight'] ?? 0), $weightUnitId) ?: PHP_INT_MAX,
            ));
        }

        foreach ($shippable as $item) {
            $item = (object) $item;
            $qty  = self::getItemQty($item);
            for ($i = 0; $i < $qty; $i++) {
                $packer->addItem(new ShippableItem(
                    description: self::getItemDescription($item),
                    width: self::toMm(self::getItemDimension($item, 'width', $defaults['default_width']), $lengthUnitId),
                    length: self::toMm(self::getItemDimension($item, 'length', $defaults['default_length']), $lengthUnitId),
                    depth: self::toMm(self::getItemDimension($item, 'height', $defaults['default_height']), $lengthUnitId),
                    weight: self::toGrams(self::getItemWeight($item, $defaults['default_weight']), $weightUnitId),
                    rotation: $rotation,
                    value: self::getItemPrice($item),
                ));
            }
        }

        $packedBoxes   = $packer->pack();
        $unpackedItems = $packer->getUnpackedItems();

        $resultBoxes = [];
        foreach ($packedBoxes as $packedBox) {
            /** @var PackedBox $packedBox */
            $box        = $packedBox->box;
            $itemsList  = [];
            $itemCounts = [];
            foreach ($packedBox->items as $packedItem) {
                $desc              = $packedItem->item->getDescription();
                $itemCounts[$desc] = ($itemCounts[$desc] ?? 0) + 1;
            }
            foreach ($itemCounts as $desc => $count) {
                $itemsList[] = ['description' => $desc, 'qty' => $count];
            }

            $visUrl = '';
            try {
                $visUrl = $packedBox->generateVisualisationURL();
            } catch (\Throwable $e) {
                // Visualization URL generation failed — not critical
            }

            $resultBoxes[] = [
                'reference'         => $box->getReference(),
                'outerLength'       => round(self::fromMm($box->getOuterLength(), $lengthUnitId), 2),
                'outerWidth'        => round(self::fromMm($box->getOuterWidth(), $lengthUnitId), 2),
                'outerHeight'       => round(self::fromMm($box->getOuterDepth(), $lengthUnitId), 2),
                'totalWeight'       => round(self::fromGrams($packedBox->getWeight(), $weightUnitId), 2),
                'itemWeight'        => round(self::fromGrams($packedBox->getItemWeight(), $weightUnitId), 2),
                'boxWeight'         => round(self::fromGrams($box->getEmptyWeight(), $weightUnitId), 2),
                'maxWeight'         => $box->getMaxWeight() === PHP_INT_MAX ? 0 : round(self::fromGrams($box->getMaxWeight(), $weightUnitId), 2),
                'totalValue'        => array_sum(array_map(fn ($pi) => $pi->item instanceof ShippableItem ? $pi->item->getValue() : 0.0, iterator_to_array($packedBox->items))),
                'volumeUtilisation' => $packedBox->getVolumeUtilisation(),
                'items'             => $itemsList,
                'visualisationUrl'  => $visUrl,
            ];
        }

        $unpackedResult = [];
        foreach ($unpackedItems as $unpackedItem) {
            $unpackedResult[] = [
                'description' => $unpackedItem->getDescription(),
                'length'      => round(self::fromMm($unpackedItem->getLength(), $lengthUnitId), 2),
                'width'       => round(self::fromMm($unpackedItem->getWidth(), $lengthUnitId), 2),
                'height'      => round(self::fromMm($unpackedItem->getDepth(), $lengthUnitId), 2),
                'weight'      => round(self::fromGrams($unpackedItem->getWeight(), $weightUnitId), 2),
            ];
        }

        return [
            'success'   => true,
            'boxCount'  => \count($resultBoxes),
            'itemCount' => array_sum(array_map(fn ($b) => array_sum(array_column($b['items'], 'qty')), $resultBoxes)),
            'boxes'     => $resultBoxes,
            'unpacked'  => $unpackedResult,
            'method'    => 'box_packing',
        ];
    }

    // =========================================================================
    // PRIVATE: BoxPacker execution
    // =========================================================================

    private static function runBoxPacker(array $boxes, array $items, array $options): PackingResult
    {
        $weightUnitId = (int) ($options['weight_unit_id'] ?? 1);
        $lengthUnitId = (int) ($options['length_unit_id'] ?? 1);
        $defaults     = self::getDefaults($options);
        $rotation     = self::resolveRotation($options['rotation'] ?? 'best_fit');

        $packer = new Packer();
        $packer->throwOnUnpackableItem(false);

        if (isset($options['max_boxes_to_balance_weight'])) {
            $packer->setMaxBoxesToBalanceWeight((int) $options['max_boxes_to_balance_weight']);
        }

        foreach ($boxes as $boxDef) {
            $boxDef = (array) $boxDef;
            $packer->addBox(new ShippingBox(
                reference: (string) ($boxDef['name'] ?? 'Box'),
                outerWidth: self::toMm((float) $boxDef['outer_width'], $lengthUnitId),
                outerLength: self::toMm((float) $boxDef['outer_length'], $lengthUnitId),
                outerDepth: self::toMm((float) $boxDef['outer_height'], $lengthUnitId),
                emptyWeight: self::toGrams((float) ($boxDef['box_weight'] ?? 0), $weightUnitId),
                innerWidth: self::toMm((float) ($boxDef['inner_width'] ?? $boxDef['outer_width']), $lengthUnitId),
                innerLength: self::toMm((float) ($boxDef['inner_length'] ?? $boxDef['outer_length']), $lengthUnitId),
                innerDepth: self::toMm((float) ($boxDef['inner_height'] ?? $boxDef['outer_height']), $lengthUnitId),
                maxWeight: self::toGrams((float) ($boxDef['max_weight'] ?? 0), $weightUnitId) ?: PHP_INT_MAX,
            ));
        }

        foreach ($items as $item) {
            $item = (object) $item;
            $qty  = self::getItemQty($item);
            for ($i = 0; $i < $qty; $i++) {
                $packer->addItem(new ShippableItem(
                    description: self::getItemDescription($item),
                    width: self::toMm(self::getItemDimension($item, 'width', $defaults['default_width']), $lengthUnitId),
                    length: self::toMm(self::getItemDimension($item, 'length', $defaults['default_length']), $lengthUnitId),
                    depth: self::toMm(self::getItemDimension($item, 'height', $defaults['default_height']), $lengthUnitId),
                    weight: self::toGrams(self::getItemWeight($item, $defaults['default_weight']), $weightUnitId),
                    rotation: $rotation,
                    value: self::getItemPrice($item),
                ));
            }
        }

        $packedBoxes   = $packer->pack();
        $unpackedItems = $packer->getUnpackedItems();

        $resultBoxes = [];
        foreach ($packedBoxes as $packedBox) {
            $box        = $packedBox->box;
            $itemsList  = [];
            $itemCounts = [];
            foreach ($packedBox->items as $packedItem) {
                $desc              = $packedItem->item->getDescription();
                $itemCounts[$desc] = ($itemCounts[$desc] ?? 0) + 1;
            }
            foreach ($itemCounts as $desc => $count) {
                $itemsList[] = ['description' => $desc, 'qty' => $count];
            }

            $totalValue = 0.0;
            foreach ($packedBox->items as $packedItem) {
                if ($packedItem->item instanceof ShippableItem) {
                    $totalValue += $packedItem->item->getValue();
                }
            }

            $resultBoxes[] = new PackedBoxResult(
                reference: $box->getReference(),
                outerLength: round(self::fromMm($box->getOuterLength(), $lengthUnitId), 4),
                outerWidth: round(self::fromMm($box->getOuterWidth(), $lengthUnitId), 4),
                outerHeight: round(self::fromMm($box->getOuterDepth(), $lengthUnitId), 4),
                totalWeight: round(self::fromGrams($packedBox->getWeight(), $weightUnitId), 4),
                itemWeight: round(self::fromGrams($packedBox->getItemWeight(), $weightUnitId), 4),
                boxWeight: round(self::fromGrams($box->getEmptyWeight(), $weightUnitId), 4),
                totalValue: $totalValue,
                volumeUtilisation: $packedBox->getVolumeUtilisation(),
                items: $itemsList,
            );
        }

        $unpackedResult = [];
        foreach ($unpackedItems as $unpackedItem) {
            $unpackedResult[] = [
                'description' => $unpackedItem->getDescription(),
                'length'      => round(self::fromMm($unpackedItem->getLength(), $lengthUnitId), 4),
                'width'       => round(self::fromMm($unpackedItem->getWidth(), $lengthUnitId), 4),
                'height'      => round(self::fromMm($unpackedItem->getDepth(), $lengthUnitId), 4),
                'weight'      => round(self::fromGrams($unpackedItem->getWeight(), $weightUnitId), 4),
            ];
        }

        return new PackingResult(boxes: $resultBoxes, unpacked: $unpackedResult, method: 'box_packing');
    }

    // =========================================================================
    // PRIVATE: Unit conversion
    // =========================================================================

    private static function toMm(float $value, int $lengthUnitId): int
    {
        if ($value <= 0) {
            return 1;
        }

        $mmId = self::getMmUnitId();
        if ($mmId !== null && LengthHelper::has($lengthUnitId)) {
            $converted = LengthHelper::convert($value, $lengthUnitId, $mmId);
            return max(1, (int) round($converted));
        }

        $unit   = LengthHelper::getUnit($lengthUnitId);
        $factor = self::LENGTH_TO_MM[strtolower($unit)] ?? 25.4;
        return max(1, (int) round($value * $factor));
    }

    private static function fromMm(int $valueMm, int $lengthUnitId): float
    {
        $mmId = self::getMmUnitId();
        if ($mmId !== null && LengthHelper::has($lengthUnitId)) {
            return LengthHelper::convert((float) $valueMm, $mmId, $lengthUnitId);
        }

        $unit   = LengthHelper::getUnit($lengthUnitId);
        $factor = self::LENGTH_TO_MM[strtolower($unit)] ?? 25.4;
        return $factor > 0 ? (float) $valueMm / $factor : (float) $valueMm;
    }

    private static function toGrams(float $value, int $weightUnitId): int
    {
        if ($value <= 0) {
            return 1;
        }

        $gId = self::getGramUnitId();
        if ($gId !== null && WeightHelper::has($weightUnitId)) {
            $converted = WeightHelper::convert($value, $weightUnitId, $gId);
            return max(1, (int) round($converted));
        }

        $unit   = WeightHelper::getUnit($weightUnitId);
        $factor = self::WEIGHT_TO_G[strtolower($unit)] ?? 453.592;
        return max(1, (int) round($value * $factor));
    }

    private static function fromGrams(int $valueG, int $weightUnitId): float
    {
        $gId = self::getGramUnitId();
        if ($gId !== null && WeightHelper::has($weightUnitId)) {
            return WeightHelper::convert((float) $valueG, $gId, $weightUnitId);
        }

        $unit   = WeightHelper::getUnit($weightUnitId);
        $factor = self::WEIGHT_TO_G[strtolower($unit)] ?? 453.592;
        return $factor > 0 ? (float) $valueG / $factor : (float) $valueG;
    }

    private static function getMmUnitId(): ?int
    {
        if (self::$mmUnitId !== null) {
            return self::$mmUnitId;
        }

        foreach (LengthHelper::getAll() as $id => $data) {
            if (strtolower($data['unit']) === 'mm') {
                self::$mmUnitId = $id;
                return $id;
            }
        }

        return null;
    }

    private static function getGramUnitId(): ?int
    {
        if (self::$gramUnitId !== null) {
            return self::$gramUnitId;
        }

        foreach (WeightHelper::getAll() as $id => $data) {
            if (strtolower($data['unit']) === 'g') {
                self::$gramUnitId = $id;
                return $id;
            }
        }

        return null;
    }

    // =========================================================================
    // PRIVATE: Item property normalization
    // =========================================================================

    private static function filterShippable(array $items): array
    {
        return array_values(array_filter($items, function ($item) {
            $item = (object) $item;
            if (isset($item->shipping) && (int) $item->shipping === 0) {
                return false;
            }
            if (isset($item->cartitem) && \is_object($item->cartitem) && isset($item->cartitem->shipping) && (int) $item->cartitem->shipping === 0) {
                return false;
            }
            return true;
        }));
    }

    private static function getItemWeight(object $item, float $default): float
    {
        $w = (float) ($item->weight ?? $item->orderitem_weight ?? 0);
        return $w > 0 ? $w : $default;
    }

    private static function getItemDimension(object $item, string $dim, float $default): float
    {
        $v = (float) ($item->{$dim} ?? 0);
        return $v > 0 ? $v : $default;
    }

    private static function getItemQty(object $item): int
    {
        return max(1, (int) ($item->product_qty ?? $item->orderitem_quantity ?? $item->qty ?? 1));
    }

    private static function getItemDescription(object $item): string
    {
        return (string) ($item->product_name ?? $item->orderitem_name ?? $item->description ?? 'Item');
    }

    private static function getItemPrice(object $item): float
    {
        return (float) ($item->price ?? $item->orderitem_price ?? 0);
    }

    private static function getDefaults(array $options): array
    {
        return [
            'default_weight' => (float) ($options['default_weight'] ?? 0.1),
            'default_length' => (float) ($options['default_length'] ?? 1.0),
            'default_width'  => (float) ($options['default_width'] ?? 1.0),
            'default_height' => (float) ($options['default_height'] ?? 1.0),
        ];
    }

    private static function resolveRotation(string $mode): Rotation
    {
        return match (strtolower($mode)) {
            'keep_flat' => Rotation::KeepFlat,
            'never'     => Rotation::Never,
            default     => Rotation::BestFit,
        };
    }

    private static function ensureBoxPackerAvailable(): bool
    {
        if (class_exists(Packer::class)) {
            return true;
        }

        if (!self::$autoloaderRegistered) {
            $autoloadFile = JPATH_LIBRARIES . '/j2commerce/vendor/dvdoug/boxpacker/autoload.php';
            if (file_exists($autoloadFile)) {
                require_once $autoloadFile;
                self::$autoloaderRegistered = true;
            }
        }

        return class_exists(Packer::class);
    }

    private static function packingResultToArray(PackingResult $result): array
    {
        $boxes = [];
        foreach ($result->boxes as $box) {
            $boxes[] = [
                'reference'         => $box->reference,
                'outerLength'       => $box->outerLength,
                'outerWidth'        => $box->outerWidth,
                'outerHeight'       => $box->outerHeight,
                'totalWeight'       => $box->totalWeight,
                'itemWeight'        => $box->itemWeight,
                'boxWeight'         => $box->boxWeight,
                'maxWeight'         => 0,
                'totalValue'        => $box->totalValue,
                'volumeUtilisation' => $box->volumeUtilisation,
                'items'             => $box->items,
                'visualisationUrl'  => $box->visualisationUrl,
            ];
        }

        return [
            'success'   => true,
            'boxCount'  => $result->getBoxCount(),
            'itemCount' => array_sum(array_map(fn ($b) => array_sum(array_column($b['items'], 'qty')), $boxes)),
            'boxes'     => $boxes,
            'unpacked'  => $result->unpacked,
            'method'    => $result->method,
        ];
    }
}
