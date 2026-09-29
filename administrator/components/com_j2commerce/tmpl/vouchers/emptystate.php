<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die();

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Vouchers\HtmlView $this */

$displayData = [
    'textPrefix' => 'COM_J2COMMERCE_VOUCHERS',
    'formURL'    => 'index.php?option=com_j2commerce&view=vouchers',
    'icon'       => 'icon-fa-solid fa-money-check',
];

$user = Factory::getApplication()->getIdentity();
if ($user->authorise('core.create', 'com_j2commerce')) {
    $displayData['createURL'] = 'index.php?option=com_j2commerce&task=voucher.add';
}

echo $this->navbar ?? '';

if (!(int) J2CommerceHelper::config()->get('enable_voucher', 0)) {
    echo LayoutHelper::render('alert.featuredisabled', ['message' => 'COM_J2COMMERCE_VOUCHERS_DISABLED_WARNING'], JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts');
}

echo LayoutHelper::render('joomla.content.emptystate', $displayData);

echo $this->footer ?? '';
