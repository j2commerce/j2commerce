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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

// No direct access
\defined('_JEXEC') or die;

/**
 * Email Helper class for J2Commerce
 *
 * Provides email template loading, processing, and sending functionality
 * for order-related emails. This class handles:
 * - Loading email templates from database
 * - Processing template tags with order data
 * - Handling inline images in emails
 * - Sending emails to customers and administrators
 *
 * @since  6.0.0
 */
class EmailHelper
{
    /**
     * Database instance
     *
     * @var   DatabaseInterface|null
     * @since 6.0.0
     */
    private static ?DatabaseInterface $db = null;

    /**
     * Singleton instance
     *
     * @var   EmailHelper|null
     * @since 6.0.0
     */
    private static ?EmailHelper $instance = null;

    /**
     * Flag indicating if template is from file
     *
     * @var   bool
     * @since 6.0.0
     */
    protected bool $isTemplateFile = false;

    /**
     * Constructor
     *
     * @param   array<string, mixed>  $config  Optional configuration array
     *
     * @since   6.0.0
     */
    public function __construct(array $config = [])
    {
        // Initialize any configuration if needed
    }

    /**
     * Get the database instance
     *
     * @return  DatabaseInterface
     *
     * @since   6.0.0
     */
    private static function getDatabase(): DatabaseInterface
    {
        if (self::$db === null) {
            self::$db = Factory::getContainer()->get(DatabaseInterface::class);
        }

        return self::$db;
    }

    /**
     * Get singleton instance
     *
     * @param   array<string, mixed>  $config  Optional configuration array
     *
     * @return  EmailHelper
     *
     * @since   6.0.0
     */
    public static function getInstance(array $config = []): EmailHelper
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /** Get the most recent order or sample data for email preview/test. */
    public function getSampleOrderData(): object
    {
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        // Try to load the most recent order
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_orders'))
            ->order($db->quoteName('j2commerce_order_id') . ' DESC');
        $db->setQuery($query, 0, 1);
        $order = $db->loadObject();

        if ($order) {
            return $order;
        }

        // Fallback sample data when no orders exist
        return (object) [
            'j2commerce_order_id' => 0,
            'order_id'            => 'J2C-1001',
            'user_id'             => 0,
            'user_email'          => 'customer@example.com',
            'order_state_id'      => 1,
            'order_total'         => 149.99,
            'order_subtotal'      => 129.99,
            'order_tax'           => 10.00,
            'order_shipping'      => 10.00,
            'order_discount'      => 0.00,
            'currency_code'       => 'USD',
            'currency_value'      => 1.00,
            'customer_note'       => 'Please gift wrap this order.',
            'token'               => 'sample-token-12345',
            'created_on'          => date('Y-m-d H:i:s'),
            'orderpayment_type'   => 'Credit Card',
            'order_params'        => '{}',
        ];
    }

    /**
     * Get all order emails for sending
     *
     * 1. Get order emails by type
     * 2. Filter by language and process each mail template
     * 3. Prepare the mailer for each template
     * 4. Set the receivers (customer emails / admins)
     * 5. Return the array
     *
     * @param   object  $order         The order object
     * @param   string  $receiverType  The receiver type ('customer', 'admin', or '*')
     *
     * @return  array<int, object>  Array of email templates with mailer instances
     *
     * @since   6.0.0
     */
    public function getOrderEmails(object $order, string $receiverType = '*'): array
    {
        $params = ComponentHelper::getParams('com_j2commerce');

        // 1. Get all mail templates related to this order
        $mailTemplates = $this->getEmailTemplates($order, $receiverType);

        // Load language overrides
        $this->loadLanguageOverrides($order);

        // Filter by language
        $mailTemplates = $this->filterByLanguage($order, $mailTemplates);

        foreach ($mailTemplates as &$template) {
            // Process each mail template
            $template->mailer = $this->processTemplate($order, $template, $receiverType);

            // Set a default in case none is set
            if (!isset($template->receiver_type) || empty($template->receiver_type)) {
                $template->receiver_type = '*';
            }

            if (\in_array($template->receiver_type, ['customer', '*']) && $receiverType === 'customer') {
                if (isset($order->user_email) && !empty($order->user_email) && $template->mailer !== false) {
                    $template->mailer->addRecipient($order->user_email);
                }
            } elseif (\in_array($template->receiver_type, ['admin', '*']) && $receiverType === 'admin') {
                $adminEmails = $params->get('admin_email', '');
                $adminEmails = explode(',', $adminEmails);
                $template->mailer->addRecipient($adminEmails);

                if (isset($order->user_email) && !empty($order->user_email)) {
                    $template->mailer->addReplyTo($order->user_email);
                }
            }
        }

        return $mailTemplates;
    }

    /**
     * Filter email templates by language preference
     *
     * @param   object              $order          The order object
     * @param   array<int, object>  $mailTemplates  Array of mail templates
     *
     * @return  array<int, object>  Filtered mail templates
     *
     * @since   6.0.0
     */
    protected function filterByLanguage(object $order, array $mailTemplates): array
    {
        $filteredTemplates    = [];
        $defaultTemplateGroup = [];
        $allLangTemplates     = [];
        $params               = ComponentHelper::getParams('com_j2commerce');

        // Look for desired languages
        $jLang     = Factory::getLanguage();
        $userLang  = $order->customer_language ?? '*';
        $languages = [
            $userLang,
            $jLang->getTag(),
            $jLang->getDefault(),
            'en-GB',
        ];

        if (\count($mailTemplates)) {
            // Pass 1 - Give match scores to each template
            foreach ($mailTemplates as $idx => $template) {
                $myLang = $template->language ?? '*';

                // All language templates need not be filtered
                if ($myLang === '*') {
                    $allLangTemplates[] = $template;
                }

                // Make sure the language matches one of our desired languages
                $langPos = array_search($myLang, $languages, true);

                if ($langPos === false) {
                    continue;
                }

                $langScore                       = (5 - $langPos);
                $template->lang_score            = $langScore;
                $filteredTemplates[$langScore][] = $template;
            }
        } else {
            // No templates found, use standard template
            $standardTemplate = (object) [
                'j2commerce_emailtemplate_id' => 0,
                'email_type'                  => '',
                'receiver_type'               => '*',
                'orderstatus_id'              => '*',
                'group_id'                    => '',
                'paymentmethod'               => '*',
                'subject'                     => Text::_('COM_J2COMMERCE_ORDER_EMAIL_TEMPLATE_STANDARD_SUBJECT'),
                'body'                        => Text::_('COM_J2COMMERCE_ORDER_EMAIL_TEMPLATE_STANDARD_BODY'),
                'body_source'                 => 'html',
                'body_source_file'            => '',
                'language'                    => '*',
                'enabled'                     => 1,
                'ordering'                    => 1,
                'lang_score'                  => 1,
            ];

            if ($params->get('send_default_email_template', 1) == 1) {
                $defaultTemplateGroup[] = $standardTemplate;
            }
        }

        // Sort by language preference
        krsort($filteredTemplates);

        $result = $defaultTemplateGroup;

        if (\count($filteredTemplates) > 0) {
            foreach ($filteredTemplates as $templateGroup) {
                if (\count($templateGroup) === 0) {
                    continue;
                }

                $result = $templateGroup;
                break;
            }
        }

        $result = array_merge($result, $allLangTemplates);

        return $result;
    }

    /**
     * Process an email template with order data
     *
     * @param   object  $order         The order object
     * @param   object  $template      The email template
     * @param   string  $receiverType  The receiver type
     *
     * @return  Mail|false  The configured mailer or false on failure
     *
     * @since   6.0.0
     */
    protected function processTemplate(object $order, object $template, string $receiverType = '*'): Mail|false
    {
        if (!isset($order->order_id) || empty($order->order_id)) {
            return false;
        }

        if (\is_array($template)) {
            $template = (object) $template;
        }

        $config = Factory::getApplication()->getConfig();
        $extras = [];

        if (isset($template->body_source) && $template->body_source === 'file') {
            $templateText         = $this->getTemplateFromFile($template, $order);
            $this->isTemplateFile = true;
        } else {
            $templateText = $template->body ?? '';
        }

        $templateText = $this->processTags($templateText, $order, $extras, $receiverType);
        $subject      = $this->processTags($template->subject ?? '', $order, $extras, $receiverType);

        $baseURL  = str_replace('/administrator', '', Uri::base());
        $baseURL  = ltrim($baseURL, '/');
        $imageUrl = str_replace(Uri::base(true), '', Uri::base());

        $isHTML = true;

        // Get the mailer
        $mailer = $this->getMailer($isHTML);

        $mailfrom = $config->get('mailfrom');
        $fromname = $config->get('fromname');

        // Set the sender information
        $mailer->setSender([$mailfrom, $fromname]);

        // Set encoding information
        $mailer->CharSet  = 'utf-8';
        $mailer->Encoding = 'base64';

        $mailer->setSubject($subject);

        // Process inline images
        $templateText = $this->processInlineImagesInternal($templateText, $mailer, $imageUrl);

        $htmlExtra = '';
        $lang      = Factory::getLanguage();

        if ($lang->isRTL()) {
            $htmlExtra = ' dir="rtl"';
        }

        // Inject custom CSS into <head>
        $headStyles = '';
        $customCss  = trim($template->custom_css ?? '');
        if ($customCss !== '') {
            $headStyles = '<style type="text/css">' . $customCss . '</style>';
        }

        $body = '<html' . $htmlExtra . '><head>'
            . '<meta http-equiv="Content-Type" content="text/html; charset=' . $mailer->CharSet . '">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . $headStyles
            . '</head><body>' . $templateText . '</body></html>';

        $mailer->setBody($body);
        $mailer->AltBody = $this->textVersion($body);

        return $mailer;
    }

