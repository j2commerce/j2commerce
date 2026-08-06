<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Controller;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use J2Commerce\Component\J2commerce\Administrator\Service\ProductService;
use J2Commerce\Component\J2commerce\Site\Helper\ProductVisibilityHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

class ProductController extends BaseController
{
    public function update(): void
    {
        $app       = Factory::getApplication();
        $productId = $app->getInput()->getInt('product_id', 0);

        header('Content-Type: application/json; charset=utf-8');

        // A product the visitor may not see reports as missing, never as forbidden.
        if (!$productId || !ProductVisibilityHelper::isViewable($productId)) {
            echo json_encode(['error' => 'Product not found']);
            $app->close();
            return;
        }

        $product = ProductHelper::getFullProduct($productId);

        if (!$product) {
            echo json_encode(['error' => 'Product not found']);
            $app->close();
            return;
        }

        $service  = new ProductService();
        $behavior = $service->getBehavior($product->product_type);

        $model = $app->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createModel('Products', 'Administrator', ['ignore_request' => true]);

        $result = $behavior->onUpdateProduct($model, $product);

        echo json_encode($result);
        $app->close();
    }
}
