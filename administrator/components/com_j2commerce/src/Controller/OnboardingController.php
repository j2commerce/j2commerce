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
     * POST params: step (int 1-5) + step-specific form fields.
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

            // Get available languages from update server
            $langModel = $this->app->bootComponent('com_installer')
                ->getMVCFactory()
                ->createModel('Languages', 'Administrator', ['ignore_request' => true]);

            $langModel->setState('list.limit', 0);
            $languages = $langModel->getItems();

            if (empty($languages)) {
                throw new \RuntimeException(Text::_('COM_J2COMMERCE_ONBOARDING_LANG_NOT_FOUND'));
            }

            // Find en-US in the available languages
            $detailsUrl = null;

            foreach ($languages as $language) {
                if (isset($language->element) && $language->element === 'pkg_en-US') {
                    $detailsUrl = $language->detailsurl ?? null;
                    break;
                }
            }

            if ($detailsUrl === null) {
                throw new \RuntimeException(Text::_('COM_J2COMMERCE_ONBOARDING_LANG_NOT_FOUND'));
            }

            // Download and install the language pack
            $packageFile = InstallerHelper::downloadPackage($detailsUrl);

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

        $config = [
            'store_name'      => $this->input->getString('store_name', ''),
            'store_address_1' => $this->input->getString('store_address_1', ''),
            'store_address_2' => $this->input->getString('store_address_2', ''),
            'store_city'      => $this->input->getString('store_city', ''),
            'country_id'      => (string) $countryId,
            'zone_id'         => (string) $this->input->getInt('zone_id', 0),
            'store_zip'       => $this->input->getString('store_zip', ''),
            'admin_email'     => $this->input->getString('admin_email', ''),
            'onboarding_last_step' => '1',
        ];

        // Validate required fields
        if (trim($config['store_name']) === '') {
            throw new \InvalidArgumentException(Text::_('COM_J2COMMERCE_ONBOARDING_ERR_REQUIRED'));
        }

        if ($countryId <= 0) {
            throw new \InvalidArgumentException(Text::_('COM_J2COMMERCE_ONBOARDING_ERR_REQUIRED'));
        }

        OnboardingHelper::persistConfig($config);

        // Get recommended defaults for Step 2 pre-fill
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
        $weightId     = $this->input->getInt('config_weight_class_id', 1);
        $lengthId     = $this->input->getInt('config_length_class_id', 1);

        $config = [
            'config_currency'        => $currencyCode,
            'config_currency_auto'   => (string) $this->input->getInt('config_currency_auto', 1),
            'config_weight_class_id' => (string) $weightId,
            'config_length_class_id' => (string) $lengthId,
            'onboarding_last_step'   => '2',
        ];

        OnboardingHelper::persistConfig($config);

        // Currency mode
        if ($currencyMode === 'single') {
            OnboardingHelper::setSingleCurrency($currencyCode, $db);
        } else {
            OnboardingHelper::setBaseCurrencyValue($currencyCode, $db);
        }

        // Sync measurements
        OnboardingHelper::syncWeights($weightId, $db);
        OnboardingHelper::syncLengths($lengthId, $db);

        // Get names for confirmation message
        $weightTitle = $this->getUnitTitle('#__j2commerce_weights', 'j2commerce_weight_id', 'weight_title', $weightId);
        $lengthTitle = $this->getUnitTitle('#__j2commerce_lengths', 'j2commerce_length_id', 'length_title', $lengthId);

        return [
            'weightTitle' => $weightTitle,
            'lengthTitle' => $lengthTitle,
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
        $config = [
            'onboarding_product_types' => $this->input->getString('product_types', ''),
            'onboarding_product_scale' => $this->input->getString('product_scale', 'small'),
            'require_shipping'         => (string) $this->input->getInt('require_shipping', 1),
            'onboarding_last_step'     => '4',
        ];

        OnboardingHelper::persistConfig($config);

        return ['saved' => true];
    }

    private function saveStep5(): array
    {
        OnboardingHelper::persistConfig([
            'onboarding_complete'  => '1',
            'onboarding_last_step' => '5',
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
}
