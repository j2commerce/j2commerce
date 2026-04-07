<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\GenericEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;

class InvoicetypeField extends ListField
{
    protected $type = 'Invoicetype';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        $builtIn = [
            'invoice'     => 'COM_J2COMMERCE_INVOICETEMPLATE_TYPE_INVOICE',
            'receipt'     => 'COM_J2COMMERCE_INVOICETEMPLATE_TYPE_RECEIPT',
            'packingslip' => 'COM_J2COMMERCE_INVOICETEMPLATE_TYPE_PACKINGSLIP',
        ];

        foreach ($builtIn as $value => $label) {
            $options[] = HTMLHelper::_('select.option', $value, Text::_($label));
        }

        // Allow plugins to add custom types
        PluginHelper::importPlugin('j2commerce');
        $types = [];
        $event = new GenericEvent('onJ2CommerceGetInvoiceTypes', ['types' => &$types]);
        Factory::getApplication()->getDispatcher()->dispatch('onJ2CommerceGetInvoiceTypes', $event);

        foreach ($types as $value => $label) {
            $options[] = HTMLHelper::_('select.option', $value, Text::_($label));
        }

        return $options;
    }
}
