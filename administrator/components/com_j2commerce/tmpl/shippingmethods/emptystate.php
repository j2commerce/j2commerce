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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Shippingmethods\HtmlView $this */

J2CommerceHelper::strapper()->addCSS();

$displayData = [
    'textPrefix' => 'COM_J2COMMERCE_SHIPPING_METHODS',
    'formURL' => 'index.php?option=com_j2commerce&view=shippingmethods',
    'helpURL' => 'https://www.j2commerce.com/support/docs/shipping-methods',
    'icon' => 'icon-truck',
];

$user = $this->getCurrentUser();

if ($user->authorise('core.create', 'com_j2commerce')
    || count($user->getAuthorisedCategories('com_j2commerce', 'core.create')) > 0) {
    $displayData['createURL'] = 'index.php?option=com_installer&view=install';
}

echo $this->navbar ?? '';

echo LayoutHelper::render('joomla.content.emptystate', $displayData);

echo $this->footer ?? '';
