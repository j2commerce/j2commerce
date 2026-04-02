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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Queues\HtmlView $this */

echo $this->navbar ?? '';

echo LayoutHelper::render('joomla.content.emptystate', [
    'textPrefix' => 'COM_J2COMMERCE_QUEUE',
    'icon'       => 'icon fa-solid fa-list-check fa-8x',
]);

echo $this->footer ?? '';
