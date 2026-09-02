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

use Joomla\CMS\Language\Text;

/**
 * One label/value block for an order's custom-field rows, shared by the admin order
 * sidebar and every site template (bootstrap5 + uikit) so billing/shipping/payment
 * custom fields render identically everywhere. Renders nothing when $rows is empty.
 *
 * @var  array  $displayData
 *       rows            array   ['label' => ..., 'value' => ...][], from
 *                                CustomFieldHelper::describeOrderFields().
 *       title           string  Language key for the heading. Default COM_J2COMMERCE_ORDER_CUSTOM_FIELDS;
 *                                pass '' where the host card header already names the block.
 *       title_tag       string  Heading element (h2..h6). Default h3.
 *       title_class     string  Classes for the heading. Default 'h6 mb-2'.
 *       wrapper_class   string  Classes for the outer wrapper; 'j2c-address-customfield'
 *                                is always appended. Default the address-detail classes.
 *       list_class      string  Classes for the <dl>. Default 'row mb-0 small'.
 *       dt_class        string  Classes for each <dt>. Default 'col-sm-5 fw-semibold'.
 *       dd_class        string  Classes for each <dd>. Default 'col-sm-7 mb-1'.
 */
$rows = $displayData['rows'] ?? [];

if (!\is_array($rows) || $rows === []) {
    return;
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$titleTag = (string) ($displayData['title_tag'] ?? 'h3');

if (!\in_array($titleTag, ['h2', 'h3', 'h4', 'h5', 'h6'], true)) {
    $titleTag = 'h3';
}

$title       = (string) ($displayData['title'] ?? 'COM_J2COMMERCE_ORDER_CUSTOM_FIELDS');
$titleClass  = (string) ($displayData['title_class'] ?? 'h6 mb-2');
$wrapperClass = trim((string) ($displayData['wrapper_class'] ?? 'j2c-address-detail mb-0 ps-4 ms-2 border-start border-primary'))
    . ' j2c-address-customfield';
$listClass   = (string) ($displayData['list_class'] ?? 'row mb-0 small');
$dtClass     = (string) ($displayData['dt_class'] ?? 'col-sm-5 fw-semibold');
$ddClass     = (string) ($displayData['dd_class'] ?? 'col-sm-7 mb-1');

?>
<div class="<?php echo $escape($wrapperClass); ?>">
    <?php if ($title !== '') : ?>
        <<?php echo $titleTag; ?> class="<?php echo $escape($titleClass); ?>"><?php echo Text::_($title); ?></<?php echo $titleTag; ?>>
    <?php endif; ?>
    <dl class="<?php echo $escape($listClass); ?>">
        <?php foreach ($rows as $row) : ?>
            <dt class="<?php echo $escape($dtClass); ?>"><?php echo $escape((string) ($row['label'] ?? '')); ?></dt>
            <dd class="<?php echo $escape($ddClass); ?>"><?php echo nl2br($escape((string) ($row['value'] ?? ''))); ?></dd>
        <?php endforeach; ?>
    </dl>
</div>
