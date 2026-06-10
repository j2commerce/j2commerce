<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\View\Vouchers;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Api\View\J2CommerceJsonapiView;

class JsonapiView extends J2CommerceJsonapiView
{
    protected string $pkField = 'j2commerce_voucher_id';


    protected $fieldsToRenderItem = [
        'j2commerce_voucher_id',
        'order_id',
        'email_to',
        'voucher_code',
        'voucher_type',
        'subject',
        'voucher_value',
        'valid_from',
        'valid_to',
        'enabled',
        'created_on',
    ];

    protected $fieldsToRenderList = [
        'j2commerce_voucher_id',
        'voucher_code',
        'voucher_type',
        'email_to',
        'voucher_value',
        'enabled',
        'created_on',
    ];

    protected $relationship = [];
}
