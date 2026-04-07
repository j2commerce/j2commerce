<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\View\Confirmation;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\UtilitiesHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

class HtmlView extends BaseHtmlView
{
    public ?object $order            = null;
    public string $plugin_html       = '';
    public string $order_link        = '';
    public ?object $params           = null;
    public ?object $currency         = null;
    public ?Registry $menuItemParams = null;
    public string $paction           = '';
    public array $orderItems         = [];
    public ?object $orderInfo        = null;
    public array $orderShippings     = [];
    public array $orderTaxes         = [];
    public array $orderFees          = [];
    public array $orderDiscounts     = [];
    public bool $showingRecent       = false;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        UtilitiesHelper::sendNoCacheHeaders();

        $this->params   = J2CommerceHelper::config();
        $this->currency = J2CommerceHelper::currency();

        $menu                 = $app->getMenu();
        $active               = $menu->getActive();
        $this->menuItemParams = \is_object($active) ? $active->getParams() : new Registry('{}');

        /** @var \J2Commerce\Component\J2commerce\Site\Model\ConfirmationModel $model */
        $model = $this->getModel();
        $model->getState();

        $this->order = $model->getOrder();

        if ($this->order === null) {
            $user = $app->getIdentity();

            // Guest → redirect to My Profile (has login + guest order lookup form)
            if (!$user || $user->id <= 0) {
                $app->enqueueMessage(
                    Text::_('COM_J2COMMERCE_CONFIRMATION_LOGIN_TO_VIEW'),
                    'notice'
                );
                $app->redirect(Route::_('index.php?option=com_j2commerce&view=myprofile', false));

                return;
            }

            // Logged-in user with no orders → show token entry form
            $this->_prepareDocument();
            parent::display('noorder');

            return;
        }

        // Check if showing most recent order (no order_id was in URL)
        $this->showingRecent = (bool) $model->getState('showing_recent', false);

        // Payment cancel detection
        $this->paction = $app->getInput()->getCmd('paction', '');

        // Order link based on configuration
        if ($this->params->get('show_postpayment_orderlink', 1)) {
            $this->order_link = Route::_('index.php?option=com_j2commerce&view=myprofile');
        }

        // Get plugin HTML from the payment flow (stored in user state)
        $this->plugin_html = $model->getPluginHtml();

        // Load order detail data
        $this->orderItems     = $model->getOrderItems();
        $this->orderInfo      = $model->getOrderInfo();
        $this->orderShippings = $model->getOrderShippings();
        $this->orderTaxes     = $model->getOrderTaxes();
        $this->orderFees      = $model->getOrderFees();
        $this->orderDiscounts = $model->getOrderDiscounts();

        $this->_prepareDocument();

        parent::display($tpl);
    }

    protected function _prepareDocument(): void
    {
        $app = Factory::getApplication();

        $title = $this->menuItemParams->get('page_title', '');

        if (empty($title)) {
            $title = Text::_('COM_J2COMMERCE_ORDER_CONFIRMATION');
        }

        $this->getDocument()->setTitle($title);

        if ($this->menuItemParams->get('menu-meta_description')) {
            $this->getDocument()->setDescription($this->menuItemParams->get('menu-meta_description'));
        }

        if ($this->menuItemParams->get('menu-meta_keywords')) {
            $this->getDocument()->setMetaData('keywords', $this->menuItemParams->get('menu-meta_keywords'));
        }

        // Force noindex,nofollow for order confirmation pages
        $this->getDocument()->setMetaData('robots', 'noindex, nofollow');
    }
}
