<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\View;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\JsonApiView;

/**
 * Base JSON:API view for J2Commerce.
 *
 * Maps custom primary keys (e.g. j2commerce_product_id) to the standard
 * 'id' field expected by the tobscure/json-api serializer.
 *
 * @since  6.0.15
 */
abstract class J2CommerceJsonapiView extends JsonApiView
{
    protected string $pkField = 'id';

    protected function prepareItem($item)
    {
        if ($this->pkField !== 'id' && isset($item->{$this->pkField})) {
            $item->id = $item->{$this->pkField};
        }

        return $item;
    }
}
