<?php
\defined('_JEXEC') or die;

extract($displayData);

$settings  = $settings ?? [];
$cssClass  = $settings['css_class'] ?? '';
$textAlign = $settings['text_align'] ?? 'left';
$styleAttr = ' style="text-align:' . htmlspecialchars($textAlign, ENT_QUOTES, 'UTF-8') . ';"';
?>
<p data-j2c-block="custom-text"<?php if ($cssClass): ?> class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?><?php echo $styleAttr; ?> contenteditable="true">
    Your text here — double-click to edit.
</p>
