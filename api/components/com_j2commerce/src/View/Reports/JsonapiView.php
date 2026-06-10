<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\View\Reports;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Api\View\J2CommerceJsonapiView;

class JsonapiView extends J2CommerceJsonapiView
{
    protected string $pkField = 'extension_id';

    protected $fieldsToRenderItem = [
        'extension_id',
        'name',
        'element',
        'enabled',
        'ordering',
        'params',
    ];

    protected $fieldsToRenderList = [
        'extension_id',
        'name',
        'element',
        'enabled',
        'ordering',
    ];

    protected $relationship = [];
}