    /**
     * Process template tags and replace with order data
     *
     * @param   string               $text          The template text
     * @param   object               $order         The order object
     * @param   array<string, mixed> $extras        Additional tag replacements
     * @param   string               $receiverType  The receiver type
     *
     * @return  string  The processed text
     *
     * @since   6.0.0
     */
    public function processTags(string $text, object $order, array $extras = [], string $receiverType = '*'): string
    {
        $params   = ComponentHelper::getParams('com_j2commerce');
        $config   = Factory::getApplication()->getConfig();
        $sitename = $config->get('sitename');

        // Site URL
        $baseURL    = Uri::base();
        $subpathURL = Uri::base(true);
        $baseURL    = str_replace('/administrator', '', $baseURL);
        $subpathURL = str_replace('/administrator', '', $subpathURL);

        // Invoice URL — links to myprofile order view (no token; unauthenticated users get login redirect)
        $orderId           = $order->order_id ?? '';
        $defaultInvoiceUrl = Route::_('index.php?option=com_j2commerce&view=myprofile&layout=order&order_id=' . urlencode((string) $orderId), false);
        $url               = str_replace('&amp;', '&', $defaultInvoiceUrl);
        $url               = str_replace('/administrator', '', $url);
        $url               = ltrim($url, '/');
        $subpathURL        = ltrim($subpathURL, '/');

        if (substr($url, 0, \strlen($subpathURL) + 1) === "$subpathURL/") {
            $url = substr($url, \strlen($subpathURL) + 1);
        }

        $invoiceURL = rtrim($baseURL, '/') . '/' . ltrim($url, '/');

        // Order date
        $tz   = $config->get('offset');
        $date = Factory::getDate($order->created_on ?? 'now');
        $date->setTimezone(new \DateTimeZone($tz));
        $dateFormat = $params->get('date_format', Text::_('DATE_FORMAT_LC1'));
        $orderDate  = $date->format($dateFormat, true);

        // Get order info
        $orderInfo   = $this->getOrderInfo($order);
        $shipping    = $this->getOrderShipping($order);
        $orderCoupon = $this->getOrderCoupons($order);
        $status      = $this->getOrderStatus((int) ($order->order_state_id ?? 0));

        $couponCode = '';
        if (!empty($orderCoupon)) {
            $couponCode = $orderCoupon[0]->discount_code ?? '';
        }

        // Get invoice number
        $invoiceNumber = $this->getInvoiceNumber($order);

        // Get order items as HTML
        $items = $this->loadItemsTemplate($order, $receiverType);

        // Get country/zone names
        $billingCountryName  = $this->getCountryName((int) ($orderInfo->billing_country_id ?? 0));
        $shippingCountryName = $this->getCountryName((int) ($orderInfo->shipping_country_id ?? 0));
        $billingZoneName     = $this->getZoneName((int) ($orderInfo->billing_zone_id ?? 0));
        $shippingZoneName    = $this->getZoneName((int) ($orderInfo->shipping_zone_id ?? 0));

        // Get bank transfer info if present
        $bankTransferInfo = '';
        if (isset($order->order_params) && !empty($order->order_params)) {
            $orderParams = json_decode($order->order_params);
            if (isset($orderParams->payment_banktransfer)) {
                $bankTransferInfo = $orderParams->payment_banktransfer;
            }
        }

        // Get language for translations
        $language = $this->getLanguageForOrder($order);

        // Format currency
        $formattedTotal = CurrencyHelper::format(
            (float) ($order->order_total ?? 0),
            $order->currency_code ?? '',
            (float) ($order->currency_value ?? 1)
        );

        $tags = [
            "\\n"                         => "\n",
            '[SITENAME]'                  => $sitename,
            '[SITEURL]'                   => $baseURL,
            '[INVOICE_URL]'               => $invoiceURL,
            '[ORDERID]'                   => $order->order_id ?? '',
            '[INVOICENO]'                 => $invoiceNumber,
            '[ORDERDATE]'                 => $orderDate,
            '[ORDERSTATUS]'               => $language->_($status->orderstatus_name ?? ''),
            '[ORDERAMOUNT]'               => $formattedTotal,
            '[CUSTOMER_NAME]'             => ($orderInfo->billing_first_name ?? '') . ' ' . ($orderInfo->billing_last_name ?? ''),
            '[BILLING_FIRSTNAME]'         => $orderInfo->billing_first_name ?? '',
            '[BILLING_LASTNAME]'          => $orderInfo->billing_last_name ?? '',
            '[BILLING_EMAIL]'             => $order->user_email ?? '',
            '[BILLING_ADDRESS_1]'         => $orderInfo->billing_address_1 ?? '',
            '[BILLING_ADDRESS_2]'         => $orderInfo->billing_address_2 ?? '',
            '[BILLING_CITY]'              => $orderInfo->billing_city ?? '',
            '[BILLING_ZIP]'               => $orderInfo->billing_zip ?? '',
            '[BILLING_COUNTRY]'           => $language->_($billingCountryName),
            '[BILLING_STATE]'             => $language->_($billingZoneName),
            '[BILLING_COMPANY]'           => $orderInfo->billing_company ?? '',
            '[BILLING_VATID]'             => $orderInfo->billing_tax_number ?? '',
            '[BILLING_PHONE]'             => $orderInfo->billing_phone_1 ?? '',
            '[BILLING_MOBILE]'            => $orderInfo->billing_phone_2 ?? '',
            '[SHIPPING_FIRSTNAME]'        => $orderInfo->shipping_first_name ?? '',
            '[SHIPPING_LASTNAME]'         => $orderInfo->shipping_last_name ?? '',
            '[SHIPPING_ADDRESS_1]'        => $orderInfo->shipping_address_1 ?? '',
            '[SHIPPING_ADDRESS_2]'        => $orderInfo->shipping_address_2 ?? '',
            '[SHIPPING_CITY]'             => $orderInfo->shipping_city ?? '',
            '[SHIPPING_ZIP]'              => $orderInfo->shipping_zip ?? '',
            '[SHIPPING_COUNTRY]'          => $language->_($shippingCountryName),
            '[SHIPPING_STATE]'            => $language->_($shippingZoneName),
            '[SHIPPING_COMPANY]'          => $orderInfo->shipping_company ?? '',
            '[SHIPPING_VATID]'            => $orderInfo->shipping_tax_number ?? '',
            '[SHIPPING_PHONE]'            => $orderInfo->shipping_phone_1 ?? '',
            '[SHIPPING_MOBILE]'           => $orderInfo->shipping_phone_2 ?? '',
            '[SHIPPING_METHOD]'           => $language->_($shipping->ordershipping_name ?? ''),
            '[SHIPPING_TYPE]'             => $language->_($shipping->ordershipping_name ?? ''),
            '[SHIPPING_TRACKING_ID]'      => $shipping->ordershipping_tracking_id ?? '',
            '[CUSTOMER_NOTE]'             => nl2br($order->customer_note ?? ''),
            '[PAYMENT_TYPE]'              => $this->getPaymentMethodTitle($order->orderpayment_type ?? '', $language),
            '[ORDER_TOKEN]'               => $order->token ?? '',
            '[TOKEN]'                     => $order->token ?? '',
            '[COUPON_CODE]'               => $couponCode,
            '[BANK_TRANSFER_INFORMATION]' => $bankTransferInfo,
            '[SHIPPING_TOTAL_WEIGHT]'     => $this->getTotalShippingWeight($order),
            '[SHIPPING_AMOUNT]'           => ((float) ($order->order_shipping ?? 0)) > 0 ? CurrencyHelper::format((float) $order->order_shipping, $order->currency_code ?? '', (float) ($order->currency_value ?? 1)) : '',
            '[DISCOUNT_AMOUNT]'           => ((float) ($order->order_discount ?? 0)) > 0 ? CurrencyHelper::format((float) $order->order_discount, $order->currency_code ?? '', (float) ($order->currency_value ?? 1)) : '',
            '[TAX_AMOUNT]'                => ((float) ($order->order_tax ?? 0)) > 0 ? CurrencyHelper::format((float) $order->order_tax, $order->currency_code ?? '', (float) ($order->currency_value ?? 1)) : '',
            '[SUBTOTAL]'                  => CurrencyHelper::format((float) ($order->order_subtotal ?? 0), $order->currency_code ?? '', (float) ($order->currency_value ?? 1)),
            '[CURRENT_YEAR]'              => date('Y'),
            '[ITEMS]'                     => $items,
            '[PACKING_ITEMS]'             => $this->loadPackingItemsTemplate($order),
        ];

        // Get customer user groups
        if (isset($order->user_id) && $order->user_id > 0) {
            $groupNames                = $this->getUserGroupNames((int) $order->user_id);
            $tags['[CUSTOMER_GROUPS]'] = trim(implode(',', $groupNames), ',');
        }

        // Brand configuration shortcodes
        $logoRaw = $params->get('email_logo_url', '');
        $logoUrl = '';
        if (!empty($logoRaw)) {
            $logoUrl = HTMLHelper::cleanImageURL($logoRaw)->url;
            if (!str_starts_with($logoUrl, 'http')) {
                $logoUrl = rtrim($baseURL, '/') . '/' . ltrim($logoUrl, '/');
            }
        }
        $tags['[STORE_LOGO_URL]']   = $logoUrl;
        $tags['[LOGO_MAX_HEIGHT]']  = (string) (int) $params->get('email_logo_max_height', 60);
        $tags['[ACCENT_COLOR]']     = $params->get('email_accent_color', '#2563EB');
        $tags['[HEADER_BG_COLOR]']  = $params->get('email_header_bg', '#FFFFFF');
        $tags['[EMAIL_BG_COLOR]']   = $params->get('email_bg_color', '#F8FAFC');
        $tags['[TEXT_COLOR]']       = $params->get('email_text_color', '#334155');
        $tags['[FOOTER_TEXT]']      = $params->get('email_footer_text', '');
        $tags['[SOCIAL_FACEBOOK]']  = $params->get('email_social_facebook', '');
        $tags['[SOCIAL_INSTAGRAM]'] = $params->get('email_social_instagram', '');
        $tags['[SOCIAL_TWITTER]']   = $params->get('email_social_twitter', '');

        // Store info shortcodes
        $tags['[STORE_NAME]']      = $params->get('store_name', '');
        $tags['[STORE_ADDRESS_1]'] = $params->get('store_address_1', '');
        $tags['[STORE_ADDRESS_2]'] = $params->get('store_address_2', '');
        $tags['[STORE_CITY]']      = $params->get('store_city', '');
        $tags['[STORE_ZIP]']       = $params->get('store_zip', '');
        $tags['[STORE_PHONE]']     = $params->get('store_phone', '');
        $tags['[STORE_EMAIL]']     = $params->get('admin_email', '');
        $tags['[STORE_COUNTRY]']   = $this->getCountryName((int) $params->get('country_id', 0));
        $tags['[STORE_STATE]']     = $this->getZoneName((int) $params->get('zone_id', 0));

        // Lowercase aliases for brand shortcodes (TinyMCE/GrapesJS may lowercase them)
        $tags['[accent_color]']    = $tags['[ACCENT_COLOR]'];
        $tags['[header_bg_color]'] = $tags['[HEADER_BG_COLOR]'];
        $tags['[email_bg_color]']  = $tags['[EMAIL_BG_COLOR]'];
        $tags['[text_color]']      = $tags['[TEXT_COLOR]'];

        // Tax line items with profile names (from ordertaxes table)
        $tags['[TAX_LINES]'] = $this->buildTaxLines($order);

        $tags = array_merge($tags, $extras);

        // Clean up GrapesJS data-j2c-src placeholders (may be persisted in DB from earlier saves)
        if (str_contains($text, 'data-j2c-src')) {
            $text = preg_replace_callback(
                '/<img([^>]*?)data-j2c-src="(\[[A-Z_]+\])"([^>]*?)>/i',
                static function (array $m): string {
                    $attrs = preg_replace('/\ssrc="[^"]*"/i', '', $m[1] . $m[3]);
                    return '<img' . $attrs . ' src="' . $m[2] . '">';
                },
                $text
            );
        }

        // Normalize curly-brace shortcodes {TAG} → [TAG] (TinyMCE sometimes converts brackets)
        $text = preg_replace_callback('/\{([A-Z][A-Z0-9_]*)\}/', static function (array $m) use ($tags): string {
            $bracket = '[' . $m[1] . ']';
            return isset($tags[$bracket]) ? $bracket : $m[0];
        }, $text);

        // Normalize lowercase shortcodes → UPPERCASE (GrapesJS lowercases attribute-like text)
        // Matches any [tag], [/tag], [PREFIX:tag] pattern containing at least one lowercase letter
        $text = preg_replace_callback('/\[(\/?[a-zA-Z][a-zA-Z0-9_]*(?::[a-zA-Z][a-zA-Z0-9_]*)?)\]/', static function (array $m): string {
            return '[' . strtoupper($m[1]) . ']';
        }, $text);

        // Process conditional blocks BEFORE tag replacement
        $text = $this->processConditionalBlocks($text, $tags);

        // Replace tags
        foreach ($tags as $key => $value) {
            if (!empty($key) && $value !== null && !empty($text)) {
                $text = str_replace($key, (string) $value, $text);
            }
        }

        // Process [ITEMS_LOOP]...[/ITEMS_LOOP] custom item rendering
        $text = $this->processItemsLoop($text, $order);

        // Process custom fields
        $text = $this->processCustomFields($orderInfo, 'billing', $text, $language);
        $text = $this->processCustomFields($orderInfo, 'shipping', $text, $language);
        $text = $this->processCustomFields($orderInfo, 'payment', $text, $language);

        // Dispatch plugin event for custom tag processing
        Factory::getApplication()->getDispatcher()->dispatch(
            'onJ2CommerceAfterProcessTags',
            new \Joomla\CMS\Event\GenericEvent('onJ2CommerceAfterProcessTags', [
                'text'  => &$text,
                'order' => $order,
                'tags'  => $tags,
            ])
        );

        // Process positional hook shortcodes via plugin events
        $text = $this->processPositionalHooks($text, $order, $receiverType);

        // Remove any unprocessed tags (except known exceptions like [if mso])
        preg_match_all("^\[(.*?)\]^", $text, $removeFields, PREG_PATTERN_ORDER);

        if (\count($removeFields[1]) > 0) {
            foreach ($removeFields[1] as $fieldName) {
                if (!\in_array($fieldName, ['if mso', 'endif'])) {
                    $text = str_replace('[' . $fieldName . ']', '', $text);
                }
            }
        }

        // Collapse consecutive <br> tags separated only by whitespace (leftover from removed conditionals)
        $text = preg_replace('/(<br\s*\/?>)(\s*<br\s*\/?>)+/', '$1', $text);

        return $text;
    }

