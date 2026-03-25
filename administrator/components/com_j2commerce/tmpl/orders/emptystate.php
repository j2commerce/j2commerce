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

use Joomla\CMS\Layout\LayoutHelper;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Orders\HtmlView $this */

$displayData = [
    'textPrefix' => 'COM_J2COMMERCE_ORDERS',
    'formURL'    => 'index.php?option=com_j2commerce&view=orders',
    'icon'       => 'icon-shopping-cart fas fa-shopping-cart',
];

echo $this->navbar;

echo  LayoutHelper::render('joomla.content.emptystate', $displayData);

echo $this->footer ?? '';
