<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use Joomla\CMS\Language\Text;

/**
 * Checkout custom fields captured on an order, read from the order's own snapshot so the
 * values stay put even after the field definition is renamed or retired.
 *
 * Shared by the confirmation page, My Profile and the admin order view across both the
 * bootstrap5 and uikit families; the framework classes are all caller-supplied so one
 * copy serves every one of them. Renders nothing when the order carries no custom values.
 *
 * @var  array  $displayData
 *       info           object|null  Row from #__j2commerce_orderinfos.
 *       card_class     string       Classes for the outer wrapper.
 *       body_class     string       Classes for the inner wrapper.
 *       heading_class  string       Classes for the heading.
 *       heading_tag    string       Heading element, so the block sits at the same level as
 *                                  the cards around it (h2..h6, default h3).
 */
$info = $displayData['info'] ?? null;

if (!\is_object($info)) {
    return;
}

$rows = [];

foreach (CustomFieldHelper::ORDER_AREAS as $snapshot => $areas) {
    foreach (CustomFieldHelper::describeOrderFields($info->{'all_' . $snapshot} ?? null, ...$areas) as $row) {
        // "Same as billing" writes one answer into both snapshots, so an identical pair is
        // one answer shown once; two different values under one label are two answers and
        // both stay.
        $rows[$row['label'] . "\0" . $row['value']] = $row;
    }
}

if ($rows === []) {
    return;
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

// Heading level is the host page's to decide — the same block sits under an h1 on the
// confirmation page and beside h2 cards in the admin sidebar.
$headingTag = (string) ($displayData['heading_tag'] ?? 'h3');

if (!\in_array($headingTag, ['h2', 'h3', 'h4', 'h5', 'h6'], true)) {
    $headingTag = 'h3';
}

?>
<div class="j2c-order-customfields <?php echo $escape((string) ($displayData['card_class'] ?? 'card mb-4')); ?>">
    <div class="<?php echo $escape((string) ($displayData['body_class'] ?? 'card-body')); ?>">
        <<?php echo $headingTag; ?> class="<?php echo $escape((string) ($displayData['heading_class'] ?? 'h6 mb-3')); ?>">
            <?php echo Text::_('COM_J2COMMERCE_FIELDSET_ADDITIONAL_INFORMATION'); ?>
        </<?php echo $headingTag; ?>>
        <dl class="j2c-order-customfields-list mb-0">
            <?php foreach ($rows as $row) : ?>
                <dt><?php echo $escape($row['label']); ?></dt>
                <dd><?php echo nl2br($escape($row['value'])); ?></dd>
            <?php endforeach; ?>
        </dl>
    </div>
</div>
