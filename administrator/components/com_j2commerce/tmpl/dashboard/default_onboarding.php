<?php

declare(strict_types=1);

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OnboardingHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Dashboard\HtmlView $this */

$db = Factory::getContainer()->get(DatabaseInterface::class);

// Load country options
$countryQuery = $db->getQuery(true)
    ->select([$db->quoteName('j2commerce_country_id', 'id'), $db->quoteName('country_name', 'name')])
    ->from($db->quoteName('#__j2commerce_countries'))
    ->where($db->quoteName('enabled') . ' = 1')
    ->order($db->quoteName('country_name') . ' ASC');
$countries = $db->setQuery($countryQuery)->loadObjectList();

// Load currency options
$currencyQuery = $db->getQuery(true)
    ->select([
        $db->quoteName('currency_code', 'code'),
        $db->quoteName('currency_title', 'title'),
        $db->quoteName('currency_symbol', 'symbol'),
        $db->quoteName('currency_position', 'position'),
    ])
    ->from($db->quoteName('#__j2commerce_currencies'))
    ->where($db->quoteName('enabled') . ' = 1')
    ->order($db->quoteName('currency_title') . ' ASC');
$currencies = $db->setQuery($currencyQuery)->loadObjectList();

// Load weight options
$weightQuery = $db->getQuery(true)
    ->select([$db->quoteName('j2commerce_weight_id', 'id'), $db->quoteName('weight_title', 'title')])
    ->from($db->quoteName('#__j2commerce_weights'))
    ->where($db->quoteName('enabled') . ' = 1')
    ->order($db->quoteName('ordering') . ' ASC');
$weights = $db->setQuery($weightQuery)->loadObjectList();

// Load length options
$lengthQuery = $db->getQuery(true)
    ->select([$db->quoteName('j2commerce_length_id', 'id'), $db->quoteName('length_title', 'title')])
    ->from($db->quoteName('#__j2commerce_lengths'))
    ->where($db->quoteName('enabled') . ' = 1')
    ->order($db->quoteName('ordering') . ' ASC');
$lengths = $db->setQuery($lengthQuery)->loadObjectList();

// Load ALL payment plugins for Step 5 (both published and unpublished)
$paymentQuery = $db->getQuery(true)
    ->select([
        $db->quoteName('extension_id', 'id'),
        $db->quoteName('element'),
        $db->quoteName('name'),
        $db->quoteName('params'),
        $db->quoteName('enabled'),
    ])
    ->from($db->quoteName('#__extensions'))
    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
    ->where($db->quoteName('folder') . ' = ' . $db->quote('j2commerce'))
    ->where($db->quoteName('element') . ' LIKE ' . $db->quote('payment_%'))
    ->order($db->quoteName('name') . ' ASC');
$allPaymentPlugins = $db->setQuery($paymentQuery)->loadObjectList();

$lang = Factory::getApplication()->getLanguage();
$paymentPlugins    = [];
$unpublishedPlugins = [];

foreach ($allPaymentPlugins as $plugin) {
    $langPrefix = 'plg_j2commerce_' . $plugin->element;
    $lang->load($langPrefix . '.sys', JPATH_ADMINISTRATOR)
        || $lang->load($langPrefix . '.sys', JPATH_PLUGINS . '/j2commerce/' . $plugin->element);
    $lang->load($langPrefix, JPATH_ADMINISTRATOR)
        || $lang->load($langPrefix, JPATH_PLUGINS . '/j2commerce/' . $plugin->element);

    $params = new \Joomla\Registry\Registry($plugin->params);
    $displayName = $params->get('display_name', '');

    // Use proper plugin language key — ignore legacy _DEFAULT keys
    $pluginLangKey = strtoupper('PLG_J2COMMERCE_' . $plugin->element);
    if ($displayName === '' || str_ends_with(strtoupper($displayName), '_DEFAULT')) {
        $plugin->display_name = Text::_($pluginLangKey);
    } else {
        $plugin->display_name = Text::_($displayName);
    }

    if ((int) $plugin->enabled === 1) {
        $paymentPlugins[] = $plugin;
    } else {
        $unpublishedPlugins[] = $plugin;
    }
}

// Load geozones for shipping rate entry
$geozones = OnboardingHelper::getGeozones($db);

// Existing config values (for resume)
$storeName   = ConfigHelper::get('store_name', '');
$address1    = ConfigHelper::get('store_address_1', '');
$address2    = ConfigHelper::get('store_address_2', '');
$city        = ConfigHelper::get('store_city', '');
$countryId   = (int) ConfigHelper::get('country_id', 0);
$zoneId      = (int) ConfigHelper::get('zone_id', 0);
$zip         = ConfigHelper::get('store_zip', '');
$adminEmail  = ConfigHelper::get('admin_email', '');
$currency    = ConfigHelper::get('config_currency', 'USD');
$weightId    = (int) ConfigHelper::get('config_weight_class_id', 2);
$lengthId    = (int) ConfigHelper::get('config_length_class_id', 1);
$resumeStep  = OnboardingHelper::getResumeStep();

