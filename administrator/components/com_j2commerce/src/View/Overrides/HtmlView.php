<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Overrides;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Builder\Service\BlockPreviewService;
use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\Service\OverrideRegistry;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;

class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    protected array $subtemplates = [];
    protected array $overrideFiles = [];
    protected ?Form $editorForm = null;
    protected ?\stdClass $source = null;
    protected string $activeTemplate = '';
    protected string $templateOverridePath = '';
    protected string $activeTab = 'overrides';
    protected string $navbar = '';
    protected array $builderPreviewProducts = [];
    protected array $builderSubLayoutFiles = [];

    public function display($tpl = null): void
    {
        // Template overrides can write arbitrary PHP — restrict to super users
        if (!Factory::getApplication()->getIdentity()->authorise('core.admin')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();
        $this->navbar = $this->getNavbar();

        $app = Factory::getApplication();
        $this->activeTab = $app->getInput()->get('tab', 'overrides', 'cmd');

        /** @var \J2Commerce\Component\J2commerce\Administrator\Model\OverridesModel $model */
        $model = $this->getModel();

        $this->subtemplates = $model->getSubtemplates();
        $this->activeTemplate = $model->getActiveTemplate();
        $this->templateOverridePath = $model->getBaseTemplateOverridePath();
        $this->overrideFiles = $model->getOverrideFiles();
        $this->editorForm = $model->getEditorForm();

        $plugin = $app->getInput()->get('plugin', '', 'cmd');
        $file = $app->getInput()->get('file', '', 'base64');

        if (!empty($plugin) && !empty($file)) {
            $this->source = $model->getSource($plugin, $file);
        } elseif (!empty($file)) {
            $this->source = $model->getSourceByPath($file);
        }

        if ($this->source && $this->editorForm) {
            $this->editorForm->setValue('source', null, $this->source->source);
            $this->editorForm->setFieldAttribute('source', 'syntax', 'php');
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $previewService = new BlockPreviewService($db);
        $this->builderPreviewProducts = $previewService->getPreviewProducts();
        $this->builderSubLayoutFiles = $this->getBuilderSubLayoutFiles();

        $doc = $this->getDocument();
        $wa  = $doc->getWebAssetManager();

        $wa->registerAndUseStyle('com_j2commerce.grapes.core', 'media/com_j2commerce/css/administrator/grapes.min.css');
        $wa->registerAndUseStyle('com_j2commerce.builder', 'media/com_j2commerce/css/administrator/builder.css');
        $wa->registerAndUseStyle('com_j2commerce.grapesjs', 'media/com_j2commerce/css/administrator/grapesjs-j2commerce.css');
        $wa->registerAndUseScript('com_j2commerce.grapes.core', 'media/com_j2commerce/js/administrator/grapes.min.js', [], ['defer' => true]);
        $wa->registerAndUseScript('com_j2commerce.builder', 'media/com_j2commerce/js/administrator/builder-phppagebuilder.js', [], ['defer' => true]);

        $doc->addScriptOptions('com_j2commerce.builder', [
            'canvasStyles' => [
                'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
                Uri::root(true) . '/media/com_j2commerce/css/site/j2commerce.css',
                Uri::root(true) . '/media/com_j2commerce/css/administrator/builder-canvas.css',
            ],
            'subLayoutFiles' => $this->builderSubLayoutFiles,
        ]);

        $this->addToolbar();

        parent::display($tpl);
    }

    private function getBuilderSubLayoutFiles(): array
    {
        $subtemplates = OverrideRegistry::getInstalledSubtemplates();
        $files = [];

        foreach ($subtemplates as $subtemplate) {
            if (!$subtemplate['enabled']) {
                continue;
            }

            $element = $subtemplate['element'];
            $subtemplateLabel = ucwords(str_replace(['app_', '_'], ['', ' '], $element));
            $layoutFiles = OverrideRegistry::getLayoutFiles($element, $this->templateOverridePath);

            foreach ($layoutFiles as $file) {
                if (!($file['hasOverride'] ?? false)) {
                    continue;
                }

                $sourcePath = OverrideRegistry::getSourcePath($element, $file['relativePath']);
                $fileType = OverrideRegistry::classifyLayoutFile($sourcePath);

                // Only show block-layout files — dispatchers and other files can't be edited visually
                if ($fileType !== OverrideRegistry::FILE_TYPE_BLOCK_LAYOUT) {
                    continue;
                }

                $files[] = [
                    'value'    => $element . '::' . $file['relativePath'],
                    'label'    => '[' . $subtemplateLabel . '] ' . $file['displayName'] . ' (' . $file['relativePath'] . ')',
                    'fileType' => $fileType,
                ];
            }
        }

        return $files;
    }

    protected function getNavbar(): string
    {
        return LayoutHelper::render('navbar.default', [
            'items'  => MenuHelper::getMenuItems(),
            'active' => MenuHelper::getActiveView(),
        ], JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    }

    protected function addToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_J2COMMERCE_TEMPLATE_OVERRIDES'), 'fa-solid fa-layer-group');

        if ($this->source) {
            $toolbar->apply('overrides.apply');
            $toolbar->save('overrides.save');
            $toolbar->cancel('overrides.cancel', 'JTOOLBAR_CLOSE');
        } else {
            $toolbar->back('JTOOLBAR_BACK', 'index.php?option=com_j2commerce&view=dashboard');
        }

        ToolbarHelper::help('Overrides', true, 'https://docs.j2commerce.com/template-overrides');
    }
}
