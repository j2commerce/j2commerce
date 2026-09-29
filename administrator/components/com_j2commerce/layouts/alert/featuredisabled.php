<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var array $displayData ['message' => language key] — shown when a store feature is switched off. */

$canConfigure = Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_j2commerce');
$configUrl    = Route::_(
    'index.php?option=com_config&view=component&component=com_j2commerce&return='
    . urlencode(base64_encode(Uri::getInstance()->toString()))
);
?>
<div class="alert alert-warning">
    <span class="icon-warning" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('WARNING'); ?></span>
    <?php echo Text::_($displayData['message']); ?>
    <?php if ($canConfigure) : ?>
        <a href="<?php echo $configUrl; ?>" class="alert-link"><?php echo Text::_('COM_J2COMMERCE_OPEN_CONFIGURATION'); ?></a>
    <?php endif; ?>
</div>
