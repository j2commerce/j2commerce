<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;

$rawFramework = $displayData['framework'] ?? '';
$framework = ($rawFramework === 'uikit3' || $rawFramework === 'uikit') ? 'uikit' : 'bootstrap5';

ProductLayoutService::setSubtemplateOverride($framework);
try {
    echo ProductLayoutService::renderLayout('fallback.missing_template', $displayData);
} finally {
    ProductLayoutService::clearSubtemplateOverride();
}
