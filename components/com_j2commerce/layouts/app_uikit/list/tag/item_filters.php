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
// Filters are rendered based on the active filter configuration
$filters = $displayData['filters'] ?? [];

if (empty($filters)) {
    return;
}
?>
<div class="j2commerce-filters-container">
    <h4><?php echo Text::_('COM_J2COMMERCE_FILTER_BY'); ?></h4>

    <?php foreach ($filters as $filter): ?>
        <div class="j2commerce-filter-group">
            <h5><?php echo htmlspecialchars($filter->title ?? '', ENT_QUOTES, 'UTF-8'); ?></h5>
            <ul class="j2commerce-filter-options uk-list">
                <?php foreach ($filter->options ?? [] as $option): ?>
                    <li>
                        <label>
                            <input type="checkbox"
                                   class="uk-checkbox j2commerce-filter-option"
                                   name="filter[<?php echo $filter->id ?? ''; ?>][]"
                                   value="<?php echo htmlspecialchars($option->value ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                   <?php echo !empty($option->selected) ? 'checked' : ''; ?> />
                            <?php echo htmlspecialchars($option->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            <?php if (!empty($option->count)): ?>
                                <span class="filter-count">(<?php echo (int) $option->count; ?>)</span>
                            <?php endif; ?>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</div>
