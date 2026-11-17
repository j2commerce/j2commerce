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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use J2Commerce\Component\J2commerce\Administrator\Service\ProductService;
use J2Commerce\Component\J2commerce\Site\Helper\ProductVisibilityHelper;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\MVC\Controller\BaseController;

class ProductController extends BaseController
{
    public function update(): void
    {
        $app       = Factory::getApplication();
        $productId = $app->getInput()->getInt('product_id', 0);

        header('Content-Type: application/json; charset=utf-8');

        self::matchLanguageToReferer($app);

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

        // The rendered layouts already hold price output to canShowPrice(); this response is the last surface that did not.
        if (\is_array($result) && !ProductHelper::canShowPrice(J2CommerceHelper::config()->getParams())) {
            unset($result['pricing'], $result['price'], $result['afterDisplayPrice']);
        }

        echo json_encode($result);
        $app->close();
    }

    /**
     * This endpoint is called by plain index.php AJAX requests that carry no SEF
     * language segment, so the language filter resolves them to the site's
     * default language rather than the one the visitor is actually browsing in.
     * The calling page's own URL — its "return" field, or the Referer header
     * when that's absent — still carries that segment, so use it to switch the
     * active language before any Text:: string is produced. This never touches
     * the request's own URL, so it cannot trigger the SEF router's redirect logic.
     */
    private static function matchLanguageToReferer(CMSApplicationInterface $app): void
    {
        if (!$app instanceof SiteApplication || !Multilanguage::isEnabled()) {
            return;
        }

        $return = (string) $app->getInput()->get('return', '', 'base64');
        $url    = $return !== '' ? (string) base64_decode($return) : $app->getInput()->server->getString('HTTP_REFERER', '');

        $path    = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segment = strtolower(strtok($path, '/') ?: '');

        if ($segment === '') {
            return;
        }

        $languages = LanguageHelper::getLanguages('sef');

        if (empty($languages[$segment])) {
            return;
        }

        $tag = $languages[$segment]->lang_code;

        if ($tag === $app->getLanguage()->getTag()) {
            return;
        }

        $app->loadLanguage(Language::getInstance($tag));
        $app->getLanguage()->load('com_j2commerce', JPATH_SITE);
    }
}