    /** Process [IF:TAG]...[/IF:TAG] and [IFNOT:TAG]...[/IFNOT:TAG] conditional blocks. */
    private function processConditionalBlocks(string $text, array $tags): string
    {
        // Process [IF:TAG] — keep content if tag value is non-empty, remove if empty
        // Skip ITEM_* tags — those are per-item tags processed inside processItemsLoop
        $text = preg_replace_callback(
            '/\[IF:([A-Z0-9_]+)\](.*?)\[\/IF:\1\]/s',
            function (array $m) use ($tags): string {
                if (str_starts_with($m[1], 'ITEM_')) {
                    return $m[0];
                }
                $tagKey = '[' . $m[1] . ']';
                return !empty($tags[$tagKey]) ? $m[2] : '';
            },
            $text
        );

        // Process [IFNOT:TAG] — keep content if tag value is empty, remove if non-empty
        $text = preg_replace_callback(
            '/\[IFNOT:([A-Z0-9_]+)\](.*?)\[\/IFNOT:\1\]/s',
            function (array $m) use ($tags): string {
                if (str_starts_with($m[1], 'ITEM_')) {
                    return $m[0];
                }
                $tagKey = '[' . $m[1] . ']';
                return empty($tags[$tagKey]) ? $m[2] : '';
            },
            $text
        );

        return $text;
    }

    /** Process [ITEMS_LOOP]...[/ITEMS_LOOP] with per-item shortcodes. */
    private function processItemsLoop(string $text, object $order): string
    {
        if (!str_contains($text, '[ITEMS_LOOP]')) {
            return $text;
        }

        // Fix editor-mangled templates: empty [ITEMS_LOOP][/ITEMS_LOOP] with item row elsewhere.
        // GrapesJS collapses the loop markers together and leaves the item <tr> outside.
        // The item row may contain nested tables (e.g. IFNOT:ITEM_IMAGE fallback) with inner
        // </tr> tags, so a simple regex can't find the correct outer <tr>. Use nesting-aware search.
        if (preg_match('/\[ITEMS_LOOP\]\s*\[\/ITEMS_LOOP\]/s', $text) && str_contains($text, '[ITEM_NAME]')) {
            $text = preg_replace('/\[ITEMS_LOOP\]\s*\[\/ITEMS_LOOP\]/', '', $text, 1);
            $text = $this->wrapItemRowInLoop($text);
        }

        $baseURL = str_replace('/administrator', '', Uri::base());

        return preg_replace_callback(
            '/\[ITEMS_LOOP\](.*?)\[\/ITEMS_LOOP\]/s',
            function (array $m) use ($order, $baseURL): string {
                $template = $m[1];
                $db       = self::getDatabase();
                $orderId  = $order->order_id ?? '';
                $query    = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__j2commerce_orderitems'))
                    ->where($db->quoteName('order_id') . ' = :order_id')
                    ->bind(':order_id', $orderId);
                $db->setQuery($query);
                $items = $db->loadObjectList() ?: [];

                if (empty($items)) {
                    return '';
                }

                $currencyCode  = $order->currency_code ?? '';
                $currencyValue = (float) ($order->currency_value ?? 1);
                $result        = '';

                foreach ($items as $item) {
                    $optionText = $this->decodeOrderItemAttributes($item->orderitem_attributes ?? '');

                    // Look up product image via ImageHelper for optimal size
                    $imageUrl = $this->getProductImageForEmail((int) ($item->product_id ?? 0), $baseURL);

                    $itemTags = [
                        '[ITEM_NAME]'    => htmlspecialchars($item->orderitem_name ?? ''),
                        '[ITEM_SKU]'     => htmlspecialchars($item->orderitem_sku ?? ''),
                        '[ITEM_QTY]'     => (string) (int) ($item->orderitem_quantity ?? 0),
                        '[ITEM_PRICE]'   => CurrencyHelper::format((float) ($item->orderitem_price ?? 0), $currencyCode, $currencyValue),
                        '[ITEM_TOTAL]'   => CurrencyHelper::format((float) ($item->orderitem_finalprice ?? 0), $currencyCode, $currencyValue),
                        '[ITEM_IMAGE]'   => htmlspecialchars($imageUrl),
                        '[ITEM_OPTIONS]' => $optionText,
                        '[ITEM_WEIGHT]'  => (string) (float) ($item->orderitem_weight ?? 0),
                    ];

                    // Process per-item [IF:ITEM_*] and [IFNOT:ITEM_*] conditionals
                    $row = $this->processItemConditionals($template, $itemTags);

                    // Replace per-item tags
                    foreach ($itemTags as $key => $value) {
                        $row = str_replace($key, $value, $row);
                    }
                    $result .= $row;
                }

                return $result;
            },
            $text
        );
    }

