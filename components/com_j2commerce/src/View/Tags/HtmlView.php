<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\View\Tags;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use J2Commerce\Component\J2commerce\Site\Helper\TagTreeHelper;
use J2Commerce\Component\J2commerce\Site\View\CustomSubtemplateTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

/** Product Tags View: a parent tag's child tags as a landing grid. The tag counterpart of the Categories view. */
class HtmlView extends BaseHtmlView
{
    use CustomSubtemplateTrait;

    public Registry $params;

    public array $items = [];

    public ?object $parent = null;

    public int $columns = 3;

    public array $products = [];

    public int $productColumns = 3;

    public array $trendingProducts = [];

    public string $displayMode = 'products';

    public function display($tpl = null): void
    {
        $app   = Factory::getApplication();
        $model = $this->getModel();

        $this->params = $app->getParams();
        $this->parent = $model->getParent();

        if (!$this->parent) {
            throw new \Exception(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        $this->items    = $model->getItems();
        $this->products = $model->getProducts();

        $landingTemplate = $this->params->get('tagstemplate', '');

        if ($landingTemplate !== '') {
            $this->params->set('subtemplate', $landingTemplate);
        }

        $this->columns        = (int) $this->params->get('tag_columns', 3);
        $this->productColumns = (int) $this->params->get('list_no_of_columns', 3);
        $this->sublayout      = $this->params->get('subtemplate', '');
        $this->displayMode    = $this->params->get('child_tag_display_mode', 'products');

        if ($this->displayMode === 'tags_popular') {
            $this->trendingProducts = $model->getPopularProducts(
                $this->parent->id,
                (int) $this->params->get('popular_product_count', 12)
            );
        }

        // Before the event: template plugins render inside it and print the page heading this sets.
        $this->prepareDocument();

        $event    = J2CommerceHelper::plugin()->eventWithHtml('ViewTagsListHtml', [null, &$this, $model]);
        $viewHtml = $event->getArgument('html', '');

        if (!empty($viewHtml)) {
            echo $viewHtml;

            return;
        }

        if (!empty($this->sublayout)) {
            $customHtml = $this->renderCustomSubtemplate();

            if ($customHtml !== null) {
                echo $customHtml;

                return;
            }
        }

        parent::display($tpl);
    }

    protected function prepareDocument(): void
    {
        $app          = Factory::getApplication();
        $menu         = $app->getMenu()->getActive();
        $menuParentId = (int) ($menu->query['id'] ?? 0) ?: TagTreeHelper::ROOT_ID;
        $isChild      = $this->parent->id !== $menuParentId;

        if ($isChild) {
            $this->params->set('page_heading', $this->parent->title);
        } elseif ($menu) {
            // A saved menu item stores page_heading and page_title as empty strings, which def() keeps.
            $this->params->set('page_heading', $this->params->get('page_heading') ?: ($this->params->get('page_title') ?: $menu->title));
        } else {
            $this->params->def('page_heading', Text::_('JTAG'));
        }

        // setDocumentTitle() applies sitename_pagetitles itself and falls back to the site name.
        $this->setDocumentTitle($isChild ? $this->parent->title : $this->params->get('page_title', ''));

        if ($isChild) {
            $pathway = $app->getPathway();

            foreach (TagTreeHelper::pathBelow($menuParentId, $this->parent->id) ?? [] as $tag) {
                $pathway->addItem($tag->title, Route::_(RouteHelper::getTagRouteInContext($tag->id, $menu)));
            }
        }

        if ($this->params->get('menu-meta_description')) {
            $this->getDocument()->setDescription($this->params->get('menu-meta_description'));
        }

        if ($this->params->get('menu-meta_keywords')) {
            $this->getDocument()->setMetaData('keywords', $this->params->get('menu-meta_keywords'));
        }

        if ($this->params->get('robots')) {
            $this->getDocument()->setMetaData('robots', $this->params->get('robots'));
        }

        // The same landing is reachable by more than one path, so name the one that is the page.
        $this->getDocument()->addHeadLink(
            Route::_(
                RouteHelper::getTagsRoute($this->parent->id > TagTreeHelper::ROOT_ID ? $this->parent->id : null),
                true,
                Route::TLS_IGNORE,
                true
            ),
            'canonical'
        );
    }

    public function getColumnClass(): string
    {
        return self::columnClass($this->columns);
    }

    public function getProductColumnClass(): string
    {
        return self::columnClass($this->productColumns);
    }

    public function getPopularColumnClass(): string
    {
        return match ((int) $this->params->get('popular_grid_columns', 4)) {
            2       => 'col-12 col-md-6',
            3       => 'col-12 col-md-6 col-lg-4',
            6       => 'col-12 col-md-4 col-lg-2',
            default => 'col-12 col-md-6 col-lg-3',
        };
    }

    private static function columnClass(int $columns): string
    {
        return match ($columns) {
            1       => 'col-12',
            2       => 'col-12 col-md-6',
            4       => 'col-12 col-md-6 col-lg-3',
            6       => 'col-12 col-md-4 col-lg-2',
            default => 'col-12 col-md-6 col-lg-4',
        };
    }
}
