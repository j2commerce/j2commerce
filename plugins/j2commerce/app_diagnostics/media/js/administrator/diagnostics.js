/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_app_diagnostics
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

((document, Joomla) => {
    'use strict';

    const init = () => {
        const button = document.getElementById('j2c-diag-mailtest');

        if (!button) {
            return;
        }

        const output = document.getElementById('j2c-diag-mailtest-result');
        const options = Joomla.getOptions('plg_j2commerce_app_diagnostics') || {};

        const alertBox = (variant, lines) => {
            const box = document.createElement('div');
            box.className = `alert alert-${variant}`;

            lines.forEach((line) => {
                const p = document.createElement('p');
                p.className = 'mb-1';
                p.textContent = line;
                box.appendChild(p);
            });

            return box;
        };

        const yesNo = (value) => Joomla.Text._(value ? 'JYES' : 'JNO');

        button.addEventListener('click', async () => {
            button.disabled = true;
            output.replaceChildren(alertBox('info', [Joomla.Text._('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_TEST_RUNNING')]));

            try {
                const body = new URLSearchParams({ task: 'mailtest', [options.token]: 1 });
                const response = await fetch(`${options.url}&task=mailtest`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body,
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const envelope = await response.json();
                const result = Array.isArray(envelope.data) ? envelope.data[0] : envelope.data;

                if (!result || result.success !== true) {
                    output.replaceChildren(alertBox(result && result.skipped ? 'info' : 'danger', [
                        (result && result.message) || Joomla.Text._('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_TEST_ERROR'),
                    ]));

                    return;
                }

                output.replaceChildren(alertBox(result.with_f ? 'success' : 'warning', [
                    `${Joomla.Text._('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_TEST_WITHOUT_F')}: ${yesNo(result.without_f)}`,
                    `${Joomla.Text._('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_TEST_WITH_F')}: ${yesNo(result.with_f)}`,
                    result.verdict,
                ]));
            } catch (error) {
                output.replaceChildren(alertBox('danger', [
                    `${Joomla.Text._('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_TEST_ERROR')} (${error.message})`,
                ]));
            } finally {
                button.disabled = false;
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(document, Joomla);
