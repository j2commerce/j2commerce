<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\View\Producttags;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use Joomla\CMS\Document\Feed\FeedItem;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

class FeedView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        $app    = Factory::getApplication();
        $model  = $this->getModel();
        $params = $app->getParams();

        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $doc = $this->getDocument();

        $doc->title       = $params->get('page_title', '') ?: $app->get('sitename');
        $doc->description = strip_tags((string) ($params->get('menu-meta_description', '')));
        $doc->link        = Route::_('index.php?option=com_j2commerce&view=producttags', true);
        $doc->setGenerator('J2Commerce - Joomla Ecommerce Platform');

        $items = $model->getItems();

        foreach ($items as $item) {
            $id    = (int) $item->j2commerce_product_id;
            $alias = $item->alias ?? null;
            $catid = isset($item->catid) ? (int) $item->catid : null;

            $feedItem              = new FeedItem();
            $feedItem->title       = strip_tags((string) ($item->product_name ?? ''));
            $feedItem->link        = Route::_(RouteHelper::getProductRoute($id, $alias, $catid), true);
            $feedItem->description = strip_tags((string) ($item->product_short_desc ?? ''));

            if (!empty($item->created)) {
                $feedItem->date = $item->created;
            }

            $doc->addItem($feedItem);
        }
    }
}
