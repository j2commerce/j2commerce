<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\View\Inventory;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Api\View\J2CommerceJsonapiView;

class JsonapiView extends J2CommerceJsonapiView
{
    protected string $pkField = 'j2commerce_product_id';


    protected $fieldsToRenderItem = [
        'j2commerce_product_id',
        'product_source_id',
        'sku',
        'quantity',
        'manage_stock',
        'min_out_qty',
        'min_sale_qty',
        'max_sale_qty',
        'notify_qty',
    ];

    protected $fieldsToRenderList = [
        'j2commerce_product_id',
        'sku',
        'quantity',
        'manage_stock',
    ];

    protected $relationship = [];
}
