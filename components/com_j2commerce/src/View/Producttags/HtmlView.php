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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\View\CustomSubtemplateTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    use CustomSubtemplateTrait;

    protected $state;
    protected $items = [];
    public $products = [];
    protected $pagination;
    public $params;
    public $parent;
    public $product;
    public $product_link = '';
    protected $columns   = 3;
    protected $user;
    public $filters;
    public $filter_catid;
    public $active_menu;
    public array $tag_ids    = [];
    public string $tag_match = 'any';

    public function display($tpl = null): void
    {
        $app   = Factory::getApplication();
        $model = $this->getModel();

        $this->params        = $app->getParams();
        $this->state         = $model->getState();
        $this->items         = $model->getItems();
        $this->products      = $this->items;
        $this->parent        = $model->getParent();
        $this->pagination    = $model->getPagination();
        $this->user          = $this->getCurrentUser();
        $this->filters       = $model->getFilters($this->items);
        $this->active_menu   = $app->getMenu()->getActive();
        $this->filter_catid  = '';
        $this->tag_ids       = $model->getState('filter.tag_ids', []);
        $this->tag_match     = $model->getState('filter.tag_match', 'any');

        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->columns   = (int) $this->params->get('list_no_of_columns', 3);
        $this->sublayout = $this->params->get('subtemplate', '');

        $event    = J2CommerceHelper::plugin()->eventWithHtml('ViewProductListTagHtml', [null, &$this, $model]);
        $viewHtml = $event->getArgument('html', '');

        if (!empty($viewHtml)) {
            $this->_prepareDocument();
            echo $viewHtml;

            return;
        }

        // If a custom subtemplate is selected, try template override directory first
        if (!empty($this->sublayout)) {
            $customHtml = $this->renderCustomSubtemplate();

            if ($customHtml !== null) {
                $this->_prepareDocument();
                echo $customHtml;

                return;
            }
        }

        $this->_prepareDocument();

        parent::display($tpl);
    }

    protected function _prepareDocument(): void
    {
        $app  = Factory::getApplication();
        $menu = $app->getMenu()->getActive();

        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', Text::_('COM_J2COMMERCE_PRODUCTTAGS_VIEW_DEFAULT_TITLE'));
        }

        $title = $this->params->get('page_title', '');
        if (empty($title)) {
            $title = $app->get('sitename');
        } elseif ($app->get('sitename_pagetitles', 0) == 1) {
            $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
        }

        $this->setDocumentTitle($title);

        if ($this->params->get('menu-meta_description')) {
            $this->getDocument()->setDescription($this->params->get('menu-meta_description'));
        }

        if ($this->params->get('menu-meta_keywords')) {
            $this->getDocument()->setMetaData('keywords', $this->params->get('menu-meta_keywords'));
        }

        if ($this->params->get('robots')) {
            $this->getDocument()->setMetaData('robots', $this->params->get('robots'));
        }

        $customCss = $this->params->get('custom_css', '');
        if (!empty($customCss)) {
            $this->getDocument()->getWebAssetManager()->addInlineStyle($customCss);
        }
    }

    public function getColumnClass(): string
    {
        return match ($this->columns) {
            1       => 'col-12',
            2       => 'col-12 col-md-6',
            3       => 'col-12 col-md-6 col-lg-4',
            4       => 'col-12 col-md-6 col-lg-3',
            6       => 'col-12 col-md-4 col-lg-2',
            default => 'col-12 col-md-6 col-lg-4',
        };
    }
}
