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

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// No direct access
\defined('_JEXEC') or die;

/**
 * Invoice Helper class for J2Commerce
 *
 * Provides invoice template loading and processing functionality
 * for generating PDF invoices and printable order documents.
 * This class handles:
 * - Loading invoice templates from database based on order criteria
 * - Filtering templates by language preference
 * - Processing inline images for embedded content
 * - Formatting invoices with order data via tag processing
 *
 * @since  6.0.0
 */
class InvoiceHelper
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
     * @var   InvoiceHelper|null
     * @since 6.0.0
     */
    private static ?InvoiceHelper $instance = null;

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
     * @return  InvoiceHelper
     *
     * @since   6.0.0
     */
    public static function getInstance(array $config = []): InvoiceHelper
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * Load invoice template for an order based on language preference
     *
     * This method loads all matching invoice templates and selects the best
     * match based on language preference scoring:
     * - User's language (highest priority)
     * - Current site language
     * - Default site language
     * - English (en-GB)
     * - All languages (*) (fallback)
     *
     * @param   object  $order  The order object containing order_state_id, customer_language,
     *                          customer_group, orderpayment_type, and user_id
     *
     * @return  string  The invoice template body text, or default template if none found
     *
     * @since   6.0.0
     */
    public function loadInvoiceTemplate(object $order): string
    {
        $templateText = '';

        // Build language preference array
        $jLang    = Factory::getLanguage();
        $userLang = $order->customer_language ?? '';

        // Try to get user's preferred language if not set on order
        if (empty($userLang)) {
            $userId = (int) ($order->user_id ?? 0);

            if ($userId > 0) {
                $user = Factory::getUser($userId);

                if ($user->id > 0) {
                    $userLang = $user->getParam('language', '');
                }
            }
        }

        $languages = [
            $userLang,
            $jLang->getTag(),
            $jLang->getDefault(),
            'en-GB',
            '*',
        ];

        // Load all matching templates
        $allTemplates = $this->getInvoiceTemplates($order);

        if (\count($allTemplates)) {
            $preferredScore = 0;

            foreach ($allTemplates as $template) {
                $myLang = $template->language ?? '*';

                // Check if language matches our preference list
                $langPos = array_search($myLang, $languages, true);

                if ($langPos === false) {
                    continue;
                }

                // Calculate language score (higher position = lower score)
                $langScore = (5 - $langPos);

                if ($langScore > $preferredScore) {
                    // Dispatch plugin event for template customization
                    Factory::getApplication()->getDispatcher()->dispatch(
                        'onJ2CommerceInvoiceFileTemplate',
                        new \Joomla\CMS\Event\GenericEvent('onJ2CommerceInvoiceFileTemplate', [
                            'template' => &$template,
                            'order'    => $order,
                        ])
                    );

                    $templateText   = $template->body ?? '';
                    $preferredScore = $langScore;
                }
            }
        } else {
            // No templates found, use default
            $templateText = Text::_('COM_J2COMMERCE_DEFAULT_INVOICE_TEMPLATE_TEXT');
        }

        return $templateText;
    }

    /**
     * Get invoice templates matching order criteria
     *
     * Retrieves all enabled invoice templates that match:
     * - Order status (or wildcard)
     * - Customer group (or wildcard) - only on frontend
     * - Payment method (or wildcard)
     *
     * @param   object  $order  The order object
     *
     * @return  array<int, object>  Array of matching invoice template objects
     *
     * @since   6.0.0
     */
    public function getInvoiceTemplates(object $order): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $orderStateId  = (string) ($order->order_state_id ?? '');
        $paymentType   = $order->orderpayment_type ?? '';
        $customerGroup = $order->customer_group ?? '';

        $invoiceType = 'invoice';

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_invoicetemplates'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('invoice_type') . ' = :invoice_type')
            ->bind(':invoice_type', $invoiceType);

        // Order status filter with CASE statement
        $query->where(
            'CASE WHEN ' . $db->quoteName('orderstatus_id') . ' = :orderstatus_id'
            . ' THEN ' . $db->quoteName('orderstatus_id') . ' = :orderstatus_id2'
            . ' ELSE ' . $db->quoteName('orderstatus_id') . ' = ' . $db->quote('*')
            . ' OR ' . $db->quoteName('orderstatus_id') . ' = ' . $db->quote('')
            . ' END'
        )
            ->bind(':orderstatus_id', $orderStateId)
            ->bind(':orderstatus_id2', $orderStateId);

        // Customer group filter (only on frontend/site) — parse to integers to prevent SQL injection
        if (!empty($customerGroup)) {
            $app = Factory::getApplication();

            if ($app->isClient('site')) {
                $groupIds = array_values(array_filter(
                    array_map('intval', explode(',', $customerGroup)),
                    fn ($id) => $id > 0
                ));

                if (!empty($groupIds)) {
                    $inList = implode(',', $groupIds);
                    $query->where(
                        'CASE WHEN ' . $db->quoteName('group_id') . ' IN (' . $inList . ')'
                        . ' THEN ' . $db->quoteName('group_id') . ' IN (' . $inList . ')'
                        . ' ELSE ' . $db->quoteName('group_id') . ' = ' . $db->quote('*')
                        . ' OR ' . $db->quoteName('group_id') . ' = ' . $db->quote('1')
                        . ' OR ' . $db->quoteName('group_id') . ' = ' . $db->quote('')
                        . ' END'
                    );
                }
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

        // Dispatch plugin event for query customization
        Factory::getApplication()->getDispatcher()->dispatch(
            'onJ2CommerceAfterInvoiceQuery',
            new \Joomla\CMS\Event\GenericEvent('onJ2CommerceAfterInvoiceQuery', [
                'query' => &$query,
                'order' => $order,
            ])
        );

        $db->setQuery($query);

        try {
            $allTemplates = $db->loadObjectList() ?: [];
        } catch (\Exception $e) {
            $allTemplates = [];
        }

        return $allTemplates;
    }

    /**
     * Get formatted invoice with all tags processed
     *
     * Loads the appropriate invoice template and processes all template tags
     * using the EmailHelper's processTags method.
     *
     * @param   object  $order  The order object with all order data
     *
     * @return  string  The fully formatted invoice HTML
     *
     * @since   6.0.0
     */
    public function getFormattedInvoice(object $order): string
    {
        $text     = $this->loadInvoiceTemplate($order);
        $template = EmailHelper::getInstance()->processTags($text, $order, []);

        return $template;
    }

    /**
     * Process inline images in template text for embedding
     *
     * Converts relative image paths to absolute URLs and identifies
     * local images for potential embedding. Returns an array with
     * the processed template text and image substitution mapping.
     *
     * @param   string  $templateText  The template text containing image tags
     *
     * @return  array{text: string, images: array<string, int>}  Processed text and image mapping
     *
     * @since   6.0.0
     */
    public function processInlineImages(string $templateText): array
    {
        $baseURL = str_replace('/administrator', '', Uri::base());
        $baseURL = ltrim($baseURL, '/');

        $imageSubs = [];
        $imgIdx    = 0;

        // Match all src attributes in the template
        $pattern         = '/(src)=\"([^"]*)\"/i';
        $numberOfMatches = preg_match_all($pattern, $templateText, $matches, PREG_OFFSET_CAPTURE);

        if ($numberOfMatches > 0) {
            $substitutions = $matches[2];
            $lastPosition  = 0;
            $temp          = '';

            foreach ($substitutions as &$entry) {
                // Copy unchanged part
                if ($entry[1] > 0) {
                    $temp .= substr($templateText, $lastPosition, $entry[1] - $lastPosition);
                }

                $url = $entry[0];

                // Check if external URL
                if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                    // External link, keep as-is
                    $temp .= $url;
                } else {
                    $ext = strtolower(File::getExt($url));

                    // Try to resolve relative path
                    if (!file_exists($url)) {
                        $url = $baseURL . ltrim($url, '/');
                    }

                    // Check if valid image file
                    if (!file_exists($url) || !\in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        // Not a valid image file
                        $temp .= $url;
                    } else {
                        // Valid image found, create CID substitution
                        if (!\array_key_exists($url, $imageSubs)) {
                            $imgIdx++;
                            $imageSubs[$url] = $imgIdx;
                        }

                        $temp .= 'cid:img' . $imageSubs[$url];
                    }
                }

                // Calculate next starting offset
                $lastPosition = $entry[1] + \strlen($entry[0]);
            }

            // Copy remaining part
            if ($lastPosition < \strlen($templateText)) {
                $temp .= substr($templateText, $lastPosition);
            }

            $templateText = $temp;
        }

        return [
            'text'   => $templateText,
            'images' => $imageSubs,
        ];
    }

    /**
     * Get invoice template by ID
     *
     * @param   int  $templateId  The invoice template ID
     *
     * @return  object|null  The template object or null if not found
     *
     * @since   6.0.0
     */
    public function getTemplateById(int $templateId): ?object
    {
        if ($templateId <= 0) {
            return null;
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_invoicetemplates'))
            ->where($db->quoteName('j2commerce_invoicetemplate_id') . ' = :id')
            ->bind(':id', $templateId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /**
     * Get all enabled invoice templates
     *
     * @param   string  $language  Optional language filter (default: all)
     *
     * @return  array<int, object>  Array of invoice template objects
     *
     * @since   6.0.0
     */
    public function getAllTemplates(string $language = ''): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $invoiceType = 'invoice';

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_invoicetemplates'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('invoice_type') . ' = :invoice_type')
            ->bind(':invoice_type', $invoiceType)
            ->order($db->quoteName('ordering') . ' ASC');

        if (!empty($language) && $language !== '*') {
            $query->where(
                '(' . $db->quoteName('language') . ' = :language'
                . ' OR ' . $db->quoteName('language') . ' = ' . $db->quote('*') . ')'
            )
                ->bind(':language', $language);
        }

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get invoice number formatted with prefix
     *
     * @param   object  $order  The order object
     *
     * @return  string  The formatted invoice number (prefix + number)
     *
     * @since   6.0.0
     */
    public static function getInvoiceNumber(object $order): string
    {
        $orderId = (string) ($order->j2commerce_order_id ?? '');

        if ($orderId === '') {
            return '';
        }

        return ($order->invoice_prefix ?? '') . $orderId;
    }

    /**
     * Check if invoice number has been generated for order
     *
     * @param   object  $order  The order object
     *
     * @return  bool  True if invoice number exists
     *
     * @since   6.0.0
     */
    public static function hasInvoiceNumber(object $order): bool
    {
        return !empty($order->j2commerce_order_id ?? 0);
    }
}
