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

// Existing config values (for resume)
$storeName   = ConfigHelper::get('store_name', '');
$address1    = ConfigHelper::get('store_address_1', '');
$address2    = ConfigHelper::get('store_address_2', '');
$city        = ConfigHelper::get('store_city', '');
$countryId   = (int) ConfigHelper::get('country_id', 223);
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

// Currency metadata for JS
$currencyMeta = [];
foreach ($currencies as $c) {
    $currencyMeta[$c->code] = ['symbol' => $c->symbol, 'position' => $c->position];
}

$e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>

<div class="modal fade" id="j2commerceOnboardingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="j2commerceOnboardingLabel" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">

      <!-- Header -->
      <div class="modal-header border-0 p-3">
        <h5 class="visually-hidden" id="j2commerceOnboardingLabel"><?php echo Text::_('COM_J2COMMERCE_CONFIG_RERUN_ONBOARDING'); ?></h5>
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
        <div class="progress-bar" id="ob-progress-bar" role="progressbar" style="width: <?php echo $resumeStep * 20; ?>%"
             aria-valuenow="<?php echo $resumeStep * 20; ?>" aria-valuemin="0" aria-valuemax="100"></div>
      </div>

      <!-- Step content -->
      <div class="modal-body j2c-onboarding-body position-relative p-3">

        <!-- ============ STEP 1: Store Info ============ -->
        <div class="j2c-step" data-step="1" <?php echo $resumeStep !== 1 ? 'hidden' : ''; ?>>
          <div class="j2c-step-icon"><span class="fa-solid fa-store" aria-hidden="true"></span></div>
          <h4 class="j2c-step-title"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP1_TITLE'); ?></h4>
          <p class="j2c-step-desc"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP1_DESC'); ?></p>
          <hr>
          <div class="row g-3">
            <div class="col-12">
              <div class="form-floating">
                <input type="text" class="form-control" id="ob-store-name" name="store_name" value="<?php echo $e($storeName); ?>" placeholder="<?php echo $e(Text::_('COM_J2COMMERCE_ONBOARDING_STORE_NAME')); ?>" required maxlength="255">
                <label for="ob-store-name"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STORE_NAME'); ?> <span class="text-danger">*</span></label>
              </div>
              <div class="invalid-feedback"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_ERR_REQUIRED'); ?></div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="ob-address1" name="store_address_1" value="<?php echo $e($address1); ?>" placeholder="<?php echo $e(Text::_('COM_J2COMMERCE_ONBOARDING_ADDRESS_1')); ?>">
                <label for="ob-address1"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_ADDRESS_1'); ?></label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="ob-address2" name="store_address_2" value="<?php echo $e($address2); ?>" placeholder="<?php echo $e(Text::_('COM_J2COMMERCE_ONBOARDING_ADDRESS_2')); ?>">
                <label for="ob-address2"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_ADDRESS_2'); ?></label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="ob-city" name="store_city" value="<?php echo $e($city); ?>" placeholder="<?php echo $e(Text::_('COM_J2COMMERCE_ONBOARDING_CITY')); ?>">
                <label for="ob-city"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_CITY'); ?></label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <select class="form-select" id="ob-country" name="country_id" required aria-label="<?php echo $e(Text::_('COM_J2COMMERCE_ONBOARDING_COUNTRY')); ?>">
                  <option value=""><?php echo Text::_('COM_J2COMMERCE_SELECT_COUNTRY'); ?></option>
                  <?php foreach ($countries as $c) : ?>
                    <option value="<?php echo (int) $c->id; ?>" <?php echo (int) $c->id === $countryId ? 'selected' : ''; ?>><?php echo $e($c->name); ?></option>
                  <?php endforeach; ?>
                </select>
                <label for="ob-country"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_COUNTRY'); ?> <span class="text-danger">*</span></label>
              </div>
              <div class="invalid-feedback"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_ERR_REQUIRED'); ?></div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <select class="form-select" id="ob-zone" name="zone_id" aria-label="<?php echo $e(Text::_('COM_J2COMMERCE_ONBOARDING_ZONE')); ?>">
                  <option value="0"><?php echo Text::_('COM_J2COMMERCE_SELECT_ZONE'); ?></option>
                </select>
                <label for="ob-zone"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_ZONE'); ?></label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="ob-zip" name="store_zip" value="<?php echo $e($zip); ?>" placeholder="<?php echo $e(Text::_('COM_J2COMMERCE_ONBOARDING_ZIP')); ?>">
                <label for="ob-zip"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_ZIP'); ?></label>
              </div>
            </div>
            <div class="col-12">
              <div class="form-floating">
                <input type="email" class="form-control" id="ob-email" name="admin_email" value="<?php echo $e($adminEmail); ?>" placeholder="<?php echo $e(Text::_('COM_J2COMMERCE_ONBOARDING_EMAIL')); ?>">
                <label for="ob-email"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_EMAIL'); ?></label>
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
                  <button type="button" class="btn btn-sm btn-primary" data-action="install-lang">
                    <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_LANG_YES'); ?>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-action="skip-lang">
                    <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_LANG_NO'); ?>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Defaults preview -->
          <div class="alert alert-light mt-3 small" id="ob-defaults-preview"></div>
        </div>

        <!-- ============ STEP 2: Currency & Measurements ============ -->
        <div class="j2c-step" data-step="2" <?php echo $resumeStep !== 2 ? 'hidden' : ''; ?>>
          <div class="j2c-step-icon"><span class="fa-solid fa-coins" aria-hidden="true"></span></div>
          <h4 class="j2c-step-title"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP2_TITLE'); ?></h4>
          <p class="j2c-step-desc"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP2_DESC'); ?></p>
          <hr>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-floating">
                <select class="form-select" id="ob-currency" name="config_currency" aria-label="<?php echo $e(Text::_('COM_J2COMMERCE_ONBOARDING_CURRENCY')); ?>">
                  <?php foreach ($currencies as $c) : ?>
                    <option value="<?php echo $e($c->code); ?>" <?php echo $c->code === $currency ? 'selected' : ''; ?>
                            data-symbol="<?php echo $e($c->symbol); ?>" data-position="<?php echo $e($c->position); ?>">
                      <?php echo $e($c->code . ' - ' . $c->title . ' (' . $c->symbol . ')'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <label for="ob-currency"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_CURRENCY'); ?></label>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_CURRENCY_AUTO'); ?></label>
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
              <div class="alert alert-info small mt-2 d-none" id="ob-currency-multi-note">
                <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_CURRENCY_MULTI_NOTE'); ?>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <select class="form-select" id="ob-weight" name="config_weight_class_id" aria-label="<?php echo $e(Text::_('COM_J2COMMERCE_ONBOARDING_WEIGHT')); ?>">
                  <?php foreach ($weights as $w) : ?>
                    <option value="<?php echo (int) $w->id; ?>" <?php echo (int) $w->id === $weightId ? 'selected' : ''; ?>><?php echo $e($w->title); ?></option>
                  <?php endforeach; ?>
                </select>
                <label for="ob-weight"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_WEIGHT'); ?></label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <select class="form-select" id="ob-length" name="config_length_class_id" aria-label="<?php echo $e(Text::_('COM_J2COMMERCE_ONBOARDING_LENGTH')); ?>">
                  <?php foreach ($lengths as $l) : ?>
                    <option value="<?php echo (int) $l->id; ?>" <?php echo (int) $l->id === $lengthId ? 'selected' : ''; ?>><?php echo $e($l->title); ?></option>
                  <?php endforeach; ?>
                </select>
                <label for="ob-length"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_LENGTH'); ?></label>
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
                'physical'     => ['icon' => 'fa-box',            'label' => 'COM_J2COMMERCE_ONBOARDING_PRODUCT_PHYSICAL',     'desc' => 'COM_J2COMMERCE_ONBOARDING_PRODUCT_PHYSICAL_DESC'],
                'digital'      => ['icon' => 'fa-download',       'label' => 'COM_J2COMMERCE_ONBOARDING_PRODUCT_DIGITAL',      'desc' => 'COM_J2COMMERCE_ONBOARDING_PRODUCT_DIGITAL_DESC'],
                'service'      => ['icon' => 'fa-calendar-check', 'label' => 'COM_J2COMMERCE_ONBOARDING_PRODUCT_SERVICE',      'desc' => 'COM_J2COMMERCE_ONBOARDING_PRODUCT_SERVICE_DESC'],
                'subscription' => ['icon' => 'fa-arrows-rotate',  'label' => 'COM_J2COMMERCE_ONBOARDING_PRODUCT_SUBSCRIPTION', 'desc' => 'COM_J2COMMERCE_ONBOARDING_PRODUCT_SUBSCRIPTION_DESC'],
            ];
            foreach ($productTypes as $type => $info) : ?>
              <div class="col-md-3 col-6">
                <div class="card j2c-product-type-card h-100" data-product-type="<?php echo $type; ?>" role="checkbox" aria-checked="false" tabindex="0">
                  <div class="card-body">
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

          <!-- Product scale -->
          <div class="mt-3">
            <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PRODUCT_SCALE'); ?></label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="product_scale" id="ob-scale-small" value="small" checked>
                <label class="form-check-label" for="ob-scale-small"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PRODUCT_SCALE_SMALL'); ?></label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="product_scale" id="ob-scale-medium" value="medium">
                <label class="form-check-label" for="ob-scale-medium"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PRODUCT_SCALE_MEDIUM'); ?></label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="product_scale" id="ob-scale-large" value="large">
                <label class="form-check-label" for="ob-scale-large"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_PRODUCT_SCALE_LARGE'); ?></label>
              </div>
            </div>
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
        </div>

        <!-- ============ STEP 5: Ready! ============ -->
        <div class="j2c-step" data-step="5" <?php echo $resumeStep !== 5 ? 'hidden' : ''; ?>>
          <div class="j2c-step-icon j2c-celebration-icon"><span class="fa-solid fa-circle-check" aria-hidden="true"></span></div>
          <h4 class="j2c-step-title"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP5_TITLE'); ?></h4>
          <p class="j2c-step-desc"><?php echo Text::_('COM_J2COMMERCE_ONBOARDING_STEP5_DESC'); ?></p>
          <hr>

          <div class="card mb-4">
            <div class="card-body" id="ob-summary">
              <!-- Populated by JS after step 5 save -->
            </div>
          </div>

          <div class="row g-3 text-center">
            <div class="col-md-4">
              <a href="index.php?option=com_content&view=article&layout=edit" class="btn btn-primary w-100">
                <span class="fa-solid fa-plus me-1" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_READY_ADD_PRODUCT'); ?>
              </a>
            </div>
            <div class="col-md-4">
              <a href="index.php?option=com_j2commerce&view=dashboard" class="btn btn-outline-primary w-100" data-action="close-onboarding">
                <span class="fa-solid fa-gauge-high me-1" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_READY_DASHBOARD'); ?>
              </a>
            </div>
            <div class="col-md-4">
              <a href="index.php?option=com_config&component=com_j2commerce" class="btn btn-outline-secondary w-100">
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
        <button type="button" id="ob-btn-back" class="btn btn-link text-muted d-none" data-action="back">
          <span class="fa-solid fa-chevron-left me-1" aria-hidden="true"></span>
          <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_BTN_BACK'); ?>
        </button>
        <div class="ms-auto d-flex gap-2">
          <button type="button" id="ob-btn-skip" class="btn btn-outline-secondary" data-action="skip">
            <?php echo Text::_('COM_J2COMMERCE_ONBOARDING_BTN_SKIP'); ?>
          </button>
          <button type="button" id="ob-btn-next" class="btn btn-primary" data-action="next">
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

<script>
    // Pass currency metadata and country defaults to JS
    Joomla.getOptions = Joomla.getOptions || function() { return {}; };
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Joomla !== 'undefined' && Joomla.optionsStorage) {
            Joomla.optionsStorage['com_j2commerce.onboarding'] = {
                currencyMeta: <?php echo json_encode($currencyMeta, JSON_HEX_TAG | JSON_HEX_AMP); ?>,
                zoneAjaxUrl: 'index.php?option=com_j2commerce&task=ajax.getZones',
                resumeStep: <?php echo $resumeStep; ?>,
                savedZoneId: <?php echo $zoneId; ?>,
            };
        }
    });
</script>
