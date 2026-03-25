<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_app_localization_data
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\AppLocalizationData\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class LocalizationDataField extends FormField
{
    protected $type = 'LocalizationData';

    protected function getInput(): string
    {
        $app = Factory::getApplication();
        $wa = $app->getDocument()?->getWebAssetManager();

        if ($wa === null) {
            return '';
        }

        $wa->useScript('core');

        $token = Session::getFormToken();
        $ajaxUrl = Uri::base() . 'index.php?option=com_ajax&plugin=app_localization_data&group=j2commerce&format=json&task=insertTableValues';

        $html = $this->buildHtml();
        $wa->addInlineScript($this->buildScript($ajaxUrl, $token));

        return $html;
    }

    private function buildHtml(): string
    {
        $countriesLabel = Text::_('PLG_J2COMMERCE_APP_LOCALIZATION_DATA_COUNTRIES');
        $zonesLabel = Text::_('PLG_J2COMMERCE_APP_LOCALIZATION_DATA_ZONES');
        $metricsLabel = Text::_('PLG_J2COMMERCE_APP_LOCALIZATION_DATA_METRICS');
        $noteLabel = Text::_('PLG_J2COMMERCE_APP_LOCALIZATION_DATA_NOTE');
        $installLabel = Text::_('PLG_J2COMMERCE_APP_LOCALIZATION_DATA_INSTALL');
        $helpText = Text::_('PLG_J2COMMERCE_APP_LOCALIZATION_DATA_HELP_TEXT');
        $processing = Text::_('PLG_J2COMMERCE_APP_LOCALIZATION_DATA_PROCESSING');
        $baseURL = Uri::root();

        return <<<HTML
<div class="j2commerce-localization-data">
    <div class="row g-4">
        <div class="col-md-3 col-lg-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="img-box mb-3"><img src="{$baseURL}media/plg_j2commerce_app_localization_data/images/localization_countries.webp" class="img-fluid" alt="{$countriesLabel}" /></div>
                    <h3 class="card-title">{$countriesLabel}</h3>
                    <p class="card-text text-muted small mb-3">{$this->getTableInfo('countries')}</p>
                    <button type="button" class="btn btn-primary j2c-localization-btn" data-table="countries">
                        <span class="icon-download me-1" aria-hidden="true"></span> {$installLabel}
                    </button>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-lg-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="img-box mb-3"><img src="{$baseURL}media/plg_j2commerce_app_localization_data/images/localization_zones.webp" class="img-fluid" alt="{$zonesLabel}" /></div>
                    <h3 class="card-title">{$zonesLabel}</h3>
                    <p class="card-text text-muted small mb-3">{$this->getTableInfo('zones')}</p>
                    <button type="button" class="btn btn-primary j2c-localization-btn" data-table="zones">
                        <span class="icon-download me-1" aria-hidden="true"></span> {$installLabel}
                    </button>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-lg-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="img-box mb-3"><img src="{$baseURL}media/plg_j2commerce_app_localization_data/images/localization_weights.webp" class="img-fluid" alt="{$metricsLabel}" /></div>
                    <h3 class="card-title">{$metricsLabel}</h3>
                    <p class="card-text text-muted small mb-3">{$this->getTableInfo('metrics')}</p>
                    <button type="button" class="btn btn-primary j2c-localization-btn" data-table="metrics">
                        <span class="icon-download me-1" aria-hidden="true"></span> {$installLabel}
                    </button>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
              <h4 class="alert-heading">{$noteLabel}</h4>
              <p>{$helpText}</p>
            </div>
        </div>
    </div>
</div>

<div id="j2c-localization-loading" class="d-none text-center my-4">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">{$processing}</span>
    </div>
    <p class="mt-2">{$processing}</p>
</div>
HTML;
    }

    private function buildScript(string $ajaxUrl, string $token): string
    {
        $confirmText = Text::_('PLG_J2COMMERCE_APP_LOCALIZATION_DATA_CONFIRM_RESET', true);
        $errorText = Text::_('PLG_J2COMMERCE_APP_LOCALIZATION_DATA_ERR_REQUEST_FAILED', true);

        return <<<SCRIPT
document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.j2commerce-localization-data');
    if (!container) return;

    const loading = document.getElementById('j2c-localization-loading');

    container.addEventListener('click', e => {
        const button = e.target.closest('.j2c-localization-btn');
        if (!button) return;

        e.preventDefault();

        const table = button.dataset.table;
        const confirmMsg = '{$confirmText}'.replace('%s', table);

        if (!confirm(confirmMsg)) return;

        const buttons = container.querySelectorAll('.j2c-localization-btn');
        buttons.forEach(btn => btn.disabled = true);
        loading.classList.remove('d-none');

        const formData = new FormData();
        formData.append('{$token}', '1');
        formData.append('table', table);

        fetch('{$ajaxUrl}', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(data => {
            // com_ajax wraps plugin result: {success: true, data: ['{"success":true,"message":"..."}'] }
            let result = data;
            if (data.data && Array.isArray(data.data) && data.data.length > 0) {
                try {
                    result = JSON.parse(data.data[0]);
                } catch (e) {
                    // data.data[0] may already be an object
                    result = typeof data.data[0] === 'object' ? data.data[0] : data;
                }
            }
            if (result.success) {
                Joomla.renderMessages({'success': [result.message]});
            } else {
                Joomla.renderMessages({'error': [result.message || '{$errorText}']});
            }
        })
        .catch(err => {
            console.error('[J2C Localization] Request failed for table:', table, err);
            Joomla.renderMessages({'error': ['{$errorText}']});
        })
        .finally(() => {
            buttons.forEach(btn => btn.disabled = false);
            loading.classList.add('d-none');
        });
    });
});
SCRIPT;
    }

    private function getTableInfo(string $table): string
    {
        static $counts = null;

        if ($counts === null) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            try {
                $query = 'SELECT'
                    . ' (SELECT COUNT(*) FROM ' . $db->quoteName('#__j2commerce_countries') . ') AS countries,'
                    . ' (SELECT COUNT(*) FROM ' . $db->quoteName('#__j2commerce_zones') . ') AS zones,'
                    . ' (SELECT COUNT(*) FROM ' . $db->quoteName('#__j2commerce_lengths') . ') AS lengths,'
                    . ' (SELECT COUNT(*) FROM ' . $db->quoteName('#__j2commerce_weights') . ') AS weights';

                $db->setQuery($query);
                $counts = $db->loadAssoc();
            } catch (\Throwable $e) {
                $counts = ['countries' => 0, 'zones' => 0, 'lengths' => 0, 'weights' => 0];
            }
        }

        return match ($table) {
            'metrics' => Text::sprintf(
                'PLG_J2COMMERCE_APP_LOCALIZATION_DATA_TABLE_INFO_METRICS',
                (int) $counts['lengths'],
                (int) $counts['weights']
            ),
            default => Text::sprintf(
                'PLG_J2COMMERCE_APP_LOCALIZATION_DATA_TABLE_INFO',
                (int) ($counts[$table] ?? 0)
            ),
        };
    }
}