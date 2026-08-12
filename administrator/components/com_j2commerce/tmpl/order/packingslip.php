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
use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Order\HtmlView $this */

$order = $this->item;

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
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Text::sprintf('COM_J2COMMERCE_PACKING_SLIP_TITLE', $this->escape($order->order_id)); ?></title>
    <style>
        /* Base document styles */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            background-color: #f8fafc;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        /* Ensure tables render correctly */
        table { border-collapse: collapse; border-spacing: 0; }
        img { max-width: 100%; height: auto; border: 0; }

        /* Print button bar */
        .no-print { margin-bottom: 20px; display: flex; gap: 8px; justify-content: center; }
        .no-print button {
            padding: 10px 24px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            color: #333;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            line-height: 1.5;
            -webkit-appearance: none;
            appearance: none;
        }
        .no-print button:hover { background: #f3f4f6; }

        /* Template-extracted styles and the record's custom CSS */
        <?php echo $safeCss; ?>

        /* Print-specific styles */
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; background: #fff; }
            /* Force background colors/images in print */
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" data-j2c-print><?php echo Text::_('COM_J2COMMERCE_PRINT'); ?></button>
        <button type="button" data-j2c-close><?php echo Text::_('JCLOSE'); ?></button>
    </div>
    <?php echo $bodyHtml; ?>
    <script>
        window.addEventListener('load', function () { window.print(); });
        document.querySelector('[data-j2c-print]').addEventListener('click', function () { window.print(); });
        document.querySelector('[data-j2c-close]').addEventListener('click', function () { window.close(); });
    </script>
</body>
</html>
