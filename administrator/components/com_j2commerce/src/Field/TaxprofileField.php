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
 * TaxProfile field - provides a dropdown of enabled tax profiles from the database.
 *
 * @since  6.0.7
 */
class TaxprofileField extends ListField
{
    protected $type = 'Taxprofile';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        // Empty value = no tax profile (not taxable). Listed first so a freshly
        // saved form defaults to "Not Taxable" instead of auto-charging the first
        // tax profile in the list.
        array_unshift($options, HTMLHelper::_('select.option', '', Text::_('COM_J2COMMERCE_NOT_TAXABLE')));

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('j2commerce_taxprofile_id', 'value'),
                    $db->quoteName('taxprofile_name', 'text'),
                ])
                ->from($db->quoteName('#__j2commerce_taxprofiles'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->order($db->quoteName('taxprofile_name') . ' ASC');

            $db->setQuery($query);
            $profiles = $db->loadObjectList();

            if ($profiles) {
                foreach ($profiles as $profile) {
                    $options[] = HTMLHelper::_('select.option', $profile->value, $profile->text);
                }
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_LOADING_TAX_PROFILES', $e->getMessage()),
                'error'
            );
        }

        return $options;
    }
}
