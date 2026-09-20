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

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

/**
 * Currencies field - provides a dropdown of enabled currencies from the database.
 *
 * @since  6.0.7
 */
class CurrenciesField extends ListField
{
    protected $type = 'Currencies';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('currency_code', 'value'),
                    'CONCAT(' . $db->quoteName('currency_title') . ', \' (\', ' . $db->quoteName('currency_code') . ', \')\') AS ' . $db->quoteName('text'),
                ])
                ->from($db->quoteName('#__j2commerce_currencies'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->order($db->quoteName('currency_title') . ' ASC');

            $db->setQuery($query);
            $currencies = $db->loadObjectList();

            if ($currencies) {
                foreach ($currencies as $currency) {
                    $options[] = HTMLHelper::_('select.option', $currency->value, $currency->text);
                }
            }
        } catch (\Exception $e) {
            Log::add('Failed to load currencies: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_LOADING_CURRENCIES', Text::_('JERROR_AN_ERROR_HAS_OCCURRED')),
                'error'
            );
        }

        return $options;
    }
}
