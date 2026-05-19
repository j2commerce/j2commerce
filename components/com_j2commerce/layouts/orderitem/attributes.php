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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderItemAttributeHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * @var array  $displayData
 * @var array  $displayData['attributes']  Array of attribute objects
 * @var object $displayData['item']        Order/cart item for context
 * @var string $displayData['context']     admin_order|admin_edit|cart|checkout|confirmation|myprofile|drawer|email|cart_module
 * @var string $displayData['variant']     full|compact|inline
 * @var string $displayData['framework']   bootstrap5|uikit3 (default bootstrap5)
 */

$attributes = $displayData['attributes'] ?? [];
$item       = $displayData['item'] ?? null;
$context    = $displayData['context'] ?? 'cart';
$variant    = $displayData['variant'] ?? 'full';
$framework  = ($displayData['framework'] ?? 'bootstrap5') === 'uikit3' ? 'uikit3' : 'bootstrap5';

if (empty($attributes)) {
    return;
}

$grouped = OrderItemAttributeHelper::groupAndDeduplicate($attributes);
if (empty($grouped)) {
    return;
}

$event = J2CommerceHelper::plugin()->event('RenderOrderItemAttributes', [
    $item,
    $grouped,
    $context,
    $variant,
]);
$typeRenderers = $event->getArgument('typeRenderers', []);

$isAdminContext = ($context === 'admin_order' || $context === 'admin_edit');
$isEmailVariant = ($variant === 'inline');

$attachmentIcon = static function (string $type) use ($framework, $isEmailVariant): string {
    if ($isEmailVariant) {
        return '';
    }
    if ($framework === 'uikit3') {
        $ukIcon = $type === 'image' ? 'image' : 'file-text';
        return '<span uk-icon="icon: ' . $ukIcon . '" class="uk-margin-small-right" aria-hidden="true"></span>';
    }
    $faIcon = $type === 'image' ? 'fa-image' : 'fa-paperclip';
    return '<span class="fa-solid ' . $faIcon . ' me-1" aria-hidden="true"></span>';
};

$buildDownloadUrl = static function (string $mangled): string {
    return Route::_('index.php?option=com_j2commerce&task=orderfile.download'
        . '&file=' . urlencode($mangled)
        . '&' . Session::getFormToken() . '=1');
};

$mutedClass = $framework === 'uikit3' ? 'uk-text-muted uk-display-block uk-text-truncate' : 'text-body-secondary d-block text-truncate';

// --- Inline variant (emails) — no paperclip icon, plain filename text ---
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
                <div class="small d-flex align-items-center">
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
                <div class="small d-flex align-items-center">
                    <div class="item-option item-option-name"><?php echo $name; ?><?php if ($value !== ''): ?>:<?php endif; ?></div>
                    <?php if ($isAdminLink):
                        $href = $buildDownloadUrl($mangled);
                        ?>
                        <a href="<?php echo $href; ?>" class="item-option item-option-value fw-bold ms-1 text-decoration-none"
                           title="<?php echo htmlspecialchars(Text::_('COM_J2COMMERCE_ORDER_DOWNLOAD_FILE'), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo $attachmentIcon($itemType); ?><?php echo $value; ?>
                        </a>
                    <?php elseif ($isFileUpload && $value !== ''): ?>
                        <div class="item-option item-option-value fw-bold ms-1">
                            <?php echo $attachmentIcon($itemType); ?><?php echo $value; ?>
                        </div>
                    <?php elseif ($value !== ''): ?>
                        <div class="item-option item-option-value fw-bold ms-1"><?php echo $value; ?></div>
                    <?php endif; ?>
                </div>
            <?php endif;
        endforeach;
    endforeach; ?>
</div>
