<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Builder\Service;

use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

final class BlockPreviewService
{
    use DatabaseAwareTrait;

    public function __construct(DatabaseInterface $db)
    {
        $this->setDatabase($db);
    }

    public function getDisplayData(int $productId): array
    {
        if ($productId <= 0) {
            $productId = $this->getDefaultProductId();
        }

        if ($productId <= 0) {
            return $this->getPlaceholderData();
        }

        $product = ProductHelper::getFullProduct($productId, false, false);

        if (!$product) {
            return $this->getPlaceholderData();
        }

        // Load master variant
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :pid')
            ->where($db->quoteName('is_master') . ' = 1')
            ->bind(':pid', $productId, ParameterType::INTEGER)
            ->setLimit(1);

        $variant = $db->setQuery($query)->loadObject();

        return $this->buildDisplayData($product, $variant);
    }

    public function getDefaultProductId(): int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_product_id'))
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('j2commerce_product_id') . ' ASC')
            ->setLimit(1);

        return (int) ($db->setQuery($query)->loadResult() ?? 0);
    }

    public function getPlaceholderData(): array
    {
        $product                        = new \stdClass();
        $product->j2commerce_product_id = 0;
        $product->product_name          = 'Sample Product';
        $product->product_short_desc    = 'A sample product description for preview purposes.';
        $product->product_source        = 'com_content';
        $product->main_image            = '';
        $product->thumb_image           = '';
        $product->enabled               = 1;
        $product->sku                   = 'SAMPLE-001';

        $variant                                   = new \stdClass();
        $variant->j2commerce_variant_id            = 0;
        $variant->price                            = 29.99;
        $variant->sku                              = 'SAMPLE-001';
        $variant->is_master                        = 1;
        $variant->manage_stock                     = 0;

        return $this->buildDisplayData($product, $variant);
    }

    public function getPreviewProducts(int $limit = 20): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('p.j2commerce_product_id', 'id'),
                $db->quoteName('a.title', 'name'),
            ])
            ->from($db->quoteName('#__j2commerce_products', 'p'))
            ->join(
                'INNER',
                $db->quoteName('#__content', 'a'),
                $db->quoteName('a.id') . ' = ' . $db->quoteName('p.product_source_id')
                    . ' AND ' . $db->quoteName('p.product_source') . ' = ' . $db->quote('com_content')
            )
            ->where($db->quoteName('p.enabled') . ' = 1')
            ->order($db->quoteName('a.title') . ' ASC')
            ->setLimit($limit);

        return $db->setQuery($query)->loadObjectList() ?? [];
    }

    private function buildDisplayData(object $product, ?object $variant): array
    {
        $params    = ComponentHelper::getParams('com_j2commerce');
        $quantity  = 0;

        // Determine stock from productquantities table if variant has an ID
        if ($variant && !empty($variant->j2commerce_variant_id)) {
            $db    = $this->getDatabase();
            $vid   = (int) $variant->j2commerce_variant_id;
            $query = $db->getQuery(true)
                ->select($db->quoteName('quantity'))
                ->from($db->quoteName('#__j2commerce_productquantities'))
                ->where($db->quoteName('variant_id') . ' = :vid')
                ->bind(':vid', $vid, ParameterType::INTEGER)
                ->setLimit(1);

            $quantity = (int) ($db->setQuery($query)->loadResult() ?? 0);
        }

        return [
            'product'         => $product,
            'variant'         => $variant ?? new \stdClass(),
            'params'          => $params,
            'productLink'     => '#',
            'showTitle'       => true,
            'showSku'         => (bool) $params->get('show_sku', 0),
            'showUpc'         => (bool) $params->get('show_upc', 0),
            'showStock'       => (bool) $params->get('show_stock', 1),
            'showPrice'       => (bool) $params->get('show_price', 1),
            'showImage'       => true,
            'showCart'        => true,
            'showDescription' => true,
            'showQuickview'   => false,
            'linkTitle'       => true,
            'linkImage'       => true,
            'context'         => 'list',
            'stockStatus'     => $quantity > 0 ? 'In Stock' : 'Out of Stock',
            'cartText'        => 'Add to Cart',
        ];
    }
}
