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
 * PaymentMethods field - provides a dropdown of enabled payment plugins.
 *
 * Loads each plugin's language file so that the plugin name constant
 * (e.g. PLG_J2COMMERCE_PAYMENT_CASH) resolves to the translated display name
 * without requiring those strings in the component's language file.
 *
 * @since  6.0.7
 */
class PaymentMethodsField extends ListField
{
    protected $type = 'PaymentMethods';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $pluginType   = 'plugin';
            $pluginFolder = 'j2commerce';
            $elementLike  = '%payment_%';

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('element', 'value'),
                    $db->quoteName('name', 'text'),
                ])
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = :type')
                ->where($db->quoteName('folder') . ' = :folder')
                ->where($db->quoteName('element') . ' LIKE :element')
                ->where($db->quoteName('enabled') . ' = 1')
                ->bind(':type', $pluginType)
                ->bind(':folder', $pluginFolder)
                ->bind(':element', $elementLike)
                ->order($db->quoteName('name') . ' ASC');

            $db->setQuery($query);
            $methods = $db->loadObjectList();

            if ($methods) {
                $lang = Factory::getApplication()->getLanguage();

                foreach ($methods as $method) {
                    // Load the plugin's language file so its name constant resolves
                    $lang->load('plg_j2commerce_' . $method->value, JPATH_PLUGINS . '/j2commerce/' . $method->value);

                    $options[] = HTMLHelper::_('select.option', $method->value, Text::_($method->text));
                }
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_LOADING_PAYMENT_METHODS', $e->getMessage()),
                'error'
            );
        }

        return $options;
    }
}
