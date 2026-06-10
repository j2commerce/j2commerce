<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\View\Coupons;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Api\View\J2CommerceJsonapiView;

class JsonapiView extends J2CommerceJsonapiView
{
    protected string $pkField = 'j2commerce_coupon_id';


    protected $fieldsToRenderItem = [
        'j2commerce_coupon_id',
        'coupon_name',
        'coupon_code',
        'enabled',
        'value',
        'value_type',
        'max_value',
        'free_shipping',
        'max_uses',
        'max_customer_uses',
        'max_quantity',
        'valid_from',
        'valid_to',
        'product_category',
        'products',
        'min_subtotal',
        'brand_ids',
    ];

    protected $fieldsToRenderList = [
        'j2commerce_coupon_id',
        'coupon_name',
        'coupon_code',
        'enabled',
        'value',
        'value_type',
        'valid_from',
        'valid_to',
    ];

    protected $relationship = [];
}
