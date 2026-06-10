<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\View\Addresses;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Api\View\J2CommerceJsonapiView;

class JsonapiView extends J2CommerceJsonapiView
{
    protected string $pkField = 'j2commerce_address_id';

    protected $fieldsToRenderItem = [
        'j2commerce_address_id',
        'user_id',
        'first_name',
        'last_name',
        'email',
        'address_1',
        'address_2',
        'city',
        'zip',
        'zone_id',
        'country_id',
        'phone_1',
        'phone_2',
        'company',
        'tax_number',
        'type',
        'country_name',
        'zone_name',
    ];

    protected $fieldsToRenderList = [
        'j2commerce_address_id',
        'user_id',
        'first_name',
        'last_name',
        'email',
        'city',
        'country_name',
        'zone_name',
        'phone_1',
        'type',
    ];

    protected $relationship = [
        'country_id',
        'zone_id',
    ];
}
