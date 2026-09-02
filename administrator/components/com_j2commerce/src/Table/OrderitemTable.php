<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Table;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

class OrderitemTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_j2commerce.orderitem';
        parent::__construct('#__j2commerce_orderitems', 'j2commerce_orderitem_id', $db);
    }

    public function check(): bool
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }

        if (empty($this->order_id)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', 'Order ID'));
            return false;
        }

        return true;
    }

    /** Children before the parent, so a mid-cascade failure leaves a re-deletable order item. */
    public function delete($pk = null)
    {
        $orderitemId = (int) ($pk ?? $this->{$this->getKeyName()} ?? 0);

        if ($orderitemId > 0) {
            $db = $this->getDbo();

            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_orderitemattributes'))
                    ->where($db->quoteName('orderitem_id') . ' = :orderitemId')
                    ->bind(':orderitemId', $orderitemId, ParameterType::INTEGER)
            )->execute();
        }

        return parent::delete($pk);
    }
}
