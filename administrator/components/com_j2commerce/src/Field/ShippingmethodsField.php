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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

/**
 * ShippingMethods field - provides a dropdown of enabled shipping plugins.
 *
 * Loads each plugin's language file so that the plugin name constant
 * resolves to the translated display name. Also includes shipping method
 * names from #__j2commerce_shippingmethods for plugins with sub-methods.
 *
 * @since  6.0.0
 */
class ShippingmethodsField extends ListField
{
    protected $type = 'Shippingmethods';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $pluginType   = 'plugin';
            $pluginFolder = 'j2commerce';
            $elementLike  = 'shipping_%';

            // Consumers name the methods their own form must not offer, e.g. exclude="shipping_free"
            $exclude = array_filter(
                array_map('trim', explode(',', (string) $this->element['exclude'])),
                static fn (string $value): bool => $value !== ''
            );

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
            $plugins = $db->loadObjectList();

            // Plugins that use the shippingmethods table for sub-methods
            $pluginsWithSubMethods = [
                'shipping_standard',
                'shipping_postcode',
                'shipping_additional',
                'shipping_incremental',
                'shipping_flatrate_advanced',
            ];

            if ($plugins) {
                $lang               = Factory::getApplication()->getLanguage();
                $hasSubMethodPlugin = false;
                $subMethodsLoaded   = false;

                foreach ($plugins as $plugin) {
                    if (\in_array($plugin->value, $pluginsWithSubMethods, true)) {
                        $hasSubMethodPlugin = true;
                        break;
                    }
                }

                // Load sub-methods once if any sub-method plugin is present
                $subMethods = [];

                if ($hasSubMethodPlugin && !$subMethodsLoaded) {
                    $subQuery = $db->getQuery(true)
                        ->select('DISTINCT ' . $db->quoteName('shipping_method_name'))
                        ->from($db->quoteName('#__j2commerce_shippingmethods'))
                        ->where($db->quoteName('published') . ' = 1');

                    $db->setQuery($subQuery);
                    $subMethods       = $db->loadColumn() ?: [];
                    $subMethodsLoaded = true;
                }

                foreach ($plugins as $plugin) {
                    if (\in_array($plugin->value, $exclude, true)) {
                        continue;
                    }

                    // Load the plugin's language file so its name constant resolves
                    $lang->load('plg_j2commerce_' . $plugin->value, JPATH_PLUGINS . '/j2commerce/' . $plugin->value);

                    if (\in_array($plugin->value, $pluginsWithSubMethods, true)) {
                        // Add sub-methods (loaded once above)
                        foreach ($subMethods as $methodName) {
                            if (\in_array($methodName, $exclude, true)) {
                                continue;
                            }

                            $options[] = HTMLHelper::_('select.option', $methodName, J2htmlHelper::translateKey($methodName));
                        }
                    } else {
                        $options[] = HTMLHelper::_('select.option', $plugin->value, Text::_($plugin->text));
                    }
                }
            }
        } catch (\Exception $e) {
            Log::add('Failed to load shipping methods: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_LOADING_SHIPPING_METHODS', Text::_('JERROR_AN_ERROR_HAS_OCCURRED')),
                'error'
            );
        }

        return $options;
    }
}