    /** Process [IF:ITEM_*] and [IFNOT:ITEM_*] conditional blocks within a single item row. */
    private function processItemConditionals(string $text, array $itemTags): string
    {
        $text = preg_replace_callback(
            '/\[IF:(ITEM_[A-Z_]+)\](.*?)\[\/IF:\1\]/s',
            function (array $m) use ($itemTags): string {
                $tagKey = '[' . $m[1] . ']';
                return !empty($itemTags[$tagKey]) ? $m[2] : '';
            },
            $text
        );

        $text = preg_replace_callback(
            '/\[IFNOT:(ITEM_[A-Z_]+)\](.*?)\[\/IFNOT:\1\]/s',
            function (array $m) use ($itemTags): string {
                $tagKey = '[' . $m[1] . ']';
                return empty($itemTags[$tagKey]) ? $m[2] : '';
            },
            $text
        );

        return $text;
    }

    /** Find the outer <tr> containing [ITEM_NAME] using nesting-aware search and wrap in ITEMS_LOOP. */
    private function wrapItemRowInLoop(string $text): string
    {
        $itemPos = strpos($text, '[ITEM_NAME]');
        if ($itemPos === false) {
            return $text;
        }

        // Collect all <tr> and </tr> positions before [ITEM_NAME]
        $before = substr($text, 0, $itemPos);
        preg_match_all('/<(\/?)tr\b[^>]*>/i', $before, $matches, PREG_OFFSET_CAPTURE);

        // Walk forward tracking nesting — unmatched <tr> tags remain on the stack
        $stack = [];
        foreach ($matches[0] as $i => $match) {
            if ($matches[1][$i][0] !== '/') {
                $stack[] = $match[1]; // push <tr> position
            } elseif (!empty($stack)) {
                array_pop($stack); // matched </tr> closes a <tr>
            }
        }

        // $stack holds positions of unmatched <tr> opens before [ITEM_NAME].
        // The item row is always the innermost (last) unmatched <tr> on the stack.
        if (empty($stack)) {
            return $text;
        }

        $idx          = \count($stack) - 1;
        $outerTrStart = $stack[$idx];
        $openCount    = \count($stack) - $idx; // nesting depth from chosen <tr> inward

        // Walk forward from [ITEM_NAME] to find the matching </tr> for the outermost <tr>
        $after = substr($text, $itemPos);
        preg_match_all('/<(\/?)tr\b[^>]*>/i', $after, $matches, PREG_OFFSET_CAPTURE);

        $depth      = $openCount;
        $outerTrEnd = null;
        foreach ($matches[0] as $i => $match) {
            if ($matches[1][$i][0] !== '/') {
                $depth++;
            } else {
                $depth--;
                if ($depth === 0) {
                    $outerTrEnd = $itemPos + $match[1] + \strlen($match[0]);
                    break;
                }
            }
        }

        if ($outerTrEnd === null) {
            return $text;
        }

        return substr($text, 0, $outerTrStart)
            . '[ITEMS_LOOP]'
            . substr($text, $outerTrStart, $outerTrEnd - $outerTrStart)
            . '[/ITEMS_LOOP]'
            . substr($text, $outerTrEnd);
    }

    /** Decode orderitem_attributes into "Option: Value" HTML text for emails. */
    private function decodeOrderItemAttributes(string $raw): string
    {
        $attributes = OrderItemAttributeHelper::parseRawAttributes($raw);

        return empty($attributes) ? '' : OrderItemAttributeHelper::formatForEmail($attributes);
    }

    /** Build nested table for tax line items with profile name and amount. */
    private function buildTaxLines(object $order): string
    {
        $orderId = $order->order_id ?? '';
        if ($orderId === '') {
            return '';
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('ordertax_title'), $db->quoteName('ordertax_percent'), $db->quoteName('ordertax_amount')])
            ->from($db->quoteName('#__j2commerce_ordertaxes'))
            ->where($db->quoteName('order_id') . ' = :order_id')
            ->bind(':order_id', $orderId);
        $taxes = $db->setQuery($query)->loadObjectList() ?: [];

        if (empty($taxes)) {
            return '';
        }

        $currencyCode  = $order->currency_code ?? '';
        $currencyValue = (float) ($order->currency_value ?? 1);
        $rows          = '';

        foreach ($taxes as $tax) {
            if ((float) $tax->ordertax_amount <= 0) {
                continue;
            }
            $title   = htmlspecialchars($tax->ordertax_title);
            $percent = (float) $tax->ordertax_percent;
            $amount  = CurrencyHelper::format((float) $tax->ordertax_amount, $currencyCode, $currencyValue);
            $label   = $title . ($percent > 0 ? ' (' . rtrim(rtrim(number_format($percent, 2), '0'), '.') . '%)' : '');
            $rows .= '<tr>'
                . '<td style="padding: 6px 20px; font-size: 13px; color: #6b7280;">' . $label . '</td>'
                . '<td style="padding: 6px 20px; font-size: 13px; color: #6b7280; text-align: right;">' . $amount . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            return '';
        }

