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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\CartHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHistoryHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\UtilitiesHelper;
use J2Commerce\Component\J2commerce\Site\Helper\CheckoutStepsHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\User\UserFactoryInterface;

class CheckoutController extends BaseController
{
    protected string $lastAddressError = '';

    public function display($cachable = false, $urlparams = []): static
    {
        UtilitiesHelper::sendNoCacheHeaders();

        return parent::display($cachable, $urlparams);
    }

    protected function getCheckoutView(): \Joomla\CMS\MVC\View\HtmlView
    {
        $view               = $this->getView('Checkout', 'Html');
        $view->params       = J2CommerceHelper::config();
        $view->currency     = J2CommerceHelper::currency();
        $view->storeProfile = J2CommerceHelper::storeProfile();
        $view->user         = $this->app->getIdentity();
        $view->logged       = ($view->user && $view->user->id > 0);

        return $view;
    }

    protected function renderStep(string $tpl, array $extraData = []): void
    {
        $view = $this->getCheckoutView();

        foreach ($extraData as $key => $value) {
            $view->$key = $value;
        }

        $view->setLayout('default');
        $html = $view->loadTemplate($tpl);

        if ($html instanceof \Exception) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($html->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
        } else {
            echo $html;
        }

        $this->app->close();
    }

    protected function jsonResponse(array $json): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo json_encode($json);
        $this->app->close();
    }

    protected function getCheckoutUrl(): string
    {
        return Route::_('index.php?option=com_j2commerce&view=checkout');
    }

    /**
     * Log out the current user and redirect based on config_cart_empty_redirect setting.
     */
    public function logout(): void
    {
        Session::checkToken('get') or $this->app->redirect($this->getCheckoutUrl());

        $this->app->logout();

        $params   = J2CommerceHelper::config();
        $redirect = $params->get('config_cart_empty_redirect', 'cart');

        switch ($redirect) {
            case 'homepage':
                $menu    = $this->app->getMenu('site');
                $default = $menu->getDefault($this->app->getLanguage()->getTag());
                $url     = $default ? Route::_($default->link . '&Itemid=' . $default->id) : Route::_('index.php');
                break;

            case 'menu':
                $menuItemId = (int) $params->get('continue_cart_redirect_menu', 0);
                $url        = $menuItemId ? Route::_('index.php?Itemid=' . $menuItemId) : Route::_('index.php');
                break;

            case 'url':
                $url = $params->get('config_cart_redirect_page_url', '') ?: Route::_('index.php');
                break;

            default:
                $url = Route::_('index.php?option=com_j2commerce&view=carts');
                break;
        }

        $this->app->redirect($url);
    }

    /**
     * Get MVCFactory for com_j2commerce.
     */
    protected function getMvcFactory(): \Joomla\CMS\MVC\Factory\MVCFactoryInterface
    {
        return $this->app->bootComponent('com_j2commerce')->getMVCFactory();
    }

    /**
     * Collect form data from POST for custom field validation.
     */
    protected function collectFormData(): array
    {
        $data     = [];
        $postData = $this->input->post->getArray();

        foreach ($postData as $key => $value) {
            $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);

            if (\is_string($value)) {
                $data[$key] = $this->input->getString($key, '');
            }
        }

        return $data;
    }

    /**
     * Save an address to the database via AddressTable.
     *
     * @return int|false  The new address ID on success, false on failure.
     */
    protected function saveAddress(array $addressData): int|false
    {
        $addressTable = $this->getMvcFactory()->createTable('Address', 'Administrator');

        if (!$addressTable) {
            $this->lastAddressError = 'Could not create Address table instance.';
            return false;
        }

        if (!$addressTable->bind($addressData)) {
            $this->lastAddressError = 'Bind failed: ' . $addressTable->getError();
            return false;
        }

        if (!$addressTable->check()) {
            $this->lastAddressError = 'Validation failed: ' . $addressTable->getError();
            return false;
        }

        if (!$addressTable->store()) {
            $this->lastAddressError = 'Store failed: ' . $addressTable->getError();
            return false;
        }

        return (int) $addressTable->j2commerce_address_id;
    }

    /**
     * Set billing session values from address data.
     */
    protected function setBillingSession(array $data): void
    {
        $session = $this->app->getSession();
        $session->set('billing_country_id', (int) ($data['country_id'] ?? 0), 'j2commerce');
        $session->set('billing_zone_id', (int) ($data['zone_id'] ?? 0), 'j2commerce');
        $session->set('billing_postcode', $data['zip'] ?? '', 'j2commerce');
    }

    /**
     * Set shipping session values from address data.
     */
    protected function setShippingSession(array $data): void
    {
        $session = $this->app->getSession();
        $session->set('shipping_country_id', (int) ($data['country_id'] ?? 0), 'j2commerce');
        $session->set('shipping_zone_id', (int) ($data['zone_id'] ?? 0), 'j2commerce');
        $session->set('shipping_postcode', $data['zip'] ?? '', 'j2commerce');
    }

    // =========================================================================
    // STEP 1: Login / Account Type
    // =========================================================================

    public function login(): void
    {
        $this->validateAjaxToken() or $this->jsonResponse(['error' => ['warning' => Text::_('JINVALID_TOKEN')]]);

        $session = $this->app->getSession();
        $account = $session->get('account', 'register', 'j2commerce');

        $this->renderStep('login', [
            'account' => $account,
        ]);
    }

    public function loginValidate(): void
    {
        $this->validateAjaxToken() or $this->jsonResponse(['error' => ['warning' => Text::_('JINVALID_TOKEN')]]);

        $user    = $this->app->getIdentity();
        $session = $this->app->getSession();
        $json    = [];

        if ($user && $user->id) {
            $json['redirect'] = $this->getCheckoutUrl();
            $this->jsonResponse($json);

            return;
        }

        J2CommerceHelper::plugin()->event('CheckoutBeforeLogin', [&$json]);

        if (!$json) {
            $email    = trim($this->input->getString('email', ''));
            $password = $this->input->getRaw('password', '');

            if ($email === '') {
                $json['error']['warning'] = Text::_('COM_J2COMMERCE_CHECKOUT_ERROR_EMAIL_REQUIRED');
                $json['error']['email']   = Text::_('COM_J2COMMERCE_CHECKOUT_ERROR_EMAIL_REQUIRED');
                $this->jsonResponse($json);

                return;
            }

            if ($password === '') {
                $json['error']['warning']  = Text::_('COM_J2COMMERCE_CHECKOUT_ERROR_PASSWORD_REQUIRED');
                $json['error']['password'] = Text::_('COM_J2COMMERCE_CHECKOUT_ERROR_PASSWORD_REQUIRED');
                $this->jsonResponse($json);

                return;
            }

            $guestSessionId = $session->getId();
            $credentials    = ['username' => $email, 'password' => $password];

            try {
                $result = $this->app->login($credentials);

                if ($result !== true) {
                    $json['error']['warning'] = Text::_('COM_J2COMMERCE_CHECKOUT_ERROR_LOGIN');
                    $this->jsonResponse($json);

                    return;
                }

                $session->set('uaccount', 'login', 'j2commerce');

                $loggedUser = $this->app->getIdentity();

                if ($loggedUser && $loggedUser->id && !empty($guestSessionId)) {
                    CartHelper::getInstance()->resetCart($guestSessionId, (int) $loggedUser->id);
                }

                $params = J2CommerceHelper::config();

                if ($loggedUser && $loggedUser->id) {
                    $addressInfo = $this->getUserFirstAddress((int) $loggedUser->id);

                    if ($addressInfo) {
                        $taxDefault = $params->get('config_tax_default', '');

                        if ($taxDefault === 'shipping') {
                            $session->set('shipping_country_id', (int) $addressInfo->country_id, 'j2commerce');
                            $session->set('shipping_zone_id', (int) $addressInfo->zone_id, 'j2commerce');
                            $session->set('shipping_postcode', $addressInfo->zip ?? '', 'j2commerce');
                        }

                        if ($taxDefault === 'billing') {
                            $session->set('billing_country_id', (int) $addressInfo->country_id, 'j2commerce');
                            $session->set('billing_zone_id', (int) $addressInfo->zone_id, 'j2commerce');
                            $session->set('billing_postcode', $addressInfo->zip ?? '', 'j2commerce');
                        }
                    } else {
                        $session->clear('shipping_country_id', 'j2commerce');
                        $session->clear('shipping_zone_id', 'j2commerce');
                        $session->clear('shipping_postcode', 'j2commerce');
                        $session->clear('billing_country_id', 'j2commerce');
                        $session->clear('billing_zone_id', 'j2commerce');
                        $session->clear('billing_postcode', 'j2commerce');
                    }
                }

                $session->clear('guest', 'j2commerce');
                $json['redirect'] = $this->getCheckoutUrl();
            } catch (\RuntimeException $e) {
                $json['error']['warning'] = $e->getMessage() ?: Text::_('COM_J2COMMERCE_CHECKOUT_ERROR_LOGIN');
            } catch (\Exception $e) {
                $json['error']['warning'] = Text::_('COM_J2COMMERCE_CHECKOUT_ERROR');
            }
        }

        J2CommerceHelper::plugin()->event('CheckoutAfterLogin', [&$json]);

        if (empty($json['error'])) {
            $this->app->getDispatcher()->dispatch(
                'onJ2CommerceCheckoutLogin',
                new \Joomla\Event\Event('onJ2CommerceCheckoutLogin', [])
            );
        }

        $this->jsonResponse($json);
    }

    // =========================================================================
    // STEP 1b: Register form
    // =========================================================================

    public function register(): void
    {
        $this->validateAjaxToken() or $this->jsonResponse(['error' => ['warning' => Text::_('JINVALID_TOKEN')]]);

        $session = $this->app->getSession();
        $session->set('uaccount', 'register', 'j2commerce');

        $order        = $this->getCartOrder();
        $showShipping = $this->determineShowShipping($order);
        $fields       = CustomFieldHelper::getFieldsByArea('register');

        $this->renderStep('register', [
            'showShipping' => $showShipping,
            'fields'       => $fields,
        ]);
    }

    public function registerValidate(): void
    {
        $this->validateAjaxToken() or $this->jsonResponse(['error' => ['warning' => Text::_('JINVALID_TOKEN')]]);

        $session  = $this->app->getSession();
        $json     = [];
        $formData = $this->collectFormData();

        // Validate custom fields
        $fields = CustomFieldHelper::getFieldsByArea('register');
        $errors = CustomFieldHelper::validateFields($fields, $formData);

        // Validate password
        $password = $this->input->getRaw('password', '');
        $confirm  = $this->input->getRaw('confirm', '');

        if (empty($password)) {
            $errors['password'] = Text::_('COM_J2COMMERCE_CHECKOUT_PASSWORD_REQUIRED');
        } elseif (\strlen($password) < 4) {
            $errors['password'] = Text::_('COM_J2COMMERCE_CHECKOUT_PASSWORD_TOO_SHORT');
        } elseif ($password !== $confirm) {
            $errors['confirm'] = Text::_('COM_J2COMMERCE_CHECKOUT_PASSWORDS_DONT_MATCH');
        }

        if ($errors) {
            $json['error'] = $errors;
            $this->jsonResponse($json);

            return;
        }

        // Create user
        $email     = trim($formData['email'] ?? '');
        $firstName = trim($formData['first_name'] ?? '');
        $lastName  = trim($formData['last_name'] ?? '');
        $name      = $firstName . ' ' . $lastName;

        // Check if email already exists
        $userFactory  = Factory::getContainer()->get(UserFactoryInterface::class);
        $existingUser = $userFactory->loadUserByUsername($email);

        if ($existingUser && $existingUser->id > 0) {
            $json['error']['warning'] = Text::_('COM_J2COMMERCE_CHECKOUT_EMAIL_EXISTS');
            $this->jsonResponse($json);

            return;
        }

        // Capture guest session ID before creating user and auto-login
        $guestSessionId = $session->getId();

        try {
            $user                 = new \Joomla\CMS\User\User();
            $user->name           = $name;
            $user->username       = $email;
            $user->email          = $email;
            $user->password_clear = $password;

            // Get default user group from Joomla global config
            $params       = \Joomla\CMS\Component\ComponentHelper::getParams('com_users');
            $defaultGroup = $params->get('new_usertype', 2);
            $user->groups = [$defaultGroup];

            $user->block        = 0;
            $user->registerDate = Factory::getDate()->toSql();

            if (!$user->save()) {
                $json['error']['warning'] = $user->getError() ?: Text::_('COM_J2COMMERCE_CHECKOUT_REGISTER_ERROR');
                $this->jsonResponse($json);

                return;
            }

            // Auto-login the new user
            $credentials = ['username' => $email, 'password' => $password];
            $this->app->login($credentials);

            $loggedUser = $this->app->getIdentity();

            // Merge guest cart items
            if ($loggedUser && $loggedUser->id && !empty($guestSessionId)) {
                CartHelper::getInstance()->resetCart($guestSessionId, (int) $loggedUser->id);
            }

            // Save address to database
            $addressData            = CustomFieldHelper::collectAddressData($fields, $formData);
            $addressData['user_id'] = (int) $loggedUser->id;
            $addressData['email']   = $email;
            $addressData['type']    = 'billing';

            $newAddressId = $this->saveAddress($addressData);

            $session->set('uaccount', 'register', 'j2commerce');

            if ($newAddressId) {
                $session->set('billing_address_id', $newAddressId, 'j2commerce');
            }

            $this->setBillingSession($addressData);

            // If shipping same as billing
            if ($this->input->getInt('shipping_address', 0)) {
                $this->setShippingSession($addressData);
            }

            $session->clear('guest', 'j2commerce');
            $session->clear('payment_method', 'j2commerce');
            $session->clear('payment_methods', 'j2commerce');
        } catch (\Exception $e) {
            $json['error']['warning'] = $e->getMessage();
            $this->jsonResponse($json);

            return;
        }

        J2CommerceHelper::plugin()->event('CheckoutAfterRegister', [&$json]);

        // After login, the CSRF token changes — send it so the JS can update
        if (empty($json['error'])) {
            $json['token'] = Session::getFormToken();
        }

        $this->jsonResponse($json);
    }

    // =========================================================================
    // STEP 1c: Guest form
    // =========================================================================

    public function guest(): void
    {
        $this->validateAjaxToken() or $this->jsonResponse(['error' => ['warning' => Text::_('JINVALID_TOKEN')]]);

        $session = $this->app->getSession();
        $session->set('uaccount', 'guest', 'j2commerce');

        $order        = $this->getCartOrder();
        $showShipping = $this->determineShowShipping($order);
        $fields       = CustomFieldHelper::getFieldsByArea('guest');

        // Retrieve previously-entered guest address from session for re-population
        $guestData = $session->get('guest', [], 'j2commerce');

        $this->renderStep('guest', [
            'showShipping' => $showShipping,
            'fields'       => $fields,
            'guestData'    => \is_array($guestData) ? $guestData : [],
        ]);
    }

    public function guestValidate(): void
    {
        $this->validateAjaxToken() or $this->jsonResponse(['error' => ['warning' => Text::_('JINVALID_TOKEN')]]);

        $session  = $this->app->getSession();
        $json     = [];
        $formData = $this->collectFormData();

        $fields = CustomFieldHelper::getFieldsByArea('guest');
        $errors = CustomFieldHelper::validateFields($fields, $formData);

        if ($errors) {
            $json['error'] = $errors;
            $this->jsonResponse($json);

            return;
        }

        // Store guest address in session
        $addressData = CustomFieldHelper::collectAddressData($fields, $formData);
        $session->set('guest', $addressData, 'j2commerce');

        $this->setBillingSession($addressData);

        // If shipping same as billing
        if ($this->input->getInt('shipping_address', 0)) {
            $this->setShippingSession($addressData);
        }

        $session->clear('payment_method', 'j2commerce');
        $session->clear('payment_methods', 'j2commerce');

        J2CommerceHelper::plugin()->event('CheckoutValidateGuest', [&$json]);

        // Actionlog: track billing complete (guest path)
        if (empty($json['error'])) {
            $this->app->getDispatcher()->dispatch(
                'onJ2CommerceCheckoutBillingComplete',
                new \Joomla\Event\Event('onJ2CommerceCheckoutBillingComplete', [])
            );
        }

        $this->jsonResponse($json);
    }

    // =========================================================================
    // STEP 2: Billing Address
    // =========================================================================

    public function billingAddress(): void
    {
        $this->validateAjaxToken() or $this->jsonResponse(['error' => ['warning' => Text::_('JINVALID_TOKEN')]]);

        $user    = $this->app->getIdentity();
        $session = $this->app->getSession();

        $addresses        = [];
        $billingAddressId = '';

        if ($user && $user->id) {
            $addressModel = $this->getMvcFactory()->createModel('Addresses', 'Administrator', ['ignore_request' => true]);

            if ($addressModel && method_exists($addressModel, 'getAddressesByUser')) {
                $addresses = $addressModel->getAddressesByUser((int) $user->id);
            }

            $billingAddressId = $session->get('billing_address_id', '', 'j2commerce');
        }

        $order        = $this->getCartOrder();
        $showShipping = $this->determineShowShipping($order);
        $fields       = CustomFieldHelper::getFieldsByArea('billing');

        $this->renderStep('billing', [
            'addresses'        => $addresses,
            'billingAddressId' => $billingAddressId,
            'showShipping'     => $showShipping,
            'fields'           => $fields,
        ]);
    }

    public function billingAddressValidate(): void
    {
        $this->validateAjaxToken() or $this->jsonResponse(['error' => ['warning' => Text::_('JINVALID_TOKEN')]]);

        $user    = $this->app->getIdentity();
        $session = $this->app->getSession();
        $json    = [];

        if (!$user || !$user->id) {
            $json['redirect'] = $this->getCheckoutUrl();
            $this->jsonResponse($json);

            return;
        }

        $billingAddress = $this->input->getString('billing_address', 'existing');
        $addressId      = $this->input->getInt('address_id', 0);

        if ($billingAddress === 'existing' && $addressId > 0) {
            $session->set('billing_address_id', $addressId, 'j2commerce');

            $addressTable = $this->getMvcFactory()->createTable('Address', 'Administrator');

            if ($addressTable && $addressTable->load($addressId)) {
                // Verify address belongs to the current user
                if ((int) ($addressTable->user_id ?? 0) !== (int) $user->id) {
                    $json['error']['warning'] = Text::_('COM_J2COMMERCE_CHECKOUT_ERROR');
                    $this->jsonResponse($json);

                    return;
                }

                $countryId = (int) ($addressTable->country_id ?? 0);
                $zoneId    = (int) ($addressTable->zone_id ?? 0);
                $postcode  = $addressTable->zip ?? '';

                if (empty($countryId)) {
                    $store     = J2CommerceHelper::storeProfile();
                    $countryId = (int) $store->get('country_id', 0);
                }

                $session->set('billing_country_id', $countryId, 'j2commerce');
                $session->set('billing_zone_id', $zoneId, 'j2commerce');
                $session->set('billing_postcode', $postcode, 'j2commerce');
            }

            $session->clear('payment_method', 'j2commerce');
            $session->clear('payment_methods', 'j2commerce');
        } else {
            $formData = $this->collectFormData();
            $fields   = CustomFieldHelper::getFieldsByArea('billing');
            $errors   = CustomFieldHelper::validateFields($fields, $formData);

            if ($errors) {
                $json['error'] = $errors;
                $this->jsonResponse($json);

                return;
            }

            $addressData            = CustomFieldHelper::collectAddressData($fields, $formData);
            $addressData['user_id'] = (int) $user->id;
            $addressData['email']   = $formData['email'] ?? $user->email;
            $addressData['type']    = 'billing';

            $newAddressId = $this->saveAddress($addressData);

            if ($newAddressId) {
                $session->set('billing_address_id', $newAddressId, 'j2commerce');
                $this->setBillingSession($addressData);
            } else {
                $errorDetail              = $this->lastAddressError ?? '';
                $json['error']['warning'] = Text::_('COM_J2COMMERCE_ADDRESS_SAVE_ERROR')
                    . ($errorDetail ? ' (' . $errorDetail . ')' : '');
                $this->jsonResponse($json);

                return;
            }

            $session->clear('payment_method', 'j2commerce');
            $session->clear('payment_methods', 'j2commerce');
        }

        // If "shipping same as billing" checkbox was checked, sync shipping address + geo data
        $shippingSameAsBilling = $this->input->getInt('shipping_address', 0);

        if ($shippingSameAsBilling) {
            $billingAddrId = $session->get('billing_address_id', 0, 'j2commerce');
            $session->set('shipping_address_id', $billingAddrId, 'j2commerce');

            // Sync geo data for tax/shipping rate calculations
            $session->set('shipping_country_id', $session->get('billing_country_id', 0, 'j2commerce'), 'j2commerce');
            $session->set('shipping_zone_id', $session->get('billing_zone_id', 0, 'j2commerce'), 'j2commerce');
            $session->set('shipping_postcode', $session->get('billing_postcode', '', 'j2commerce'), 'j2commerce');
        }

        J2CommerceHelper::plugin()->event('CheckoutValidateBilling', [&$json]);

        // Actionlog: track billing complete
        if (empty($json['error'])) {
            $this->app->getDispatcher()->dispatch(
                'onJ2CommerceCheckoutBillingComplete',
                new \Joomla\Event\Event('onJ2CommerceCheckoutBillingComplete', [])
            );
        }

        $this->jsonResponse($json);
    }

    // =========================================================================
    // STEP 3: Shipping Address
    // =========================================================================

    public function shippingAddress(): void
    {
        $this->validateAjaxToken() or $this->jsonResponse(['error' => ['warning' => Text::_('JINVALID_TOKEN')]]);

        $user     = $this->app->getIdentity();
        $session  = $this->app->getSession();
        $uaccount = $session->get('uaccount', '', 'j2commerce');
        $isGuest  = ($uaccount === 'guest');

        $addresses         = [];
        $shippingAddressId = '';

        if ($user && $user->id) {
            $addressModel = $this->getMvcFactory()->createModel('Addresses', 'Administrator', ['ignore_request' => true]);

            if ($addressModel && method_exists($addressModel, 'getAddressesByUser')) {
                $addresses = $addressModel->getAddressesByUser((int) $user->id);
            }

            $shippingAddressId = $session->get('shipping_address_id', '', 'j2commerce');
        }

        $area   = $isGuest ? 'guest_shipping' : 'shipping';
        $fields = CustomFieldHelper::getFieldsByArea($area);

        // Retrieve previously-entered guest shipping data from session for re-population
        $guestShippingData = [];

        if ($isGuest) {
            $guestShippingData = $session->get('guest_shipping', [], 'j2commerce');

            if (!\is_array($guestShippingData)) {
                $guestShippingData = [];
            }
        }

        $this->renderStep('shipping', [
            'addresses'         => $addresses,
            'shippingAddressId' => $shippingAddressId,
            'fields'            => $fields,
            'isGuest'           => $isGuest,
            'guestShippingData' => $guestShippingData,
        ]);
    }

    public function shippingAddressValidate(): void
    {
        $this->validateAjaxToken() or $this->jsonResponse(['error' => ['warning' => Text::_('JINVALID_TOKEN')]]);

        $user    = $this->app->getIdentity();
        $session = $this->app->getSession();
        $json    = [];

        if (!$user || !$user->id) {
            $json['redirect'] = $this->getCheckoutUrl();
            $this->jsonResponse($json);

            return;
        }

        $shippingAddress = $this->input->getString('shipping_address', 'existing');
        $addressId       = $this->input->getInt('address_id', 0);

        if ($shippingAddress === 'existing' && $addressId > 0) {
            $session->set('shipping_address_id', $addressId, 'j2commerce');

            $addressTable = $this->getMvcFactory()->createTable('Address', 'Administrator');

            if ($addressTable && $addressTable->load($addressId)) {
                // Verify address belongs to the current user
                if ((int) ($addressTable->user_id ?? 0) !== (int) $user->id) {
                    $json['error']['warning'] = Text::_('COM_J2COMMERCE_CHECKOUT_ERROR');
                    $this->jsonResponse($json);

                    return;
                }

                $session->set('shipping_country_id', (int) ($addressTable->country_id ?? 0), 'j2commerce');
                $session->set('shipping_zone_id', (int) ($addressTable->zone_id ?? 0), 'j2commerce');
                $session->set('shipping_postcode', $addressTable->zip ?? '', 'j2commerce');
            }

            $session->clear('shipping_method', 'j2commerce');
            $session->clear('shipping_methods', 'j2commerce');
        } else {
            $formData = $this->collectFormData();
            $fields   = CustomFieldHelper::getFieldsByArea('shipping');
            $errors   = CustomFieldHelper::validateFields($fields, $formData);

            if ($errors) {
                $json['error'] = $errors;
                $this->jsonResponse($json);

                return;
            }

            $addressData            = CustomFieldHelper::collectAddressData($fields, $formData);
            $addressData['user_id'] = (int) $user->id;
            $addressData['type']    = 'shipping';

            // Set email if not collected from shipping fields
            if (empty($addressData['email'])) {
                $addressData['email'] = $user->email;
            }

            $newAddressId = $this->saveAddress($addressData);

            if ($newAddressId) {
                $session->set('shipping_address_id', $newAddressId, 'j2commerce');
            }

            $this->setShippingSession($addressData);
            $session->clear('shipping_method', 'j2commerce');
            $session->clear('shipping_methods', 'j2commerce');
        }

        J2CommerceHelper::plugin()->event('BeforeCheckoutValidateShipping', [&$json]);

        // Actionlog: track shipping complete
        if (empty($json['error'])) {
            $this->app->getDispatcher()->dispatch(
                'onJ2CommerceCheckoutShippingComplete',
                new \Joomla\Event\Event('onJ2CommerceCheckoutShippingComplete', [])
            );
        }

        $this->jsonResponse($json);
    }

    // =========================================================================
    // STEP 3b: Guest Shipping Address
    // =========================================================================

    public function guestShippingValidate(): void
    {
        $this->validateAjaxToken() or $this->jsonResponse(['error' => ['warning' => Text::_('JINVALID_TOKEN')]]);

        $session  = $this->app->getSession();
        $json     = [];
        $formData = $this->collectFormData();

        $fields = CustomFieldHelper::getFieldsByArea('guest_shipping');
        $errors = CustomFieldHelper::validateFields($fields, $formData);

        if ($errors) {
            $json['error'] = $errors;
            $this->jsonResponse($json);

            return;
        }

        $addressData = CustomFieldHelper::collectAddressData($fields, $formData);
        $session->set('guest_shipping', $addressData, 'j2commerce');

        $this->setShippingSession($addressData);
        $session->clear('shipping_method', 'j2commerce');
        $session->clear('shipping_methods', 'j2commerce');

        J2CommerceHelper::plugin()->event('BeforeCheckoutValidateGuestShipping', [&$json]);

        // Actionlog: track shipping complete (guest path)
        if (empty($json['error'])) {
            $this->app->getDispatcher()->dispatch(
                'onJ2CommerceCheckoutShippingComplete',
                new \Joomla\Event\Event('onJ2CommerceCheckoutShippingComplete', [])
            );
        }

        $this->jsonResponse($json);
    }

    // =========================================================================
    // STEP 4: Shipping & Payment Method
    // =========================================================================

    public function shippingPaymentMethod(): void
    {
        $this->validateAjaxToken() or $this->jsonResponse(['error' => ['warning' => Text::_('JINVALID_TOKEN')]]);

        $session             = $this->app->getSession();
        $order               = $this->getCartOrder();
        $showShipping        = $this->determineShowShipping($order);
        $showShippingMethods = $this->determineShowShippingMethods($order);

        $shippingRates  = [];
        $shippingValues = $session->get('shipping_values', [], 'j2commerce');

        if ($showShippingMethods && $order) {
            $shippingResults = J2CommerceHelper::plugin()->eventWithArray('GetShippingRates', [$order]);

            foreach ($shippingResults as $result) {
                if (\is_array($result) && isset($result['element'])) {
                    $shippingRates[] = $result;
                } elseif (\is_array($result)) {
                    $shippingRates = array_merge($shippingRates, $result);
                }
            }

            // Allow plugins to filter the combined rates (e.g., exclusions)
            $filterEvent = new \Joomla\Event\Event('onJ2CommerceFilterShippingRates', [
                'rates' => $shippingRates,
                'order' => $order,
            ]);
            $this->app->getDispatcher()->dispatch('onJ2CommerceFilterShippingRates', $filterEvent);
            $shippingRates = $filterEvent->getArgument('rates', $shippingRates);

            // Sort rates by price (cheapest first)
            usort($shippingRates, function (array $a, array $b): int {
                return ((float) ($a['price'] ?? 0)) <=> ((float) ($b['price'] ?? 0));
            });

            // Auto-select cheapest rate if no selection exists or previous selection is no longer available
            if (!empty($shippingRates)) {
                $existingName            = $shippingValues['shipping_name'] ?? '';
                $selectionStillAvailable = false;

                $matchedRate = null;

                if ($existingName !== '') {
                    foreach ($shippingRates as $rate) {
                        if (($rate['name'] ?? '') === $existingName) {
                            $selectionStillAvailable = true;
                            $matchedRate             = $rate;
                            break;
                        }
                    }
                }

                // Refresh session with current rate data (tax amounts may have changed)
                if ($selectionStillAvailable && $matchedRate !== null) {
                    $shippingValues['shipping_tax']          = (string) ((float) ($matchedRate['tax'] ?? 0));
                    $shippingValues['shipping_tax_class_id'] = (int) ($matchedRate['tax_class_id'] ?? 0);
                    $shippingValues['shipping_price']        = (string) ((float) ($matchedRate['price'] ?? 0));
                    $session->set('shipping_values', $shippingValues, 'j2commerce');
                }

                if (!$selectionStillAvailable) {
                    $cheapest       = $shippingRates[0];
                    $shippingValues = [
                        'shipping_plugin'       => $cheapest['element'] ?? '',
                        'shipping_name'         => $cheapest['name'] ?? '',
                        'shipping_price'        => (string) ((float) ($cheapest['price'] ?? 0)),
                        'shipping_code'         => $cheapest['code'] ?? '',
                        'shipping_tax'          => (string) ((float) ($cheapest['tax'] ?? 0)),
                        'shipping_tax_class_id' => (int) ($cheapest['tax_class_id'] ?? 0),
                        'shipping_extra'        => $cheapest['extra'] ?? '',
                    ];
                    $session->set('shipping_values', $shippingValues, 'j2commerce');
                    $session->set('shipping_method', $shippingValues['shipping_plugin'], 'j2commerce');
                }
            }
        }

        $paymentMethods = [];
        $paymentResults = J2CommerceHelper::plugin()->eventWithArray('GetPaymentPlugins', [$order]);

        foreach ($paymentResults as $result) {
            if (\is_array($result) && isset($result['element'])) {
                // Single payment method array from event result
                $paymentMethods[] = $result;
            } elseif (\is_array($result)) {
                // Array of payment method arrays (legacy compatibility)
                $paymentMethods = array_merge($paymentMethods, $result);
            }
        }

        $defaultPaymentMethod = J2CommerceHelper::config()->get('default_payment_method', '');
        $selectedPayment      = $session->get('payment_method', $defaultPaymentMethod, 'j2commerce');

        $showPayment = true;

        if ($order && (float) ($order->order_total ?? 0) === 0.0) {
            $showPayment = false;
            J2CommerceHelper::plugin()->event('ChangeShowPaymentOnTotalZero', [$order, &$showPayment]);
        }

        $this->renderStep('shipping_payment', [
            'order'               => $order,
            'showShipping'        => $showShipping,
            'showShippingMethods' => $showShippingMethods,
            'shippingRates'       => $shippingRates,
            'shippingValues'      => $shippingValues,
            'paymentMethods'      => $paymentMethods,
            'selectedPayment'     => $selectedPayment,
            'showPayment'         => $showPayment,
            'showTerms'           => (int) J2CommerceHelper::config()->get('show_terms', 0),
            'termsDisplayType'    => J2CommerceHelper::config()->get('terms_display_type', 'link'),
        ]);
    }

    public function shippingPaymentMethodValidate(): void
    {
        $this->validateAjaxToken() or $this->jsonResponse(['error' => ['warning' => Text::_('JINVALID_TOKEN')]]);

        $session = $this->app->getSession();
        $params  = J2CommerceHelper::config();
        $json    = [];

        // Allowlist known checkout fields
        $allowedFields = [
            'shipping_plugin', 'shipping_price', 'shipping_name',
            'shipping_code', 'shipping_tax', 'shipping_tax_class_id', 'shipping_extra',
            'payment_plugin', 'tos_check', 'shippingrequired',
        ];

        $values = [];

        foreach ($allowedFields as $field) {
            $raw = $this->input->getString($field, null);

            if ($raw !== null) {
                $values[$field] = $raw;
            }
        }

        // Capture payment plugin custom fields (prefixed with payment_)
        $postData = $this->input->post->getArray();

        foreach ($postData as $key => $val) {
            $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);

            if (str_starts_with($key, 'payment_') && !isset($values[$key]) && \is_string($val)) {
                $values[$key] = $this->input->getString($key, '');
            }
        }

        $order               = $this->getCartOrder();
        $showShippingMethods = $this->determineShowShippingMethods($order);

        if ($showShippingMethods && $order) {
            $shippingRequired = $this->input->getInt('shippingrequired', 0);

            if ($shippingRequired && empty($values['shipping_plugin'] ?? '')) {
                $json['error']['shipping'] = Text::_('COM_J2COMMERCE_CHECKOUT_SELECT_A_SHIPPING_METHOD');
            } else {
                $shippingValues = [
                    'shipping_plugin'       => $values['shipping_plugin'] ?? '',
                    'shipping_name'         => $values['shipping_name'] ?? '',
                    'shipping_price'        => $values['shipping_price'] ?? 0,
                    'shipping_code'         => $values['shipping_code'] ?? '',
                    'shipping_tax'          => $values['shipping_tax'] ?? 0,
                    'shipping_tax_class_id' => (int) ($values['shipping_tax_class_id'] ?? 0),
                    'shipping_extra'        => $values['shipping_extra'] ?? '',
                ];
                $session->set('shipping_values', $shippingValues, 'j2commerce');
                $session->set('shipping_method', $values['shipping_plugin'] ?? '', 'j2commerce');
            }
        }

        if (!$json) {
            $showPayment = true;

            if ($order && (float) ($order->order_total ?? 0) === 0.0) {
                $showPayment = false;
                J2CommerceHelper::plugin()->event('ChangeShowPaymentOnTotalZero', [$order, &$showPayment]);
            }

            if ($showPayment) {
                $paymentPlugin = $this->input->getString('payment_plugin', '');

                if (empty($paymentPlugin)) {
                    $json['error']['warning'] = Text::_('COM_J2COMMERCE_CHECKOUT_ERROR_PAYMENT_METHOD');
                }
            }

            if ($params->get('show_terms', 0) && $params->get('terms_display_type', 'link') === 'checkbox') {
                $tosCheck = $this->input->get('tos_check');

                if (empty($tosCheck)) {
                    $json['error']['tos_check'] = Text::_('COM_J2COMMERCE_CHECKOUT_ERROR_AGREE_TERMS');
                }
            }

            if (!$json) {
                $paymentPlugin = $this->input->getString('payment_plugin', '');
                $session->set('payment_values', $values, 'j2commerce');
                $session->set('payment_method', $paymentPlugin, 'j2commerce');
            }
        }

        $this->jsonResponse($json);
    }

    // =========================================================================
    // STEP 4b: Custom Checkout Steps (plugin-provided)
    // =========================================================================

    public function getCustomSteps(): void
    {
        $this->validateAjaxToken() or $this->jsonResponse(['error' => ['warning' => Text::_('JINVALID_TOKEN')]]);

        $position = $this->input->getString('position', 'after_billing');
        $order    = $this->getCartOrder();
        $items    = $order ? $order->getItems() : [];

        $context = [
            'items'   => $items,
            'order'   => $order,
            'session' => $this->app->getSession(),
            'user'    => $this->app->getIdentity(),
        ];

        $html     = CheckoutStepsHelper::renderSteps($position, $context);
        $hasSteps = !empty(trim($html));
        $heading  = $hasSteps ? CheckoutStepsHelper::getHeading($position, $context) : '';

        $this->jsonResponse([
            'html'     => $html,
            'hasSteps' => $hasSteps,
            'heading'  => $heading,
        ]);
    }

    public function saveCustomSteps(): void
    {
        $this->validateAjaxToken() or $this->jsonResponse(['error' => ['warning' => Text::_('JINVALID_TOKEN')]]);

        $position = $this->input->getString('position', 'after_billing');
        $order    = $this->getCartOrder();
        $items    = $order ? $order->getItems() : [];

        $context = [
            'items'   => $items,
            'order'   => $order,
            'session' => $this->app->getSession(),
            'user'    => $this->app->getIdentity(),
        ];

        $steps     = CheckoutStepsHelper::getStepsForPosition($position, $context);
        $postData  = $this->input->post->getArray();
        $allErrors = [];

        foreach ($steps as $step) {
            $errors = $step->validate($postData, $context);

            foreach ($errors as $field => $message) {
                $allErrors[$field] = Text::_($message);
            }
        }

        if ($allErrors) {
            $this->jsonResponse(['error' => $allErrors]);

            return;
        }

        foreach ($steps as $step) {
            $step->save($postData, $context);
        }

        $this->jsonResponse([]);
    }

    // =========================================================================
    // STEP 5: Confirm Order
    // =========================================================================

    public function confirm(): void
    {
        $this->validateAjaxToken() or $this->jsonResponse(['error' => ['warning' => Text::_('JINVALID_TOKEN')]]);

        UtilitiesHelper::sendNoCacheHeaders();

        $session = $this->app->getSession();
        $errors  = [];

        if ($session->has('payment_values', 'j2commerce')) {
            $paymentValues = $session->get('payment_values', [], 'j2commerce');

            foreach ($paymentValues as $name => $value) {
                if (\is_string($value)) {
                    $this->input->set($name, $value);
                }
            }
        }

        try {
            $order = $this->getCartOrder();

            if ($order && method_exists($order, 'validateOrder')) {
                $order->validateOrder($order);
            }

            J2CommerceHelper::plugin()->event('AfterOrderValidate', [&$order]);
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }

        $orderpaymentType = $session->get('payment_method', '', 'j2commerce');

        $showPayment  = true;
        $freeRedirect = '';

        if ($order && (float) ($order->order_total ?? 0) === 0.0) {
            $showPayment      = false;
            $orderpaymentType = Text::_('COM_J2COMMERCE_PAYMENT_FREE');
            J2CommerceHelper::plugin()->event('ChangeShowPaymentOnTotalZero', [$order, &$showPayment]);

            if ($showPayment) {
                $orderpaymentType = $session->get('payment_method', '', 'j2commerce');
            } else {
                $freeRedirect = Route::_('index.php?option=com_j2commerce&task=checkout.confirmPayment&' . Session::getFormToken() . '=1');
            }
        }

        if ($showPayment && empty(trim($orderpaymentType))) {
            $errors[] = Text::_('COM_J2COMMERCE_CHECKOUT_ERROR_PAYMENT_METHOD_NOT_SELECTED');
        }

        $pluginHtml = '';
        $orderItems = [];
        $taxes      = [];
        $shipping   = null;
        $coupons    = [];
        $vouchers   = [];

        if (!$errors && $order) {
            $order->orderpayment_type = $orderpaymentType;

            try {
                $savedOrder = $order->saveOrder();

                $this->app->setUserState('j2commerce.order_id', $savedOrder->order_id ?? null);
                $this->app->setUserState('j2commerce.orderpayment_id', $savedOrder->j2commerce_order_id ?? null);
                $this->app->setUserState('j2commerce.order_token', $savedOrder->token ?? null);

                if ($showPayment && !empty($orderpaymentType)) {
                    $paymentValues = [
                        'order_id'            => $savedOrder->order_id ?? '',
                        'orderpayment_id'     => $savedOrder->j2commerce_order_id ?? '',
                        'orderpayment_amount' => $savedOrder->order_total ?? 0,
                        'order'               => $savedOrder,
                    ];

                    $prePaymentResults = J2CommerceHelper::plugin()->eventWithArray('PrePayment', [$orderpaymentType, $paymentValues]);

                    foreach ($prePaymentResults as $result) {
                        $pluginHtml .= $result;
                    }
                }

                $order = $savedOrder;
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($order) {
            $orderItems = \is_object($order) && method_exists($order, 'getItems') ? $order->getItems() : [];
            $taxes      = \is_object($order) && method_exists($order, 'getOrderTaxrates') ? $order->getOrderTaxrates() : [];
            $shipping   = \is_object($order) && method_exists($order, 'getOrderShippingRate') ? $order->getOrderShippingRate() : null;
            $coupons    = \is_object($order) && method_exists($order, 'getOrderCoupons') ? $order->getOrderCoupons() : [];
            $vouchers   = \is_object($order) && method_exists($order, 'getOrderVouchers') ? $order->getOrderVouchers() : [];
        }

        J2CommerceHelper::plugin()->event('BeforeCheckoutConfirm', [$this]);

        $this->renderStep('confirm', [
            'order'         => $order,
            'items'         => $orderItems,
            'taxes'         => $taxes,
            'shipping'      => $shipping,
            'coupons'       => $coupons,
            'vouchers'      => $vouchers,
            'plugin_html'   => $pluginHtml,
            'showPayment'   => $showPayment,
            'free_redirect' => $freeRedirect,
            'errors'        => $errors,
        ]);
    }

    public function confirmPayment(): void
    {
        UtilitiesHelper::sendNoCacheHeaders();

        $orderpaymentType = $this->input->getString('orderpayment_type', '');

        if (empty($orderpaymentType)) {
            Session::checkToken('get') or $this->app->redirect($this->getCheckoutUrl());
        }

        $session        = $this->app->getSession();
        $params         = J2CommerceHelper::config();
        $orderpaymentId = (int) $this->app->getUserState('j2commerce.orderpayment_id', 0);
        $orderId        = $this->app->getUserState('j2commerce.order_id', '');

        $orderTable = $this->getMvcFactory()->createTable('Order', 'Administrator');

        if ($orderTable) {
            $orderTable->load(['order_id' => $orderId]);
        }

        // Save customer_note from the confirm step (textarea moved here from shipping/payment step)
        $customerNote = strip_tags($this->input->getString('customer_note', ''));

        if ($orderTable && !empty($customerNote) && !empty($orderId)) {
            $orderTable->customer_note = $customerNote;
            $orderTable->store();
        }

        // ---------------------------------------------------------------
        // Save guest info before payment processing (session may change).
        // Cart and session clearing are deferred until AFTER payment
        // succeeds, so a failed payment does not empty the cart.
        // ---------------------------------------------------------------
        $user = $this->app->getIdentity();

        if (!$user || !$user->id) {
            $guest = $session->get('guest', [], 'j2commerce');

            if (\is_array($guest) && !empty($guest['email'])) {
                $session->set('guest_order_email', $guest['email'], 'j2commerce');
            }

            if (isset($orderTable->token) && !empty($orderTable->token)) {
                $session->set('guest_order_token', $orderTable->token, 'j2commerce');
            }
        }

        // ---------------------------------------------------------------
        // Process payment via plugin events
        // ---------------------------------------------------------------
        $html = '';

        $showPayment = false;
        J2CommerceHelper::plugin()->event('ChangeShowPaymentOnTotalZero', [$orderTable, &$showPayment]);

        if (!empty($orderId) && (float) ($orderTable->order_total ?? 0) === 0.0 && !$showPayment) {
            if (method_exists($orderTable, 'payment_complete')) {
                $orderTable->payment_complete();
            }

            OrderHistoryHelper::add(
                orderId: $orderId,
                comment: Text::_('COM_J2COMMERCE_ORDER_HISTORY_PAYMENT_COMPLETE'),
                orderStateId: (int) ($orderTable->order_state_id ?? 1),
            );

            J2CommerceHelper::plugin()->event('AfterConfirmFreeProduct', [$orderTable]);

            // Payment succeeded — clear cart and checkout session
            $this->clearCartAndSession($orderId, $session);

            // Send order confirmation emails for free orders
            $this->sendOrderEmails($orderId);
        } else {
            $values = [
                'order_id'       => $orderId,
                'order_state_id' => 1,
            ];

            $results = J2CommerceHelper::plugin()->eventWithArray('PostPayment', [$orderpaymentType, $values]);

            // Check if a payment plugin returned JSON (AJAX paction=process).
            // Plugins return JSON instead of calling $app->close() so that
            // the controller can reliably dispatch onJ2CommerceAfterPayment.
            foreach ($results as $result) {
                if (\is_string($result) && ($decoded = json_decode($result, true)) !== null) {
                    // Only clear cart and send emails for successful results (not errors)
                    $isError = isset($decoded['error']);

                    if (!$isError) {
                        $orderTable->load(['order_id' => $orderId]);

                        $this->clearCartAndSession($orderId, $session);

                        if (!empty($orderTable->order_id)) {
                            J2CommerceHelper::plugin()->event('AfterPayment', [$orderTable]);
                            $this->sendOrderEmails($orderId);
                        }
                    }

                    echo json_encode($decoded);
                    $this->app->close();
                }

                $html .= $result;
            }

            $orderTable->load(['order_id' => $orderId]);
        }

        if (isset($orderTable->order_id) && !empty($orderTable->order_id)) {
            $results = J2CommerceHelper::plugin()->eventWithArray('AfterPayment', [$orderTable]);

            foreach ($results as $result) {
                $html .= $result;
            }

            // Send order confirmation emails (non-AJAX payment path)
            $this->sendOrderEmails($orderId);
        }

        // Clear cart only after confirmed success. When paction=process and
        // the plugin did NOT redirect, payment likely failed — preserve cart.
        // Cart is cleared on the subsequent paction=display call instead.
        $paction = $this->input->getString('paction', '');

        if ($paction !== 'process') {
            $this->clearCartAndSession($orderId, $session);
        }

        // Store plugin HTML in user state for the confirmation view to retrieve
        $this->app->setUserState('j2commerce.confirmation_plugin_html', $html);

        // Clear order IDs from session (no longer needed)
        $this->app->setUserState('j2commerce.order_id', null);
        $this->app->setUserState('j2commerce.orderpayment_id', null);

        // Redirect to the dedicated confirmation view with order_id and token in URL
        $confirmUrl = Route::_(
            'index.php?option=com_j2commerce&view=confirmation&order_id=' . urlencode($orderId)
            . '&token=' . urlencode($orderTable->token ?? ''),
            false
        );
        $this->app->redirect($confirmUrl);
    }

    private function clearCartAndSession(string $orderId, \Joomla\CMS\Session\Session $session): void
    {
        if (!empty($orderId)) {
            CartHelper::emptyCart($orderId);
        }

        $session->clear('shipping_method', 'j2commerce');
        $session->clear('shipping_methods', 'j2commerce');
        $session->clear('payment_method', 'j2commerce');
        $session->clear('payment_methods', 'j2commerce');
        $session->clear('payment_values', 'j2commerce');
        $session->clear('guest', 'j2commerce');
        $session->clear('guest_shipping', 'j2commerce');
        $session->clear('customer_note', 'j2commerce');
        $session->clear('order_fees', 'j2commerce');

        J2CommerceHelper::plugin()->event('CheckoutCleanup', [$session]);
    }

    private function sendOrderEmails(string $orderId): void
    {
        try {
            $orderModel = $this->app->bootComponent('com_j2commerce')->getMVCFactory()
                ->createModel('Order', 'Administrator');
            $orderModel->sendOrderNotification($orderId, true, true);
        } catch (\Throwable $e) {
            \Joomla\CMS\Log\Log::add(
                'Order email send failed for ' . $orderId . ': ' . $e->getMessage(),
                \Joomla\CMS\Log\Log::ERROR,
                'com_j2commerce'
            );
        }
    }

    // =========================================================================
    // AJAX: PayPal Smart Payment Buttons Integration
    // =========================================================================

    public function createPayPalOrder(): void
    {
        $rawInput = file_get_contents('php://input');
        $input    = json_decode($rawInput, true) ?? [];

        // Validate CSRF token from JSON body
        $tokenName = Session::getFormToken();

        if (empty($input[$tokenName])) {
            $this->jsonResponse(['success' => false, 'error' => Text::_('JINVALID_TOKEN')]);
        }

        $orderId = $input['order_id'] ?? '';

        if (empty($orderId)) {
            $this->jsonResponse(['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')]);
        }

        // Dispatch to payment plugin via event
        $event  = J2CommerceHelper::plugin()->event('PaymentCreateOrder', ['payment_paypal', $input]);
        $result = $event->getArgument('result', ['success' => false, 'error' => 'No payment plugin responded']);

        $this->jsonResponse($result);
    }

    public function capturePayPalOrder(): void
    {
        $rawInput = file_get_contents('php://input');
        $input    = json_decode($rawInput, true) ?? [];

        // Validate CSRF token from JSON body
        $tokenName = Session::getFormToken();

        if (empty($input[$tokenName])) {
            $this->jsonResponse(['success' => false, 'error' => Text::_('JINVALID_TOKEN')]);
        }

        $paypalOrderId = $input['paypal_order_id'] ?? '';
        $orderId       = $input['order_id'] ?? '';

        if (empty($paypalOrderId) || empty($orderId)) {
            $this->jsonResponse(['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')]);
        }

        $event  = J2CommerceHelper::plugin()->event('PaymentCaptureOrder', ['payment_paypal', $input]);
        $result = $event->getArgument('result', ['success' => false, 'error' => 'No payment plugin responded']);

        $this->jsonResponse($result);
    }

    // =========================================================================
    // AJAX: Save Shipping Selection (lightweight, no full validation)
    // =========================================================================

    public function saveShippingSelection(): void
    {
        UtilitiesHelper::sendNoCacheHeaders();
        header('Content-Type: application/json; charset=utf-8');

        $json = [];

        if (!$this->validateAjaxToken()) {
            $json['success'] = false;
            $json['message'] = Text::_('JINVALID_TOKEN');
            echo json_encode($json);
            $this->app->close();

            return;
        }

        try {
            $session = $this->app->getSession();

            $shippingValues = [
                'shipping_plugin'       => $this->input->getString('shipping_plugin', ''),
                'shipping_name'         => $this->input->getString('shipping_name', ''),
                'shipping_price'        => $this->input->getString('shipping_price', '0'),
                'shipping_code'         => $this->input->getString('shipping_code', ''),
                'shipping_tax'          => $this->input->getString('shipping_tax', '0'),
                'shipping_tax_class_id' => $this->input->getInt('shipping_tax_class_id', 0),
                'shipping_extra'        => $this->input->getString('shipping_extra', ''),
            ];

            $session->set('shipping_values', $shippingValues, 'j2commerce');
            $session->set('shipping_method', $shippingValues['shipping_plugin'], 'j2commerce');

            $json['success'] = true;
        } catch (\Exception $e) {
            $json['success'] = false;
            $json['message'] = $e->getMessage();
        }

        echo json_encode($json);
        $this->app->close();
    }

    // =========================================================================
    // AJAX: Sidecart Refresh
    // =========================================================================

    public function refreshSidecart(): void
    {
        UtilitiesHelper::sendNoCacheHeaders();
        header('Content-Type: application/json; charset=utf-8');

        $json = [];

        if (!$this->validateAjaxToken()) {
            $json['success'] = false;
            $json['message'] = Text::_('JINVALID_TOKEN');
            echo json_encode($json);
            $this->app->close();

            return;
        }

        try {
            $view = $this->getCheckoutView();

            $order = $this->getCartOrder();
            $items = $order ? $order->getItems() : [];

            $view->order = $order;
            $view->items = $items;
            $view->taxes = ($order && method_exists($order, 'getOrderTaxrates')) ? $order->getOrderTaxrates() : [];

            ob_start();
            $view->setLayout('default');
            echo $view->loadTemplate('sidecart');
            $html = ob_get_clean();

            $json['success'] = true;
            $json['html']    = $html;
        } catch (\Exception $e) {
            $json['success'] = false;
            $json['message'] = $e->getMessage();
        }

        echo json_encode($json);
        $this->app->close();
    }

    /**
     * Validate CSRF token for AJAX endpoints without triggering redirects.
     *
     * Joomla's Session::checkToken() redirects to the homepage when the session
     * is new (after login or session regeneration). This breaks AJAX endpoints.
     */
    private function validateAjaxToken(): bool
    {
        $token = Session::getFormToken();

        if ($token === $this->input->server->get('HTTP_X_CSRF_TOKEN', '', 'alnum')) {
            return true;
        }

        return (bool) $this->input->post->get($token, '', 'alnum');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function getCartOrder(): ?object
    {
        $cartsModel = $this->getMvcFactory()->createModel('Carts', 'Site', ['ignore_request' => true]);

        if (!$cartsModel) {
            return null;
        }

        $cartsModel->getState();

        $user = $this->app->getIdentity();

        if ($user && $user->id) {
            $cartsModel->setState('filter.user_id', (int) $user->id);
        }

        return $cartsModel->getOrder();
    }

    protected function determineShowShipping(?object $order = null): bool
    {
        $params = J2CommerceHelper::config();

        if ($params->get('show_shipping_address', 0)) {
            return true;
        }

        if ($order && method_exists($order, 'getItems')) {
            foreach ($order->getItems() as $item) {
                if (!empty($item->shipping)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function determineShowShippingMethods(?object $order = null): bool
    {
        if ($order && method_exists($order, 'getItems')) {
            foreach ($order->getItems() as $item) {
                if (!empty($item->shipping)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function getUserFirstAddress(int $userId): ?object
    {
        $db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_addresses'))
            ->where($db->quoteName('user_id') . ' = :userId')
            ->bind(':userId', $userId, \Joomla\Database\ParameterType::INTEGER)
            ->setLimit(1);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }
}
