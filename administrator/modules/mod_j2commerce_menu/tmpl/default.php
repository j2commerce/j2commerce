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

$wa->addInlineStyle('#j2commerceOffcanvas{width:320px;max-inline-size:320px;min-block-size:auto;overflow:visible;flex:none;z-index:1045}#j2commerceOffcanvas .offcanvas-header{border-bottom:1px solid rgb(255 255 255 / .1)}#j2commerceOffcanvas .offcanvas-title{color:var(--sidebar-item-color)}#j2commerceOffcanvas .btn-close{filter:invert(1) grayscale(100%) brightness(200%)}#j2commerceOffcanvas .j2c-nav .has-arrow .sidebar-item-title{margin-inline-end:auto}#j2commerceOffcanvas .j2c-nav .has-arrow:after{content:"\f105";justify-content:center;align-items:center;inline-size:2rem;font-family:"Font Awesome 6 Free";font-weight:900;display:flex}#j2commerceOffcanvas .j2c-nav .mm-active>.has-arrow:after{content:"\f107"}#j2commerceOffcanvas .j2c-nav a.mm-active{background-color:var(--main-nav-mm-active-bg)}#j2commerceOffcanvas .j2c-nav .mm-collapse{display:none}#j2commerceOffcanvas .j2c-nav .mm-collapse.mm-collapsed,#j2commerceOffcanvas .j2c-nav .mm-collapse.mm-show{display:block;padding-left:0}#j2commerceOffcanvas .j2c-nav .mm-collapsing{height:0;transition:all .35s;position:relative;overflow:hidden}#j2commerceOffcanvas .item-level-1.parent{flex-direction:column}#j2commerceOffcanvas .item-level-2>a{padding-inline-start:1.5rem}');

$wa->addInlineScript(
    'new MetisMenu("#j2commerceNav", { toggle: true });',
    ['position' => 'after'],
    ['type' => 'module'],
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
