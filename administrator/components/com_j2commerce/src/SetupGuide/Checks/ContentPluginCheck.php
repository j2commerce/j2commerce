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

class ContentPluginCheck extends AbstractSetupCheck
{
    public function getId(): string
    {
        return 'content_plugin';
    }

    public function getGroup(): string
    {
        return 'system_requirements';
    }

    public function getGroupOrder(): int
    {
        return 200;
    }

    public function isDismissible(): bool
    {
        return false;
    }

    public function getLabel(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CONTENT_PLUGIN');
    }

    public function getDescription(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CONTENT_PLUGIN_DESC');
    }

    public function check(): SetupCheckResult
    {
        $db      = $this->getDatabase();
        $folder  = 'content';
        $element = 'j2commerce';
        $query   = $db->getQuery(true)
            ->select($db->quoteName('enabled'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = :folder')
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':folder', $folder)
            ->bind(':element', $element);
        $enabled = (int) $db->setQuery($query)->loadResult();

        return $enabled === 1
            ? new SetupCheckResult('pass', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CONTENT_PLUGIN_PASS'))
            : new SetupCheckResult('fail', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CONTENT_PLUGIN_FAIL'));
    }

    public function getDetailView(): string
    {
        $pluginsUrl = 'index.php?option=com_plugins&filter[folder]=content&filter[search]=j2commerce';

        return '<h5>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CONTENT_PLUGIN') . '</h5>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CONTENT_PLUGIN_DESC') . '</p>'
            . '<a href="' . $pluginsUrl . '" class="btn btn-primary w-100">'
            . Text::_('COM_J2COMMERCE_SETUP_GUIDE_ACTION_MANAGE_PLUGINS')
            . '</a>';
    }

    public function getActions(): array
    {
        return [
            [
                'action' => 'enable_plugin',
                'label'  => 'COM_J2COMMERCE_SETUP_GUIDE_ACTION_ENABLE',
                'params' => ['folder' => 'content', 'element' => 'j2commerce'],
            ],
        ];
    }
}
