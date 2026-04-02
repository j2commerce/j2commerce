<?php
defined('_JEXEC') or die;

extract($displayData);

$settings = $settings ?? [];
$cssClass = $settings['css_class'] ?? '';
?>
<div data-j2c-block="custom-html"<?php if ($cssClass): ?> class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?> contenteditable="true">
    <p class="text-muted small">Custom HTML content — double-click to edit.</p>
</div>
