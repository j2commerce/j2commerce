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

class OrderinfoTable extends Table
{
    /**
     * Transient: the caller's answer to "does this order need a destination?".
     * Not a column, so insertObject() drops it — check() reads it because it has no
     * access to the parent order row and must stay free of I/O.
     *
     * Set it before bind(), never from bound data: bind() walks getProperties(), which
     * includes this one, so a caller handing bind() a request array would let the
     * request turn the invariant off.
     */
    public bool $requiresShipping = false;

    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_j2commerce.orderinfo';
        parent::__construct('#__j2commerce_orderinfos', 'j2commerce_orderinfo_id', $db);
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

        // NOT NULL without a DB default — the row cannot reach the table without these.
        $this->from_order_id  ??= '0';
        $this->shipping_zip   ??= '';
        $this->shipping_id    ??= '';

        // One empty-marker across every writer, so the columns stay queryable.
        foreach (['all_billing', 'all_shipping', 'all_payment'] as $jsonField) {
            $value = $this->$jsonField ?? '';

            if ($value === '' || $value === '[]' || $value === 'null') {
                $this->$jsonField = '{}';
            }
        }

        if (!$this->requiresShipping) {
            return true;
        }

        // A shippable order that reached here without a destination resolved one of its
        // addresses to nothing — persisting the blanks would ship it nowhere.
        $required = [
            'shipping_address_1' => 'COM_J2COMMERCE_FIELD_ADDRESS_1',
            'shipping_city'      => 'COM_J2COMMERCE_FIELD_CITY',
        ];

        foreach ($required as $field => $label) {
            if (trim((string) ($this->$field ?? '')) === '') {
                $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_($label)));

                return false;
            }
        }

        if ((int) ($this->shipping_country_id ?? 0) <= 0) {
            $this->setError(
                Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_COUNTRY'))
            );

            return false;
        }

        if ($this->all_shipping === '{}') {
            $this->setError(
                Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_SHIPPING_ADDRESS'))
            );

            return false;
        }

        return true;
    }
}
