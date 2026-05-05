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

/**
 * Layout variables supplied via $displayData:
 *
 * @var string      $context
 * @var object      $product
 * @var string      $inputName
 * @var string      $inputClass
 * @var string      $inputType        'text' | 'number'
 * @var int         $defaultQty
 * @var int         $minQty
 * @var int         $maxQty           0 = unlimited
 * @var bool        $isCart
 * @var bool        $showButtons
 * @var string      $iconSet          'fontawesome' | 'uikit' | 'icomoon' | 'none'
 * @var string      $iconMinus        resolved class string (empty for uikit/none)
 * @var string      $iconPlus         resolved class string (empty for uikit/none)
 * @var string      $decrementDisabled ' disabled' or ''
 * @var string      $incrementDisabled ' disabled' or ''
 */

extract($displayData, EXTR_SKIP);

// Plain input (cart context or buttons suppressed)
if ($isCart || !$showButtons) {
    $html  = '<input type="' . htmlspecialchars($inputType, ENT_QUOTES, 'UTF-8') . '"';
    $html .= ' name="' . htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') . '"';
    $html .= ' value="' . (int) $defaultQty . '"';
    $html .= ' min="' . (int) $minQty . '"';
    if ($maxQty > 0) {
        $html .= ' max="' . (int) $maxQty . '"';
    }
    $html .= ' step="1"';
    if ($inputType === 'number') {
        // nothing extra
    }
    $html .= ' class="' . htmlspecialchars($inputClass, ENT_QUOTES, 'UTF-8') . '"';
    $html .= ' aria-label="' . htmlspecialchars(Text::_('COM_J2COMMERCE_QUANTITY'), ENT_QUOTES, 'UTF-8') . '"';
    $html .= ' />';
    echo $html;
    return;
}

// Full quantity control with +/- buttons
$inputHtml  = '<input type="' . htmlspecialchars($inputType, ENT_QUOTES, 'UTF-8') . '"';
$inputHtml .= ' name="' . htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') . '"';
$inputHtml .= ' value="' . (int) $defaultQty . '"';
$inputHtml .= ' min="' . (int) $minQty . '"';
if ($inputType === 'text') {
    $inputHtml .= ' pattern="[0-9]*"';
    $inputHtml .= ' inputmode="numeric"';
}
if ($maxQty > 0) {
    $inputHtml .= ' max="' . (int) $maxQty . '"';
}
$inputHtml .= ' step="1"';
$inputHtml .= ' readonly';
$inputHtml .= ' class="' . htmlspecialchars($inputClass, ENT_QUOTES, 'UTF-8') . '"';
$inputHtml .= ' aria-label="' . htmlspecialchars(Text::_('COM_J2COMMERCE_QUANTITY'), ENT_QUOTES, 'UTF-8') . '"';
$inputHtml .= ' />';
?>
<div class="count-input flex-shrink-0">
    <button type="button" class="btn btn-icon btn-lg" data-decrement aria-label="<?php echo htmlspecialchars(Text::_('COM_J2COMMERCE_DECREASE_QUANTITY'), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $decrementDisabled; ?>>
        <?php if ($iconSet === 'uikit') : ?>
            <span uk-icon="icon: minus" aria-hidden="true"></span>
        <?php elseif ($iconSet === 'none') : ?>
            <span aria-hidden="true"></span>
        <?php else : ?>
            <span class="<?php echo htmlspecialchars($iconMinus, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
        <?php endif; ?>
    </button>
    <?php echo $inputHtml; ?>
    <button type="button" class="btn btn-icon btn-lg" data-increment aria-label="<?php echo htmlspecialchars(Text::_('COM_J2COMMERCE_INCREASE_QUANTITY'), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $incrementDisabled; ?>>
        <?php if ($iconSet === 'uikit') : ?>
            <span uk-icon="icon: plus" aria-hidden="true"></span>
        <?php elseif ($iconSet === 'none') : ?>
            <span aria-hidden="true"></span>
        <?php else : ?>
            <span class="<?php echo htmlspecialchars($iconPlus, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
        <?php endif; ?>
    </button>
</div>
