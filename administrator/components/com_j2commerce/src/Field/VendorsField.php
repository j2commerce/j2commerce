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
 * Vendors field - provides a dropdown of enabled vendors from the database.
 *
 * @since  6.0.7
 */
class VendorsField extends ListField
{
    protected $type = 'Vendors';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('v.j2commerce_vendor_id', 'value'),
                    $db->quoteName('a.company', 'text'),
                ])
                ->from($db->quoteName('#__j2commerce_vendors', 'v'))
                ->join('INNER', $db->quoteName('#__j2commerce_addresses', 'a') . ' ON ' . $db->quoteName('v.address_id') . ' = ' . $db->quoteName('a.j2commerce_address_id'))
                ->where($db->quoteName('v.enabled') . ' = 1')
                ->order($db->quoteName('a.company') . ' ASC');

            $db->setQuery($query);
            $vendors = $db->loadObjectList();

            if ($vendors) {
                foreach ($vendors as $vendor) {
                    $text = trim((string) $vendor->text);
                    if (empty($text)) {
                        $text = Text::_('COM_J2COMMERCE_VENDOR_UNNAMED');
                    }
                    $options[] = HTMLHelper::_('select.option', $vendor->value, $text);
                }
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_LOADING_VENDORS', $e->getMessage()),
                'error'
            );
        }

        return $options;
    }
}
