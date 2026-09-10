<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Renders the operator-authored tracking snippet held in a Checkout or Confirmation
 * menu item, with the order/cart values substituted for their tokens.
 */
final class TrackingHelper
{
    private const MONEY_TOKENS = [
        'ORDER_TOTAL'           => 'order_total',
        'ORDER_SUBTOTAL'        => 'order_subtotal',
        'ORDER_SUBTOTAL_EX_TAX' => 'order_subtotal_ex_tax',
        'ORDER_TAX'             => 'order_tax',
        'ORDER_SHIPPING'        => 'order_shipping',
        'ORDER_SHIPPING_TAX'    => 'order_shipping_tax',
        'ORDER_DISCOUNT'        => 'order_discount',
        'ORDER_FEES'            => 'order_fees',
        'ORDER_SURCHARGE'       => 'order_surcharge',
        'ORDER_CREDIT'          => 'order_credit',
    ];

    private const TEXT_TOKENS = ['ORDER_ID', 'ORDER_NUMBER', 'CURRENCY_CODE', 'PAYMENT_METHOD', 'TRANSACTION_ID'];

    private const DATE_TOKENS = ['ORDER_DATE', 'ORDER_DATE:Y-m-d H:i:s', 'ORDER_TIMESTAMP'];

    private const CUSTOMER_TOKENS = ['CUSTOMER_EMAIL', 'USER_ID', 'IS_NEW_CUSTOMER'];

    private const ITEM_TOKENS = ['ITEM_COUNT', 'ITEM_QUANTITY_TOTAL', 'ITEM_SKUS', 'ITEM_IDS', 'ITEMS_JSON'];

    /** Token groups for the admin reference list, keyed by their heading language key. */
    public static function getTokenGroups(): array
    {
        return [
            'COM_J2COMMERCE_TRACKING_TOKENS_ORDER'    => array_merge(array_keys(self::MONEY_TOKENS), self::TEXT_TOKENS, self::DATE_TOKENS),
            'COM_J2COMMERCE_TRACKING_TOKENS_CUSTOMER' => self::CUSTOMER_TOKENS,
            'COM_J2COMMERCE_TRACKING_TOKENS_ITEMS'    => self::ITEM_TOKENS,
        ];
    }

    /**
     * @param  object|null  $order    CartOrder on checkout, order row on confirmation
     * @param  array        $items    Cart items or order items
     * @param  string       $context  'checkout' or 'confirmation'
     *
     * @return string  Markup to echo inline, or an empty string when nothing is emitted
     */
    public static function render(?object $order, array $items, Registry $params, string $context): string
    {
        // trim() keeps a leading no-break space or BOM, which pasted snippets often carry and
        // which would send stored markup down the wrap branch below.
        $raw    = (string) $params->get('tracking_script', '');
        $script = trim(preg_replace('/^[\s\x{00A0}\x{FEFF}]+/u', '', $raw) ?? $raw);

        if ($script === '' || $order === null) {
            return '';
        }

        $app     = Factory::getApplication();
        $preview = $context === 'confirmation' && $app->getInput()->getInt('tracking_preview', 0) === 1;

        if (!$preview && $context === 'confirmation' && (int) $params->get('tracking_script_once', 1) === 1 && !self::claimOrder($order)) {
            return '';
        }

        $html = self::substitute($script, self::buildTokens($order, $items, $context));

        // Bare JavaScript is wrapped here; a snippet that opens with markup is emitted as entered.
        if (!str_starts_with($script, '<')) {
            $html = "<script>\n" . $html . "\n</script>";
        }

        // Preview: show the substituted snippet without running it, and without claiming the
        // order, so the merchant can read the resolved values and the real visit still fires.
        if ($preview) {
            return self::previewBlock($html);
        }

        if ((string) $params->get('tracking_script_position', 'inline') === 'head') {
            $document = $app->getDocument();

            if ($document instanceof HtmlDocument) {
                $document->addCustomTag($html);
            }

            return '';
        }

        return $html;
    }

    /**
     * The snippet is emitted twice, both inert: once as an HTML comment for anyone reading the
     * page source, once on screen so the resolved values can be read without it. Both `-->` and
     * `--!>` end a comment, so either inside the merchant's own snippet is broken up first.
     */
    private static function previewBlock(string $html): string
    {
        $commented = preg_replace('/--!?>/', '--&gt;', $html) ?? '';

        return "\n<!-- " . Text::_('COM_J2COMMERCE_TRACKING_PREVIEW_HEADING') . "\n" . $commented . "\n-->\n"
            . '<div class="card my-3"><div class="card-body">'
            . '<p class="fw-bold mb-2">' . Text::_('COM_J2COMMERCE_TRACKING_PREVIEW_HEADING') . '</p>'
            . '<p class="mb-2">' . Text::_('COM_J2COMMERCE_TRACKING_PREVIEW_NOTICE') . '</p>'
            . '<pre class="mb-0" style="white-space:pre-wrap;overflow-x:auto"><code>'
            . htmlspecialchars($html, ENT_QUOTES, 'UTF-8')
            . '</code></pre></div></div>' . "\n";
    }

    /**
     * The confirmation page re-renders on reload and falls back to the most recent order,
     * so the snippet is emitted once per order per session unless the merchant opts out.
     */
    private static function claimOrder(object $order): bool
    {
        $orderId = (string) ($order->order_id ?? '');

        if ($orderId === '') {
            return true;
        }

        $session = Factory::getApplication()->getSession();
        $fired   = (array) $session->get('tracking_fired', [], 'j2commerce');

        if (\in_array($orderId, $fired, true)) {
            return false;
        }

        $fired[] = $orderId;
        $session->set('tracking_fired', \array_slice($fired, -20), 'j2commerce');

        return true;
    }

