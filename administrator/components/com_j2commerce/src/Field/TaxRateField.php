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
use Joomla\Database\DatabaseInterface;

/**
 * TaxRate field - provides a dropdown of enabled tax rates from the database.
 *
 * @since  6.0.7
 */
class TaxRateField extends ListField
{
    protected $type = 'TaxRate';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('j2commerce_taxrate_id', 'value'),
                    $db->quoteName('taxrate_name', 'text'),
                ])
                ->from($db->quoteName('#__j2commerce_taxrates'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->order($db->quoteName('taxrate_name') . ' ASC');

            $db->setQuery($query);
            $rates = $db->loadObjectList();

            if ($rates) {
                foreach ($rates as $rate) {
                    $options[] = HTMLHelper::_('select.option', $rate->value, $rate->text);
                }
            }
        } catch (\Exception $e) {
            // Silently fail - parent options will still be available
        }

        return $options;
    }
}
