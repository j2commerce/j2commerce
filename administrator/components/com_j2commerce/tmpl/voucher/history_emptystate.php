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

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Voucher\HtmlView $this */
?>
<div class="px-4 py-5 text-center">
    <span class="fa-4x icon-list mb-3 d-block text-body-secondary" aria-hidden="true"></span>
    <h3 class="fs-5"><?php echo Text::_('COM_J2COMMERCE_VOUCHER_HISTORY_EMPTYSTATE_TITLE'); ?></h3>
    <p class="text-body-secondary col-lg-6 mx-auto mb-3">
        <?php echo Text::_('COM_J2COMMERCE_VOUCHER_HISTORY_EMPTYSTATE_CONTENT'); ?>
    </p>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adjustBalanceModal">
        <?php echo Text::_('COM_J2COMMERCE_VOUCHER_ADJUST_BALANCE'); ?>
    </button>
</div>
