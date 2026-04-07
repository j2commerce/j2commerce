<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Builder\Service\BlockPreviewService;
use J2Commerce\Component\J2commerce\Administrator\Builder\Service\BlockRenderService;
use J2Commerce\Component\J2commerce\Administrator\Builder\Service\SubLayoutRendererService;
use J2Commerce\Component\J2commerce\Administrator\Service\OverrideRegistry;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

final class BuilderController extends BaseController
{
    public function renderSubLayout(): void
    {
        $this->validateRequest();

        $input         = $this->app->getInput();
        $pluginElement = $input->getString('plugin_element', '');
        $fileId        = $input->getString('file_id', '');
        $productId     = $input->getInt('product_id', 0);

        if (empty($pluginElement) || empty($fileId)) {
            $this->sendJson(null, 'Missing required fields: plugin_element, file_id', true);
            return;
        }

        // Resolve the source path (used for classification)
        $sourcePath = OverrideRegistry::getSourcePath($pluginElement, $fileId);

        // Confirm this is a sub-layout file, not a composition file or dispatcher
        if (!OverrideRegistry::isSubLayoutFile($fileId)) {
            $this->sendJson(null, 'This file is not a sub-layout. Use composition mode for dispatcher or block-layout files.', true);
            return;
        }

        // Prefer override over source if it exists
        $overridePath = $this->resolveOverridePath($pluginElement, $fileId);
        $filePath     = ($overridePath !== null && is_file($overridePath)) ? $overridePath : $sourcePath;

        if (!is_file($filePath)) {
            $this->sendJson(null, 'Sub-layout file not found: ' . $fileId, true);
            return;
        }

        // Determine sub-layout ID (e.g. "item_title.php" → "item-title")
        $subLayoutId = str_replace(['item_', '.php'], ['item-', ''], basename($fileId));

        // Find the companion view-edit.php template
        $companionPath = JPATH_ADMINISTRATOR . '/components/com_j2commerce/src/Builder/sublayouts/'
            . $subLayoutId . '/view-edit.php';

        if (!is_file($companionPath)) {
            $this->sendJson(null, 'No edit-mode companion template found for: ' . $subLayoutId, true);
            return;
        }

        $db             = Factory::getContainer()->get('DatabaseDriver');
        $previewService = new BlockPreviewService($db);
        $displayData    = $previewService->getDisplayData($productId);

        // Execute the companion template in a sandboxed closure
        $html = $this->executeSubLayoutTemplate($companionPath, $displayData);

        $this->sendJson([
            'html'          => $html,
            'sub_layout_id' => $subLayoutId,
            'file_id'       => $fileId,
            'has_override'  => ($overridePath !== null && is_file($overridePath)),
        ]);
    }

    public function saveSubLayoutHtml(): void
    {
        $this->validateRequest();

        $body = json_decode((string) file_get_contents('php://input'), true);

        $pluginElement = $body['plugin_element'] ?? '';
        $fileId        = $body['file_id'] ?? '';
        $html          = $body['html'] ?? '';

        if (empty($pluginElement) || empty($fileId) || empty($html)) {
            $this->sendJson(null, 'Missing required fields: plugin_element, file_id, html', true);
            return;
        }

        // Only sub-layout files can be saved via this endpoint
        if (!OverrideRegistry::isSubLayoutFile($fileId)) {
            $this->sendJson(null, 'This file is not a sub-layout file.', true);
            return;
        }

        // Resolve or create the override path (copy-on-write)
        $overridePath = $this->resolveOrCreateOverridePath($pluginElement, $fileId);

        if ($overridePath === null) {
            $this->sendJson(null, 'Cannot resolve override path or file is not a valid override', true);
            return;
        }

        // Determine sub-layout ID for PHP regeneration
        $subLayoutId = str_replace(['item_', '.php'], ['item-', ''], basename($fileId));

        $renderer = new SubLayoutRendererService();
        $result   = $renderer->regenerateSubLayoutPhp($html, $subLayoutId);

        if (!$result['success']) {
            $this->sendJson(null, $result['error'], true);
            return;
        }

        if (file_put_contents($overridePath, $result['php']) === false) {
            $this->sendJson(null, 'Failed to write override file', true);
            return;
        }

        $this->sendJson(['saved' => true, 'hasOverride' => true, 'sub_layout_id' => $subLayoutId]);
    }

