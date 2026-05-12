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
use Joomla\Database\DatabaseInterface;

/** @var \J2Commerce\Component\J2commerce\Site\View\Myprofile\HtmlView $this */

$order = $this->order;

if (!$order || empty($order->order_id)) {
    echo '<div class="alert alert-danger">' . Text::_('COM_J2COMMERCE_ORDER_MISMATCH') . '</div>';
    return;
}

$helper          = PackingSlipHelper::getInstance();
$packingSlipHtml = $helper->getFormattedPackingSlip($order);

// Extract <style> blocks from the template body and move them to <head>
$extractedStyles = '';
$bodyHtml        = preg_replace_callback(
    '/<style\b[^>]*>(.*?)<\/style>/si',
    function (array $m) use (&$extractedStyles): string {
        $extractedStyles .= $m[1] . "\n";
        return '';
    },
    $packingSlipHtml
);

// Load custom CSS from the matched packing slip template record
$customCss = '';
$db = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);
$query = $db->getQuery(true)
    ->select($db->quoteName('custom_css'))
    ->from($db->quoteName('#__j2commerce_invoicetemplates'))
    ->where($db->quoteName('invoice_type') . ' = ' . $db->quote('packingslip'))
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
        <?php echo $extractedStyles; ?>
        <?php if ($customCss !== '') echo $customCss; ?>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; background: #fff; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()"><?php echo Text::_('COM_J2COMMERCE_PRINT'); ?></button>
        <button onclick="window.close()" style="margin-left: 8px;"><?php echo Text::_('JCLOSE'); ?></button>
    </div>
    <?php echo $bodyHtml; ?>
    <script>window.onload = function() { window.print(); };</script>
</body>
</html>
