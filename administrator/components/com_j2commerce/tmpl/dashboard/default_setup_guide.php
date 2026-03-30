<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

?>
<div class="offcanvas offcanvas-end sidebar-wrapper" tabindex="-1" id="j2commerce-setup-guide"
     aria-labelledby="j2commerce-setup-guide-label" style="width: 400px;" data-bs-theme="dark">
    <div class="offcanvas-header">
        <button type="button" class="btn btn-sm btn-link p-0 me-2 d-none" data-setup-back
                aria-label="<?php echo Text::_('COM_J2COMMERCE_SETUP_GUIDE_BACK'); ?>">
            <span class="icon-chevron-left" aria-hidden="true"></span>
        </button>
        <h5 class="offcanvas-title d-flex align-items-center gap-2" id="j2commerce-setup-guide-label">
            <span class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_SETUP_GUIDE'); ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="setup-guide-container small">
            <!-- Progress bar -->
            <div class="setup-progress px-3 py-3 border-bottom">
                <div class="d-flex justify-content-between mb-1">
                    <small class="setup-progress-label"></small>
                    <small class="setup-progress-count"></small>
                </div>
                <div class="setup-progress-bar rounded-1">
                    <div class="setup-progress-fill rounded-1" style="width: 0%"></div>
                </div>
            </div>
            <!-- Loading spinner (shown while fetching) -->
            <div class="setup-loading text-center py-5">
                <div class="spinner-border text-secondary" role="status">
                    <span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span>
                </div>
            </div>
            <!-- Check groups container (AJAX-loaded) -->
            <div class="setup-groups-list d-none"></div>
            <!-- Detail view container (hidden initially) -->
            <div class="setup-detail-view d-none px-3 py-3"></div>
        </div>
    </div>
</div>
