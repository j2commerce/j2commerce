<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\View\Orders;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Api\View\J2CommerceJsonapiView;

class JsonapiView extends J2CommerceJsonapiView
{
    protected string $pkField = 'j2commerce_order_id';


    protected $fieldsToRenderItem = [
        'j2commerce_order_id',
        'order_id',
        'user_id',
        'user_email',
        'order_state_id',
        'order_state',
        'orderstatus_name',
        'order_total',
        'order_subtotal',
        'order_tax',
        'order_shipping',
        'order_shipping_tax',
        'order_discount',
        'order_surcharge',
        'order_fees',
        'order_credit',
        'order_refund',
        'orderpayment_type',
        'transaction_id',
        'transaction_status',
        'currency_code',
        'currency_value',
        'customer_note',
        'customer_language',
        'is_shippable',
        'invoice_prefix',
        'invoice_number',
        'billing_first_name',
        'billing_last_name',
        'created_on',
        'modified_on',
    ];

    protected $fieldsToRenderList = [
        'j2commerce_order_id',
        'order_id',
        'user_id',
        'user_email',
        'order_state_id',
        'orderstatus_name',
        'order_total',
        'orderpayment_type',
        'currency_code',
        'billing_first_name',
        'billing_last_name',
        'created_on',
    ];

    protected $relationship = [
        'user_id',
        'order_state_id',
    ];
}
