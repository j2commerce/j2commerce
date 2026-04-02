<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Edit-mode companion template for list/category/item_quickview.php
 * Outputs tokenized HTML for the Visual Builder sub-layout editor.
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

extract($displayData);
?>
<j2c-conditional data-condition="$showQuickview">
    <div class="j2commerce-product-quickview position-absolute bottom-0 end-0 mb-2 me-2 mb-lg-3 me-lg-3">
        <a class="btn btn-dark btn-sm j2commerce-quickview-btn"
           href="<j2c-token data-token="PRODUCT_LINK"><?php echo htmlspecialchars($productLink ?? '#', ENT_QUOTES, 'UTF-8'); ?></j2c-token>"
           title="<?php echo Text::_('COM_J2COMMERCE_PRODUCT_QUICKVIEW'); ?>">
            <span class="fa-solid fa-eye" aria-hidden="true"></span>
        </a>
    </div>
</j2c-conditional>
