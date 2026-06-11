<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\View\Countries;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Api\View\J2CommerceJsonapiView;

class JsonapiView extends J2CommerceJsonapiView
{
    protected string $pkField = 'j2commerce_country_id';


    protected $fieldsToRenderItem = [
        'j2commerce_country_id',
        'country_name',
        'country_isocode_2',
        'country_isocode_3',
        'enabled',
    ];

    protected $fieldsToRenderList = [
        'j2commerce_country_id',
        'country_name',
        'country_isocode_2',
        'country_isocode_3',
        'enabled',
    ];

    protected $relationship = [];
}
