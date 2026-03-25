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

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Orderstatuses\HtmlView $this */

$displayData = [
    'textPrefix' => 'COM_J2COMMERCE_ORDERSTATUSES',
    'formURL'    => 'index.php?option=com_j2commerce&view=orderstatuses',
    'icon'       => 'icon-fa-solid fa-check-square',
];

$user = Factory::getApplication()->getIdentity();
if ($user->authorise('core.create', 'com_j2commerce')) {
    $displayData['createURL'] = 'index.php?option=com_j2commerce&task=orderstatus.add';
}

echo $this->navbar;

echo LayoutHelper::render('joomla.content.emptystate', $displayData);

echo $this->footer ?? '';
