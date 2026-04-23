<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\View\Plugins;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;

class HtmlView extends BaseHtmlView
{
    public array $plugins = [];

    public function display($tpl = null): void
    {
        $model = $this->getModel();

        $this->plugins = $model->getMergedAdapterList();

        $this->setToolbar();
        $this->loadAssets();

        parent::display($tpl);
    }

    private function setToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->title(Text::_('COM_J2COMMERCEMIGRATOR_VIEW_PLUGINS_TITLE'), 'fa-solid fa-puzzle-piece');
        $toolbar->link(
            Text::_('COM_J2COMMERCEMIGRATOR_TOOLBAR_DASHBOARD'),
            'index.php?option=com_j2commercemigrator',
            'fa-solid fa-house'
        );
    }

    private function loadAssets(): void
    {
        Text::script('COM_J2COMMERCEMIGRATOR_PLUGINS_CONFIGURE');
        Text::script('COM_J2COMMERCEMIGRATOR_PLUGINS_DISABLE');
        Text::script('COM_J2COMMERCEMIGRATOR_PLUGINS_ENABLE');
        Text::script('COM_J2COMMERCEMIGRATOR_PLUGINS_NONE_INSTALLED');
        Text::script('COM_J2COMMERCEMIGRATOR_PLUGINS_STATUS_DISABLED');
        Text::script('COM_J2COMMERCEMIGRATOR_PLUGINS_STATUS_ENABLED');

        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_j2commercemigrator.migrator',
            'media/com_j2commercemigrator/css/administrator/migrator.css'
        );
        $wa->registerAndUseScript(
            'com_j2commercemigrator.migrator',
            'media/com_j2commercemigrator/js/administrator/migrator.js',
            [],
            ['defer' => true]
        );
        $wa->registerAndUseScript(
            'com_j2commercemigrator.migrator-plugins',
            'media/com_j2commercemigrator/js/administrator/migrator-plugins.js',
            [],
            ['defer' => true]
        );

        $this->getDocument()->addScriptOptions('com_j2commercemigrator.config', [
            'token'  => Session::getFormToken(),
            'apiUrl' => 'index.php?option=com_j2commercemigrator&task=api.run',
        ]);
    }
}
