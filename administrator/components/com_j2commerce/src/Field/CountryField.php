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
use Joomla\Database\DatabaseInterface;

/**
 * Country field - provides a dropdown of enabled countries from the database.
 *
 * @since  6.0.7
 */
class CountryField extends ListField
{
    protected $type = 'Country';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('j2commerce_country_id', 'value'),
                    $db->quoteName('country_name', 'text'),
                ])
                ->from($db->quoteName('#__j2commerce_countries'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->order($db->quoteName('country_name') . ' ASC');

            $db->setQuery($query);
            $countries = $db->loadObjectList();

            if ($countries) {
                foreach ($countries as $country) {
                    $options[] = HTMLHelper::_('select.option', $country->value, $country->text);
                }
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_LOADING_COUNTRIES', $e->getMessage()),
                'error'
            );
        }

        return $options;
    }
}
