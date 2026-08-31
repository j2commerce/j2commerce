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
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

class PackingSlipHelper
{
    /**
     * Tags a packing slip drops at render. [ORDERAMOUNT] is deliberately absent: a declared
     * value is asked for by carriers and insurers, so a tag the author placed is honoured.
     * The shipped presets carry no money tags, which is what keeps the default price-free.
     * The authoring UI reads this list so it cannot offer what the renderer will delete.
     */
    public const STRIPPED_TAGS = [
        'SUBTOTAL', 'TAX_AMOUNT', 'SHIPPING_AMOUNT',
        'DISCOUNT_AMOUNT', 'TAX_LINES', 'COUPON_CODE', 'TOTALS',
        'ORDER_EXTRA_ROWS', 'ITEM_PRICE', 'ITEM_TOTAL',
    ];

    private static ?DatabaseInterface $db       = null;
    private static ?PackingSlipHelper $instance = null;

    /** Candidate rows per order, so body and custom_css cannot be resolved from different queries. */
    private array $templateCache = [];

    /** Winning row per order — body and CSS are read separately, and the pick loads a user. */
    private array $selectedCache = [];

    private static function getDatabase(): DatabaseInterface
    {
        return self::$db ??= Factory::getContainer()->get(DatabaseInterface::class);
    }

    public static function getInstance(): PackingSlipHelper
    {
        return self::$instance ??= new self();
    }

    public function loadPackingSlipTemplate(object $order): string
    {
        if (\count($this->getPackingSlipTemplates($order)) === 0) {
            return Text::_('COM_J2COMMERCE_DEFAULT_PACKINGSLIP_TEMPLATE_TEXT');
        }

        return $this->getSelectedTemplate($order)?->body ?? '';
    }

    /** The record loadPackingSlipTemplate() takes its body from — read custom_css from this, never a second query. */
    public function getSelectedTemplate(object $order): ?object
    {
        $cacheKey = $this->getCacheKey($order);

        if (\array_key_exists($cacheKey, $this->selectedCache)) {
            return $this->selectedCache[$cacheKey];
        }

        $jLang    = Factory::getLanguage();
        $userLang = $order->customer_language ?? '';

        if (empty($userLang) && !empty($order->user_id) && (int) $order->user_id > 0) {
            $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
            $user        = $userFactory->loadUserById((int) $order->user_id);

            if ($user->id > 0) {
                $userLang = $user->getParam('language', '');
            }
        }

        $languages      = [$userLang, $jLang->getTag(), $jLang->getDefault(), 'en-GB', '*'];
        $selected       = null;
        $preferredScore = 0;

        foreach ($this->getPackingSlipTemplates($order) as $template) {
            $myLang  = $template->language ?? '*';
            $langPos = array_search($myLang, $languages, true);

            if ($langPos === false) {
                continue;
            }

            $langScore = 5 - $langPos;

            if ($langScore > $preferredScore) {
                $selected       = $template;
                $preferredScore = $langScore;
            }
        }

        return $this->selectedCache[$cacheKey] = $selected;
    }

    private function getCacheKey(object $order): string
    {
        return !empty($order->order_id) ? 'id:' . $order->order_id : 'obj:' . spl_object_id($order);
    }

    public function getPackingSlipTemplates(object $order): array
    {
        $cacheKey = $this->getCacheKey($order);

        if (isset($this->templateCache[$cacheKey])) {
            return $this->templateCache[$cacheKey];
        }

        $db           = self::getDatabase();
        $query        = $db->getQuery(true);
        $orderStateId = (string) ($order->order_state_id ?? '');
        $paymentType  = $order->orderpayment_type ?? '';
        $invoiceType  = 'packingslip';

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_invoicetemplates'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('invoice_type') . ' = :invoice_type')
            ->bind(':invoice_type', $invoiceType);

        $query->where(
            'CASE WHEN ' . $db->quoteName('orderstatus_id') . ' = :orderstatus_id'
            . ' THEN ' . $db->quoteName('orderstatus_id') . ' = :orderstatus_id2'
            . ' ELSE ' . $db->quoteName('orderstatus_id') . ' = ' . $db->quote('*')
            . ' OR ' . $db->quoteName('orderstatus_id') . ' = ' . $db->quote('')
            . ' END'
        )
            ->bind(':orderstatus_id', $orderStateId)
            ->bind(':orderstatus_id2', $orderStateId);

        $query->where(
            'CASE WHEN ' . $db->quoteName('paymentmethod') . ' = :paymentmethod'
            . ' THEN ' . $db->quoteName('paymentmethod') . ' = :paymentmethod2'
            . ' ELSE ' . $db->quoteName('paymentmethod') . ' = ' . $db->quote('*')
            . ' OR ' . $db->quoteName('paymentmethod') . ' = ' . $db->quote('')
            . ' END'
        )
            ->bind(':paymentmethod', $paymentType)
            ->bind(':paymentmethod2', $paymentType);

        $db->setQuery($query);

        try {
            return $this->templateCache[$cacheKey] = $db->loadObjectList() ?: [];
        } catch (\Exception $e) {
            return $this->templateCache[$cacheKey] = [];
        }
    }

    public function getFormattedPackingSlip(object $order, string $receiverType = '*'): string
    {
        // Strip while the price tags are still placeholders — processTags() replaces them with
        // formatted amounts, leaving nothing for str_replace() to match afterwards.
        $text = $this->stripPricingFromItemsTable($this->loadPackingSlipTemplate($order));

        return EmailHelper::getInstance()->processTags($text, $order, [], $receiverType, true);
    }

    /** Expects raw template text: the tags must not have been through processTags() yet. */
    public function stripPricingFromItemsTable(string $html): string
    {
        // Match the editor manglings processTags() normalises — {TAG} from TinyMCE, lowercase
        // from GrapesJS — because that normalisation runs after this strip, not before it.
        // [ITEMS] carries price and total columns; [PACKING_ITEMS] is its price-free twin.
        $strip = static function (array $m): string {
            $tag = strtoupper($m[1] !== '' ? $m[1] : ($m[2] ?? ''));

            if (\in_array($tag, self::STRIPPED_TAGS, true)) {
                return '';
            }

            return $tag === 'ITEMS' ? '[PACKING_ITEMS]' : $m[0];
        };

        // Repeat until stable: removing a tag splices its neighbours, which can spell a fresh one.
        for ($pass = 0; $pass < 10; $pass++) {
            $stripped = preg_replace_callback('/\[([a-zA-Z][a-zA-Z0-9_]*)\]|\{([A-Z][A-Z0-9_]*)\}/', $strip, $html) ?? $html;

            if ($stripped === $html) {
                break;
            }

            $html = $stripped;
        }

        return $html;
    }
}
