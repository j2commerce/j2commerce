<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\View\Products;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Api\View\J2CommerceJsonapiView;

class JsonapiView extends J2CommerceJsonapiView
{
    protected string $pkField = 'j2commerce_product_id';


    protected $fieldsToRenderItem = [
        'j2commerce_product_id',
        'product_source_id',
        'product_type',
        'product_name',
        'visibility',
        'enabled',
        'taxprofile_id',
        'manufacturer_id',
        'vendor_id',
        'has_options',
        'sku',
        'upc',
        'price',
        'sale_price',
        'quantity',
        'main_image',
        'created_on',
        'modified_on',
    ];

    protected $fieldsToRenderList = [
        'j2commerce_product_id',
        'product_source_id',
        'product_type',
        'product_name',
        'visibility',
        'enabled',
        'manufacturer_id',
        'has_options',
        'sku',
        'price',
        'sale_price',
        'quantity',
        'main_image',
        'created_on',
    ];

    protected $relationship = [
        'manufacturer_id',
        'taxprofile_id',
    ];
}
