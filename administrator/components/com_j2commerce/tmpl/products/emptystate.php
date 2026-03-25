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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Products\HtmlView $this */

$displayData = [
    'textPrefix' => 'COM_J2COMMERCE_PRODUCTS',
    'formURL'    => 'index.php?option=com_j2commerce&view=products',
    'icon'       => 'icon-fa-solid fa-tags',
];

$user = Factory::getApplication()->getIdentity();
if ($user->authorise('core.create', 'com_j2commerce')) {
    $return = urlencode(base64_encode((string) Uri::getInstance()));
    $displayData['createURL'] = Route::_('index.php?option=com_content&view=article&layout=edit&return=' . $return, false);
}

echo $this->navbar;

echo LayoutHelper::render('joomla.content.emptystate', $displayData);

echo $this->footer ?? '';
