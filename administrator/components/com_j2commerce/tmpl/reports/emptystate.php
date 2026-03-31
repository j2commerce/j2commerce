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

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Reports\HtmlView $this */

J2CommerceHelper::strapper()->addCSS();

$displayData = [
    'textPrefix' => 'COM_J2COMMERCE_REPORTS',
    'formURL' => 'index.php?option=com_j2commerce&view=reports',
    'helpURL' => 'https://www.j2commerce.com/support/docs/reports',
    'icon' => 'icon-chart-line',
];

$user = $this->getCurrentUser();

if ($user->authorise('core.create', 'com_j2commerce')
    || count($user->getAuthorisedCategories('com_j2commerce', 'core.create')) > 0) {
    $displayData['createURL'] = 'index.php?option=com_installer&view=install';
}
echo $this->navbar ?? '';

echo LayoutHelper::render('joomla.content.emptystate', $displayData);

echo $this->reportCards;

echo $this->footer ?? '';
