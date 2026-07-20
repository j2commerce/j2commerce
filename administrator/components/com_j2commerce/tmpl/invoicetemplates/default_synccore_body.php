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

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Invoicetemplates\HtmlView $this */
?>

<div class="p-3">
    <p><?php echo Text::_('COM_J2COMMERCE_INVOICETEMPLATE_SYNC_CORE_DESC'); ?></p>
    <joomla-toolbar-button task="invoicetemplates.syncCore">
        <button type="button" class="btn btn-primary btn-sm">
            <span class="fa-solid fa-rotate-right" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_INVOICETEMPLATE_SYNC_CORE_CONFIRM'); ?>
        </button>
    </joomla-toolbar-button>
</div>
