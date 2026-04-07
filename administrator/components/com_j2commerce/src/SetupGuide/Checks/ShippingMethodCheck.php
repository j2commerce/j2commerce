<?php

declare(strict_types=1);

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks;

use J2Commerce\Component\J2commerce\Administrator\SetupGuide\AbstractSetupCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\SetupCheckResult;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

class ShippingMethodCheck extends AbstractSetupCheck
{
    public function getId(): string
    {
        return 'shipping_method';
    }

    public function getGroup(): string
    {
        return 'payments_shipping';
    }

    public function getGroupOrder(): int
    {
        return 500;
    }

    public function isDismissible(): bool
    {
        return false;
    }

    public function getLabel(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_SHIPPING_METHOD');
    }

    public function getDescription(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_SHIPPING_METHOD_DESC');
    }

    public function check(): SetupCheckResult
    {
        $db     = $this->getDatabase();
        $folder = 'j2commerce';
        $like   = 'shipping_%';
        $type   = 'plugin';

        // Check for enabled shipping plugins in #__extensions
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('folder') . ' = :folder')
            ->where($db->quoteName('element') . ' LIKE :element')
            ->where($db->quoteName('enabled') . ' = 1')
            ->bind(':type', $type)
            ->bind(':folder', $folder)
            ->bind(':element', $like);

        $pluginCount = (int) $db->setQuery($query)->loadResult();

        return $pluginCount > 0
            ? new SetupCheckResult('pass', Text::sprintf('COM_J2COMMERCE_SETUP_GUIDE_CHECK_SHIPPING_METHOD_PASS', $pluginCount))
            : new SetupCheckResult('fail', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_SHIPPING_METHOD_FAIL'));
    }

    public function getDetailView(): string
    {
        $db     = $this->getDatabase();
        $folder = 'j2commerce';
        $like   = 'shipping_%';
        $type   = 'plugin';

        // Get enabled shipping plugins
        $query = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('element'), $db->quoteName('name')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('folder') . ' = :folder')
            ->where($db->quoteName('element') . ' LIKE :element')
            ->where($db->quoteName('enabled') . ' = 1')
            ->bind(':type', $type)
            ->bind(':folder', $folder)
            ->bind(':element', $like)
            ->order($db->quoteName('name') . ' ASC');

        $plugins = $db->setQuery($query)->loadObjectList();

        $shippingUrl = 'index.php?option=com_j2commerce&view=shippingmethods';

        $html = '<h5>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_SHIPPING_METHOD') . '</h5>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_SHIPPING_METHOD_DESC') . '</p>';

        if ($plugins) {
            $html .= '<p class="text-muted small mb-1">' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_ENABLED_SHIPPING_METHODS') . '</p>'
                . '<ul class="list-unstyled mb-3">';

            $lang = \Joomla\CMS\Factory::getApplication()->getLanguage();

            foreach ($plugins as $plugin) {
                $ext = 'plg_j2commerce_' . $plugin->element;
                $lang->load($ext . '.sys', JPATH_ADMINISTRATOR)
                    || $lang->load($ext . '.sys', JPATH_PLUGINS . '/j2commerce/' . $plugin->element);
                $title = Text::_($plugin->name);

                $editUrl = 'index.php?option=com_plugins&task=plugin.edit&extension_id=' . (int) $plugin->extension_id;

                $html .= '<li class="py-1 small">'
                    . '<i class="fa-regular fa-circle-check text-success me-1" aria-hidden="true"></i>'
                    . '<a href="' . $editUrl . '">' . htmlspecialchars($title) . '</a>'
                    . '</li>';
            }

            $html .= '</ul>';
        }

        $html .= '<a href="' . $shippingUrl . '" class="btn btn-primary w-100">'
            . Text::_('COM_J2COMMERCE_SETUP_GUIDE_ACTION_MANAGE_SHIPPING')
            . '</a>';

        return $html;
    }
}
