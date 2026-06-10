<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\View\Taxrates;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Api\View\J2CommerceJsonapiView;

class JsonapiView extends J2CommerceJsonapiView
{
    protected string $pkField = 'j2commerce_taxrate_id';


    protected $fieldsToRenderItem = [
        'j2commerce_taxrate_id',
        'taxrate_name',
        'tax_percent',
        'geozone_id',
        'enabled',
    ];

    protected $fieldsToRenderList = [
        'j2commerce_taxrate_id',
        'taxrate_name',
        'tax_percent',
        'geozone_id',
        'enabled',
    ];

    protected $relationship = [
        'geozone_id',
    ];
}
