<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Service\EmailTypeRegistry;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

$messageTags = J2CommerceHelper::message()->getMessageTags();

// $emailType may be set by the including context (controller getShortcodes or view)
if (!isset($emailType)) {
    $emailType = (isset($this) && isset($this->item->email_type)) ? $this->item->email_type : 'transactional';
}
$typeTags  = [];

if ($emailType !== 'transactional') {
    try {
        $db       = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);
        $registry = new EmailTypeRegistry($db);
        $rawGroups = $registry->getGroupedTagsForType($emailType);

        foreach ($rawGroups as $group => $tags) {
            foreach ($tags as $tagName => $tagConfig) {
                $typeTags[$group][$tagName] = Text::_($tagConfig['label'] ?? $tagName);
            }
        }
    } catch (\Throwable $e) {
        // Registry unavailable — skip type-specific tags
    }
}
?>

<?php if (!empty($typeTags)): ?>
    <?php foreach ($typeTags as $group => $tags): ?>
        <div class="shortcode-group mb-2">
            <div class="shortcode-category"><?php echo htmlspecialchars(ucfirst($group), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php foreach ($tags as $tagKey => $text): ?>
                <a href="#" class="shortcode-btn" data-shortcode="[<?php echo htmlspecialchars($tagKey, ENT_QUOTES, 'UTF-8'); ?>]" title="[<?php echo htmlspecialchars($tagKey, ENT_QUOTES, 'UTF-8'); ?>]"><?php echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); ?></a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($messageTags)): ?>
    <?php foreach ($messageTags as $key => $optionGroup): ?>
        <div class="shortcode-group mb-2">
            <div class="shortcode-category"><?php echo Text::_('COM_J2COMMERCE_' . strtoupper($key)); ?></div>
            <?php if (!empty($optionGroup)): ?>
                <?php foreach ($optionGroup as $tagKey => $text): ?>
                    <a href="#" class="shortcode-btn" data-shortcode="<?php echo htmlspecialchars($tagKey, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($tagKey, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Conditional Blocks -->
<div class="shortcode-group mb-2">
    <div class="shortcode-category"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_CONDITIONAL_BLOCKS'); ?></div>
    <a href="#" class="shortcode-btn" data-shortcode="[IF:TAG]...[/IF:TAG]" title="<?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_IF_DESC'); ?>">IF / ENDIF</a>
    <a href="#" class="shortcode-btn" data-shortcode="[IFNOT:TAG]...[/IFNOT:TAG]" title="<?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_IFNOT_DESC'); ?>">IFNOT / ENDIF</a>
</div>

<!-- Item Loop -->
<div class="shortcode-group mb-2">
    <div class="shortcode-category"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_ITEM_LOOP'); ?></div>
    <a href="#" class="shortcode-btn" data-shortcode="[ITEMS_LOOP]&#10;[/ITEMS_LOOP]" title="<?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_ITEMS_LOOP_DESC'); ?>">ITEMS_LOOP</a>
    <a href="#" class="shortcode-btn" data-shortcode="[ITEM_NAME]">ITEM_NAME</a>
    <a href="#" class="shortcode-btn" data-shortcode="[ITEM_SKU]">ITEM_SKU</a>
    <a href="#" class="shortcode-btn" data-shortcode="[ITEM_QTY]">ITEM_QTY</a>
    <a href="#" class="shortcode-btn" data-shortcode="[ITEM_PRICE]">ITEM_PRICE</a>
    <a href="#" class="shortcode-btn" data-shortcode="[ITEM_TOTAL]">ITEM_TOTAL</a>
    <a href="#" class="shortcode-btn" data-shortcode="[ITEM_IMAGE]">ITEM_IMAGE</a>
    <a href="#" class="shortcode-btn" data-shortcode="[ITEM_OPTIONS]">ITEM_OPTIONS</a>
    <a href="#" class="shortcode-btn" data-shortcode="[ITEM_WEIGHT]">ITEM_WEIGHT</a>
</div>

<!-- Plugin Hooks -->
<div class="shortcode-group mb-2">
    <div class="shortcode-category"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_HOOKS'); ?></div>
    <a href="#" class="shortcode-btn" data-shortcode="[HOOK:AFTER_HEADER]" title="onJ2CommerceEmailAfterHeader">AFTER_HEADER</a>
    <a href="#" class="shortcode-btn" data-shortcode="[HOOK:BEFORE_ITEMS]" title="onJ2CommerceEmailBeforeItems">BEFORE_ITEMS</a>
    <a href="#" class="shortcode-btn" data-shortcode="[HOOK:AFTER_ITEMS]" title="onJ2CommerceEmailAfterItems">AFTER_ITEMS</a>
    <a href="#" class="shortcode-btn" data-shortcode="[HOOK:BEFORE_SHIPPING]" title="onJ2CommerceEmailBeforeShipping">BEFORE_SHIPPING</a>
    <a href="#" class="shortcode-btn" data-shortcode="[HOOK:AFTER_PAYMENT]" title="onJ2CommerceEmailAfterPayment">AFTER_PAYMENT</a>
    <a href="#" class="shortcode-btn" data-shortcode="[HOOK:BEFORE_FOOTER]" title="onJ2CommerceEmailBeforeFooter">BEFORE_FOOTER</a>
</div>

<!-- Brand & Colors -->
<div class="shortcode-group mb-2">
    <div class="shortcode-category"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_BRAND_TAGS'); ?></div>
    <a href="#" class="shortcode-btn" data-shortcode="[STORE_LOGO_URL]">STORE_LOGO_URL</a>
    <a href="#" class="shortcode-btn" data-shortcode="[LOGO_MAX_HEIGHT]">LOGO_MAX_HEIGHT</a>
    <a href="#" class="shortcode-btn" data-shortcode="[ACCENT_COLOR]">ACCENT_COLOR</a>
    <a href="#" class="shortcode-btn" data-shortcode="[HEADER_BG_COLOR]">HEADER_BG</a>
    <a href="#" class="shortcode-btn" data-shortcode="[EMAIL_BG_COLOR]">EMAIL_BG</a>
    <a href="#" class="shortcode-btn" data-shortcode="[TEXT_COLOR]">TEXT_COLOR</a>
    <a href="#" class="shortcode-btn" data-shortcode="[FOOTER_TEXT]">FOOTER_TEXT</a>
    <a href="#" class="shortcode-btn" data-shortcode="[SOCIAL_FACEBOOK]">FACEBOOK</a>
    <a href="#" class="shortcode-btn" data-shortcode="[SOCIAL_INSTAGRAM]">INSTAGRAM</a>
    <a href="#" class="shortcode-btn" data-shortcode="[SOCIAL_TWITTER]">TWITTER</a>
</div>
