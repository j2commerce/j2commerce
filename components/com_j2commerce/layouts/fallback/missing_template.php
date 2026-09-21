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

use J2Commerce\Component\J2commerce\Administrator\Helper\SubtemplateHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;

// CustomSubtemplateTrait passes no framework — resolve it as checkout/payment_icons.php does,
// so a bootstrap5 guess cannot lead the chain on a UIkit store.
$app          = Factory::getApplication();
$rawFramework = $displayData['framework']
    ?? ($app instanceof SiteApplication ? SubtemplateHelper::framework($app->getParams()) : '');
$framework    = ($rawFramework === 'uikit3' || $rawFramework === 'uikit') ? 'uikit' : 'bootstrap5';

// The framework folder is a fallback, not an override — see product/quantity.php.
echo ProductLayoutService::renderLayout('fallback.missing_template', $displayData, [$framework]);
