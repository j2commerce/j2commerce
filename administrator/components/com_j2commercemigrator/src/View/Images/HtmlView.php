<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\View\Images;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;

class HtmlView extends BaseHtmlView
{
    public array  $imageDirectories = [];
    public array  $savedFolder      = [];
    public array  $imageSettings    = [];

    public function display($tpl = null): void
    {
        $model = $this->getModel();

        $this->imageDirectories = $model->listImageDirectories();
        $this->savedFolder      = $model->getSavedImageFolder();
        $this->imageSettings    = $model->getImageSettings();

        $this->setToolbar();
        $this->loadAssets();

        parent::display($tpl);
    }

    private function setToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->title(Text::_('COM_J2COMMERCEMIGRATOR_VIEW_IMAGES_TITLE'), 'fa-solid fa-images');
        $toolbar->link(
            Text::_('COM_J2COMMERCEMIGRATOR_TOOLBAR_DASHBOARD'),
            'index.php?option=com_j2commercemigrator',
            'fa-solid fa-house'
        );
    }

    private function loadAssets(): void
    {
        Text::script('COM_J2COMMERCEMIGRATOR_IMAGES_COMPLETE');
        Text::script('COM_J2COMMERCEMIGRATOR_IMAGES_DISCOVERY_TITLE');
        Text::script('COM_J2COMMERCEMIGRATOR_IMAGES_ESTIMATED');
        Text::script('COM_J2COMMERCEMIGRATOR_IMAGES_NONE_FOUND');
        Text::script('COM_J2COMMERCEMIGRATOR_IMAGES_SOURCE_ROOT');
        Text::script('COM_J2COMMERCEMIGRATOR_IMAGES_SUBDIRS');

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
            'com_j2commercemigrator.migrator-images',
            'media/com_j2commercemigrator/js/administrator/migrator-images.js',
            [],
            ['defer' => true]
        );

        $this->getDocument()->addScriptOptions('com_j2commercemigrator.config', [
            'token'        => Session::getFormToken(),
            'apiUrl'       => 'index.php?option=com_j2commercemigrator&task=api.run',
            'imageSettings' => $this->imageSettings,
        ]);
    }
}
