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

/** @var \J2Commerce\Component\J2commerce\Site\View\Myprofile\HtmlView $this */
?>

<div id="j2c-orders-container">
    <h4 class="uk-margin-bottom"><?php echo Text::_('COM_J2COMMERCE_MYPROFILE_ORDERS'); ?></h4>
    <div class="uk-margin-bottom">
        <input type="text" id="j2c-order-search" class="uk-input" placeholder="<?php echo $this->escape(Text::_('COM_J2COMMERCE_MYPROFILE_SEARCH_ORDERS')); ?>" autocomplete="off">
    </div>

    <!-- Announces the result of a search or page change; must live outside the region
         that is replaced, or the mutation happens to the live region itself. -->
    <div id="j2c-orders-status" class="uk-hidden-visually" role="status" aria-live="polite"></div>

    <!-- Orders list — myprofile.js replaces this container with a fresh render of
         default_orderslist.php, so a template override survives search and pagination. -->
    <div id="j2c-orders-table-wrap">
        <?php echo $this->loadTemplate('orderslist'); ?>
    </div>
</div>
