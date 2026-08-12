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

use J2Commerce\Component\J2commerce\Administrator\Helper\PackingSlipHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Site\View\Myprofile\HtmlView $this */

$order = $this->order;

if (!$order || empty($order->order_id)) {
    echo '<div class="alert alert-danger">' . Text::_('COM_J2COMMERCE_ORDER_MISMATCH') . '</div>';
    return;
}

$helper          = PackingSlipHelper::getInstance();
$packingSlipHtml = $helper->getFormattedPackingSlip($order);

// Extract <style> blocks from the template body and move them to <head>. The closing pattern
// is a best effort at the element boundary, not the tokenizer's exact rule; whatever it leaves
// behind is neutralised with the CSS below.
$extractedStyles = '';
$bodyHtml        = preg_replace_callback(
    '#<style\b[^>]*>(.*?)</\s*style\b[^>]*>#si',
    function (array $m) use (&$extractedStyles): string {
        $extractedStyles .= $m[1] . "\n";
        return '';
    },
    $packingSlipHtml
);

// Custom CSS from the record the body came from
$customCss = trim((string) ($helper->getSelectedTemplate($order)?->custom_css ?? ''));

// custom_css is stored with filter="raw" and the extracted blocks come from the template body,
// so both reach this element unfiltered. CSS has no syntax that needs "<", and dropping a
// character cannot spell it again, so the combined text cannot end the element or open a tag.
// A pattern that removes only "</style" is single-pass and can be reassembled around itself.
$safeCss = str_replace('<', '', $extractedStyles . "\n" . $customCss);

// Fetched into the order-view modal and reprinted from there: emit only the slip, in the
// wrapper the print handler looks for. The CSS rides along in an inert <template> so it
// cannot restyle the storefront behind the modal; the print handler revives it into the
// print document's <head>. The standalone document below serves a direct hit.
if (Factory::getApplication()->getInput()->getCmd('tmpl') === 'component') {
    ?>
<div class="j2commerce j2commerce-packingslip-detail">
    <template class="j2commerce-packingslip-css"><style><?php echo $safeCss; ?></style></template>
    <?php echo $bodyHtml; ?>
</div>
    <?php
    return;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Text::sprintf('COM_J2COMMERCE_PACKING_SLIP_TITLE', $this->escape($order->order_id)); ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            background-color: #f8fafc;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        table { border-collapse: collapse; border-spacing: 0; }
        img { max-width: 100%; height: auto; border: 0; }
        .no-print { margin-bottom: 20px; text-align: center; }
        .no-print button {
            padding: 10px 24px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            cursor: pointer;
            font-size: 14px;
        }
        .no-print button:hover { background: #f3f4f6; }
        <?php echo $safeCss; ?>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; background: #fff; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" data-j2c-print><?php echo Text::_('COM_J2COMMERCE_PRINT'); ?></button>
        <button type="button" data-j2c-close style="margin-left: 8px;"><?php echo Text::_('JCLOSE'); ?></button>
    </div>
    <?php echo $bodyHtml; ?>
    <script>
        window.addEventListener('load', function () { window.print(); });
        document.querySelector('[data-j2c-print]').addEventListener('click', function () { window.print(); });
        document.querySelector('[data-j2c-close]').addEventListener('click', function () { window.close(); });
    </script>
</body>
</html>