// Pre-fill admin email from super admin if empty
if ($adminEmail === '') {
    $adminEmail = Factory::getApplication()->getIdentity()->email ?? '';
}

$e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>

<div class="modal fade" id="j2commerceOnboardingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="j2commerceOnboardingLabel" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <!-- Header -->
      <div class="modal-header border-0 p-3">
        <h5 class="modal-title" id="j2commerceOnboardingLabel"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_WIZARD_TITLE'); ?></h5>
        <button type="button" class="btn-close" data-action="dismiss-onboarding"
                aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
      </div>

      <!-- Stepper -->
      <nav class="j2c-onboarding-stepper" role="navigation" aria-label="<?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP1_LABEL'); ?>">
        <?php
        $stepLabels = [
            1 => Text::_('COM_J2COMMERCE_ONBOARDING_STEP1_LABEL'),
            2 => Text::_('COM_J2COMMERCE_ONBOARDING_STEP2_LABEL'),
            3 => Text::_('COM_J2COMMERCE_ONBOARDING_STEP3_LABEL'),
            4 => Text::_('COM_J2COMMERCE_ONBOARDING_STEP4_LABEL'),
            5 => Text::_('COM_J2COMMERCE_ONBOARDING_STEP5_LABEL'),
            6 => Text::_('COM_J2COMMERCE_ONBOARDING_STEP6_LABEL'),
        ];
        foreach ($stepLabels as $num => $label) :
            $state = $num < $resumeStep ? 'completed' : ($num === $resumeStep ? 'active' : 'upcoming');
            if ($num > 1) : ?>
                <div class="j2c-step-connector <?php echo $num <= $resumeStep ? 'completed' : ''; ?>" data-connector="<?php echo $num; ?>"></div>
            <?php endif; ?>
            <div class="j2c-step-item <?php echo $state; ?>" data-step-indicator="<?php echo $num; ?>">
                <div class="j2c-step-indicator <?php echo $state; ?>"
                     aria-current="<?php echo $state === 'active' ? 'step' : 'false'; ?>"
                     aria-label="<?php echo $e("Step $num: $label"); ?>">
                    <?php if ($state === 'completed') : ?>
                        <span class="fa-solid fa-check" aria-hidden="true"></span>
                    <?php else : ?>
                        <?php echo $num; ?>
                    <?php endif; ?>
                </div>
                <span class="j2c-step-label"><?php echo $e($label); ?></span>
            </div>
        <?php endforeach; ?>
      </nav>

      <!-- Progress bar -->
      <div class="progress j2c-onboarding-progress">
        <?php $progressPct = (int) round($resumeStep * 100 / 6); ?>
        <div class="progress-bar" id="ob-progress-bar" role="progressbar" style="width: <?php echo $progressPct; ?>%"
             aria-valuenow="<?php echo $progressPct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
      </div>

      <!-- Step content -->
      <div class="modal-body j2c-onboarding-body position-relative p-3">

        <!-- ============ STEP 1: Store Info ============ -->
        <div class="j2c-step" data-step="1" <?php echo $resumeStep !== 1 ? 'hidden' : ''; ?>>
          <div class="j2c-step-icon"><span class="fa-solid fa-location-dot" aria-hidden="true"></span></div>
          <h4 class="j2c-step-title"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP1_TITLE'); ?></h4>
          <p class="j2c-step-desc"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP1_DESC'); ?></p>
          <hr>
          <div class="row g-3">
            <div class="col-12">
              <div class="form-floating">
                <input type="text" class="form-control" id="ob-store-name" name="store_name" value="<?php echo $e($storeName); ?>" placeholder="<?php echo $e(Text::_('COM_J2COMMERCE_CONFIG_STORE_NAME')); ?>" required maxlength="255">
                <label for="ob-store-name"><?php echo Text::_('COM_J2COMMERCE_CONFIG_STORE_NAME'); ?> <span class="text-danger">*</span></label>
              </div>
              <div class="invalid-feedback"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_ERR_REQUIRED'); ?></div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="ob-address1" name="store_address_1" value="<?php echo $e($address1); ?>" placeholder="<?php echo $e(Text::_('COM_J2COMMERCE_CONFIG_STORE_ADDRESS_1')); ?>">
                <label for="ob-address1"><?php echo Text::_('COM_J2COMMERCE_CONFIG_STORE_ADDRESS_1'); ?></label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="ob-address2" name="store_address_2" value="<?php echo $e($address2); ?>" placeholder="<?php echo $e(Text::_('COM_J2COMMERCE_CONFIG_STORE_ADDRESS_2')); ?>">
                <label for="ob-address2"><?php echo Text::_('COM_J2COMMERCE_CONFIG_STORE_ADDRESS_2'); ?></label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="ob-city" name="store_city" value="<?php echo $e($city); ?>" placeholder="<?php echo $e(Text::_('COM_J2COMMERCE_CONFIG_STORE_CITY')); ?>">
                <label for="ob-city"><?php echo Text::_('COM_J2COMMERCE_CONFIG_STORE_CITY'); ?></label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <select class="form-select" id="ob-country" name="country_id" required aria-label="<?php echo $e(Text::_('COM_J2COMMERCE_CONFIG_STORE_COUNTRY')); ?>">
                  <option value=""><?php echo Text::_('COM_J2COMMERCE_SELECT_COUNTRY'); ?></option>
                  <?php foreach ($countries as $c) : ?>
                    <option value="<?php echo (int) $c->id; ?>" <?php echo (int) $c->id === $countryId ? 'selected' : ''; ?>><?php echo $e($c->name); ?></option>
                  <?php endforeach; ?>
                </select>
                <label for="ob-country"><?php echo Text::_('COM_J2COMMERCE_CONFIG_STORE_COUNTRY'); ?> <span class="text-danger">*</span></label>
              </div>
              <div class="invalid-feedback"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_ERR_REQUIRED'); ?></div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <select class="form-select" id="ob-zone" name="zone_id" required aria-label="<?php echo $e(Text::_('COM_J2COMMERCE_CONFIG_STORE_ZONE')); ?>">
                  <option value=""><?php echo Text::_('COM_J2COMMERCE_SELECT_ZONE'); ?></option>
                </select>
                <label for="ob-zone"><?php echo Text::_('COM_J2COMMERCE_CONFIG_STORE_ZONE'); ?> <span class="text-danger">*</span></label>
              </div>
              <div class="invalid-feedback"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_ERR_REQUIRED'); ?></div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="ob-zip" name="store_zip" value="<?php echo $e($zip); ?>" placeholder="<?php echo $e(Text::_('COM_J2COMMERCE_CONFIG_STORE_ZIP')); ?>">
                <label for="ob-zip"><?php echo Text::_('COM_J2COMMERCE_CONFIG_STORE_ZIP'); ?></label>
              </div>
            </div>
            <div class="col-12">
              <div class="form-floating">
                <input type="email" class="form-control" id="ob-email" name="admin_email" value="<?php echo $e($adminEmail); ?>" placeholder="<?php echo $e(Text::_('COM_J2COMMERCE_CONFIG_ADMIN_EMAIL')); ?>">
                <label for="ob-email"><?php echo Text::_('COM_J2COMMERCE_CONFIG_ADMIN_EMAIL'); ?></label>
              </div>
            </div>
          </div>

          <!-- Language prompt (hidden, shown via JS for country 223) -->
          <div class="j2c-lang-prompt mt-3 d-none" id="ob-lang-prompt">
            <div class="alert alert-info d-flex align-items-start gap-3">
              <span class="fa-solid fa-language fa-2x mt-1 text-primary" aria-hidden="true"></span>
              <div>
                <strong><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_LANG_PROMPT_TITLE'); ?></strong>
                <p class="mb-2"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_LANG_PROMPT_DESC'); ?></p>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-sm btn-primary shadow-none" data-action="install-lang">
                    <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_LANG_YES'); ?>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-secondary shadow-none" data-action="skip-lang">
                    <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_LANG_NO'); ?>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Defaults preview (shown before weight/length fields) -->
          <div class="alert alert-info mt-3 small d-none" id="ob-defaults-preview"></div>

          <!-- Weight & Length (set after country/language, before currency step) -->
          <div class="row g-3 mt-2">
            <div class="col-md-6">
              <div class="form-floating">
                <select class="form-select" id="ob-weight" name="config_weight_class_id" aria-label="<?php echo $e(Text::_('COM_J2COMMERCE_CONFIG_DEFAULT_WEIGHT')); ?>">
                  <?php foreach ($weights as $w) : ?>
                    <option value="<?php echo (int) $w->id; ?>" <?php echo (int) $w->id === $weightId ? 'selected' : ''; ?>><?php echo $e($w->title); ?></option>
                  <?php endforeach; ?>
                </select>
                <label for="ob-weight"><?php echo Text::_('COM_J2COMMERCE_CONFIG_DEFAULT_WEIGHT'); ?></label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <select class="form-select" id="ob-length" name="config_length_class_id" aria-label="<?php echo $e(Text::_('COM_J2COMMERCE_CONFIG_DEFAULT_LENGTH')); ?>">
                  <?php foreach ($lengths as $l) : ?>
                    <option value="<?php echo (int) $l->id; ?>" <?php echo (int) $l->id === $lengthId ? 'selected' : ''; ?>><?php echo $e($l->title); ?></option>
                  <?php endforeach; ?>
                </select>
                <label for="ob-length"><?php echo Text::_('COM_J2COMMERCE_CONFIG_DEFAULT_LENGTH'); ?></label>
              </div>
            </div>
          </div>
        </div>

        <!-- ============ STEP 2: Currency & Measurements ============ -->
        <div class="j2c-step" data-step="2" <?php echo $resumeStep !== 2 ? 'hidden' : ''; ?>>
          <div class="j2c-step-icon"><span class="fa-solid fa-coins" aria-hidden="true"></span></div>
          <h4 class="j2c-step-title"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP2_TITLE'); ?></h4>
          <p class="j2c-step-desc"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP2_DESC'); ?></p>
          <div class="alert alert-info small d-none" id="ob-currency-defaults-preview"></div>
          <hr>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-floating">
                <select class="form-select" id="ob-currency" name="config_currency" aria-label="<?php echo $e(Text::_('COM_J2COMMERCE_CONFIG_DEFAULT_CURRENCY')); ?>">
                  <?php foreach ($currencies as $c) : ?>
                    <option value="<?php echo $e($c->code); ?>" <?php echo $c->code === $currency ? 'selected' : ''; ?>
                            data-symbol="<?php echo $e($c->symbol); ?>" data-position="<?php echo $e($c->position); ?>">
                      <?php echo $e($c->code . ' - ' . $c->title . ' (' . $c->symbol . ')'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <label for="ob-currency"><?php echo Text::_('COM_J2COMMERCE_CONFIG_DEFAULT_CURRENCY'); ?></label>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_CONFIG_CURRENCY_AUTO_UPDATE'); ?></label>
              <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" id="ob-currency-auto" name="config_currency_auto" value="1" checked>
                <label class="form-check-label" for="ob-currency-auto"><?php echo Text::_('JYES'); ?></label>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_CURRENCY_MODE'); ?></label>
              <div class="d-flex gap-3">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="currency_mode" id="ob-currency-single" value="single" checked>
                  <label class="form-check-label" for="ob-currency-single"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_CURRENCY_SINGLE'); ?></label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="currency_mode" id="ob-currency-multi" value="multi">
                  <label class="form-check-label" for="ob-currency-multi"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_CURRENCY_MULTI'); ?></label>
                </div>
              </div>
              <div class="alert alert-info small mt-2" id="ob-currency-single-note">
                <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_CURRENCY_MODE_DESC'); ?>
              </div>
              <div class="alert alert-info small mt-2 d-none" id="ob-currency-multi-note">
                <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_CURRENCY_MULTI_NOTE'); ?>
              </div>
            </div>
          </div>
          <div class="alert alert-info small d-none mt-3" id="ob-step2-info"></div>
        </div>

        <!-- ============ STEP 3: Tax ============ -->
        <div class="j2c-step" data-step="3" <?php echo $resumeStep !== 3 ? 'hidden' : ''; ?>>
          <div class="j2c-step-icon"><span class="fa-solid fa-receipt" aria-hidden="true"></span></div>
          <h4 class="j2c-step-title"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP3_TITLE'); ?></h4>
          <p class="j2c-step-desc"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP3_DESC'); ?></p>
          <hr>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_TAX_INCLUDE'); ?></label>
              <div class="d-flex gap-3">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="config_including_tax" id="ob-tax-exclude" value="0" checked>
                  <label class="form-check-label" for="ob-tax-exclude"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_TAX_EXCLUDE'); ?></label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="config_including_tax" id="ob-tax-include" value="1">
                  <label class="form-check-label" for="ob-tax-include"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_TAX_INCLUDE'); ?></label>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="input-group">
                <div class="form-floating">
                  <input type="number" class="form-control" id="ob-tax-rate" name="tax_percent" step="0.001" min="0" max="100" placeholder="<?php echo $e(Text::_('COM_J2COMMERCE_ONBOARDING_TAX_RATE')); ?>">
                  <label for="ob-tax-rate"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_TAX_RATE'); ?></label>
                </div>
                <span class="input-group-text">%</span>
              </div>
              <div class="form-text"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_TAX_RATE_HELP'); ?></div>
            </div>
          </div>
        </div>

        <!-- ============ STEP 4: Product Type ============ -->
        <div class="j2c-step" data-step="4" <?php echo $resumeStep !== 4 ? 'hidden' : ''; ?>>
          <div class="j2c-step-icon"><span class="fa-solid fa-boxes-stacked" aria-hidden="true"></span></div>
          <h4 class="j2c-step-title"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP4_TITLE'); ?></h4>
          <p class="j2c-step-desc"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP4_DESC'); ?></p>
          <hr>
          <div class="row g-3 mb-3">
            <?php
            $productTypes = [
                'physical'     => ['icon' => 'fa-tags',            'label' => 'COM_J2COMMERCE_ONBOARDING_PRODUCT_PHYSICAL',     'desc' => 'COM_J2COMMERCE_ONBOARDING_PRODUCT_PHYSICAL_DESC'],
                'digital'      => ['icon' => 'fa-download',       'label' => 'COM_J2COMMERCE_ONBOARDING_PRODUCT_DIGITAL',      'desc' => 'COM_J2COMMERCE_ONBOARDING_PRODUCT_DIGITAL_DESC'],
                'service'      => ['icon' => 'fa-calendar-check', 'label' => 'COM_J2COMMERCE_ONBOARDING_PRODUCT_SERVICE',      'desc' => 'COM_J2COMMERCE_ONBOARDING_PRODUCT_SERVICE_DESC'],
            ];
            foreach ($productTypes as $type => $info) : ?>
              <div class="col-md-3 col-6">
                <div class="card j2c-product-type-card h-100 shadow-none" data-product-type="<?php echo $type; ?>" role="checkbox" aria-checked="false" tabindex="0">
                  <div class="card-body text-center">
                    <span class="j2c-card-check fa-solid fa-circle-check" aria-hidden="true"></span>
                    <span class="fa-solid <?php echo $info['icon']; ?> d-block" aria-hidden="true"></span>
                    <strong class="d-block mt-2"><?php echo Text::_($info['label']); ?></strong>
                    <small class="text-muted"><?php echo Text::_($info['desc']); ?></small>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Info notes (shown conditionally) -->
          <div class="alert alert-info small d-none" id="ob-note-subscription">
            <span class="fa-solid fa-circle-info me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PRODUCT_SUBSCRIPTION_NOTE'); ?>
          </div>
          <div class="alert alert-info small d-none" id="ob-note-digital">
            <span class="fa-solid fa-circle-info me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PRODUCT_DIGITAL_NOTE'); ?>
          </div>



          <!-- Shipping question (shown only if physical selected) -->
          <div class="mt-3 d-none" id="ob-shipping-question">
            <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_QUESTION'); ?></label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="require_shipping" id="ob-shipping-yes" value="1" checked>
                <label class="form-check-label" for="ob-shipping-yes"><?php echo Text::_('JYES'); ?></label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="require_shipping" id="ob-shipping-no" value="0">
                <label class="form-check-label" for="ob-shipping-no"><?php echo Text::_('JNO'); ?></label>
              </div>
            </div>
            <div class="form-text"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_NOTE'); ?></div>
          </div>

          <!-- Free shipping question (shown when require_shipping = Yes) -->
          <div class="mt-3 d-none" id="ob-free-shipping-question">
            <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_FREE_SHIPPING_QUESTION'); ?></label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="offer_free_shipping" id="ob-free-yes" value="1">
                <label class="form-check-label" for="ob-free-yes"><?php echo Text::_('JYES'); ?></label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="offer_free_shipping" id="ob-free-no" value="0">
                <label class="form-check-label" for="ob-free-no"><?php echo Text::_('JNO'); ?></label>
              </div>
            </div>
          </div>

          <!-- Free shipping min subtotal (shown when offer_free_shipping = Yes) -->
          <div class="mt-3 d-none" id="ob-free-shipping-config">
            <label class="form-label" for="ob-free-min-subtotal"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_FREE_SHIPPING_MIN_SUBTOTAL'); ?></label>
            <div class="col-md-6">
              <input type="number" class="form-control" id="ob-free-min-subtotal" name="free_shipping_min_subtotal" value="0" min="0" step="0.01" placeholder="<?php echo $e(Text::_('COM_J2COMMERCE_ONBOARDING_FREE_SHIPPING_MIN_SUBTOTAL_PLACEHOLDER')); ?>">
            </div>
            <div class="form-text"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_FREE_SHIPPING_MIN_SUBTOTAL_DESC'); ?></div>
          </div>

          <!-- Fixed vs Calculated question (shown when require_shipping = Yes) -->
          <div class="mt-3 d-none" id="ob-shipping-type-question">
            <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_TYPE_QUESTION'); ?></label>
            <div class="d-flex flex-column gap-2">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="shipping_rate_type" id="ob-rate-fixed" value="fixed">
                <label class="form-check-label" for="ob-rate-fixed">
                  <strong><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_TYPE_FIXED'); ?></strong>
                  <div class="form-text mt-0"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_TYPE_FIXED_DESC'); ?></div>
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="shipping_rate_type" id="ob-rate-calculated" value="calculated">
                <label class="form-check-label" for="ob-rate-calculated">
                  <strong><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_TYPE_CALCULATED'); ?></strong>
                  <div class="form-text mt-0"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_TYPE_CALCULATED_DESC'); ?></div>
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="shipping_rate_type" id="ob-rate-both" value="both">
                <label class="form-check-label" for="ob-rate-both">
                  <strong><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_TYPE_BOTH'); ?></strong>
                  <div class="form-text mt-0"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_TYPE_BOTH_DESC'); ?></div>
                </label>
              </div>
            </div>
          </div>

          <!-- Calculated shipping info (shown when shipping_rate_type = calculated or both) -->
          <div class="mt-3 d-none" id="ob-calculated-shipping-info">
            <div class="alert alert-info d-flex align-items-start gap-3">
              <span class="fa-solid fa-truck-plane fa-2x mt-1 text-primary" aria-hidden="true"></span>
              <div>
                <strong><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_CALCULATED_LINK'); ?></strong>
                <p class="mb-2"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_CALCULATED_INFO'); ?></p>
                <a href="https://www.j2commerce.com/extensions/shipping-plugins" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-info shadow-none">
                  <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_CALCULATED_LINK'); ?>
                </a>
              </div>
            </div>
          </div>

          <!-- Fixed shipping config (shown when shipping_rate_type = fixed) -->
          <div class="mt-3 d-none" id="ob-fixed-shipping-config">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="ob-shipping-method-type"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_FIXED_TYPE'); ?></label>
                <select class="form-select" id="ob-shipping-method-type" name="shipping_method_type">
                  <option value="0"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TYPE_PER_ORDER_FLAT'); ?></option>
                  <option value="1"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TYPE_PER_ORDER_QUANTITY'); ?></option>
                  <option value="2"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TYPE_PER_ORDER_PRICE'); ?></option>
                  <option value="3"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TYPE_PER_ITEM_FLAT'); ?></option>
                  <option value="4"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TYPE_PER_ITEM_WEIGHT'); ?></option>
                  <option value="5"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TYPE_PER_ORDER_WEIGHT'); ?></option>
                  <option value="6"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TYPE_PER_ITEM_PERCENTAGE'); ?></option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="ob-shipping-method-name"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_METHOD_NAME'); ?></label>
                <input type="text" class="form-control" id="ob-shipping-method-name" name="shipping_method_name" value="<?php echo $e(Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_METHOD_NAME_DEFAULT')); ?>" maxlength="255">
              </div>
            </div>

            <!-- Rate entry table -->
            <div class="mt-3">
              <table class="table table-sm ob-rates-table mb-0">
                <thead>
                  <tr>
                    <th><?php echo Text::_('COM_J2COMMERCE_GEOZONE'); ?></th>
                    <th class="ob-rate-range d-none"><?php echo Text::_('COM_J2COMMERCE_FIELD_RANGE_START'); ?></th>
                    <th class="ob-rate-range d-none"><?php echo Text::_('COM_J2COMMERCE_FIELD_RANGE_END'); ?></th>
                    <th><?php echo Text::_('COM_J2COMMERCE_SHIPPING_COST'); ?></th>
                    <th><?php echo Text::_('COM_J2COMMERCE_HANDLING_COST'); ?></th>
                    <th><span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_ACTIONS'); ?></span></th>
                  </tr>
                </thead>
                <tbody id="ob-rates-body">
                  <tr data-rate-row="1">
                    <td>
                      <select class="form-select form-select-sm ob-rate-geozone">
                        <?php foreach ($geozones as $gz) : ?>
                          <option value="<?php echo (int) $gz->id; ?>"><?php echo $e($gz->name); ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($geozones)) : ?>
                          <option value="0"><?php echo Text::_('COM_J2COMMERCE_OPTION_NONE'); ?></option>
                        <?php endif; ?>
                      </select>
                    </td>
                    <td class="ob-rate-range d-none">
                      <input type="number" class="form-control form-control-sm ob-rate-weight-start" value="0" min="0" step="0.001">
                    </td>
                    <td class="ob-rate-range d-none">
                      <input type="number" class="form-control form-control-sm ob-rate-weight-end" value="0" min="0" step="0.001">
                    </td>
                    <td>
                      <input type="number" class="form-control form-control-sm ob-rate-price" value="0" min="0" step="0.01">
                    </td>
                    <td>
                      <input type="number" class="form-control form-control-sm ob-rate-handling" value="0" min="0" step="0.01">
                    </td>
                    <td></td>
                  </tr>
                </tbody>
              </table>
              <div class="text-end mt-2">
                <button type="button" class="btn btn-sm btn-success shadow-none" data-action="add-rate">
                  <span class="fa-solid fa-plus me-1" aria-hidden="true"></span>
                  <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SHIPPING_ADD_RATE'); ?>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- ============ STEP 5: Payment ============ -->
        <div class="j2c-step" data-step="5" <?php echo $resumeStep !== 5 ? 'hidden' : ''; ?>>
          <div class="j2c-step-icon"><span class="fa-solid fa-credit-card" aria-hidden="true"></span></div>
          <h4 class="j2c-step-title"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP5_TITLE'); ?></h4>
          <p class="j2c-step-desc"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP5_DESC'); ?></p>
          <hr>

          <!-- Payment plugin checkbox list -->
          <label class="form-label mb-2"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PAYMENT_SELECT'); ?></label>
          <div class="border rounded mb-3 <?php echo empty($paymentPlugins) ? 'd-none' : ''; ?>" id="ob-payment-list">
            <?php foreach ($paymentPlugins as $plugin) : ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="payment_plugins[]"
                       value="<?php echo $e($plugin->element); ?>"
                       id="ob-pay-<?php echo $e($plugin->element); ?>"
                       data-label="<?php echo $e($plugin->display_name); ?>"
                       checked>
                <label class="form-check-label" for="ob-pay-<?php echo $e($plugin->element); ?>"><?php echo $e($plugin->display_name); ?></label>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Warning shown when no payment plugins are published -->
          <div class="<?php echo !empty($paymentPlugins) ? 'd-none' : ''; ?>" id="ob-payment-none-warning">
            <div class="alert alert-warning">
              <span class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></span>
              <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PAYMENT_NONE_WARNING'); ?>
              <?php if (!empty($unpublishedPlugins)) : ?>
                <div class="mt-2">
                  <button type="button" class="btn btn-sm btn-warning shadow-none" data-action="enable-payment-plugins">
                    <span class="fa-solid fa-plug me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PAYMENT_ENABLE_BTN'); ?>
                  </button>
                </div>
              <?php endif; ?>
            </div>

            <!-- Hidden checkbox list of unpublished payment plugins -->
            <?php if (!empty($unpublishedPlugins)) : ?>
              <div class="mb-3 d-none" id="ob-payment-enable-list">
                <?php foreach ($unpublishedPlugins as $plugin) : ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="enable_payment_plugins[]"
                           value="<?php echo $e($plugin->element); ?>"
                           id="ob-enable-<?php echo $e($plugin->element); ?>"
                           data-label="<?php echo $e($plugin->display_name); ?>">
                    <label class="form-check-label" for="ob-enable-<?php echo $e($plugin->element); ?>"><?php echo $e($plugin->display_name); ?></label>
                  </div>
                <?php endforeach; ?>
                <div class="p-2 text-end">
                  <button type="button" class="btn btn-sm btn-success shadow-none" data-action="confirm-enable-payment">
                    <span class="fa-solid fa-check me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PAYMENT_ENABLE_CONFIRM'); ?>
                  </button>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <!-- Default payment dropdown (shown when at least one plugin checked) -->
          <div class="mb-3 <?php echo empty($paymentPlugins) ? 'd-none' : ''; ?>" id="ob-default-payment-section">
            <label class="form-label" for="ob-default-payment"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PAYMENT_DEFAULT'); ?></label>
            <select class="form-select" id="ob-default-payment" name="default_payment_method">
              <option value=""><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PAYMENT_SELECT_DEFAULT'); ?></option>
              <?php foreach ($paymentPlugins as $plugin) : ?>
                <option value="<?php echo $e($plugin->element); ?>"><?php echo $e($plugin->display_name); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- PayPal configuration (shown when PayPal is checked) -->
          <div class="d-none" id="ob-paypal-config">
            <hr>
            <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PAYPAL_HAVE_KEYS'); ?></label>
            <div class="d-flex gap-3 mb-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="paypal_keys_status" id="ob-paypal-have" value="have_keys">
                <label class="form-check-label" for="ob-paypal-have"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PAYPAL_YES_KEYS'); ?></label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="paypal_keys_status" id="ob-paypal-help" value="need_help">
                <label class="form-check-label" for="ob-paypal-help"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PAYPAL_NEED_HELP'); ?></label>
              </div>
            </div>

            <!-- PayPal key fields (shown when have_keys selected) -->
            <div class="d-none" id="ob-paypal-keys">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label" for="ob-paypal-client-id"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PAYPAL_CLIENT_ID'); ?></label>
                  <input type="text" class="form-control" id="ob-paypal-client-id" name="paypal_client_id" placeholder="<?php echo $e(Text::_('COM_J2COMMERCE_ONBOARDING_PAYPAL_CLIENT_ID_PLACEHOLDER')); ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="ob-paypal-client-secret"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PAYPAL_CLIENT_SECRET'); ?></label>
                  <input type="text" class="form-control" id="ob-paypal-client-secret" name="paypal_client_secret" placeholder="<?php echo $e(Text::_('COM_J2COMMERCE_ONBOARDING_PAYPAL_CLIENT_SECRET_PLACEHOLDER')); ?>">
                </div>
              </div>
            </div>

            <!-- PayPal help link (shown when need_help selected) -->
            <div class="d-none" id="ob-paypal-help-info">
              <div class="alert alert-info">
                <span class="fa-solid fa-book me-1" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PAYPAL_HELP_TEXT'); ?>
                <div class="mt-2">
                  <a href="https://docs.j2commerce.com/v6/payment-methods/payment_paypal" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-info shadow-none">
                    <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PAYPAL_HELP_LINK'); ?>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ============ STEP 6: Ready! ============ -->
        <div class="j2c-step" data-step="6" <?php echo $resumeStep !== 6 ? 'hidden' : ''; ?>>
          <div class="j2c-step-icon j2c-celebration-icon"><span class="fa-solid fa-circle-check text-primary" aria-hidden="true"></span></div>
          <h4 class="j2c-step-title"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP6_TITLE'); ?></h4>
          <p class="j2c-step-desc"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP6_DESC'); ?></p>
          <hr>

          <div class="card mb-4 shadow-none">
            <div class="card-body" id="ob-summary">
              <!-- Populated by JS after step 5 save -->
            </div>
          </div>

          <div id="ob-sampledata-prompt" class="d-none">
            <div class="alert alert-info text-center mb-0">
              <p class="mb-2"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SAMPLEDATA_PROMPT'); ?></p>
              <button type="button" class="btn btn-outline-primary btn-sm me-2" data-action="load-sampledata">
                <span class="fa-solid fa-database me-1" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SAMPLEDATA_LOAD'); ?>
              </button>
              <button type="button" class="btn btn-link btn-sm" data-action="skip-sampledata">
                <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_SAMPLEDATA_SKIP'); ?>
              </button>
            </div>
          </div>

          <div class="row g-3 text-center">
            <div class="col-md-3">
              <button type="button" class="btn btn-outline-secondary w-100 shadow-none" data-action="back">
                <span class="fa-solid fa-chevron-left me-1" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_BTN_BACK'); ?>
              </button>
            </div>
            <div class="col-md-3">
              <a href="index.php?option=com_content&view=article&layout=edit" class="btn btn-primary w-100 shadow-none">
                <span class="fa-solid fa-plus me-1" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_READY_ADD_PRODUCT'); ?>
              </a>
            </div>
            <div class="col-md-3">
              <a href="index.php?option=com_j2commerce&view=dashboard" class="btn btn-outline-primary w-100 shadow-none" data-action="close-onboarding">
                <span class="fa-solid fa-gauge-high me-1" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_READY_DASHBOARD'); ?>
              </a>
            </div>
            <div class="col-md-3">
              <a href="index.php?option=com_config&view=component&component=com_j2commerce" class="btn btn-outline-secondary w-100 shadow-none">
                <span class="fa-solid fa-gear me-1" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_READY_SETTINGS'); ?>
              </a>
            </div>
          </div>
        </div>

        <!-- Inline alert area for AJAX errors -->
        <div class="d-none" id="ob-alert-area"></div>
      </div>

      <!-- Footer -->
      <div class="modal-footer j2c-onboarding-footer" id="ob-footer">
        <button type="button" id="ob-btn-back" class="btn btn-link text-muted shadow-none" hidden data-action="back">
          <span class="fa-solid fa-chevron-left me-1" aria-hidden="true"></span>
          <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_BTN_BACK'); ?>
        </button>
        <div class="ms-auto d-flex gap-2">
          <button type="button" id="ob-btn-skip" class="btn btn-outline-secondary shadow-none" data-action="skip">
            <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_BTN_SKIP'); ?>
          </button>
          <button type="button" id="ob-btn-next" class="btn btn-primary shadow-none" data-action="next">
            <span class="btn-label"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_BTN_CONTINUE'); ?></span>
            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
            <span class="fa-solid fa-chevron-right ms-1" aria-hidden="true"></span>
          </button>
        </div>
      </div>

    </div>
  </div>
</div>

<input type="hidden" id="ob-token" value="<?php echo Session::getFormToken(); ?>">
<input type="hidden" id="ob-resume-step" value="<?php echo $resumeStep; ?>">
<input type="hidden" id="ob-zone-id" value="<?php echo $zoneId; ?>">