    public function renderBlock(): void
    {
        $this->validateRequest();

        $input     = $this->app->getInput();
        $slug      = $input->getString('slug', '');
        $productId = $input->getInt('product_id', 0);
        $settings  = $input->get('settings', [], 'array');
        $editMode  = $input->getBool('edit_mode', true);

        $db            = Factory::getContainer()->get('DatabaseDriver');
        $renderService = new BlockRenderService($db);

        $html = $renderService->renderBlock($slug, $settings, $productId, $editMode);

        $this->sendJson(['html' => $html]);
    }

    public function renderAllBlocks(): void
    {
        $this->validateRequest();

        $body      = json_decode((string) file_get_contents('php://input'), true);
        $productId = (int) ($body['product_id'] ?? 0);
        $editMode  = (bool) ($body['edit_mode'] ?? true);
        $blocks    = $body['blocks'] ?? [];

        if (empty($blocks)) {
            $this->sendJson(['blocks' => []]);
            return;
        }

        $db            = Factory::getContainer()->get('DatabaseDriver');
        $renderService = new BlockRenderService($db);

        $rendered = $renderService->renderAllBlocks($blocks, $productId, $editMode);

        $this->sendJson(['blocks' => $rendered]);
    }

    public function saveSubLayout(): void
    {
        $this->validateRequest();

        $body = json_decode((string) file_get_contents('php://input'), true);

        $pluginElement = $body['plugin_element'] ?? '';
        $fileId        = $body['file_id'] ?? '';
        $blockOrder    = $body['block_order'] ?? [];

        if (empty($pluginElement) || empty($fileId) || empty($blockOrder)) {
            $this->sendJson(null, 'Missing required fields: plugin_element, file_id, block_order', true);
            return;
        }

        // Validate block_order is an array of strings
        $blockOrder = array_values(array_filter(
            array_map('strval', $blockOrder),
            fn (string $slug): bool => (bool) preg_match('/^[a-z0-9-]+$/', $slug)
        ));

        if (empty($blockOrder)) {
            $this->sendJson(null, 'Invalid block order — no valid block slugs found', true);
            return;
        }

        // Guard: reject dispatcher files
        $sourcePath = OverrideRegistry::getSourcePath($pluginElement, $fileId);
        if (OverrideRegistry::classifyLayoutFile($sourcePath) === OverrideRegistry::FILE_TYPE_DISPATCHER) {
            $this->sendJson(null, 'This file is a product type dispatcher and cannot be saved from the visual builder. Edit the type-specific layout files instead.', true);
            return;
        }

        // Resolve the override file path, creating it from source if it doesn't exist yet (copy-on-write)
        $overridePath = $this->resolveOrCreateOverridePath($pluginElement, $fileId);

        if ($overridePath === null) {
            $this->sendJson(null, 'Cannot resolve override path or file is not a valid override', true);
            return;
        }

        // Generate composition PHP from the block order
        $renderer = new SubLayoutRendererService();
        $result   = $renderer->regenerateFromBlockOrder($blockOrder);

        if (!$result['success']) {
            $this->sendJson(null, $result['error'], true);
            return;
        }

        // Write to override file
        if (file_put_contents($overridePath, $result['php']) === false) {
            $this->sendJson(null, 'Failed to write override file', true);
            return;
        }

        $this->sendJson(['saved' => true, 'hasOverride' => true, 'block_order' => $blockOrder]);
    }

