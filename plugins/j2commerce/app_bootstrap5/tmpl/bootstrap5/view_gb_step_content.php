<?php
/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.AppGuidedbuilder
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;

/**
 * Variables available (injected by JS rendering — this PHP serves as template override reference):
 * $step         — Step definition object (step_number, step_label, display_type, columns, required, etc.)
 * $optionValues — Array of option value objects with display metadata
 */
$step         = $step ?? new \stdClass();
$optionValues = $optionValues ?? [];
?>
<div class="gb-step" data-step="<?php echo (int) ($step->step_number ?? 0); ?>">

    <div class="gb-step-header mb-3">
        <?php if (!empty($step->step_icon)) : ?>
        <span class="<?php echo htmlspecialchars($step->step_icon, ENT_QUOTES, 'UTF-8'); ?> me-2" aria-hidden="true"></span>
        <?php endif; ?>

        <h4 class="d-inline"><?php echo htmlspecialchars($step->step_label ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>

        <?php if (!empty($step->required)) : ?>
        <span class="badge bg-danger ms-2" style="font-size:0.7em;vertical-align:middle;">*</span>
        <?php endif; ?>

        <?php if (!empty($step->step_description)) : ?>
        <p class="text-muted mt-1 mb-0"><?php echo htmlspecialchars($step->step_description, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Display-type-specific content rendered here by JS or sub-template -->
    <?php
    // Render display type sub-template when used server-side
    $displayType = $step->display_type ?? 'visual_card';

    switch ($displayType) {
        case 'color_swatch':
            $subTemplate = 'view_gb_color_swatches';
            break;
        case 'image_swatch':
            $subTemplate = 'view_gb_image_swatches';
            break;
        case 'dropdown':
            $subTemplate = 'view_gb_dropdown';
            break;
        case 'range_input':
            $subTemplate = 'view_gb_range_input';
            break;
        case 'radio_card':
            $subTemplate = 'view_gb_radio_cards';
            break;
        case 'toggle':
            $subTemplate = 'view_gb_toggle';
            break;
        case 'text_input':
            $subTemplate = 'view_gb_text_input';
            break;
        case 'checkbox_group':
            $subTemplate = 'view_gb_checkbox_group';
            break;
        default:
            $subTemplate = 'view_gb_visual_cards';
    }

    // When rendered server-side, sub-templates can be included here.
    // In AJAX mode this wrapper is built by GuidedBuilder.renderStep() in JS.
    ?>

</div>
