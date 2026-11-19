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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class OrderHistoryHelper
{
    /** Marks a history line as admin-only; MyprofileModel::getOrderHistory() filters on this shape. */
    public const ADMIN_NOTE_PARAMS = '{"type":"admin_note"}';

    private static array $languages = [];

    /** Own admin Language: a webhook has nothing loaded, and a site request keeps its own wording for shared keys. Keys it lacks fall back to the request language. */
    public static function text(string $key, string|int|float ...$args): string
    {
        $tag = Factory::getApplication()->getLanguage()->getTag();

        if (!isset(self::$languages[$tag])) {
            self::$languages[$tag] = Factory::getContainer()->get(LanguageFactoryInterface::class)->createLanguage($tag);
            self::$languages[$tag]->load('com_j2commerce', JPATH_ADMINISTRATOR);
        }

        $text = self::$languages[$tag]->hasKey($key) ? self::$languages[$tag]->_($key) : Text::_($key);

        return $args ? \sprintf($text, ...$args) : $text;
    }

    public static function add(
        string $orderId,
        string $comment,
        int $orderStateId = 0,
        bool $notifyCustomer = false,
        int $createdBy = 0,
        array|string|null $params = null,
    ): bool {
        if (empty($orderId)) {
            return false;
        }

        // If no state ID provided, look up the current order state
        if ($orderStateId === 0) {
            $orderStateId = self::getCurrentStateId($orderId);
        }

        try {
            $db  = Factory::getContainer()->get(DatabaseInterface::class);
            $now = Factory::getDate()->toSql();

            if ($createdBy === 0) {
                $user      = Factory::getApplication()->getIdentity();
                $createdBy = $user?->id ?? 0;
            }

            $notifyInt  = $notifyCustomer ? 1 : 0;
            $paramsJson = match (true) {
                $params === null   => '{}',
                \is_array($params) => (string) json_encode($params),
                default            => $params,
            };

            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__j2commerce_orderhistories'))
                ->columns([
                    $db->quoteName('order_id'),
                    $db->quoteName('order_state_id'),
                    $db->quoteName('notify_customer'),
                    $db->quoteName('comment'),
                    $db->quoteName('created_on'),
                    $db->quoteName('created_by'),
                    $db->quoteName('params'),
                ])
                ->values(':orderId, :statusId, :notify, :comment, :createdOn, :createdBy, :params')
                ->bind(':orderId', $orderId)
                ->bind(':statusId', $orderStateId, ParameterType::INTEGER)
                ->bind(':notify', $notifyInt, ParameterType::INTEGER)
                ->bind(':comment', $comment)
                ->bind(':createdOn', $now)
                ->bind(':createdBy', $createdBy, ParameterType::INTEGER)
                ->bind(':params', $paramsJson);

            $db->setQuery($query);

            return $db->execute();
        } catch (\Exception $e) {
            return false;
        }
    }

    /** Admin-only "status left unchanged" line, once per order and $context; deduped on the params marker, not the translated text, so another site language cannot add a second. */
    public static function noteStatusUnchanged(int $orderPk, string $context = ''): void
    {
        if ($orderPk <= 0) {
            return;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->getQuery(true)
                ->select($db->quoteName('order_id'))
                ->from($db->quoteName('#__j2commerce_orders'))
                ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
                ->bind(':pk', $orderPk, ParameterType::INTEGER);

            $orderNumber = (string) $db->setQuery($query)->loadResult();

            if ($orderNumber === '') {
                return;
            }

            $pattern = '%"status_unchanged":1%';
            $query   = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__j2commerce_orderhistories'))
                ->where($db->quoteName('order_id') . ' = :orderNumber')
                ->where($db->quoteName('params') . ' LIKE :pattern')
                ->bind(':orderNumber', $orderNumber)
                ->bind(':pattern', $pattern);

            foreach ($db->setQuery($query)->loadColumn() as $json) {
                $marker = json_decode((string) $json, true);

                if (\is_array($marker) && ($marker['context'] ?? '') === $context) {
                    return;
                }
            }

            // A site-side webhook has not loaded the admin file, and Text::_() would store the raw key.
            Factory::getApplication()->getLanguage()->load('com_j2commerce', JPATH_ADMINISTRATOR);

            self::add(
                orderId: $orderNumber,
                comment: Text::_('COM_J2COMMERCE_ORDER_STATUS_UNCHANGED_NOTE'),
                params: ['type' => 'admin_note', 'status_unchanged' => 1, 'context' => $context],
            );
        } catch (\Throwable $e) {
            Log::add('Status-unchanged note failed for order ' . $orderPk . ': ' . $e->getMessage(), Log::WARNING, 'com_j2commerce');
        }
    }

    private static function getCurrentStateId(string $orderId): int
    {
        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('order_state_id'))
                ->from($db->quoteName('#__j2commerce_orders'))
                ->where($db->quoteName('order_id') . ' = :orderId')
                ->bind(':orderId', $orderId);

            $db->setQuery($query);

            return (int) ($db->loadResult() ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
