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

use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
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
 * @var string      $iconSet          'fontawesome' | 'uikit' | 'icomoon' | 'custom'
 * @var string      $iconMinus        resolved class string (empty when $iconSet === 'uikit')
 * @var string      $iconPlus         resolved class string (empty when $iconSet === 'uikit')
 * @var string      $decrementDisabled ' disabled' or ''
 * @var string      $incrementDisabled ' disabled' or ''
 */

extract($displayData, EXTR_SKIP);

// Plain input (cart context or buttons suppressed) — framework-agnostic
if ($isCart || !$showButtons) {
    $html  = '<input type="' . htmlspecialchars($inputType, ENT_QUOTES, 'UTF-8') . '"';
    $html .= ' name="' . htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') . '"';
    $html .= ' value="' . (int) $defaultQty . '"';
    $html .= ' min="' . (int) $minQty . '"';
    if ($maxQty > 0) {
        $html .= ' max="' . (int) $maxQty . '"';
    }
    $html .= ' step="1"';
    $html .= ' class="' . htmlspecialchars($inputClass, ENT_QUOTES, 'UTF-8') . '"';
    $html .= ' aria-label="' . htmlspecialchars(Text::_('COM_J2COMMERCE_QUANTITY'), ENT_QUOTES, 'UTF-8') . '"';
    $html .= ' />';
    echo $html;
    return;
}

// Full quantity control with +/- buttons — delegate to framework file
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

$displayData['inputHtml'] = $inputHtml;

$framework = ($iconSet === 'uikit') ? 'uikit' : 'bootstrap5';
ProductLayoutService::setSubtemplateOverride($framework);
try {
    echo ProductLayoutService::renderLayout('product.quantity', $displayData);
} finally {
    ProductLayoutService::clearSubtemplateOverride();
}
