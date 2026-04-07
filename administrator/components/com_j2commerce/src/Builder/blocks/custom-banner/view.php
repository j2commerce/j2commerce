<?php
\defined('_JEXEC') or die;

extract($displayData);

$settings   = $settings ?? [];
$bgColor    = htmlspecialchars($settings['bg_color'] ?? '#f8f9fa', ENT_QUOTES, 'UTF-8');
$textColor  = htmlspecialchars($settings['text_color'] ?? '#212529', ENT_QUOTES, 'UTF-8');
$padding    = htmlspecialchars($settings['padding'] ?? '1rem', ENT_QUOTES, 'UTF-8');
$cssClass   = $settings['css_class'] ?? '';
$content    = $settings['content'] ?? '';
$classAttr  = 'j2c-banner' . ($cssClass ? ' ' . htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8') : '');
?>
<div class="<?php echo $classAttr; ?>" style="background:<?php echo $bgColor; ?>; color:<?php echo $textColor; ?>; padding:<?php echo $padding; ?>;"><?php echo $content; ?></div>
