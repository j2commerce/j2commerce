<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\View\Orderitems;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Api\View\J2CommerceJsonapiView;

class JsonapiView extends J2CommerceJsonapiView
{
    protected string $pkField = 'j2commerce_orderitem_id';


    protected $fieldsToRenderItem = [
        'j2commerce_orderitem_id',
        'order_id',
        'product_id',
        'variant_id',
        'orderitem_sku',
        'orderitem_name',
        'orderitem_attributes',
        'orderitem_quantity',
        'orderitem_price',
        'orderitem_option_price',
        'orderitem_finalprice',
        'orderitem_finalprice_with_tax',
        'orderitem_tax',
        'orderitem_discount',
        'orderitem_weight',
        'created_on',
    ];

    protected $fieldsToRenderList = [
        'j2commerce_orderitem_id',
        'order_id',
        'orderitem_sku',
        'orderitem_name',
        'orderitem_quantity',
        'orderitem_finalprice',
        'orderitem_tax',
    ];

    protected $relationship = [
        'product_id',
        'variant_id',
    ];
}
