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

use J2Commerce\Component\J2commerce\Administrator\Helper\InvoiceHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Order\HtmlView $this */

$order = $this->item;

if (!$order || empty($order->order_id)) {
    echo '<div class="alert alert-danger">' . Text::_('COM_J2COMMERCE_ORDER_MISMATCH') . '</div>';
    return;
}

$helper     = InvoiceHelper::getInstance();
$invoiceHtml = $helper->getFormattedInvoice($order);

// Extract <style> blocks from the template body and move them to <head>
$extractedStyles = '';
$bodyHtml        = preg_replace_callback(
    '/<style\b[^>]*>(.*?)<\/style>/si',
    function (array $m) use (&$extractedStyles): string {
        $extractedStyles .= $m[1] . "\n";
        return '';
    },
    $invoiceHtml
);

// Load custom CSS from the matched invoice template record
$customCss = '';
$db = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);
$query = $db->getQuery(true)
    ->select($db->quoteName('custom_css'))
    ->from($db->quoteName('#__j2commerce_invoicetemplates'))
    ->where($db->quoteName('invoice_type') . ' = ' . $db->quote('invoice'))
    ->where($db->quoteName('enabled') . ' = 1')
    ->order($db->quoteName('ordering') . ' ASC');
$db->setQuery($query, 0, 1);
$customCss = trim((string) $db->loadResult());
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Text::sprintf('COM_J2COMMERCE_PRINT_INVOICE'); ?> - <?php echo $this->escape($order->order_id); ?></title>
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

        /* Template-extracted styles */
        <?php echo $extractedStyles; ?>

        /* Custom CSS from template record */
        <?php if ($customCss !== '') echo $customCss; ?>

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
        <button onclick="window.print()"><?php echo Text::_('COM_J2COMMERCE_PRINT'); ?></button>
        <button onclick="window.close()"><?php echo Text::_('JCLOSE'); ?></button>
    </div>
    <?php echo $bodyHtml; ?>
    <script>window.onload = function() { window.print(); };</script>
</body>
</html>
