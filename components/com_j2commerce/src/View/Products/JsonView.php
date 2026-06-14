<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\View\Products;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\JsonView as BaseJsonView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * JSON Products list view for site frontend.
 *
 * @since  6.0.0
 */
class JsonView extends BaseJsonView
{
    public function display($tpl = null): void
    {
        $model = $this->getModel();

        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $items    = $model->getItems();
        $currency = CurrencyHelper::getCode();
        $mapped   = [];

        foreach ($items as $item) {
            $id    = (int) $item->j2commerce_product_id;
            $alias = $item->alias ?? null;
            $catid = isset($item->catid) ? (int) $item->catid : null;

            $url = Route::_(
                RouteHelper::getProductRoute($id, $alias, $catid),
                true
            );

            $sku   = isset($item->variant->sku) ? (string) $item->variant->sku : null;
            $price = isset($item->variant->price) ? (float) $item->variant->price : null;

            $inStock = null;
            if (isset($item->variant->availability)) {
                $inStock = (int) $item->variant->availability > 0;
            }

            $mapped[] = [
                'id'            => $id,
                'name'          => strip_tags((string) ($item->product_name ?? '')),
                'sku'           => $sku,
                'price'         => $price,
                'currency'      => $currency,
                'url'           => $url,
                'image'         => $this->cleanImageUrl($item->main_image ?? null),
                'description'   => strip_tags((string) ($item->product_short_desc ?? '')),
                'category_id'   => $catid,
                'category_name' => isset($item->source->category_title)
                    ? strip_tags((string) $item->source->category_title)
                    : '',
                'in_stock'      => $inStock,
            ];
        }

        $this->_output = [
            'products' => $mapped,
            'count'    => \count($mapped),
        ];

        // The site application overwrites the JSON document buffer with the
        // component's echoed output, so emit the payload via echo rather than
        // relying on the parent setBuffer() path.
        echo json_encode(
            $this->_output,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    /**
     * Strip the #joomlaImage:// metadata fragment and return an absolute URL
     * so external readers can resolve the image.
     */
    private function cleanImageUrl(?string $image): ?string
    {
        if (empty($image)) {
            return null;
        }

        $clean = HTMLHelper::_('cleanImageURL', $image)->url;

        return $clean === '' ? null : Uri::root() . ltrim($clean, '/');
    }
}
