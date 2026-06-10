<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;

/**
 * Base API controller for J2Commerce.
 *
 * Forces 'Administrator' prefix for model resolution since J2Commerce
 * API controllers reuse admin models (no API-specific models exist).
 *
 * @since  6.0.15
 */
abstract class J2CommerceApiController extends ApiController
{
    public function getModel($name = '', $prefix = '', $config = [])
    {
        if (!$prefix) {
            $prefix = 'Administrator';
        }

        return parent::getModel($name, $prefix, $config);
    }
}
