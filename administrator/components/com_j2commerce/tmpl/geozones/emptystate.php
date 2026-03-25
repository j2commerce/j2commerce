<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;

$displayData = [
    'textPrefix' => 'COM_J2COMMERCE_GEOZONES',
    'formURL'    => 'index.php?option=com_j2commerce&view=geozones',
    'icon'       => 'icon-fa-solid fa-border-none',
];

$user = Factory::getApplication()->getIdentity();
if ($user->authorise('core.create', 'com_j2commerce')) {
    $displayData['createURL'] = 'index.php?option=com_j2commerce&task=geozone.add';
}

echo LayoutHelper::render('joomla.content.emptystate', $displayData);

