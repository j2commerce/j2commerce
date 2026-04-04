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

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CategoryWizardHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

class CategorywizardController extends BaseController
{
    private function getDb(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }

    private function jsonError(string $message, int $status = 400): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->app->setHeader('status', (string) $status);
        echo new JsonResponse(null, $message, true);
        $this->app->close();
    }

    private function jsonSuccess(mixed $data, string $message = ''): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo new JsonResponse($data, $message);
        $this->app->close();
    }

    private function requireAdmin(): bool
    {
        if (!$this->app->getIdentity()->authorise('core.admin', 'com_j2commerce')) {
            $this->jsonError(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
            return false;
        }

        return true;
    }

    /**
     * GET — detect template and subtemplate availability.
     * No CSRF needed (read-only, no side effects).
     */
    public function detectTemplate(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $subtemplates       = CategoryWizardHelper::getAvailableSubtemplates();
            $yoothemeInstalled  = CategoryWizardHelper::isYooThemeActive();
            $defaultSubtemplate = in_array('uikit', $subtemplates, true) && $yoothemeInstalled
                ? 'uikit'
                : 'bootstrap5';

            $this->jsonSuccess([
                'yoothemeInstalled'     => $yoothemeInstalled,
                'availableSubtemplates' => $subtemplates,
                'defaultSubtemplate'    => $defaultSubtemplate,
            ]);
        } catch (\Throwable $e) {
            \Joomla\CMS\Log\Log::add($e->getMessage(), \Joomla\CMS\Log\Log::ERROR, 'com_j2commerce');
            $this->jsonError(Text::_('COM_J2COMMERCE_ERR_GENERIC'), 500);
        }
    }

    /**
     * POST — execute single product flow.
     */
    public function createSingleProduct(): void
    {
        if (!Session::checkToken()) {
            $this->jsonError(Text::_('JINVALID_TOKEN'), 403);
            return;
        }

        if (!$this->requireAdmin()) {
            return;
        }

        $input       = $this->input;
        $productName = trim($input->getString('product_name', ''));
        $productType = $input->getString('product_type', 'simple');
        $subtemplate = $input->getString('subtemplate', 'bootstrap5');

        // Validate product type
        $allowedTypes = ['simple', 'variable', 'downloadable'];

        if (!in_array($productType, $allowedTypes, true)) {
            $productType = 'simple';
        }

        // Validate subtemplate
        if (!in_array($subtemplate, ['bootstrap5', 'uikit'], true)) {
            $subtemplate = 'bootstrap5';
        }

        if ($productName === '') {
            $this->jsonError(Text::_('COM_J2COMMERCE_WIZARD_ERR_PRODUCT_NAME_REQUIRED'), 422);
            return;
        }

        // Parse options array from raw POST
        $rawOptions = $input->get('options', [], 'array');
        $options    = $this->sanitizeOptions($rawOptions);

        try {
            $db = $this->getDb();

            // 1. Find or create root "Shop" category
            $categoryId = CategoryWizardHelper::findOrCreateShopCategory('Shop', $db);

            if ($categoryId <= 0) {
                throw new \RuntimeException('Failed to create shop category');
            }

            // 2. Create Joomla article
            $articleId = CategoryWizardHelper::createArticle($productName, $categoryId, $db);

            if ($articleId <= 0) {
                throw new \RuntimeException('Failed to create article');
            }

            // 3. Create J2Commerce product + master variant
            $productId = CategoryWizardHelper::createProduct($articleId, $productType, $db);

            if ($productId <= 0) {
                throw new \RuntimeException('Failed to create product');
            }

            // 4. Create product options (if provided)
            $optionIds        = [];
            $optionValueCount = 0;

            if (!empty($options)) {
                $isVariable = $productType === 'variable';

                foreach ($options as $i => $optionData) {
                    $optionTitle  = $optionData['title'] ?? '';
                    $optionValues = $optionData['values'] ?? [];

                    if ($optionTitle === '' || empty($optionValues)) {
                        continue;
                    }

                    $optionId = CategoryWizardHelper::findOrCreateOption($optionTitle, $db);

                    if ($optionId <= 0) {
                        continue;
                    }

                    $optionIds[] = $optionId;

                    CategoryWizardHelper::createProductOptionValues(
                        $productId,
                        $optionId,
                        $optionValues,
                        $db,
                        $i + 1,
                        $isVariable
                    );

                    $optionValueCount += count($optionValues);
                }

                if (!empty($optionIds)) {
                    CategoryWizardHelper::setHasOptions($productId, $db);
                }
            }

            // 5. Create menu item
            $link     = 'index.php?option=com_j2commerce&view=product&id=' . $productId;
            $params   = ['subtemplate' => $subtemplate];
            $menuItemId = CategoryWizardHelper::createMenuItem('Shop', $link, $params, $db);

            $editUrl     = 'index.php?option=com_j2commerce&task=products.editProduct&id=' . $productId;
            $frontendUrl = '/shop/' . \Joomla\CMS\Application\ApplicationHelper::stringURLSafe($productName);

            $responseData = [
                'categoryId'       => $categoryId,
                'articleId'        => $articleId,
                'productId'        => $productId,
                'menuItemId'       => $menuItemId,
                'optionIds'        => $optionIds,
                'optionValueCount' => $optionValueCount,
                'productType'      => $productType,
                'editUrl'          => $editUrl,
                'frontendUrl'      => $frontendUrl,
            ];

            $this->jsonSuccess($responseData);
        } catch (\Throwable $e) {
            \Joomla\CMS\Log\Log::add($e->getMessage(), \Joomla\CMS\Log\Log::ERROR, 'com_j2commerce');
            $this->jsonError(Text::_('COM_J2COMMERCE_WIZARD_ERR_CREATION_FAILED'), 500);
        }
    }

    /**
     * POST — execute multi product flow.
     */
    public function createMultiProduct(): void
    {
        if (!Session::checkToken()) {
            $this->jsonError(Text::_('JINVALID_TOKEN'), 403);
            return;
        }

        if (!$this->requireAdmin()) {
            return;
        }

        $input            = $this->input;
        $rootCategoryName = trim($input->getString('root_category_name', 'Shop'));
        $menuType         = $input->getString('menu_type', 'categories');
        $subtemplate      = $input->getString('subtemplate', 'bootstrap5');

        // Validate inputs
        if ($rootCategoryName === '') {
            $rootCategoryName = 'Shop';
        }

        if (!in_array($menuType, ['categories', 'products'], true)) {
            $menuType = 'categories';
        }

        if (!in_array($subtemplate, ['bootstrap5', 'uikit'], true)) {
            $subtemplate = 'bootstrap5';
        }

        $rawCategories = $input->get('categories', [], 'array');
        $categories    = $this->sanitizeStringArray($rawCategories);

        try {
            $db = $this->getDb();

            // 1. Create root "Shop" category
            $rootCategoryId = CategoryWizardHelper::findOrCreateShopCategory($rootCategoryName, $db);

            if ($rootCategoryId <= 0) {
                throw new \RuntimeException('Failed to create root category');
            }

            // 2. Create subcategories
            $subcategoryIds = [];

            foreach ($categories as $catName) {
                if ($catName === '') {
                    continue;
                }

                $catId = CategoryWizardHelper::createCategory($catName, $rootCategoryId, $db);

                if ($catId > 0) {
                    $subcategoryIds[] = ['id' => $catId, 'name' => $catName];
                }
            }

            // 3. Create menu item(s)
            $menuItemIds    = [];

            if ($menuType === 'categories' || empty($subcategoryIds)) {
                // Single "Product Categories" menu item pointing at root
                $params     = ['categoriestemplate' => $subtemplate, 'subtemplate' => $subtemplate];
                $link       = 'index.php?option=com_j2commerce&view=categories&id=' . $rootCategoryId;
                $menuItemId = CategoryWizardHelper::createMenuItem($rootCategoryName, $link, $params, $db);

                if ($menuItemId > 0) {
                    $menuItemIds[] = $menuItemId;
                }
            } else {
                // One "Product Category" menu item per subcategory
                $params = ['subtemplate' => $subtemplate];

                foreach ($subcategoryIds as $cat) {
                    $link       = 'index.php?option=com_j2commerce&view=products&catid=' . $cat['id'];
                    $menuItemId = CategoryWizardHelper::createMenuItem($cat['name'], $link, $params, $db);

                    if ($menuItemId > 0) {
                        $menuItemIds[] = $menuItemId;
                    }
                }
            }

            $this->jsonSuccess([
                'rootCategoryId'     => $rootCategoryId,
                'subcategoryIds'     => array_column($subcategoryIds, 'id'),
                'menuItemIds'        => $menuItemIds,
                'adminCategoryUrl'   => 'index.php?option=com_categories&extension=com_content',
                'adminProductsUrl'   => 'index.php?option=com_j2commerce&view=products',
            ]);
        } catch (\Throwable $e) {
            \Joomla\CMS\Log\Log::add($e->getMessage(), \Joomla\CMS\Log\Log::ERROR, 'com_j2commerce');
            $this->jsonError(Text::_('COM_J2COMMERCE_WIZARD_ERR_CREATION_FAILED'), 500);
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Sanitize options array from form input.
     * Expected format: options[0][title]=Size, options[0][values][]=Small, etc.
     */
    private function sanitizeOptions(array $raw): array
    {
        $options = [];

        foreach ($raw as $optionData) {
            if (!is_array($optionData)) {
                continue;
            }

            $title  = trim((string) ($optionData['title'] ?? ''));
            $values = [];

            if (!empty($optionData['values']) && is_array($optionData['values'])) {
                foreach ($optionData['values'] as $val) {
                    $clean = trim((string) $val);

                    if ($clean !== '') {
                        $values[] = $clean;
                    }
                }
            }

            if ($title !== '' && !empty($values)) {
                $options[] = ['title' => $title, 'values' => $values];
            }
        }

        return $options;
    }

    /**
     * Sanitize an array of strings (e.g., category names).
     */
    private function sanitizeStringArray(array $raw): array
    {
        $result = [];

        foreach ($raw as $item) {
            $clean = trim(strip_tags((string) $item));

            if ($clean !== '') {
                $result[] = $clean;
            }
        }

        return $result;
    }
}
