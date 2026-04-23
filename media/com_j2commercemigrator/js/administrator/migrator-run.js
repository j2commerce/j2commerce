/**
 * J2Commerce Migrator — Run step: batch migration orchestration
 *
 * Polls the server tier-by-tier, batch-by-batch, updating the progress bar
 * and activity log. Exposes window.J2cmRun, window.J2cmDiscover,
 * window.J2cmPreflight, window.J2cmPlan, window.J2cmVerify, and
 * window.J2cmFinalize namespaces for the core wizard to call.
 *
 * @copyright (C)2024-2026 J2Commerce, LLC
 * @license GNU General Public License version 2 or later
 */

'use strict';

((document, window) => {

    // ===== Discover =====

    window.J2cmDiscover = {
        async run() {
            const { apiFetch, hideSkeleton, showBtn, setAlert, state } = window.J2cmCore;

            try {
                const data = await apiFetch('migrate.audit', { adapter: state.adapterKey });
                hideSkeleton('j2cm-discover-skeleton', 'j2cm-discover-tiers');
                renderTierAudit(normalizeTiers(data.tiers));
                showBtn('j2cm-btn-next-preflight');
            } catch (err) {
                setAlert('j2cm-discover-status', 'j2cm-discover-alert', 'danger', err.message);
            }
        },
    };

    /**
     * Normalize tiers from the API response (object keyed by tier number)
     * into an array of { name, tables[] } for rendering.
     */
    function normalizeTiers(tiersObj) {
        return Object.entries(tiersObj ?? {}).map(([tierNum, tier]) => ({
            key:    tierNum,
            name:   tier.name ?? '',
            label:  tier.name ?? '',
            tables: Object.entries(tier.tables ?? {}).map(([srcTable, t]) => ({
                source:        t.source_table ?? srcTable,
                target:        t.target_table ?? '',
                source_count:  t.source_count ?? t.source ?? 0,
                source_error:  t.source_error ?? null,
                target_count:  t.target_count ?? t.target ?? 0,
                target_error:  t.target_error ?? null,
                status:        t.status ?? '',
            })),
        }));
    }

    function renderTierAudit(tiers) {
        const container = document.getElementById('j2cm-discover-tiers');
        if (!container) return;

        container.innerHTML = tiers.map((tier) => {
            const rows = tier.tables.map((t) => {
                const srcCell = t.source_error
                    ? `<span class="badge text-bg-danger" title="${esc(t.source_error)}">ERR</span>`
                    : Number(t.source_count).toLocaleString();
                const tgtCell = t.target_error
                    ? `<span class="badge text-bg-warning" title="${esc(t.target_error)}">ERR</span>`
                    : Number(t.target_count).toLocaleString();
                return `
                <tr>
                    <td class="font-monospace small">${esc(t.source)}</td>
                    <td class="font-monospace small text-muted">${esc(t.target)}</td>
                    <td class="text-end">${srcCell}</td>
                    <td class="text-end">${tgtCell}</td>
                </tr>`;
            }).join('');

            return `
            <div class="card mb-3">
                <div class="card-header fw-semibold">${esc(tier.name)}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Source Table</th>
                                <th>Target Table</th>
                                <th class="text-end">Source Rows</th>
                                <th class="text-end">Target Rows</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            </div>`;
        }).join('');
    }

    // ===== Preflight =====

    window.J2cmPreflight = {
        async run() {
            const { apiFetch, hideSkeleton, showBtn, setAlert, state } = window.J2cmCore;

            try {
                const data = await apiFetch('migrate.audit', { adapter: state.adapterKey, mode: 'preflight' });
                hideSkeleton('j2cm-preflight-skeleton', 'j2cm-preflight-checks');
                renderPreflightChecks(data.checks ?? []);

                document.getElementById('j2cm-conflict-settings')?.classList.remove('d-none');

                const hasBlock = (data.checks ?? []).some((c) => c.status === 'fail');
                if (!hasBlock) {
                    showBtn('j2cm-btn-next-plan');
                } else {
                    setAlert('j2cm-preflight-status', 'j2cm-preflight-alert', 'warning',
                        'One or more checks failed. Resolve issues before continuing.');
                }
            } catch (err) {
                setAlert('j2cm-preflight-status', 'j2cm-preflight-alert', 'danger', err.message);
            }
        },
    };

    function renderPreflightChecks(checks) {
        const container = document.getElementById('j2cm-preflight-checks');
        if (!container) return;

        const iconMap = { pass: 'check text-success', warn: 'exclamation-triangle text-warning', fail: 'times text-danger', skip: 'minus text-muted' };

        container.innerHTML = checks.map((c) => {
            const icon = iconMap[c.status] ?? 'circle text-secondary';
            return `
            <div class="d-flex align-items-start gap-2 mb-2 p-2 border rounded-2">
                <span class="fa-solid fa-${icon} mt-1 flex-shrink-0" aria-hidden="true"></span>
                <div>
                    <div class="fw-semibold small">${esc(c.label)}</div>
                    ${c.detail ? `<div class="text-muted small">${esc(c.detail)}</div>` : ''}
                </div>
            </div>`;
        }).join('');
    }

    // ===== Plan =====

    window.J2cmPlan = {
        render() {
            const { state } = window.J2cmCore;
            // Pull audit data from discover step if available; re-fetch if needed
            apiFetch('migrate.audit', { adapter: state.adapterKey }).then((data) => {
                renderPlan(normalizeTiers(data.tiers));
            }).catch(() => {});
        },
    };

    function renderPlan(tiers) {
        let totalRows = 0;
        let totalTables = 0;

        tiers.forEach((tier) => {
            (tier.tables ?? []).forEach((t) => {
                totalRows   += Number(t.source_count) || 0;
                totalTables += 1;
            });
        });

        setText('j2cm-plan-total-rows',   totalRows.toLocaleString());
        setText('j2cm-plan-total-tables', totalTables.toLocaleString());

        const estSeconds = Math.max(1, Math.ceil(totalRows / 500));
        setText('j2cm-plan-estimated-time', formatDuration(estSeconds));

        const container = document.getElementById('j2cm-plan-tiers');
        if (!container) return;

        container.innerHTML = tiers.map((tier) => `
            <div class="card mb-3">
                <div class="card-header fw-semibold">${esc(tier.label)}</div>
                <ul class="list-group list-group-flush">
                    ${(tier.tables ?? []).map((t) => `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="font-monospace small">${esc(t.source)} → ${esc(t.target)}</span>
                        <span class="badge text-bg-secondary">${Number(t.source_count).toLocaleString()} rows</span>
                    </li>`).join('')}
                </ul>
            </div>`).join('');
    }

    // ===== Run =====

    let cancelled = false;

    window.J2cmRun = {
        async start(runState) {
            const { apiFetch, showBtn, hideBtn, setAlert } = window.J2cmCore;
            cancelled = false;

            showBtn('j2cm-btn-cancel-run');
            hideBtn('j2cm-btn-next-verify');

            document.getElementById('j2cm-btn-cancel-run')?.addEventListener('click', () => {
                cancelled = true;
            }, { once: true });

            // Create run record, get tier definitions
            let runData;
            try {
                runData = await apiFetch('migrate.getStatus', {
                    adapter:       runState.adapterKey,
                    conflict_mode: runState.conflictMode,
                    batch_size:    runState.batchSize,
                });
            } catch (err) {
                setAlert('j2cm-run-status', 'j2cm-run-alert', 'danger', err.message);
                hideBtn('j2cm-btn-cancel-run');
                return;
            }

            const tiers = normalizeTiers(runData.tiers);
            renderRunTiers(tiers);

            let totalRows     = 0;
            let processedRows = 0;

            tiers.forEach((tier) => {
                (tier.tables ?? []).forEach((t) => { totalRows += Number(t.source_count) || 0; });
            });

            for (const tier of tiers) {
                if (cancelled) break;

                for (const table of (tier.tables ?? [])) {
                    if (cancelled) break;

                    let offset = 0;
                    let done   = false;

                    updateTierCard(tier.key, table.source, 'running');

                    while (!done && !cancelled) {
                        try {
                            const result = await apiFetch('migrate.migrateTable', {
                                adapter:       runState.adapterKey,
                                source_table:  table.source,
                                target_table:  table.target,
                                conflict_mode: runState.conflictMode,
                                batch_size:    runState.batchSize,
                                offset,
                            });

                            offset       += result.processed ?? 0;
                            processedRows += result.processed ?? 0;

                            appendLog(`[${tier.label}] ${table.source}: ${result.processed} rows (total ${offset})`);
                            updateProgress(processedRows, totalRows);

                            done = (result.processed ?? 0) < runState.batchSize || result.done;
                        } catch (err) {
                            appendLog(`ERROR: ${err.message}`);
                            done = true;
                        }
                    }

                    updateTierCard(tier.key, table.source, cancelled ? 'cancelled' : 'done');
                }
            }

            hideBtn('j2cm-btn-cancel-run');
            showBtn('j2cm-btn-next-verify');

            if (cancelled) {
                setAlert('j2cm-run-status', 'j2cm-run-alert', 'warning', 'Migration cancelled.');
            } else {
                setAlert('j2cm-run-status', 'j2cm-run-alert', 'success', 'Migration complete. Click Next to verify results.');
            }
        },
    };

    function renderRunTiers(tiers) {
        const container = document.getElementById('j2cm-run-tiers');
        if (!container) return;

        container.innerHTML = tiers.map((tier) => `
            <div class="j2cm-tier-card card mb-2 pending" id="j2cm-tier-${esc(tier.key)}">
                <div class="card-body py-2 px-3">
                    <div class="fw-semibold small mb-1">${esc(tier.label)}</div>
                    <div class="d-flex flex-wrap gap-1" id="j2cm-tier-tables-${esc(tier.key)}">
                        ${(tier.tables ?? []).map((t) => `
                        <span class="badge text-bg-secondary font-monospace" id="j2cm-tbl-${esc(t.source.replace(/\W/g, '_'))}">${esc(t.source)}</span>
                        `).join('')}
                    </div>
                </div>
            </div>`).join('');
    }

    function updateTierCard(tierKey, sourceTable, status) {
        const card   = document.getElementById(`j2cm-tier-${tierKey}`);
        const badge  = document.getElementById(`j2cm-tbl-${sourceTable.replace(/\W/g, '_')}`);

        if (card) {
            card.classList.remove('pending', 'running', 'done', 'failed', 'cancelled');
            card.classList.add(status === 'done' ? 'done' : status === 'running' ? 'running' : 'failed');
        }

        if (badge) {
            badge.className = `badge font-monospace ${
                status === 'done' ? 'text-bg-success' :
                status === 'running' ? 'text-bg-info' :
                'text-bg-danger'
            }`;
        }
    }

    function updateProgress(done, total) {
        const pct   = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
        const label = document.getElementById('j2cm-run-progress-label');
        const pctEl = document.getElementById('j2cm-run-progress-pct');
        const bar   = document.getElementById('j2cm-run-progressbar');
        const wrap  = document.getElementById('j2cm-run-progressbar-wrap');

        if (label) label.textContent = `${done.toLocaleString()} / ${total.toLocaleString()} rows`;
        if (pctEl) pctEl.textContent = `${pct}%`;
        if (bar)   bar.style.width   = `${pct}%`;
        if (wrap)  wrap.setAttribute('aria-valuenow', pct);
    }

    function appendLog(message) {
        const log = document.getElementById('j2cm-run-log-output');
        if (!log) return;
        log.textContent += `[${timestamp()}] ${message}\n`;
        log.scrollTop = log.scrollHeight;
    }

    // ===== Verify =====

    window.J2cmVerify = {
        async run() {
            const { apiFetch, hideSkeleton, showBtn, state } = window.J2cmCore;

            try {
                const data = await apiFetch('migrate.audit', { adapter: state.adapterKey, mode: 'verify' });
                hideSkeleton('j2cm-verify-skeleton', 'j2cm-verify-checks');
                renderVerifyTable(normalizeTiers(data.tiers));
                showBtn('j2cm-btn-next-finalize');
            } catch (err) {
                window.J2cmCore.setAlert('j2cm-verify-status', 'j2cm-verify-alert', 'danger', err.message);
            }
        },
    };

    function renderVerifyTable(tiers) {
        const tbody = document.getElementById('j2cm-verify-table-body');
        if (!tbody) return;

        let html = '';
        tiers.forEach((tier) => {
            (tier.tables ?? []).forEach((t) => {
                const hasError = t.source_error !== null || t.target_error !== null;
                const match    = !hasError && t.source_count === t.target_count;
                const status   = hasError
                    ? '<span class="badge text-bg-danger">Error</span>'
                    : match
                        ? '<span class="badge text-bg-success">Match</span>'
                        : '<span class="badge text-bg-warning">Mismatch</span>';
                const srcCell = t.source_error
                    ? `<span class="badge text-bg-danger" title="${esc(t.source_error)}">ERR</span>`
                    : Number(t.source_count).toLocaleString();
                const tgtCell = t.target_error
                    ? `<span class="badge text-bg-warning" title="${esc(t.target_error)}">ERR</span>`
                    : Number(t.target_count).toLocaleString();
                html += `<tr>
                    <td class="font-monospace small">${esc(t.source)}</td>
                    <td class="text-end">${srcCell}</td>
                    <td class="text-end">${tgtCell}</td>
                    <td class="text-center">${status}</td>
                </tr>`;
            });
        });

        tbody.innerHTML = html;
    }

    // ===== Finalize =====

    window.J2cmFinalize = {
        render() {
            // Stats are already set during run phase; just show them
        },
    };

    // ===== Shared apiFetch (local alias) =====
    function apiFetch(action, payload = {}) {
        return window.J2cmCore.apiFetch(action, payload);
    }

    // ===== Utilities =====

    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function timestamp() {
        return new Date().toTimeString().slice(0, 8);
    }

    function formatDuration(seconds) {
        if (seconds < 60) return `${seconds}s`;
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return s > 0 ? `${m}m ${s}s` : `${m}m`;
    }

})(document, window);
