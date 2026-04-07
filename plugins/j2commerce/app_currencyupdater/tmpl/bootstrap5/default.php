<?php
/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.AppCurrencyupdater
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$apiType         = $displayData->params?->get('currency_converter_api_type', 'frankfurter') ?? 'frankfurter';
$exchangerateApiKey = $displayData->params?->get('exchangerate_api_key', '') ?? '';
$currencyapiKey  = $displayData->params?->get('currencyapi_key', '') ?? '';
?>

<div class="j2commerce-configuration">
    <form action="<?php echo htmlspecialchars($displayData->action, ENT_QUOTES, 'UTF-8'); ?>" method="post" name="adminForm" id="adminForm" class="form-horizontal form-validate">

        <input type="hidden" name="option" value="com_j2commerce">
        <input type="hidden" name="view" value="apps">
        <input type="hidden" name="app_id" value="<?php echo (int) $displayData->id; ?>">
        <input type="hidden" name="appTask" id="appTask" value="">
        <input type="hidden" name="task" id="task" value="view">
        <?php echo HTMLHelper::_('form.token'); ?>

        <div class="card">
            <div class="card-header">
                <h3><?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER'); ?></h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-4">
                    <h4 class="alert-heading"><?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_ABOUT'); ?></h4>
                    <p><?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_DESC'); ?></p>
                </div>

                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="control-group">
                            <label class="form-label" for="params_currency_converter_api_type">
                                <?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_API_TYPE'); ?>
                            </label>
                            <select
                                name="params[currency_converter_api_type]"
                                id="params_currency_converter_api_type"
                                class="form-select"
                            >
                                <option value="frankfurter" <?php echo $apiType === 'frankfurter' ? 'selected' : ''; ?>>
                                    <?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_FRANKFURTER'); ?>
                                </option>
                                <option value="exchangerate_host" <?php echo $apiType === 'exchangerate_host' ? 'selected' : ''; ?>>
                                    <?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_EXCHANGERATE_HOST'); ?>
                                </option>
                                <option value="exchangerate_api" <?php echo $apiType === 'exchangerate_api' ? 'selected' : ''; ?>>
                                    <?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_EXCHANGERATE_API'); ?>
                                </option>
                                <option value="currencyapi" <?php echo $apiType === 'currencyapi' ? 'selected' : ''; ?>>
                                    <?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_CURRENCYAPI'); ?>
                                </option>
                            </select>
                            <div class="form-text">
                                <?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_API_TYPE_DESC'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="frankfurter_div" class="row mb-4">
                    <div class="col-lg-6">
                        <div class="alert alert-success">
                            <h5><span class="fa-solid fa-check" aria-hidden="true"></span> <?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_FRANKFURTER'); ?></h5>
                            <p><?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_FRANKFURTER_DESC'); ?></p>
                        </div>
                    </div>
                </div>

                <div id="exchangerate_host_div" class="row mb-4" style="display: none;">
                    <div class="col-lg-6">
                        <div class="alert alert-success">
                            <h5><span class="fa-solid fa-check" aria-hidden="true"></span> <?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_EXCHANGERATE_HOST'); ?></h5>
                            <p><?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_EXCHANGERATE_HOST_DESC'); ?></p>
                        </div>
                    </div>
                </div>

                <div id="exchangerate_api_div" class="row mb-4" style="display: none;">
                    <div class="col-lg-6">
                        <div class="control-group">
                            <label class="form-label" for="params_exchangerate_api_key">
                                <?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_API_KEY'); ?>
                            </label>
                            <input
                                type="text"
                                name="params[exchangerate_api_key]"
                                id="params_exchangerate_api_key"
                                class="form-control"
                                value="<?php echo htmlspecialchars($exchangerateApiKey, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="alert alert-info">
                            <h5><?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_EXCHANGERATE_API'); ?></h5>
                            <p><?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_EXCHANGERATE_API_DESC'); ?></p>
                            <p>
                                <a href="https://app.exchangerate-api.com/keys" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                                    <span class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></span> <?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_GET_API_KEY'); ?>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>

                <div id="currencyapi_div" class="row mb-4" style="display: none;">
                    <div class="col-lg-6">
                        <div class="control-group">
                            <label class="form-label" for="params_currencyapi_key">
                                <?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_API_KEY'); ?>
                            </label>
                            <input
                                type="text"
                                name="params[currencyapi_key]"
                                id="params_currencyapi_key"
                                class="form-control"
                                value="<?php echo htmlspecialchars($currencyapiKey, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="alert alert-info">
                            <h5><?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_CURRENCYAPI'); ?></h5>
                            <p><?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_CURRENCYAPI_DESC'); ?></p>
                            <p>
                                <a href="https://app.currencyapi.com/api-keys" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                                    <span class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></span> <?php echo Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_GET_API_KEY'); ?>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>

<script>
Joomla.submitbutton = (pressbutton) => {
    if (pressbutton === 'save' || pressbutton === 'apply') {
        document.getElementById('task').value = 'view';
        document.getElementById('appTask').value = pressbutton;
        Joomla.submitform('view');
        return;
    }
    Joomla.submitform(pressbutton);
};

(() => {
    const apiTypeSelect = document.getElementById('params_currency_converter_api_type');
    const frankfurterDiv = document.getElementById('frankfurter_div');
    const exchangerateHostDiv = document.getElementById('exchangerate_host_div');
    const exchangerateApiDiv = document.getElementById('exchangerate_api_div');
    const currencyapiDiv = document.getElementById('currencyapi_div');

    const updateVisibility = () => {
        const value = apiTypeSelect.value;

        frankfurterDiv.style.display = value === 'frankfurter' ? 'flex' : 'none';
        exchangerateHostDiv.style.display = value === 'exchangerate_host' ? 'flex' : 'none';
        exchangerateApiDiv.style.display = value === 'exchangerate_api' ? 'flex' : 'none';
        currencyapiDiv.style.display = value === 'currencyapi' ? 'flex' : 'none';
    };

    apiTypeSelect.addEventListener('change', updateVisibility);
    updateVisibility();
})();
</script>
