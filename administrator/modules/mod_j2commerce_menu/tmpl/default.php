<?php
/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_menu
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$hideLinks = $app->getInput()->getBool('hidemainmenu');

if ($hideLinks || $menuItems < 1) {
    return;
}

// Detect active section for auto-expand
$activeSection = null;
foreach ($menuItems as $idx => $item) {
    if (!empty($item['children'])) {
        foreach ($item['children'] as $child) {
            if (($child['view'] ?? '') === $currentView) {
                $activeSection = $idx;
                break 2;
            }
        }
    }
}

$wa = $app->getDocument()->getWebAssetManager();

$wa->registerAndUseStyle(
    'mod_j2commerce_menu',
    'media/com_j2commerce/css/administrator/mod-menu.css'
);

$wa->registerAndUseScript(
    'mod_j2commerce_menu',
    'media/com_j2commerce/js/administrator/mod-menu.js',
    [],
    ['defer' => true],
    ['metismenujs']
);
?>

<div class="header-item-content header-profile">
    <button class="d-flex align-items-center ps-0 py-0 border-0 bg-transparent"
            data-bs-toggle="offcanvas"
            data-bs-target="#j2commerceOffcanvas"
            type="button"
            title="<?php echo Text::_('COM_J2COMMERCE'); ?>"
            aria-controls="j2commerceOffcanvas">
        <div class="header-item-icon">
            <span class="fa-solid fa-cart-shopping" aria-hidden="true"></span>
        </div>
        <div class="header-item-text">
            <?php echo Text::_('COM_J2COMMERCE'); ?>
        </div>
    </button>
</div>

<div class="offcanvas offcanvas-end sidebar-wrapper" tabindex="-1" id="j2commerceOffcanvas"
     aria-labelledby="j2commerceOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title d-flex align-items-center gap-2" id="j2commerceOffcanvasLabel">
            <span class="fa-solid fa-cart-shopping" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE'); ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
    </div>
    <div class="offcanvas-body p-0">
        <ul id="j2commerceNav" class="nav flex-column j2c-nav">
            <?php foreach ($menuItems as $idx => $item) : ?>
                <?php if (!empty($item['children'])) : ?>
                    <?php $isExpanded = ($activeSection === $idx); ?>
                    <li class="item item-level-1 parent<?php echo $isExpanded ? ' mm-active' : ''; ?>">
                        <a class="has-arrow" href="#">
                            <?php if (!empty($item['icon'])) : ?>
                                <span class="<?php echo $item['icon']; ?> fa-fw" aria-hidden="true"></span>
                            <?php endif; ?>
                            <span class="sidebar-item-title"><?php echo Text::_($item['title']); ?></span>
                        </a>
                        <ul class="collapse-level-1 mm-collapse">
                            <?php foreach ($item['children'] as $child) : ?>
                                <?php $isActive = (($child['view'] ?? '') === $currentView); ?>
                                <li class="item item-level-2">
                                    <a class="no-dropdown<?php echo $isActive ? ' mm-active' : ''; ?>"
                                       href="<?php echo Route::_($child['link']); ?>">
                                        <?php if (!empty($child['icon'])) : ?>
                                            <span class="<?php echo $child['icon']; ?> fa-fw" aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <span class="sidebar-item-title"><?php echo Text::_($child['title']); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php else : ?>
                    <?php $isActive = (($item['view'] ?? '') === $currentView); ?>
                    <li class="item item-level-1">
                        <a class="no-dropdown<?php echo $isActive ? ' mm-active' : ''; ?>"
                           href="<?php echo Route::_($item['link']); ?>">
                            <?php if (!empty($item['icon'])) : ?>
                                <span class="<?php echo $item['icon']; ?> fa-fw" aria-hidden="true"></span>
                            <?php endif; ?>
                            <span class="sidebar-item-title"><?php echo Text::_($item['title']); ?></span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
