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

use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Coupons\HtmlView $this */

$displayData = [
    'textPrefix' => 'COM_J2COMMERCE_COUPONS',
    'formURL'    => 'index.php?option=com_j2commerce&view=coupons',
    /*'helpURL'    => 'https://docs.j2commerce.com/v6/sales/coupons',*/
    'icon'       => 'icon-fa-solid fa-scissors',
];

$user = Factory::getApplication()->getIdentity();
if ($user->authorise('core.create', 'com_j2commerce')) {
    $displayData['createURL'] = 'index.php?option=com_j2commerce&task=coupon.add';
}

echo $this->navbar;

echo LayoutHelper::render('joomla.content.emptystate', $displayData);

echo $this->footer ?? '';
