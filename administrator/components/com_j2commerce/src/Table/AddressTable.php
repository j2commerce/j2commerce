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

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\PhoneHelper;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class AddressTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__j2commerce_addresses', 'j2commerce_address_id', $db);
    }

    public function check(): bool
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }

        // Set empty-string defaults for NOT NULL varchar columns without DB defaults
        $defaults = [
            'first_name', 'last_name', 'email', 'address_1', 'address_2',
            'city', 'zip', 'zone_id', 'country_id', 'phone_1', 'phone_2',
            'fax', 'type', 'company', 'tax_number',
        ];

        foreach ($defaults as $field) {
            if (!isset($this->$field)) {
                $this->$field = '';
            }
        }

        // Strip common separators (space, dash, paren, dot) from phone fields
        // so admin and frontend agree on storage format. The frontend widget
        // expects digits-only (optionally prefixed with +); admin saves from a
        // plain <field type="tel"> with no filter. Normalizing here keeps
        // editing round-trips safe on both sides.
        foreach (['phone_1', 'phone_2', 'fax'] as $phoneField) {
            if (!empty($this->$phoneField)) {
                $this->$phoneField = PhoneHelper::normalize((string) $this->$phoneField);
            }
        }

        if (empty($this->type)) {
            $this->type = 'billing';
        }

        // user_id is int NOT NULL with no DB default
        if (empty($this->user_id)) {
            $this->user_id = 0;
        }

        return true;
    }
}
