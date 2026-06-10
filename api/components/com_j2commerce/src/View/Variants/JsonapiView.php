<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\View\Variants;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Api\View\J2CommerceJsonapiView;

class JsonapiView extends J2CommerceJsonapiView
{
    protected string $pkField = 'j2commerce_variant_id';

    protected $fieldsToRenderItem = [
        'j2commerce_variant_id',
        'product_id',
        'is_master',
        'sku',
        'upc',
        'price',
        'manage_stock',
        'quantity_restriction',
        'min_sale_qty',
        'max_sale_qty',
        'availability',
        'sold',
        'allow_backorder',
        'isdefault_variant',
        'weight',
        'length',
        'width',
        'height',
        'shipping',
    ];

    protected $fieldsToRenderList = [
        'j2commerce_variant_id',
        'product_id',
        'is_master',
        'sku',
        'price',
        'manage_stock',
        'availability',
        'sold',
        'isdefault_variant',
    ];

    protected $relationship = [
        'product_id',
    ];
}