    public function loadProject(): void
    {
        $this->validateRequest();

        $input         = $this->app->getInput();
        $pluginElement = $input->getString('plugin_element', '');
        $fileId        = $input->getString('file_id', '');

        if (empty($pluginElement) || empty($fileId)) {
            $this->sendJson(null, 'Missing required fields: plugin_element, file_id', true);
            return;
        }

        // Resolve override path and read the current override content
        $overridePath    = $this->resolveOverridePath($pluginElement, $fileId);
        $overrideContent = '';

        if ($overridePath !== null && is_file($overridePath)) {
            $overrideContent = file_get_contents($overridePath) ?: '';
        }

        // Also read the original source file for reference
        $sourcePath    = OverrideRegistry::getSourcePath($pluginElement, $fileId);
        $sourceContent = is_file($sourcePath) ? (file_get_contents($sourcePath) ?: '') : '';

        // Classify the file — dispatchers cannot be edited visually
        $fileType = OverrideRegistry::classifyLayoutFile($sourcePath);

        if ($fileType === OverrideRegistry::FILE_TYPE_DISPATCHER) {
            $this->sendJson([
                'file_type' => $fileType,
                'message'   => 'This file is a product type dispatcher. Edit the type-specific layout files (e.g. item_simple.php) instead.',
            ]);
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $renderService   = new BlockRenderService($db);
        $availableBlocks = $renderService->getAvailableBlocks();

        // Parse the override (or source) file to determine block order
        $contentToParse = !empty($overrideContent) ? $overrideContent : $sourceContent;
        $blockOrder     = $renderService->parseLayoutBlockOrder($contentToParse);

        $previewService  = new BlockPreviewService($db);
        $previewProducts = $previewService->getPreviewProducts();

        $this->sendJson([
            'file_type'        => $fileType,
            'override_content' => $overrideContent,
            'source_content'   => $sourceContent,
            'available_blocks' => $availableBlocks,
            'block_order'      => $blockOrder,
            'preview_products' => $previewProducts,
        ]);
    }

    public function getPreviewProducts(): void
    {
        $this->validateRequest();

        $db             = Factory::getContainer()->get('DatabaseDriver');
        $previewService = new BlockPreviewService($db);
        $products       = $previewService->getPreviewProducts();

        $this->sendJson(['products' => $products]);
    }

    public function listPresets(): void
    {
        $this->validateRequest();

        $presetsDir = JPATH_ADMINISTRATOR . '/components/com_j2commerce/src/Builder/presets';
        $presets    = [];

        if (is_dir($presetsDir)) {
            foreach (glob($presetsDir . '/*.json') as $file) {
                $id = basename($file, '.json');
                // Only allow alphanumeric + hyphens
                if (!preg_match('/^[a-z0-9-]+$/', $id)) {
                    continue;
                }
                $json = file_get_contents($file);
                if ($json === false) {
                    continue;
                }
                $data = json_decode($json, true);
                if (!\is_array($data)) {
                    continue;
                }
                $data['id'] = $id;
                $presets[]  = $data;
            }
        }

        $this->sendJson(['presets' => $presets]);
    }

    public function loadPreset(): void
    {
        $this->validateRequest();

        $input  = $this->app->getInput();
        $preset = $input->getString('preset', '');

        // Validate: only alphanumeric + hyphens (prevent path traversal)
        if (!preg_match('/^[a-z0-9-]+$/', $preset)) {
            $this->sendJson(null, 'Invalid preset name', true);
            return;
        }

        $presetFile = JPATH_ADMINISTRATOR . '/components/com_j2commerce/src/Builder/presets/' . $preset . '.json';

        if (!is_file($presetFile)) {
            $this->sendJson(null, 'Preset not found', true);
            return;
        }

        $json = file_get_contents($presetFile);

        if ($json === false) {
            $this->sendJson(null, 'Failed to read preset file', true);
            return;
        }

        $data = json_decode($json, true);

        if (!\is_array($data)) {
            $this->sendJson(null, 'Invalid preset data', true);
            return;
        }

        $this->sendJson($data);
    }

    public function resetToDefault(): void
    {
        $this->validateRequest();

        $body          = json_decode((string) file_get_contents('php://input'), true);
        $pluginElement = $body['plugin_element'] ?? '';
        $fileId        = $body['file_id'] ?? '';

        if (empty($pluginElement) || empty($fileId)) {
            $this->sendJson(null, 'Missing required fields: plugin_element, file_id', true);
            return;
        }

        $overridePath = $this->resolveOverridePath($pluginElement, $fileId);

        if ($overridePath === null || !is_file($overridePath)) {
            $this->sendJson(null, 'Override file not found or path is invalid', true);
            return;
        }

        if (!@unlink($overridePath)) {
            $this->sendJson(null, 'Failed to delete override file', true);
            return;
        }

        $this->sendJson(['deleted' => true, 'hasOverride' => false]);
    }

    /**
     * Resolve and validate the override path. If the override file does not yet exist,
     * copy the source file to create it (copy-on-write). Returns null on any validation failure.
     */
    private function resolveOrCreateOverridePath(string $pluginElement, string $fileId): ?string
    {
        // Validate plugin element is a known subtemplate
        if (!preg_match('/^app_[a-z0-9_]+$/', $pluginElement)) {
            return null;
        }

        // Validate file ID doesn't contain path traversal
        if (str_contains($fileId, '..') || str_contains($fileId, "\0")) {
            return null;
        }

        $template             = $this->getActiveSiteTemplate();
        $templateOverridePath = Path::clean(
            JPATH_ROOT . '/templates/' . $template . '/html/layouts/com_j2commerce/' . $pluginElement
        );

        $overrideFile = Path::clean($templateOverridePath . '/' . $fileId);

        // Ensure the resolved path is inside the template override directory (prevent traversal)
        if (strpos($overrideFile, Path::clean($templateOverridePath)) !== 0) {
            return null;
        }

        // Ensure we're NOT inside a core component/plugin directory
        $corePaths = [
            Path::clean(JPATH_ROOT . '/components/'),
            Path::clean(JPATH_ROOT . '/plugins/'),
            Path::clean(JPATH_ADMINISTRATOR . '/components/'),
        ];

        foreach ($corePaths as $corePath) {
            if (strpos($overrideFile, $corePath) === 0) {
                return null;
            }
        }

        // If override already exists, return it directly
        if (is_file($overrideFile)) {
            return $overrideFile;
        }

        // Copy-on-write: verify source file exists and is a block-layout before copying
        $sourcePath = OverrideRegistry::getSourcePath($pluginElement, $fileId);

        if (!is_file($sourcePath)) {
            return null;
        }

        if (OverrideRegistry::classifyLayoutFile($sourcePath) !== OverrideRegistry::FILE_TYPE_BLOCK_LAYOUT) {
            return null;
        }

        // Create the directory tree if needed
        $overrideDir = \dirname($overrideFile);

        if (!is_dir($overrideDir) && !Folder::create($overrideDir)) {
            return null;
        }

        // Copy source to override path
        if (!@copy($sourcePath, $overrideFile)) {
            return null;
        }

        return $overrideFile;
    }

    /**
     * Resolve and validate the override file path.
     *
     * Safety: ensures the resolved path is inside the active template's
     * html/com_j2commerce override directory and that the file already exists
     * (store owner must create the override first via the Overrides tab).
     */
    private function resolveOverridePath(string $pluginElement, string $fileId): ?string
    {
        // Validate plugin element is a known subtemplate
        if (!preg_match('/^app_[a-z0-9_]+$/', $pluginElement)) {
            return null;
        }

        // Validate file ID doesn't contain path traversal
        if (str_contains($fileId, '..') || str_contains($fileId, "\0")) {
            return null;
        }

        // Get the active SITE template (not admin) — same approach as OverridesModel
        $template             = $this->getActiveSiteTemplate();
        $templateOverridePath = Path::clean(
            JPATH_ROOT . '/templates/' . $template . '/html/layouts/com_j2commerce/' . $pluginElement
        );

        $overrideFile = Path::clean($templateOverridePath . '/' . $fileId);

        // Ensure the resolved path is inside the template override directory (prevent traversal)
        if (strpos($overrideFile, Path::clean($templateOverridePath)) !== 0) {
            return null;
        }

        // The override file MUST already exist — we never create overrides, only edit existing ones
        if (!is_file($overrideFile)) {
            return null;
        }

        // Ensure we're NOT inside a core component/plugin directory
        $corePaths = [
            Path::clean(JPATH_ROOT . '/components/'),
            Path::clean(JPATH_ROOT . '/plugins/'),
            Path::clean(JPATH_ADMINISTRATOR . '/components/'),
        ];

        foreach ($corePaths as $corePath) {
            if (strpos($overrideFile, $corePath) === 0) {
                return null;
            }
        }

        return $overrideFile;
    }

    private function executeSubLayoutTemplate(string $filePath, array $displayData): string
    {
        ob_start();

        try {
            (static function (string $__file, array $displayData): void {
                extract($displayData);
                include $__file;
            })($filePath, $displayData);
        } catch (\Throwable $e) {
            ob_end_clean();

            return '<div class="alert alert-danger">Sub-layout render error: '
                . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
        }

        return ob_get_clean() ?: '';
    }

    private function getActiveSiteTemplate(): string
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('template'))
            ->from($db->quoteName('#__template_styles'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('home') . ' = ' . $db->quote('1'));
        $db->setQuery($query);

        return $db->loadResult() ?: 'cassiopeia';
    }

    private function validateRequest(): void
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_j2commerce')) {
            $this->sendJson(null, 'Access denied', true);
        }

        if (!Session::checkToken('get') && !Session::checkToken('post')) {
            $this->sendJson(null, 'Invalid token', true);
        }
    }

    private function sendJson(mixed $data, string $error = '', bool $isError = false): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo new JsonResponse($data, $error, $isError);
        $this->app->close();
    }
}