    private static function buildTokens(object $order, array $items, string $context): array
    {
        $tokens = [];

        foreach (self::MONEY_TOKENS as $token => $property) {
            $value = (float) ($order->{$property} ?? 0);

            $tokens[$token]                = CurrencyHelper::format($value, '', 0.0, false);
            $tokens[$token . '_FORMATTED'] = self::jsString(
                html_entity_decode(CurrencyHelper::format($value), ENT_QUOTES, 'UTF-8')
            );
        }

        $invoiceNumber = (int) ($order->invoice_number ?? 0);

        $tokens['ORDER_ID']        = self::jsString((string) ($order->order_id ?? ''));
        $tokens['ORDER_NUMBER']    = self::jsString($invoiceNumber > 0 ? ((string) ($order->invoice_prefix ?? '') . $invoiceNumber) : '');
        $tokens['CURRENCY_CODE']   = self::jsString((string) ($order->currency_code ?? '') ?: CurrencyHelper::getCode());
        $tokens['PAYMENT_METHOD']  = self::jsString((string) ($order->orderpayment_type ?? ''));
        $tokens['TRANSACTION_ID']  = self::jsString((string) ($order->transaction_id ?? ''));
        $tokens['CUSTOMER_EMAIL']  = self::jsString((string) ($order->user_email ?? ''));
        $tokens['USER_ID']         = (string) (int) ($order->user_id ?? 0);
        $tokens['IS_NEW_CUSTOMER'] = $context === 'confirmation' ? self::isFirstOrder($order) : '';

        $created = (string) ($order->created_on ?? '');

        $tokens['__CREATED_ON']    = $created;
        $tokens['ORDER_DATE']      = self::formatDate($created, 'Y-m-d');
        $tokens['ORDER_TIMESTAMP'] = self::formatDate($created, 'U');

        return $tokens + self::buildItemTokens($items);
    }

    private static function buildItemTokens(array $items): array
    {
        $quantity = 0;
        $skus     = [];
        $ids      = [];
        $rows     = [];

        foreach ($items as $item) {
            $itemQuantity = (int) ($item->orderitem_quantity ?? 0);
            $quantity += $itemQuantity;
            $sku          = (string) ($item->orderitem_sku ?? '');
            $productId    = (int) ($item->product_id ?? 0);

            if ($sku !== '') {
                $skus[] = $sku;
            }

            $ids[]  = $productId;
            $rows[] = [
                'item_id'   => $productId,
                'item_name' => (string) ($item->orderitem_name ?? ''),
                'sku'       => $sku,
                'price'     => CurrencyHelper::format((float) ($item->orderitem_finalprice ?? 0), '', 0.0, false),
                'quantity'  => $itemQuantity,
            ];
        }

        $itemsJson = json_encode($rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        return [
            'ITEM_COUNT'          => (string) \count($items),
            'ITEM_QUANTITY_TOTAL' => (string) $quantity,
            'ITEM_SKUS'           => self::jsString(implode(',', $skus)),
            'ITEM_IDS'            => implode(',', $ids),
            'ITEMS_JSON'          => $itemsJson === false ? '[]' : $itemsJson,
        ];
    }

    private static function isFirstOrder(object $order): string
    {
        $userId = (int) ($order->user_id ?? 0);

        if ($userId <= 0) {
            return '1';
        }

        $orderKey = (int) ($order->j2commerce_order_id ?? 0);
        $db       = Factory::getContainer()->get(DatabaseInterface::class);
        $query    = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('user_id') . ' = :user_id')
            ->where($db->quoteName('j2commerce_order_id') . ' < :order_key')
            ->bind(':user_id', $userId, ParameterType::INTEGER)
            ->bind(':order_key', $orderKey, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult() > 0 ? '0' : '1';
    }

    /**
     * Escaped for a JavaScript string literal, without the wrapping quotes. The backtick and
     * dollar are escaped as well so a token also holds inside a template literal; both are
     * identity escapes in a quoted string, so nothing else is affected.
     */
    private static function jsString(string $value): string
    {
        $encoded = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '' : str_replace(['`', '$'], ['\\`', '\\$'], substr($encoded, 1, -1));
    }

    /** Zero date, empty string and unparseable input all resolve to an empty token. */
    private static function formatDate(string $created, string $format): string
    {
        if ($created === '' || str_starts_with($created, '0000-00-00')) {
            return '';
        }

        try {
            return (string) HTMLHelper::_('date', $created, $format);
        } catch (\Throwable) {
            return '';
        }
    }

    private static function substitute(string $script, array $tokens): string
    {
        $map = [];

        foreach ($tokens as $token => $value) {
            $map['[' . $token . ']'] = $value;
        }

        $created = $tokens['__CREATED_ON'] ?? '';
        unset($map['[__CREATED_ON]']);

        // [ORDER_DATE:<php date format>] — whatever shape the vendor's script asks for.
        $script = preg_replace_callback(
            '/\[ORDER_DATE:([A-Za-z0-9 :\/\-\.,]{1,32})\]/',
            static fn (array $m): string => self::jsString(self::formatDate((string) $created, $m[1])),
            $script
        ) ?? $script;

        // A token this build does not know would otherwise survive into a numeric
        // position and break every other script on the page, so drop the leftovers.
        return preg_replace(
            '/\[(?:ORDER|ITEM|ITEMS|CART|CUSTOMER|CURRENCY|PAYMENT|TRANSACTION|USER|IS)_[A-Z0-9_]*\]/',
            '',
            strtr($script, $map)
        ) ?? '';
    }
}
