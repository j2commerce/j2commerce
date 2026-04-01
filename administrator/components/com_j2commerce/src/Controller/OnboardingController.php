<?php

declare(strict_types=1);

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OnboardingHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class OnboardingController extends BaseController
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
     * Save a single onboarding step.
     * POST params: step (int 1-6) + step-specific form fields.
     */
    public function saveStep(): void
    {
        if (!Session::checkToken()) {
            $this->jsonError(Text::_('JINVALID_TOKEN'), 403);
            return;
        }

        if (!$this->requireAdmin()) {
            return;
        }

        $step = $this->input->getInt('step', 0);

        try {
            $result = match ($step) {
                1 => $this->saveStep1(),
                2 => $this->saveStep2(),
                3 => $this->saveStep3(),
                4 => $this->saveStep4(),
                5 => $this->saveStep5(),
                6 => $this->saveStep6(),
                default => throw new \InvalidArgumentException('Invalid step number'),
            };

            $this->jsonSuccess($result);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * Dismiss onboarding without completing it.
     */
    public function dismiss(): void
    {
        if (!Session::checkToken()) {
            $this->jsonError(Text::_('JINVALID_TOKEN'), 403);
            return;
        }

        if (!$this->requireAdmin()) {
            return;
        }

        try {
            OnboardingHelper::persistConfig(['onboarding_complete' => '1']);
            $this->jsonSuccess(null, Text::_('COM_J2COMMERCE_ONBOARDING_BTN_DISMISS'));
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * Download and install en-US language, set as default.
     * Only triggered for country_id 223 (US).
     */
    public function installLanguage(): void
    {
        if (!Session::checkToken()) {
            $this->jsonError(Text::_('JINVALID_TOKEN'), 403);
            return;
        }

        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $lang = $this->input->getString('lang', '');

            if ($lang !== 'en-US') {
                throw new \InvalidArgumentException('Only en-US installation is supported');
            }

            // Fetch the details XML to get the actual download URL.
            // This mirrors the Joomla admin UI: install_url = detailsurl → InstallModel.
            $detailsUrl = 'https://update.joomla.org/language/details6/en-US_details.xml';

            $response = (new \Joomla\Http\HttpFactory())->getHttp()->get($detailsUrl);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException(Text::_('COM_J2COMMERCE_ONBOARDING_LANG_NOT_FOUND'));
            }

            $xml = simplexml_load_string((string) $response->getBody());

            if (!$xml || !isset($xml->update->downloads->downloadurl)) {
                throw new \RuntimeException(Text::_('COM_J2COMMERCE_ONBOARDING_LANG_NOT_FOUND'));
            }

            $downloadUrl = (string) $xml->update->downloads->downloadurl;

            if ($downloadUrl === '') {
                throw new \RuntimeException(Text::_('COM_J2COMMERCE_ONBOARDING_LANG_NOT_FOUND'));
            }

            // Download the ZIP package
            $packageFile = InstallerHelper::downloadPackage($downloadUrl);

            if (!$packageFile) {
                throw new \RuntimeException(Text::_('COM_J2COMMERCE_ONBOARDING_LANG_NOT_FOUND'));
            }

            $tmpPath = $this->app->get('tmp_path', JPATH_ROOT . '/tmp');
            $package = InstallerHelper::unpack($tmpPath . '/' . $packageFile, true);

            if (!$package || !isset($package['dir'])) {
                throw new \RuntimeException(Text::_('COM_J2COMMERCE_ONBOARDING_LANG_NOT_FOUND'));
            }

            $installer = Installer::getInstance();
            $result    = $installer->install($package['dir']);

            InstallerHelper::cleanupInstall($package['packagefile'] ?? '', $package['extractdir'] ?? '');

            if (!$result) {
                throw new \RuntimeException(Text::_('COM_J2COMMERCE_ONBOARDING_LANG_NOT_FOUND'));
            }

            // Set en-US as default for both site and admin
            $db = $this->getDb();

            // Ensure en-US is published in #__languages
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__languages'))
                ->set($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('lang_code') . ' = ' . $db->quote('en-US'));
            $db->setQuery($query)->execute();

            // Update com_languages params
            $query = $db->getQuery(true)
                ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_languages'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'));

            $ext = $db->setQuery($query)->loadObject();

            if ($ext) {
                $params = new \Joomla\Registry\Registry($ext->params);
                $params->set('site', 'en-US');
                $params->set('administrator', 'en-US');
                $paramsJson = $params->toString();
                $extId      = (int) $ext->extension_id;

                $update = $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('params') . ' = :params')
                    ->where($db->quoteName('extension_id') . ' = :id')
                    ->bind(':params', $paramsJson)
                    ->bind(':id', $extId, ParameterType::INTEGER);
                $db->setQuery($update)->execute();
            }

            $this->jsonSuccess(
                ['installed' => true],
                Text::_('COM_J2COMMERCE_ONBOARDING_LANG_SUCCESS'),
            );
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // Per-step save logic
    // =========================================================================

    private function saveStep1(): array
    {
        $countryId = $this->input->getInt('country_id', 0);
        $weightId  = $this->input->getInt('config_weight_class_id', 1);
        $lengthId  = $this->input->getInt('config_length_class_id', 1);

        $config = [
            'store_name'             => $this->input->getString('store_name', ''),
            'store_address_1'        => $this->input->getString('store_address_1', ''),
            'store_address_2'        => $this->input->getString('store_address_2', ''),
            'store_city'             => $this->input->getString('store_city', ''),
            'country_id'             => (string) $countryId,
            'zone_id'                => (string) $this->input->getInt('zone_id', 0),
            'store_zip'              => $this->input->getString('store_zip', ''),
            'admin_email'            => $this->input->getString('admin_email', ''),
            'config_weight_class_id' => (string) $weightId,
            'config_length_class_id' => (string) $lengthId,
            'onboarding_last_step'   => '1',
        ];

        // Validate required fields
        if (trim($config['store_name']) === '') {
            throw new \InvalidArgumentException(Text::_('COM_J2COMMERCE_ONBOARDING_ERR_REQUIRED'));
        }

        if ($countryId <= 0) {
            throw new \InvalidArgumentException(Text::_('COM_J2COMMERCE_ONBOARDING_ERR_REQUIRED'));
        }

        OnboardingHelper::persistConfig($config);

        // Sync measurement conversions relative to the selected defaults
        $db = $this->getDb();
        OnboardingHelper::syncWeights($weightId, $db);
        OnboardingHelper::syncLengths($lengthId, $db);

        // Swap address_1/address_2 ordering based on country convention
        OnboardingHelper::reorderAddressFields($countryId, $db);

        // Get recommended defaults for Step 2 pre-fill (currency only now)
        $defaults = OnboardingHelper::getCountryDefaults($countryId);

        // Check en-US language status (only for US)
        $languagePrompt = false;

        if ($countryId === 223) {
            $langStatus     = OnboardingHelper::isLanguageInstalled('en-US');
            $languagePrompt = !$langStatus['is_default'];
        }

        return [
            'defaults'       => $defaults,
            'languagePrompt' => $languagePrompt,
        ];
    }

    private function saveStep2(): array
    {
        $db           = $this->getDb();
        $currencyCode = $this->input->getString('config_currency', 'USD');
        $currencyMode = $this->input->getString('currency_mode', 'single');

        $config = [
            'config_currency'      => $currencyCode,
            'config_currency_auto' => (string) $this->input->getInt('config_currency_auto', 1),
            'onboarding_last_step' => '2',
        ];

        OnboardingHelper::persistConfig($config);

        // Currency mode
        if ($currencyMode === 'single') {
            OnboardingHelper::setSingleCurrency($currencyCode, $db);
        } else {
            OnboardingHelper::setBaseCurrencyValue($currencyCode, $db);
        }

        return [
            'currencyMode' => $currencyMode,
        ];
    }

    private function saveStep3(): array
    {
        $includingTax = $this->input->getInt('config_including_tax', 0);
        $taxPercent   = $this->input->getFloat('tax_percent', 0.0);

        $config = [
            'config_including_tax'   => (string) $includingTax,
            'onboarding_last_step'   => '3',
        ];

        OnboardingHelper::persistConfig($config);

        $taxCreated = false;
        $taxResult  = [];

        if ($taxPercent > 0.0 && $taxPercent <= 100.0) {
            $db        = $this->getDb();
            $countryId = (int) ConfigHelper::get('country_id', 0);
            $zoneId    = (int) ConfigHelper::get('zone_id', 0);

            $taxResult  = OnboardingHelper::createDefaultTax($countryId, $zoneId, $taxPercent, $db);
            $taxCreated = true;
        }

        return [
            'taxCreated' => $taxCreated,
            'taxPercent' => $taxPercent,
            'taxResult'  => $taxResult,
        ];
    }

    private function saveStep4(): array
    {
        $db = $this->getDb();
        $requireShipping   = $this->input->getInt('require_shipping', 1);
        $offerFreeShipping = $this->input->getInt('offer_free_shipping', 0);
        $shippingRateType  = $this->input->getString('shipping_rate_type', '');

        $config = [
            'onboarding_product_types'      => $this->input->getString('product_types', ''),
            'require_shipping'              => (string) $requireShipping,
            'onboarding_shipping_rate_type' => $shippingRateType,
            'onboarding_last_step'          => '4',
        ];

        OnboardingHelper::persistConfig($config);

        if ($requireShipping === 1) {
            // Free shipping configuration
            if ($offerFreeShipping === 1) {
                $minSubtotal = $this->input->getString('free_shipping_min_subtotal', '0');
                OnboardingHelper::updatePluginParam('shipping_free', 'j2commerce', [
                    'min_subtotal' => $minSubtotal,
                ], $db);
                OnboardingHelper::setPluginEnabled('shipping_free', 'j2commerce', 1, $db);
            } else {
                OnboardingHelper::setPluginEnabled('shipping_free', 'j2commerce', 0, $db);
            }

            // Fixed shipping rates (also applies to "both")
            if ($shippingRateType === 'fixed' || $shippingRateType === 'both') {
                $methodType = $this->input->getInt('shipping_method_type', 0);
                $methodName = $this->input->getString('shipping_method_name', 'Standard Shipping');
                $ratesJson  = $this->input->getRaw('shipping_rates');
                $rates      = json_decode($ratesJson ?: '[]', true) ?: [];

                if ($methodName !== '' && !empty($rates)) {
                    $methodId = OnboardingHelper::createShippingMethod($methodName, $methodType, $db);
                    OnboardingHelper::createShippingRates($methodId, $rates, $db);
                }
            }
        }

        return ['saved' => true, 'requireShipping' => $requireShipping];
    }

    private function saveStep5(): array
    {
        $db              = $this->getDb();
        $selectedPlugins = $this->input->getString('selected_payment_plugins', '');
        $selectedArray   = array_filter(explode(',', $selectedPlugins));
        $defaultPayment  = $this->input->getString('default_payment_method', '');

        // Get ALL payment plugins regardless of current enabled state
        $query = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('element')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('j2commerce'))
            ->where($db->quoteName('element') . ' LIKE ' . $db->quote('payment_%'));
        $allPlugins = $db->setQuery($query)->loadObjectList();

        // Publish selected, unpublish unselected
        foreach ($allPlugins as $plugin) {
            $shouldEnable = \in_array($plugin->element, $selectedArray, true);
            OnboardingHelper::setPluginEnabled($plugin->element, 'j2commerce', $shouldEnable ? 1 : 0, $db);
        }

        // PayPal credentials
        $paypalClientId     = $this->input->getString('paypal_client_id', '');
        $paypalClientSecret = $this->input->getString('paypal_client_secret', '');

        if (\in_array('payment_paypal', $selectedArray, true) && $paypalClientId !== '' && $paypalClientSecret !== '') {
            OnboardingHelper::updatePluginParam('payment_paypal', 'j2commerce', [
                'client_id'     => $paypalClientId,
                'client_secret' => $paypalClientSecret,
            ], $db);
        }

        // Validation: if no payment selected OR (PayPal only + no keys), unpublish all
        $paypalOnly      = \count($selectedArray) === 1 && $selectedArray[0] === 'payment_paypal';
        $paypalKeysEmpty = $paypalClientId === '' || $paypalClientSecret === '';

        if (empty($selectedArray) || ($paypalOnly && $paypalKeysEmpty)) {
            foreach ($allPlugins as $plugin) {
                OnboardingHelper::setPluginEnabled($plugin->element, 'j2commerce', 0, $db);
            }
            $paymentConfigured = false;
        } else {
            $paymentConfigured = true;
        }

        // Set default payment method
        if ($paymentConfigured && $defaultPayment !== '') {
            OnboardingHelper::persistConfig(['default_payment_method' => $defaultPayment]);
        }

        OnboardingHelper::persistConfig(['onboarding_last_step' => '5']);

        return [
            'saved'             => true,
            'paymentConfigured' => $paymentConfigured,
            'selectedPlugins'   => $selectedArray,
            'defaultPayment'    => $defaultPayment,
        ];
    }

    private function saveStep6(): array
    {
        OnboardingHelper::persistConfig([
            'onboarding_complete'  => '1',
            'onboarding_last_step' => '6',
        ]);

        $db = $this->getDb();

        // Get human-readable country name
        $countryId   = (int) ConfigHelper::get('country_id', 0);
        $countryName = $countryId > 0 ? $this->getCountryName($countryId) : '';

        $weightTitle = $this->getUnitTitle('#__j2commerce_weights', 'j2commerce_weight_id', 'weight_title', (int) ConfigHelper::get('config_weight_class_id', 1));
        $lengthTitle = $this->getUnitTitle('#__j2commerce_lengths', 'j2commerce_length_id', 'length_title', (int) ConfigHelper::get('config_length_class_id', 1));

        // Get the most recently created tax rate, if any
        $query = $db->getQuery(true)
            ->select($db->quoteName('tax_percent'))
            ->from($db->quoteName('#__j2commerce_taxrates'))
            ->order($db->quoteName('j2commerce_taxrate_id') . ' DESC')
            ->setLimit(1);
        $lastTaxPercent = $db->setQuery($query)->loadResult();

        $taxStyle = (int) ConfigHelper::get('config_including_tax', 0) === 1 ? 'Including tax' : 'Excluding tax';
        $tax      = $lastTaxPercent ? $lastTaxPercent . '% (' . $taxStyle . ')' : '';

        return [
            'storeName'    => ConfigHelper::get('store_name', ''),
            'countryName'  => $countryName,
            'currency'     => ConfigHelper::get('config_currency', 'USD'),
            'measurements' => trim($weightTitle . ' / ' . $lengthTitle, ' /'),
            'tax'          => $tax,
            'productTypes' => ConfigHelper::get('onboarding_product_types', ''),
            'shipping'     => $this->getShippingSummary(),
            'payment'      => $this->getPaymentSummary(),
        ];
    }

    private function getCountryName(int $countryId): string
    {
        $db    = $this->getDb();
        $query = $db->getQuery(true)
            ->select($db->quoteName('country_name'))
            ->from($db->quoteName('#__j2commerce_countries'))
            ->where($db->quoteName('j2commerce_country_id') . ' = :id')
            ->bind(':id', $countryId, ParameterType::INTEGER);

        return (string) $db->setQuery($query)->loadResult();
    }

    private function getUnitTitle(string $table, string $pkCol, string $titleCol, int $id): string
    {
        $db    = $this->getDb();
        $query = $db->getQuery(true)
            ->select($db->quoteName($titleCol))
            ->from($db->quoteName($table))
            ->where($db->quoteName($pkCol) . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        return (string) $db->setQuery($query)->loadResult();
    }

    private function getShippingSummary(): string
    {
        $requireShipping = (int) ConfigHelper::get('require_shipping', 1);

        if ($requireShipping === 0) {
            return Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_NOT_REQUIRED');
        }

        $db    = $this->getDb();
        $parts = [];

        // Check free shipping plugin
        $query = $db->getQuery(true)
            ->select([$db->quoteName('enabled'), $db->quoteName('params')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('shipping_free'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('j2commerce'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));
        $freePlugin = $db->setQuery($query)->loadObject();

        if ($freePlugin && (int) $freePlugin->enabled === 1) {
            $freeParams  = new \Joomla\Registry\Registry($freePlugin->params);
            $minSubtotal = (float) $freeParams->get('min_subtotal', 0);

            if ($minSubtotal > 0) {
                $parts[] = Text::sprintf('Free shipping (min %s)', number_format($minSubtotal, 2));
            } else {
                $parts[] = 'Free shipping';
            }
        }

        // Check standard shipping methods
        $typeLabels = [
            0 => Text::_('COM_J2COMMERCE_SHIPPING_TYPE_PER_ORDER_FLAT'),
            1 => Text::_('COM_J2COMMERCE_SHIPPING_TYPE_PER_ORDER_QUANTITY'),
            2 => Text::_('COM_J2COMMERCE_SHIPPING_TYPE_PER_ORDER_PRICE'),
            3 => Text::_('COM_J2COMMERCE_SHIPPING_TYPE_PER_ITEM_FLAT'),
            4 => Text::_('COM_J2COMMERCE_SHIPPING_TYPE_PER_ITEM_WEIGHT'),
            5 => Text::_('COM_J2COMMERCE_SHIPPING_TYPE_PER_ORDER_WEIGHT'),
            6 => Text::_('COM_J2COMMERCE_SHIPPING_TYPE_PER_ITEM_PERCENTAGE'),
        ];

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('m.shipping_method_name'),
                $db->quoteName('m.shipping_method_type'),
                $db->quoteName('m.j2commerce_shippingmethod_id', 'id'),
            ])
            ->from($db->quoteName('#__j2commerce_shippingmethods', 'm'))
            ->where($db->quoteName('m.published') . ' = 1');
        $methods = $db->setQuery($query)->loadObjectList();

        foreach ($methods as $method) {
            $type = (int) $method->shipping_method_type;
            $typeName = $typeLabels[$type] ?? '';
            $methodId = (int) $method->id;

            // Count rates for this method
            $rateQuery = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__j2commerce_shippingrates'))
                ->where($db->quoteName('shipping_method_id') . ' = :mid')
                ->bind(':mid', $methodId, ParameterType::INTEGER);
            $rateCount = (int) $db->setQuery($rateQuery)->loadResult();

            $label = $method->shipping_method_name;

            if ($typeName !== '') {
                $label .= ' (' . $typeName . ')';
            }

            if ($rateCount > 0) {
                $label .= ' — ' . $rateCount . ' ' . ($rateCount === 1 ? 'rate' : 'rates');
            }

            $parts[] = $label;
        }

        // Note if calculated carrier rates were also selected
        $shippingRateType = ConfigHelper::get('onboarding_shipping_rate_type', '');

        if ($shippingRateType === 'calculated' || $shippingRateType === 'both') {
            if (empty($methods)) {
                $parts[] = Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_CALCULATED_LATER');
            } else {
                $parts[] = 'Calculated carrier rates (configure separately)';
            }
        }

        if (empty($parts)) {
            return Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_CALCULATED_LATER');
        }

        return implode(' + ', $parts);
    }

    private function getPaymentSummary(): string
    {
        $db             = $this->getDb();
        $defaultPayment = ConfigHelper::get('default_payment_method', '');

        $plugins = OnboardingHelper::getPublishedPaymentPlugins($db);

        if (empty($plugins)) {
            return Text::_('COM_J2COMMERCE_ONBOARDING_PAYMENT_NOT_CONFIGURED');
        }

        $lang  = Factory::getApplication()->getLanguage();
        $names = [];

        foreach ($plugins as $plugin) {
            $langPrefix = 'plg_j2commerce_' . $plugin->element;
            $lang->load($langPrefix, JPATH_ADMINISTRATOR);
            $lang->load($langPrefix, JPATH_PLUGINS . '/j2commerce/' . $plugin->element);

            $params      = new \Joomla\Registry\Registry($plugin->params);
            $displayName = $params->get('display_name', '');

            if ($displayName === '') {
                $displayName = Text::_(strtoupper('PLG_J2COMMERCE_' . $plugin->element));
            }

            if ($plugin->element === $defaultPayment) {
                $displayName .= ' (default)';
            }

            $names[] = $displayName;
        }

        return implode(', ', $names);
    }
}
