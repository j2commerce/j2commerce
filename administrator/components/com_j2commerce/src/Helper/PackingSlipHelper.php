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
    private static ?DatabaseInterface $db       = null;
    private static ?PackingSlipHelper $instance = null;

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
        $jLang    = Factory::getLanguage();
        $userLang = $order->customer_language ?? '';

        if (empty($userLang) && !empty($order->user_id) && (int) $order->user_id > 0) {
            $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
            $user        = $userFactory->loadUserById((int) $order->user_id);

            if ($user->id > 0) {
                $userLang = $user->getParam('language', '');
            }
        }

        $languages    = [$userLang, $jLang->getTag(), $jLang->getDefault(), 'en-GB', '*'];
        $allTemplates = $this->getPackingSlipTemplates($order);

        if (\count($allTemplates) === 0) {
            return Text::_('COM_J2COMMERCE_DEFAULT_PACKINGSLIP_TEMPLATE_TEXT');
        }

        $templateText   = '';
        $preferredScore = 0;

        foreach ($allTemplates as $template) {
            $myLang  = $template->language ?? '*';
            $langPos = array_search($myLang, $languages, true);

            if ($langPos === false) {
                continue;
            }

            $langScore = 5 - $langPos;

            if ($langScore > $preferredScore) {
                $templateText   = $template->body ?? '';
                $preferredScore = $langScore;
            }
        }

        return $templateText;
    }

    public function getPackingSlipTemplates(object $order): array
    {
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
            return $db->loadObjectList() ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getFormattedPackingSlip(object $order): string
    {
        // Strip while the price tags are still placeholders — processTags() replaces them with
        // formatted amounts, leaving nothing for str_replace() to match afterwards.
        $text = $this->stripPricingFromItemsTable($this->loadPackingSlipTemplate($order));

        return EmailHelper::getInstance()->processTags($text, $order, [], '*', true);
    }

    /** Expects raw template text: the tags must not have been through processTags() yet. */
    public function stripPricingFromItemsTable(string $html): string
    {
        $priceTags = [
            'ORDERAMOUNT', 'SUBTOTAL', 'TAX_AMOUNT', 'SHIPPING_AMOUNT',
            'DISCOUNT_AMOUNT', 'TAX_LINES', 'COUPON_CODE', 'TOTALS',
            'ORDER_EXTRA_ROWS', 'ITEM_PRICE', 'ITEM_TOTAL',
        ];

        // Match the editor manglings processTags() normalises — {TAG} from TinyMCE, lowercase
        // from GrapesJS — because that normalisation runs after this strip, not before it.
        // [ITEMS] carries price and total columns; [PACKING_ITEMS] is its price-free twin.
        $strip = static function (array $m) use ($priceTags): string {
            $tag = strtoupper($m[1] !== '' ? $m[1] : ($m[2] ?? ''));

            if (\in_array($tag, $priceTags, true)) {
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
