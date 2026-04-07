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
 * StoreProfile field - provides a dropdown of enabled store profiles from the database.
 *
 * @since  6.0.7
 */
class StoreProfileField extends ListField
{
    protected $type = 'StoreProfile';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('j2commerce_storeprofile_id', 'value'),
                    $db->quoteName('store_name', 'text'),
                ])
                ->from($db->quoteName('#__j2commerce_storeprofiles'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->order($db->quoteName('store_name') . ' ASC');

            $db->setQuery($query);
            $profiles = $db->loadObjectList();

            if ($profiles) {
                foreach ($profiles as $profile) {
                    $options[] = HTMLHelper::_('select.option', $profile->value, $profile->text);
                }
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_LOADING_STORE_PROFILES', $e->getMessage()),
                'error'
            );
        }

        return $options;
    }
}
