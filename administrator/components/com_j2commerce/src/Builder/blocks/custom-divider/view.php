<?php
\defined('_JEXEC') or die;

extract($displayData);

$settings  = $settings ?? [];
$style     = htmlspecialchars($settings['style'] ?? 'solid', ENT_QUOTES, 'UTF-8');
$thickness = htmlspecialchars($settings['thickness'] ?? '1px', ENT_QUOTES, 'UTF-8');
$color     = htmlspecialchars($settings['color'] ?? '#dee2e6', ENT_QUOTES, 'UTF-8');
?>
<hr style="border-style:<?php echo $style; ?>; border-width:<?php echo $thickness; ?>; border-color:<?php echo $color; ?>; border-bottom:none;" />
