<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$grouped         = $displayData['grouped'] ?? [];
$typeRenderers   = $displayData['typeRenderers'] ?? [];
$isAdminContext  = $displayData['isAdminContext'] ?? false;
$buildDownloadUrl = $displayData['buildDownloadUrl'] ?? static fn(string $m): string => '';
$variant         = $displayData['variant'] ?? 'full';

$mutedClass      = 'uk-text-muted uk-display-block uk-text-truncate';
$attachmentIcon  = static function (string $type) use ($variant): string {
    if ($variant === 'inline') {
        return '';
    }
    $ukIcon = $type === 'image' ? 'image' : 'file-text';
    return '<span uk-icon="icon: ' . $ukIcon . '" class="uk-margin-small-right" aria-hidden="true"></span>';
};

// --- Inline variant (emails) — no icon, plain filename text ---
if ($variant === 'inline') {
    $parts = [];
    foreach ($grouped as $group) {
        $groupType = $group['type'] ?? '';
        if (!empty($typeRenderers[$groupType])) {
            $parts[] = $typeRenderers[$groupType];
            continue;
        }
        foreach ($group['items'] as $gItem) {
            if ($groupType === 'product_children') {
                $qty = (int) ($gItem['qty'] ?? 1);
                $label = $qty > 1
                    ? '(' . $qty . ') ' . htmlspecialchars($gItem['name'], ENT_QUOTES, 'UTF-8')
                    : htmlspecialchars($gItem['name'], ENT_QUOTES, 'UTF-8');
                $parts[] = $label;
            } else {
                $name  = htmlspecialchars($gItem['name'] ?? '', ENT_QUOTES, 'UTF-8');
                $value = htmlspecialchars($gItem['value'] ?? '', ENT_QUOTES, 'UTF-8');
                $parts[] = $value !== '' ? $name . ': ' . $value : $name;
            }
        }
    }
    echo implode('<br>', $parts);
    return;
}

// --- Compact variant (sidecart, checkout, confirmation, myprofile, drawer, cart_module) ---
if ($variant === 'compact') {
    foreach ($grouped as $group) {
        $groupType = $group['type'] ?? '';
        if (!empty($typeRenderers[$groupType])) {
            echo $typeRenderers[$groupType];
            continue;
        }
        foreach ($group['items'] as $gItem) {
            if ($groupType === 'product_children') {
                $qty = (int) ($gItem['qty'] ?? 1);
                $label = $qty > 1
                    ? '(' . $qty . ') ' . htmlspecialchars(Text::_($gItem['name']), ENT_QUOTES, 'UTF-8')
                    : htmlspecialchars(Text::_($gItem['name']), ENT_QUOTES, 'UTF-8');
                ?>
                <small class="<?php echo $mutedClass; ?>"><?php echo $label; ?></small>
                <?php
                continue;
            }

            $name      = htmlspecialchars(Text::_($gItem['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $value     = htmlspecialchars(Text::_($gItem['value'] ?? ''), ENT_QUOTES, 'UTF-8');
            $mangled   = (string) ($gItem['mangled_name'] ?? '');
            $itemType  = (string) ($gItem['type'] ?? '');
            ?>
            <small class="<?php echo $mutedClass; ?>">
                <?php echo $name; ?><?php if ($value !== ''): ?>:
                    <?php if ($mangled !== ''): ?>
                        <?php echo $attachmentIcon($itemType); ?><?php echo $value; ?>
                    <?php else: ?>
                        <?php echo $value; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </small>
            <?php
        }
    }
    return;
}

// --- Full variant (cart page, admin order, admin edit) ---
?>
<div class="cart-item-options">
    <?php foreach ($grouped as $group):
        $groupType = $group['type'] ?? '';
        if (!empty($typeRenderers[$groupType])) {
            echo $typeRenderers[$groupType];
            continue;
        }
        foreach ($group['items'] as $gItem):
            if ($groupType === 'product_children'):
                $qty = (int) ($gItem['qty'] ?? 1);
                $label = $qty > 1
                    ? '(' . $qty . ') ' . htmlspecialchars(Text::_($gItem['name']), ENT_QUOTES, 'UTF-8')
                    : htmlspecialchars(Text::_($gItem['name']), ENT_QUOTES, 'UTF-8');
                ?>
                <div class="uk-text-small uk-flex uk-flex-middle">
                    <div class="item-option item-option-name"><?php echo $label; ?></div>
                </div>
            <?php else:
                $name      = htmlspecialchars(Text::_($gItem['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                $value     = htmlspecialchars(Text::_($gItem['value'] ?? ''), ENT_QUOTES, 'UTF-8');
                $mangled   = (string) ($gItem['mangled_name'] ?? '');
                $itemType  = (string) ($gItem['type'] ?? '');
                $isFileUpload = $mangled !== '';
                $isAdminLink  = $isFileUpload && $isAdminContext;
                ?>
                <div class="uk-text-small uk-flex uk-flex-middle">
                    <div class="item-option item-option-name"><?php echo $name; ?><?php if ($value !== ''): ?>:<?php endif; ?></div>
                    <?php if ($isAdminLink):
                        $href = $buildDownloadUrl($mangled);
                        ?>
                        <a href="<?php echo $href; ?>" class="item-option item-option-value uk-text-bold uk-margin-small-left text-decoration-none"
                           title="<?php echo htmlspecialchars(Text::_('COM_J2COMMERCE_ORDER_DOWNLOAD_FILE'), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo $attachmentIcon($itemType); ?><?php echo $value; ?>
                        </a>
                    <?php elseif ($isFileUpload && $value !== ''): ?>
                        <div class="item-option item-option-value uk-text-bold uk-margin-small-left">
                            <?php echo $attachmentIcon($itemType); ?><?php echo $value; ?>
                        </div>
                    <?php elseif ($value !== ''): ?>
                        <div class="item-option item-option-value uk-text-bold uk-margin-small-left"><?php echo $value; ?></div>
                    <?php endif; ?>
                </div>
            <?php endif;
        endforeach;
    endforeach; ?>
</div>
