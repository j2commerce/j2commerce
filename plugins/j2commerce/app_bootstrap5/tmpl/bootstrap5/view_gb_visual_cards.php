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
$columns      = (int) ($step->columns ?? 2);
$colClass     = 'col-md-' . (int) floor(12 / max(1, min(4, $columns)));
?>
<div class="row g-3">
    <?php foreach ($optionValues as $val) :
        $label    = htmlspecialchars($val->display_label ?? $val->name ?? '', ENT_QUOTES, 'UTF-8');
        $valueId  = (int) ($val->id ?? 0);
    ?>
    <div class="<?php echo $colClass; ?>">
        <div class="gb-card" data-value-id="<?php echo $valueId; ?>" role="button" tabindex="0">

            <?php if (!empty($val->badge)) : ?>
            <span class="gb-badge"><?php echo htmlspecialchars($val->badge, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>

            <?php if (!empty($val->card_image)) : ?>
            <div class="gb-card-image">
                <img src="<?php echo htmlspecialchars($val->card_image, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo $label; ?>"
                     loading="lazy">
            </div>
            <?php elseif (!empty($val->card_icon)) : ?>
            <div class="gb-card-image text-center p-3">
                <span class="<?php echo htmlspecialchars($val->card_icon, ENT_QUOTES, 'UTF-8'); ?> fa-3x" aria-hidden="true"></span>
            </div>
            <?php endif; ?>

            <div class="gb-card-body">
                <div class="gb-card-title"><?php echo $label; ?></div>

                <?php if (!empty($val->description)) : ?>
                <div class="gb-card-description text-muted small">
                    <?php echo htmlspecialchars($val->description, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($val->price) && (float) $val->price !== 0.0) : ?>
                <div class="gb-card-price">
                    <?php
                    $price  = (float) $val->price;
                    $prefix = $val->price_prefix ?? '+';
                    echo htmlspecialchars(
                        match ($prefix) {
                            '-'   => '-' . number_format(abs($price), 2),
                            '='   => number_format($price, 2),
                            '%+'  => '+' . number_format($price, 2) . '%',
                            '%-'  => '-' . number_format(abs($price), 2) . '%',
                            default => '+' . number_format($price, 2),
                        },
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </div>
                <?php endif; ?>

                <div class="gb-card-check"><span class="fa-solid fa-circle-check" aria-hidden="true"></span></div>
            </div>

        </div>
    </div>
    <?php endforeach; ?>
</div>
