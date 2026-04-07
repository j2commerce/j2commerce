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

extract($displayData);

// This layout is typically loaded via loadTemplate in the main list view
// For standalone usage via ProductLayoutService
$sortOptions = $params->get('list_sort_options', []);
$currentSort = $displayData['currentSort'] ?? '';
$currentDir = $displayData['currentDir'] ?? 'asc';
?>
<div class="j2commerce-sortfilter-container">
    <div class="j2commerce-sort-options">
        <label for="j2commerce-sort"><?php echo Text::_('COM_J2COMMERCE_SORT_BY'); ?></label>
        <select id="j2commerce-sort" class="uk-select j2commerce-sort-select">
            <option value=""><?php echo Text::_('COM_J2COMMERCE_SELECT_SORT'); ?></option>
            <option value="product_name" <?php echo $currentSort === 'product_name' ? 'selected' : ''; ?>>
                <?php echo Text::_('COM_J2COMMERCE_SORT_NAME'); ?>
            </option>
            <option value="price" <?php echo $currentSort === 'price' ? 'selected' : ''; ?>>
                <?php echo Text::_('COM_J2COMMERCE_SORT_PRICE'); ?>
            </option>
            <option value="created_on" <?php echo $currentSort === 'created_on' ? 'selected' : ''; ?>>
                <?php echo Text::_('COM_J2COMMERCE_SORT_NEWEST'); ?>
            </option>
        </select>
    </div>
</div>
