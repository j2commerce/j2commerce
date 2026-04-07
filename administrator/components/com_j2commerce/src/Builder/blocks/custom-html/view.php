<?php
\defined('_JEXEC') or die;

extract($displayData);

$settings = $settings ?? [];
$cssClass = $settings['css_class'] ?? '';
$content  = $settings['content'] ?? '';
?>
<div<?php if ($cssClass): ?> class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>><?php echo $content; ?></div>
