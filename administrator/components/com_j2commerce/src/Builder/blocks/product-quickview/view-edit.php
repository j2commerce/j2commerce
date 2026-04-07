<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

extract($displayData);

$settings = $settings ?? [];
$cssClass = $settings['css_class'] ?? 'j2commerce-quickview';
?>
<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>" data-j2c-block="product-quickview">
    <button type="button" class="btn btn-sm btn-light" disabled>
        <span class="fa-solid fa-eye me-1" aria-hidden="true"></span>
        <j2c-token data-j2c-token="QUICK_VIEW"><?php echo Text::_('COM_J2COMMERCE_QUICK_VIEW'); ?></j2c-token>
    </button>
</div>
