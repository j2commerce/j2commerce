<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\View\Templates;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;

class HtmlView extends BaseHtmlView
{
    public array $templates          = [];
    public array $templateOverrides  = [];

    public function display($tpl = null): void
    {
        $model = $this->getModel();

        $this->templates         = $model->discover();
        $this->templateOverrides = $model->discoverTemplateOverrides();

        $this->setToolbar();
        $this->loadAssets();

        parent::display($tpl);
    }

    private function setToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->title(Text::_('COM_J2COMMERCEMIGRATOR_VIEW_TEMPLATES_TITLE'), 'fa-solid fa-file-code');
        $toolbar->link(
            Text::_('COM_J2COMMERCEMIGRATOR_TOOLBAR_DASHBOARD'),
            'index.php?option=com_j2commercemigrator',
            'fa-solid fa-house'
        );
    }

    private function loadAssets(): void
    {
        Text::script('COM_J2COMMERCEMIGRATOR_TEMPLATES_CHANGES');
        Text::script('COM_J2COMMERCEMIGRATOR_TEMPLATES_COMPLETE');
        Text::script('COM_J2COMMERCEMIGRATOR_TEMPLATES_MIGRATE_ONE');
        Text::script('COM_J2COMMERCEMIGRATOR_TEMPLATES_NONE_FOUND');
        Text::script('COM_J2COMMERCEMIGRATOR_TEMPLATES_PREVIEW');
        Text::script('COM_J2COMMERCEMIGRATOR_TEMPLATES_RESOLUTION');
        Text::script('COM_J2COMMERCEMIGRATOR_TEMPLATES_RES_AUTO');
        Text::script('COM_J2COMMERCEMIGRATOR_TEMPLATES_RES_MANUAL');
        Text::script('COM_J2COMMERCEMIGRATOR_TEMPLATES_RES_SKIP');

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
            'com_j2commercemigrator.migrator-templates',
            'media/com_j2commercemigrator/js/administrator/migrator-templates.js',
            [],
            ['defer' => true]
        );

        $this->getDocument()->addScriptOptions('com_j2commercemigrator.config', [
            'token'  => Session::getFormToken(),
            'apiUrl' => 'index.php?option=com_j2commercemigrator&task=api.run',
        ]);
    }
}