        return '<table width="100%" cellpadding="0" cellspacing="0" border="0">' . $rows . '</table>';
    }

    /** Get product thumbnail image URL for email, using ImageHelper for optimal sizing. */
    private function getProductImageForEmail(int $productId, string $baseURL): string
    {
        if ($productId <= 0) {
            return '';
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('thumb_image'), $db->quoteName('main_image')])
            ->from($db->quoteName('#__j2commerce_productimages'))
            ->where($db->quoteName('product_id') . ' = :product_id')
            ->bind(':product_id', $productId, ParameterType::INTEGER);
        $db->setQuery($query, 0, 1);
        $row = $db->loadObject();

        if (!$row) {
            return '';
        }

        // Prefer thumb_image, fall back to main_image
        $imagePath = !empty($row->thumb_image) ? $row->thumb_image : ($row->main_image ?? '');
        if (empty($imagePath)) {
            return '';
        }

        // Use ImageHelper to resolve optimal size and strip #joomlaImage metadata
        $url = ImageHelper::getProductImage($imagePath, 80, 'raw');
        if (empty($url)) {
            return '';
        }

        // Make absolute URL for email
        if (!str_starts_with($url, 'http')) {
            $url = rtrim($baseURL, '/') . '/' . ltrim($url, '/');
        }

        return $url;
    }

    /** Process [HOOK:POSITION] shortcodes by dispatching positional plugin events. */
    private function processPositionalHooks(string $text, object $order, string $receiverType): string
    {
        $hookMap = [
            'AFTER_HEADER'    => 'onJ2CommerceEmailAfterHeader',
            'BEFORE_ITEMS'    => 'onJ2CommerceEmailBeforeItems',
            'AFTER_ITEMS'     => 'onJ2CommerceEmailAfterItems',
            'BEFORE_SHIPPING' => 'onJ2CommerceEmailBeforeShipping',
            'AFTER_PAYMENT'   => 'onJ2CommerceEmailAfterPayment',
            'BEFORE_FOOTER'   => 'onJ2CommerceEmailBeforeFooter',
        ];

        $dispatcher = Factory::getApplication()->getDispatcher();

        foreach ($hookMap as $position => $eventName) {
            $shortcode = '[HOOK:' . $position . ']';
            if (str_contains($text, $shortcode)) {
                $event = new \Joomla\CMS\Event\GenericEvent($eventName, [
                    'order'        => $order,
                    'receiverType' => $receiverType,
                    'result'       => '',
                ]);
                $dispatcher->dispatch($eventName, $event);
                $hookHtml = $event->getArgument('result') ?: '';
                $text     = str_replace($shortcode, $hookHtml, $text);
            }
        }

        return $text;
    }

    /**
     * Get email templates matching order criteria
     *
     * @param   object  $order         The order object
     * @param   string  $receiverType  The receiver type
     *
     * @return  array<int, object>  Array of email templates
     *
     * @since   6.0.0
     */
    public function getEmailTemplates(object $order, string $receiverType = '*'): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $orderStateId  = (int) ($order->order_state_id ?? 0);
        $paymentType   = $order->orderpayment_type ?? '';
        $customerGroup = $order->customer_group ?? '';

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_emailtemplates'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('email_type') . ' = ' . $db->quote('transactional'));

        // Order status filter with CASE statement
        $query->where(
            'CASE WHEN ' . $db->quoteName('orderstatus_id') . ' = :orderstatus_id'
            . ' THEN ' . $db->quoteName('orderstatus_id') . ' = :orderstatus_id2'
            . ' ELSE ' . $db->quoteName('orderstatus_id') . ' = ' . $db->quote('*')
            . ' OR ' . $db->quoteName('orderstatus_id') . ' = ' . $db->quote('')
            . ' END'
        )
            ->bind(':orderstatus_id', $orderStateId, ParameterType::INTEGER)
            ->bind(':orderstatus_id2', $orderStateId, ParameterType::INTEGER);

        // Customer group filter — parse to integers to prevent SQL injection
        if (!empty($customerGroup)) {
            $groupIds = array_values(array_filter(
                array_map('intval', explode(',', $customerGroup)),
                fn($id) => $id > 0
            ));

            if (!empty($groupIds)) {
                $inList = implode(',', $groupIds);
                $query->where(
                    'CASE WHEN ' . $db->quoteName('group_id') . ' IN (' . $inList . ')'
                    . ' THEN ' . $db->quoteName('group_id') . ' IN (' . $inList . ')'
                    . ' ELSE ' . $db->quoteName('group_id') . ' = ' . $db->quote('*')
                    . ' OR ' . $db->quoteName('group_id') . ' = ' . $db->quote('1')
                    . ' OR ' . $db->quoteName('group_id') . ' = ' . $db->quote('')
                    . ' OR ' . $db->quoteName('group_id') . ' = ' . $db->quote('0')
                    . ' END'
                );
            }
        }

        // Payment method filter
        $query->where(
            'CASE WHEN ' . $db->quoteName('paymentmethod') . ' = :paymentmethod'
            . ' THEN ' . $db->quoteName('paymentmethod') . ' = :paymentmethod2'
            . ' ELSE ' . $db->quoteName('paymentmethod') . ' = ' . $db->quote('*')
            . ' OR ' . $db->quoteName('paymentmethod') . ' = ' . $db->quote('')
            . ' END'
        )
            ->bind(':paymentmethod', $paymentType)
            ->bind(':paymentmethod2', $paymentType);

        // Receiver type filter
        $query->where(
            'CASE WHEN ' . $db->quoteName('receiver_type') . ' = :receiver_type'
            . ' THEN ' . $db->quoteName('receiver_type') . ' = :receiver_type2'
            . ' ELSE ' . $db->quoteName('receiver_type') . ' = ' . $db->quote('*')
            . ' OR ' . $db->quoteName('receiver_type') . ' = ' . $db->quote('')
            . ' END'
        )
            ->bind(':receiver_type', $receiverType)
            ->bind(':receiver_type2', $receiverType);

        $db->setQuery($query);

        try {
            $allTemplates = $db->loadObjectList() ?: [];
        } catch (\Exception $e) {
            $allTemplates = [];
        }

        return $allTemplates;
    }

    /**
     * Get configured mailer instance
     *
     * @param   bool  $isHTML  Whether to send HTML email
     *
     * @return  Mail  The mailer instance
     *
     * @since   6.0.0
     */
    private function getMailer(bool $isHTML = true): Mail
    {
        $mailer = clone Factory::getMailer();
        $mailer->IsHTML($isHTML);
        $mailer->CharSet = 'UTF-8';

        return $mailer;
    }

    /**
     * Initialize mailer with default settings
     *
     * @return  Mail  The configured mailer
     *
     * @since   6.0.0
     */
    private function initMailer(): Mail
    {
        $config   = Factory::getApplication()->getConfig();
        $mailer   = $this->getMailer();
        $mailfrom = $config->get('mailfrom');
        $fromname = $config->get('fromname');
        $mailer->setSender([$mailfrom, $fromname]);

        return $mailer;
    }

    /**
     * Get pre-loaded email for an order
     *
     * @param   object  $order  The order object
     *
     * @return  Mail|false  The configured mailer or false on failure
     *
     * @since   6.0.0
     */
    public function getEmail(object $order): Mail|false
    {
        if (!isset($order->order_id) || empty($order->order_id)) {
            return false;
        }

        $this->getOrderEmails($order);

        [$isHTML, $subject, $templateText, $loadLanguage] = $this->loadEmailTemplate($order);

        // Load language overrides
        $this->loadLanguageOverrides($order);

        $extras       = [];
        $templateText = $this->processTags($templateText, $order, $extras);
        $subject      = $this->processTags($subject, $order, $extras);

        $baseURL = str_replace('/administrator', '', Uri::base());
        $baseURL = ltrim($baseURL, '/');

        // Get the mailer
        $mailer = $this->getMailer($isHTML);
        $mailer->setSubject($subject);

        // Process inline images
        $templateText = $this->processInlineImagesInternal($templateText, $mailer, $baseURL);

        $htmlExtra = '';
        $lang      = Factory::getLanguage();

        if ($lang->isRTL()) {
            $htmlExtra = ' dir="rtl"';
        }

        $body = '<html' . $htmlExtra . '><head>'
            . '<meta http-equiv="Content-Type" content="text/html; charset=' . $mailer->CharSet . '">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '</head><body>' . $templateText . '</body></html>';

        $mailer->setBody($body);
        $mailer->AltBody = $this->textVersion($body);

        return $mailer;
    }

    /**
     * Load email template for an order
     *
     * @param   object  $order  The order object
     *
     * @return  array{0: bool, 1: string, 2: string, 3: string|null}  [isHTML, subject, templateText, loadLanguage]
     *
     * @since   6.0.0
     */
    protected function loadEmailTemplate(object $order): array
    {
        $templateText = '';
        $subject      = '';
        $loadLanguage = null;
        $isHTML       = false;

        // Look for desired languages
        $jLang     = Factory::getLanguage();
        $userLang  = $order->customer_language ?? '';
        $languages = [
            $userLang,
            $jLang->getTag(),
            $jLang->getDefault(),
            'en-GB',
            '*',
        ];

        $allTemplates = $this->getEmailTemplates($order);

        if (\count($allTemplates)) {
            $preferredScore = 0;

            foreach ($allTemplates as $template) {
                $myLang = $template->language ?? '*';

                $langPos = array_search($myLang, $languages, true);

                if ($langPos === false) {
                    continue;
                }

                $langScore = (5 - $langPos);
                $score     = $langScore;

                if ($score > $preferredScore) {
                    $loadLanguage = $myLang;
                    $subject      = $template->subject ?? '';

                    if (isset($template->body_source) && $template->body_source === 'file') {
                        $templateText         = $this->getTemplateFromFile($template, $order);
                        $this->isTemplateFile = true;
                    } else {
                        $templateText = $template->body ?? '';
                    }

                    $preferredScore = $score;
                    $isHTML         = true;
                }
            }
        } else {
            $isHTML       = true;
            $templateText = Text::_('COM_J2COMMERCE_ORDER_EMAIL_TEMPLATE_STANDARD_BODY');
            $subject      = Text::_('COM_J2COMMERCE_ORDER_EMAIL_TEMPLATE_STANDARD_SUBJECT');
        }

        return [$isHTML, $subject, $templateText, $loadLanguage];
    }

    /**
     * Get template content from file
     *
     * @param   object  $template  The template object
     * @param   object  $order     The order object
     *
     * @return  string  The template content
     *
     * @since   6.0.0
     */
    public function getTemplateFromFile(object $template, object $order): string
    {
        if (!isset($template->body_source) || $template->body_source !== 'file') {
            return $template->body ?? '';
        }

        if (empty($template->body_source_file)) {
            return $template->body ?? '';
        }

        $fileName = $template->body_source_file;

        // Plugin-prefixed path: "plg:<group>.<name>:<relative/path.html>"
        // Resolves to: JPATH_PLUGINS/<group>/<name>/tmpl/email/<relative/path.html>
        if (str_starts_with($fileName, 'plg:')) {
            $rest                  = substr($fileName, 4);
            [$pluginRef, $relPath] = array_pad(explode(':', $rest, 2), 2, '');
            [$group, $name]        = array_pad(explode('.', $pluginRef, 2), 2, '');

            if ($group === '' || $name === '' || $relPath === '') {
                return $template->body ?? '';
            }

            $filePath = Path::clean(
                JPATH_PLUGINS . '/' . $group . '/' . $name . '/tmpl/email/' . $relPath
            );
        } else {
            // Standard path: resolves under component layouts/templates/email/
            $filePath = Path::clean(
                JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts/templates/email/' . $fileName
            );
        }

        if (!file_exists($filePath)) {
            return $template->body ?? '';
        }

        // Try to make the template file readable
        Path::setPermissions($filePath, '0644');

        if (!is_readable($filePath)) {
            return $template->body ?? '';
        }

        return $this->getLayout($filePath, $order);
    }

    /**
     * Get parsed layout file content
     *
     * @param   string  $layout  The layout file path
     * @param   object  $order   The order object
     *
     * @return  string  The parsed layout content
     *
     * @since   6.0.0
     */
    protected function getLayout(string $layout, object $order): string
    {
        ob_start();
        $this->loadLanguageOverrides($order);
        include $layout;
        $html = ob_get_contents();
        ob_end_clean();

        return $html ?: '';
    }

    /**
     * Load language overrides for order language
     *
     * @param   object  $order  The order object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function loadLanguageOverrides(object $order): void
    {
        $extension = 'com_j2commerce';
        $jlang     = Factory::getLanguage();

        // English (default fallback)
        $jlang->load($extension, JPATH_ADMINISTRATOR, 'en-GB', true);
        $jlang->load($extension . '.override', JPATH_ADMINISTRATOR, 'en-GB', true);

        // Default site language
        $jlang->load($extension, JPATH_ADMINISTRATOR, $jlang->getDefault(), true);
        $jlang->load($extension . '.override', JPATH_ADMINISTRATOR, $jlang->getDefault(), true);

        // Current site language
        $jlang->load($extension, JPATH_ADMINISTRATOR, null, true);
        $jlang->load($extension . '.override', JPATH_ADMINISTRATOR, null, true);

        // Customer language
        if (isset($order->customer_language) && !empty($order->customer_language)) {
            $jlang->load($extension, JPATH_ADMINISTRATOR, $order->customer_language, true);
            $jlang->load($extension . '.override', JPATH_ADMINISTRATOR, $order->customer_language, true);
        }
    }

    /**
     * Send error notification emails
     *
     * @param   string       $receiver  The recipient email address
     * @param   string       $subject   The email subject
     * @param   string       $body      The email body
     * @param   string|null  $cc        CC recipient(s)
     * @param   string|null  $bcc       BCC recipient(s)
     *
     * @return  bool  True on success, false on failure
     *
     * @since   6.0.0
     */
    public function sendErrorEmails(
        string $receiver,
        string $subject,
        string $body,
        ?string $cc = null,
        ?string $bcc = null
    ): bool {
        if (empty($receiver)) {
            return false;
        }

        $mailer = $this->initMailer();
        $mailer->addRecipient($receiver);
        $mailer->setSubject($subject);
        $mailer->setBody($body);

        if (!empty($cc)) {
            $mailer->addCC($cc);
        }

        if (!empty($bcc)) {
            $mailer->addBCC($bcc);
        }

        return $mailer->Send();
    }

    /** Log an email send attempt to the j2commerce_email_log table. */
    public function logEmailSend(
        int|string $orderId,
        string $receiverType,
        string $subject,
        array $recipients,
        bool $success,
        string $errorMessage = ''
    ): void {
        try {
            $db                 = Factory::getContainer()->get(DatabaseInterface::class);
            $log                = new \stdClass();
            $log->order_id      = $orderId;
            $log->receiver_type = $receiverType;
            $log->subject       = mb_substr($subject, 0, 255);
            $log->recipients    = implode(', ', $recipients);
            $log->success       = $success ? 1 : 0;
            $log->error_message = $errorMessage;
            $log->sent_on       = Factory::getDate()->toSql();
            $log->sent_by       = Factory::getApplication()->getIdentity()?->id ?? 0;

            $db->insertObject('#__j2commerce_email_log', $log);
        } catch (\Throwable $e) {
            // Logging failure should never break the email flow
        }
    }

    /**
     * Process inline images in template text
     *
     * @param   string  $templateText  The template text
     * @param   Mail    $mailer        The mailer instance
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function processInlineImages(string $templateText, Mail &$mailer): void
    {
        $baseURL = str_replace('/administrator', '', Uri::base());
        $baseURL = ltrim($baseURL, '/');

        $this->processInlineImagesInternal($templateText, $mailer, $baseURL);
    }

    /**
     * Internal method to process inline images
     *
     * @param   string  $templateText  The template text
     * @param   Mail    $mailer        The mailer instance
     * @param   string  $baseURL       The base URL for images
     *
     * @return  string  The processed template text
     *
     * @since   6.0.0
     */
    protected function processInlineImagesInternal(string $templateText, Mail &$mailer, string $baseURL): string
    {
        $pattern         = '/(src)=\"([^"]*)\"/i';
        $numberOfMatches = preg_match_all($pattern, $templateText, $matches, PREG_OFFSET_CAPTURE);

        if ($numberOfMatches > 0) {
            $substitutions = $matches[2];
            $lastPosition  = 0;
            $temp          = '';
            $imgIdx        = 0;
            $imageSubs     = [];

            foreach ($substitutions as &$entry) {
                // Copy unchanged part
                if ($entry[1] > 0) {
                    $temp .= substr($templateText, $lastPosition, $entry[1] - $lastPosition);
                }

                $url = $entry[0];

                if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                    // External link, skip
                    $temp .= $url;
                } else {
                    $ext = strtolower(File::getExt($url));

                    if (!file_exists($url)) {
                        // Relative path, make absolute
                        $url = $baseURL . ltrim($url, '/');
                    }

                    if (!file_exists($url) || !\in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        // Not an image or non-existent file
                        $temp .= $url;
                    } else {
                        // Image found, substitute
                        if (!\array_key_exists($url, $imageSubs)) {
                            $imgIdx++;
                            $mailer->AddEmbeddedImage($url, 'img' . $imgIdx, basename($url));
                            $imageSubs[$url] = $imgIdx;
                        }

                        $temp .= 'cid:img' . $imageSubs[$url];
                    }
                }

                $lastPosition = $entry[1] + \strlen($entry[0]);
            }

            // Copy remaining part
            if ($lastPosition < \strlen($templateText)) {
                $temp .= substr($templateText, $lastPosition);
            }

            $templateText = $temp;
        }

        return $templateText;
    }

    /**
     * Convert HTML to plain text version
     *
     * @param   string  $html  The HTML content
     *
     * @return  string  The plain text version
     *
     * @since   6.0.0
     */
    public function textVersion(string $html): string
    {
        $html = preg_replace('# +#', ' ', $html);
        $html = str_replace(["\n", "\r", "\t"], '', $html);

        $removeScript           = "#< *script(?:(?!< */ *script *>).)*< */ *script *>#isU";
        $removeStyle            = "#< *style(?:(?!< */ *style *>).)*< */ *style *>#isU";
        $removeStrikeTags       = '#< *strike(?:(?!< */ *strike *>).)*< */ *strike *>#iU';
        $replaceByTwoReturnChar = '#< *(h1|h2)[^>]*>#Ui';
        $replaceByStars         = '#< *li[^>]*>#Ui';
        $replaceByReturnChar1   = '#< */ *(li|td|tr|div|p)[^>]*> *< *(li|td|tr|div|p)[^>]*>#Ui';
        $replaceByReturnChar    = '#< */? *(br|p|h1|h2|h3|li|ul|h4|h5|h6|tr|td|div)[^>]*>#Ui';
        $replaceLinks           = '/< *a[^>]*href *= *"([^"]*)"[^>]*>(.*)< *\/ *a *>/Uis';

        $text = preg_replace(
            [
                $removeScript,
                $removeStyle,
                $removeStrikeTags,
                $replaceByTwoReturnChar,
                $replaceByStars,
                $replaceByReturnChar1,
                $replaceByReturnChar,
                $replaceLinks,
            ],
            ['', '', '', "\n\n", "\n* ", "\n", "\n", '${2} ( ${1} )'],
            $html
        );

        $text = str_replace([" ", "&nbsp;"], ' ', strip_tags($text));
        $text = trim(@html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
        $text = preg_replace('# +#', ' ', $text);
        $text = preg_replace('#\n *\n\s+#', "\n\n", $text);

        return $text;
    }

    /**
     * Process custom fields in template text
     *
     * @param   object    $row       The order info object
     * @param   string    $type      The field type ('billing', 'shipping', 'payment')
     * @param   string    $text      The template text
     * @param   Language  $language  The language instance
     *
     * @return  string  The processed text
     *
     * @since   6.0.0
     */
    protected function processCustomFields(object $row, string $type, string $text, Language $language): string
    {
        $field = match ($type) {
            'billing'  => 'all_billing',
            'shipping' => 'all_shipping',
            'payment'  => 'all_payment',
            default    => ''
        };

        if (empty($field)) {
            return $text;
        }

        $fields = [];

        if (!empty($row->$field) && \strlen($row->$field) > 0) {
            $customFields = $this->getDecodedFields($row->$field);

            if (!empty($customFields)) {
                foreach ($customFields as $namekey => $fieldData) {
                    if (
                        !property_exists($row, $type . '_' . $namekey)
                        && !property_exists($row, 'user_' . $namekey)
                        && !\in_array($namekey, ['country_id', 'zone_id', 'option', 'task', 'view'])
                    ) {
                        if (\is_array($fieldData['value'] ?? null)) {
                            $fieldData['value'] = implode(',', $fieldData['value']);
                        }

                        if (isset($fieldData['value'])) {
                            $fieldData['value'] = nl2br((string) $fieldData['value']);
                        }

                        $fields[$namekey] = $fieldData;
                    }
                }
            }
        }

        // Dispatch plugin event
        Factory::getApplication()->getDispatcher()->dispatch(
            'onJ2CommerceBeforeReplaceCustomFields',
            new \Joomla\CMS\Event\GenericEvent('onJ2CommerceBeforeReplaceCustomFields', [
                'fields' => &$fields,
                'text'   => &$text,
                'type'   => $type,
            ])
        );

        if (!empty($fields)) {
            foreach ($fields as $namekey => $fieldData) {
                $string = '';
                $value  = $fieldData['value'] ?? '';

                if (\is_array($value)) {
                    foreach ($value as $val) {
                        $string .= '-' . $language->_($val) . '\n';
                    }
                } elseif (\is_object($value)) {
                    $objArray = (array) $value;
                    $string .= '\n';

                    foreach ($objArray as $val) {
                        $string .= '- ' . Text::_($val) . '\n';
                    }
                } elseif (\is_string($value) && $this->isJson(stripcslashes($value))) {
                    $jsonValues = json_decode(stripcslashes($value));

                    if (\is_array($jsonValues)) {
                        foreach ($jsonValues as $val) {
                            $string .= '-' . $language->_($val) . '\n';
                        }
                    } else {
                        $string .= $language->_($value);
                    }
                } else {
                    $string = $language->_((string) $value);
                }

                // Handle zone/country type fields
                if (isset($fieldData['zone_type']) && !empty($value)) {
                    if ($fieldData['zone_type'] === 'zone') {
                        $string = $language->_($this->getZoneName((int) $value));
                    } elseif ($fieldData['zone_type'] === 'country') {
                        $string = $language->_($this->getCountryName((int) $value));
                    }
                }

                $formattedValue = $language->_($fieldData['label'] ?? '') . ' : ' . $string;
                $tagValue       = '[CUSTOM_' . strtoupper($type) . '_FIELD:' . strtoupper($namekey) . ']';
                $text           = str_replace($tagValue, $formattedValue, $text);
            }
        }

        return $text;
    }

    /**
     * Decode JSON fields to array
     *
     * @param   string  $json  The JSON string
     *
     * @return  array<string, mixed>  The decoded array
     *
     * @since   6.0.0
     */
    protected function getDecodedFields(string $json): array
    {
        if (empty($json)) {
            return [];
        }

        $registry = new Registry($json);

        return $registry->toArray();
    }

    /**
     * Check if string is valid JSON
     *
     * @param   string  $string  The string to check
     *
     * @return  bool  True if valid JSON
     *
     * @since   6.0.0
     */
    protected function isJson(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Get order information record
     *
     * @param   object  $order  The order object
     *
     * @return  object  The order info object
     *
     * @since   6.0.0
     */
    protected function getOrderInfo(object $order): object
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $orderId = $order->order_id ?? '';

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_orderinfos'))
            ->where($db->quoteName('order_id') . ' = :order_id')
            ->bind(':order_id', $orderId);

        $db->setQuery($query);

        return $db->loadObject() ?: new \stdClass();
    }

    /**
     * Get order shipping record
     *
     * @param   object  $order  The order object
     *
     * @return  object  The order shipping object
     *
     * @since   6.0.0
     */
    protected function getOrderShipping(object $order): object
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $orderId = $order->order_id ?? '';

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_ordershippings'))
            ->where($db->quoteName('order_id') . ' = :order_id')
            ->bind(':order_id', $orderId);

        $db->setQuery($query);

        return $db->loadObject() ?: new \stdClass();
    }

    /**
     * Get order coupons/discounts
     *
     * @param   object  $order  The order object
     *
     * @return  array<int, object>  Array of discount records
     *
     * @since   6.0.0
     */
    protected function getOrderCoupons(object $order): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $orderId      = $order->order_id ?? '';
        $discountType = 'coupon';

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_orderdiscounts'))
            ->where($db->quoteName('order_id') . ' = :order_id')
            ->where($db->quoteName('discount_type') . ' = :discount_type')
            ->bind(':order_id', $orderId)
            ->bind(':discount_type', $discountType);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get order status by ID
     *
     * @param   int  $orderStatusId  The order status ID
     *
     * @return  object  The order status object
     *
     * @since   6.0.0
     */
    protected function getOrderStatus(int $orderStatusId): object
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_orderstatuses'))
            ->where($db->quoteName('j2commerce_orderstatus_id') . ' = :id')
            ->bind(':id', $orderStatusId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObject() ?: new \stdClass();
    }

    /**
     * Get country name by ID
     *
     * @param   int  $countryId  The country ID
     *
     * @return  string  The country name
     *
     * @since   6.0.0
     */
    public function getCountryName(int $countryId): string
    {
        if ($countryId <= 0) {
            return '';
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName('country_name'))
            ->from($db->quoteName('#__j2commerce_countries'))
            ->where($db->quoteName('j2commerce_country_id') . ' = :id')
            ->bind(':id', $countryId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadResult() ?: '';
    }

    /**
     * Get zone name by ID
     *
     * @param   int  $zoneId  The zone ID
     *
     * @return  string  The zone name
     *
     * @since   6.0.0
     */
    public function getZoneName(int $zoneId): string
    {
        if ($zoneId <= 0) {
            return '';
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName('zone_name'))
            ->from($db->quoteName('#__j2commerce_zones'))
            ->where($db->quoteName('j2commerce_zone_id') . ' = :id')
            ->bind(':id', $zoneId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadResult() ?: '';
    }

    /**
     * Get country by ID (returns full object)
     *
     * @param   int  $countryId  The country ID
     *
     * @return  object  The country object
     *
     * @since   6.0.0
     */
    public function getCountryById(int $countryId): object
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_countries'))
            ->where($db->quoteName('j2commerce_country_id') . ' = :id')
            ->bind(':id', $countryId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObject() ?: new \stdClass();
    }

    /**
     * Get zone by ID (returns full object)
     *
     * @param   int  $zoneId  The zone ID
     *
     * @return  object  The zone object
     *
     * @since   6.0.0
     */
    public function getZoneById(int $zoneId): object
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_zones'))
            ->where($db->quoteName('j2commerce_zone_id') . ' = :id')
            ->bind(':id', $zoneId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObject() ?: new \stdClass();
    }

    /** Get human-readable payment method title from plugin element name. */
    private function getPaymentMethodTitle(string $element, Language $language): string
    {
        if (empty($element)) {
            return '';
        }

        // Try language string: PLG_J2COMMERCE_{ELEMENT}_TITLE (e.g. PLG_J2COMMERCE_PAYMENT_CASH_TITLE)
        $langKey    = 'PLG_J2COMMERCE_' . strtoupper($element) . '_TITLE';
        $translated = $language->_($langKey);
        if ($translated !== $langKey) {
            return $translated;
        }

        // Fall back to the extension name field from the database
        $db     = self::getDatabase();
        $folder = 'j2commerce';
        $query  = $db->getQuery(true)
            ->select($db->quoteName('name'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = :folder')
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':folder', $folder)
            ->bind(':element', $element);
        $db->setQuery($query, 0, 1);
        $name = $db->loadResult();

        if ($name) {
            $translated = $language->_($name);
            if ($translated !== $name) {
                return $translated;
            }
        }

        // Last resort: humanize the element name (payment_cash → Cash)
        $short = str_replace(['payment_', 'shipping_', 'app_'], '', $element);
        return ucwords(str_replace('_', ' ', $short));
    }

    /**
     * Get user group names
     *
     * @param   int  $userId  The user ID
     *
     * @return  array<int, string>  Array of group names
     *
     * @since   6.0.0
     */
    protected function getUserGroupNames(int $userId): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName('g.title'))
            ->from($db->quoteName('#__usergroups', 'g'))
            ->join(
                'INNER',
                $db->quoteName('#__user_usergroup_map', 'm')
                . ' ON ' . $db->quoteName('g.id') . ' = ' . $db->quoteName('m.group_id')
            )
            ->where($db->quoteName('m.user_id') . ' = :user_id')
            ->bind(':user_id', $userId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadColumn() ?: [];
    }

    /**
     * Get invoice number for order
     *
     * @param   object  $order  The order object
     *
     * @return  string  The formatted invoice number
     *
     * @since   6.0.0
     */
    protected function getInvoiceNumber(object $order): string
    {
        $prefix = $order->invoice_prefix ?? '';
        $number = $order->invoice_number ?? 0;

        if (empty($number)) {
            return '';
        }

        return $prefix . $number;
    }

    /**
     * Get total shipping weight for order
     *
     * @param   object  $order  The order object
     *
     * @return  string  The total weight
     *
     * @since   6.0.0
     */
    protected function getTotalShippingWeight(object $order): string
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $orderId = $order->order_id ?? '';

        $query->select('SUM(' . $db->quoteName('orderitem_weight') . ') AS total_weight')
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :order_id')
            ->bind(':order_id', $orderId);

        $db->setQuery($query);
        $weight = $db->loadResult();

        return $weight ? (string) $weight : '0';
    }

    /**
     * Get language instance for order
     *
     * @param   object  $order  The order object
     *
     * @return  Language  The language instance
     *
     * @since   6.0.0
     */
    protected function getLanguageForOrder(object $order): Language
    {
        $customerLanguage = $order->customer_language ?? '';

        if (empty($customerLanguage) || $customerLanguage === '*') {
            return Factory::getLanguage();
        }

        $conf     = Factory::getApplication()->getConfig();
        $debug    = $conf->get('debug_lang');
        $language = Language::getInstance($customerLanguage, $debug);
        $language->load('com_j2commerce');

        return $language;
    }

    /**
     * Load order items as HTML template
     *
     * @param   object  $order         The order object
     * @param   string  $receiverType  The receiver type
     *
     * @return  string  The HTML template for order items
     *
     * @since   6.0.0
     */
    protected function loadPackingItemsTemplate(object $order): string
    {
        $db      = self::getDatabase();
        $query   = $db->getQuery(true);
        $orderId = $order->order_id ?? '';

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :order_id')
            ->bind(':order_id', $orderId);

        $db->setQuery($query);
        $items = $db->loadObjectList() ?: [];

        if (empty($items)) {
            return '';
        }

        $html  = '<table style="width:100%; border-collapse:collapse;">';
        $html .= '<thead>';
        $html .= '<tr style="background:#f5f5f5;">';
        $html .= '<th style="padding:8px; text-align:left; border:1px solid #ddd;">' . Text::_('COM_J2COMMERCE_EMAIL_PRODUCT') . '</th>';
        $html .= '<th style="padding:8px; text-align:left; border:1px solid #ddd;">' . Text::_('COM_J2COMMERCE_EMAIL_SKU') . '</th>';
        $html .= '<th style="padding:8px; text-align:center; border:1px solid #ddd;">' . Text::_('COM_J2COMMERCE_EMAIL_QUANTITY') . '</th>';
        $html .= '<th style="padding:8px; text-align:center; border:1px solid #ddd;">' . Text::_('COM_J2COMMERCE_FIELD_WEIGHT') . '</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ($items as $item) {
            $html .= '<tr>';
            $html .= '<td style="padding:8px; border:1px solid #ddd;">';
            $html .= htmlspecialchars($item->orderitem_name ?? '');

            $optionText = $this->decodeOrderItemAttributes($item->orderitem_attributes ?? '');

            if (!empty($optionText)) {
                $html .= '<br><small style="color:#666;">' . $optionText . '</small>';
            }

            $html .= '</td>';
            $html .= '<td style="padding:8px; border:1px solid #ddd;">' . htmlspecialchars($item->orderitem_sku ?? '') . '</td>';
            $html .= '<td style="padding:8px; text-align:center; border:1px solid #ddd;">' . (int) ($item->orderitem_quantity ?? 0) . '</td>';
            $html .= '<td style="padding:8px; text-align:center; border:1px solid #ddd;">' . (float) ($item->orderitem_weight ?? 0) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    protected function loadItemsTemplate(object $order, string $receiverType = '*'): string
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $orderId = $order->order_id ?? '';

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :order_id')
            ->bind(':order_id', $orderId);

        $db->setQuery($query);
        $items = $db->loadObjectList() ?: [];

        if (empty($items)) {
            return '';
        }

        $baseURL       = str_replace('/administrator', '', Uri::base());
        $currencyCode  = $order->currency_code ?? '';
        $currencyValue = (float) ($order->currency_value ?? 1);

        $html = '<table style="width:100%; border-collapse:collapse;">';
        $html .= '<thead>';
        $html .= '<tr style="background:#f5f5f5;">';
        $html .= '<th style="padding:8px; text-align:left; border:1px solid #ddd;">' . Text::_('COM_J2COMMERCE_EMAIL_PRODUCT') . '</th>';
        $html .= '<th style="padding:8px; text-align:right; border:1px solid #ddd;">' . Text::_('COM_J2COMMERCE_EMAIL_QUANTITY') . '</th>';
        $html .= '<th style="padding:8px; text-align:right; border:1px solid #ddd;">' . Text::_('COM_J2COMMERCE_EMAIL_PRICE') . '</th>';
        $html .= '<th style="padding:8px; text-align:right; border:1px solid #ddd;">' . Text::_('COM_J2COMMERCE_EMAIL_TOTAL') . '</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ($items as $item) {
            $imageUrl = $this->getProductImageForEmail((int) ($item->product_id ?? 0), $baseURL);

            $html .= '<tr>';
            $html .= '<td style="padding:8px; border:1px solid #ddd;">';

            if (!empty($imageUrl)) {
                $html .= '<img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($item->orderitem_name ?? '') . '" width="50" height="50" style="border-radius:4px; object-fit:cover; margin-right:8px; vertical-align:middle;" />';
            }

            $html .= htmlspecialchars($item->orderitem_name ?? '');

            if (!empty($item->orderitem_sku)) {
                $html .= '<br><small>' . Text::_('COM_J2COMMERCE_EMAIL_SKU') . ': ' . htmlspecialchars($item->orderitem_sku) . '</small>';
            }

            $html .= '</td>';
            $html .= '<td style="padding:8px; text-align:right; border:1px solid #ddd;">' . (int) ($item->orderitem_quantity ?? 0) . '</td>';
            $html .= '<td style="padding:8px; text-align:right; border:1px solid #ddd;">'
                . CurrencyHelper::format((float) ($item->orderitem_price ?? 0), $currencyCode, $currencyValue) . '</td>';
            $html .= '<td style="padding:8px; text-align:right; border:1px solid #ddd;">'
                . CurrencyHelper::format((float) ($item->orderitem_finalprice ?? 0), $currencyCode, $currencyValue) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Get all registered email types from core and plugins.
     *
     * @return  array  Email type definitions
     *
     * @since   6.1.0
     */
    public static function getEmailTypes(): array
    {
        static $types = null;

        if ($types !== null) {
            return $types;
        }

        $types = self::getEmailTypeRegistry()->getTypes();

        return $types;
    }

    /**
     * Get available tags for an email type.
     *
     * @param   string  $emailType  The email type
     *
     * @return  array  Tag definitions grouped by category
     *
     * @since   6.1.0
     */
    public static function getTagsForType(string $emailType): array
    {
        return self::getEmailTypeRegistry()->getGroupedTagsForType($emailType);
    }

    /**
     * Get available contexts for an email type.
     *
     * @param   string  $emailType  The email type
     *
     * @return  array  Context definitions
     *
     * @since   6.1.0
     */
    public static function getContextsForType(string $emailType): array
    {
        return self::getEmailTypeRegistry()->getContextsForType($emailType);
    }

    private static function getEmailTypeRegistry(): \J2Commerce\Component\J2commerce\Administrator\Service\EmailTypeRegistry
    {
        static $registry = null;

        if ($registry === null) {
            $db       = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $registry = new \J2Commerce\Component\J2commerce\Administrator\Service\EmailTypeRegistry($db);
        }

        return $registry;
    }

    /**
     * Get template by type and context.
     *
     * @param   string  $emailType   The email type
     * @param   string  $context     The context (optional)
     * @param   string  $language    Language code (default: current)
     *
     * @return  object|null
     *
     * @since   6.1.0
     */
    public static function getTemplateByType(string $emailType, string $context = '', string $language = ''): ?object
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_emailtemplates'))
            ->where($db->quoteName('email_type') . ' = :emailType')
            ->where($db->quoteName('enabled') . ' = 1')
            ->bind(':emailType', $emailType);

        if ($context !== '') {
            $query->where($db->quoteName('context') . ' = :context')
                ->bind(':context', $context);
        }

        $langAll = '*';

        if ($language !== '') {
            $query->where($db->quoteName('language') . ' IN (:language, :languageAll)')
                ->bind(':language', $language)
                ->bind(':languageAll', $langAll);
        } else {
            $query->where($db->quoteName('language') . ' = :languageAll')
                ->bind(':languageAll', $langAll);
        }

        $query->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /**
     * Process email tags for a given type and context.
     *
     * This method processes both core tags and plugin-specific tags.
     *
     * @param   string  $emailType  The email type
     * @param   string  $context    The context
     * @param   object  $data       Context data (order, voucher, etc.)
     * @param   string  $body       The body to process
     *
     * @return  string  Processed body
     *
     * @since   6.1.0
     */
    public static function processTypeTags(string $emailType, string $context, object $data, string $body): string
    {
        // First, process core transactional tags if applicable
        if ($emailType === 'transactional' && isset($data->order_id)) {
            $instance = self::getInstance();
            $body     = $instance->processTags($body, $data);
        }

        // Dispatch event for plugin-specific tag processing
        try {
            $app   = Factory::getApplication();
            $event = new \Joomla\Event\Event('onJ2CommerceProcessEmailTags', [
                'emailType' => $emailType,
                'context'   => $context,
                'data'      => $data,
                'body'      => $body,
            ]);

            $app->getDispatcher()->dispatch('onJ2CommerceProcessEmailTags', $event);
            $result = $event->getArgument('body');

            if ($result !== null) {
                $body = $result;
            }
        } catch (\Exception $e) {
            // Event dispatching failed, continue with original body
        }

        return $body;
    }

    /**
     * Check if an email type is registered.
     *
     * @param   string  $emailType  The email type to check
     *
     * @return  bool
     *
     * @since   6.1.0
     */
    public static function hasEmailType(string $emailType): bool
    {
        $registry = Factory::getContainer()->get(\J2Commerce\Component\J2commerce\Administrator\Service\EmailTypeRegistry::class);
        return $registry->hasType($emailType);
    }
}
