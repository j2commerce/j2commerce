/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 * @copyright   (C)2024-2026 J2Commerce, LLC
 * @license     GNU General Public License version 2 or later
 */
'use strict';

(() => {
    const COLORS = [
        'rgba(75, 192, 192, 1)',
        'rgba(54, 162, 235, 1)',
        'rgba(153, 102, 255, 1)',
        'rgba(255, 159, 64, 1)',
        'rgba(255, 205, 86, 1)',
        'rgba(201, 203, 207, 1)',
        'rgba(255, 99, 132, 1)'
    ];

    const COLORS_BG = [
        'rgba(75, 192, 192, 0.2)',
        'rgba(54, 162, 235, 0.2)',
        'rgba(153, 102, 255, 0.2)',
        'rgba(255, 159, 64, 0.2)',
        'rgba(255, 205, 86, 0.2)',
        'rgba(201, 203, 207, 0.2)',
        'rgba(255, 99, 132, 0.2)'
    ];

    let charts = {};

    function localDateStr(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function fillDateGaps(data, from, to) {
        const map = {};
        (data || []).forEach(d => { map[d.day] = parseFloat(d.revenue); });
        const result = [];
        const current = new Date(from + 'T00:00:00');
        const end = new Date(to + 'T00:00:00');
        while (current <= end) {
            const key = localDateStr(current);
            result.push({ day: key, revenue: map[key] || 0 });
            current.setDate(current.getDate() + 1);
        }
        return result;
    }

    function getOpts() {
        return Joomla.getOptions('com_j2commerce.dashboard') || {};
    }

    function formatCurrency(value) {
        const opts = getOpts();
        const symbol = opts.currencySymbol || '$';
        const position = opts.currencyPosition || 'pre';
        const num = parseFloat(value || 0).toFixed(2);
        return position === 'pre' ? symbol + num : num + symbol;
    }

    function formatChartDate(dateStr) {
        const opts = getOpts();
        let fmt = (opts.dateFormat || 'Y-m-d');
        // Strip time components
        fmt = fmt.replace(/[HhGgisueOPTZAa]/g, '').replace(/[\s:]+$/g, '').trim();
        // Strip year and surrounding separators for shorter daily labels
        fmt = fmt.replace(/[Yy]\s*[\/\-.,]\s*|\s*[\/\-.,]\s*[Yy]/g, '').replace(/^[\/\-.,\s]+|[\/\-.,\s]+$/g, '').trim();
        if (!fmt) fmt = 'm-d';

        const parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;

        const y = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10) - 1;
        const day = parseInt(parts[2], 10);
        const date = new Date(y, m, day);

        const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const monthsShort = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

        let result = '';
        for (let i = 0; i < fmt.length; i++) {
            const c = fmt[i];
            if (c === '\\' && i + 1 < fmt.length) { result += fmt[++i]; continue; }
            switch (c) {
                case 'Y': result += String(date.getFullYear()); break;
                case 'y': result += String(date.getFullYear()).slice(-2); break;
                case 'F': result += months[date.getMonth()]; break;
                case 'M': result += monthsShort[date.getMonth()]; break;
                case 'm': result += String(date.getMonth() + 1).padStart(2, '0'); break;
                case 'n': result += String(date.getMonth() + 1); break;
                case 'd': result += String(date.getDate()).padStart(2, '0'); break;
                case 'j': result += String(date.getDate()); break;
                default: result += c; break;
            }
        }
        return result;
    }

    function destroyChart(name) {
        if (charts[name]) {
            charts[name].destroy();
            delete charts[name];
        }
    }

    function destroyDateFilteredCharts() {
        destroyChart('revenue');
    }

    // ─── Revenue Trend (date-filtered) ───

    function initRevenueChart(data, from, to) {
        destroyChart('revenue');
        const ctx = document.getElementById('chart-revenue');
        if (!ctx) return;

        const filled = (from && to) ? fillDateGaps(data, from, to) : (data || []);
        if (!filled.length) return;

        charts.revenue = new Chart(ctx, {
            type: 'line',
            data: {
                labels: filled.map(d => formatChartDate(d.day)),
                datasets: [{
                    label: 'Revenue',
                    data: filled.map(d => parseFloat(d.revenue)),
                    borderColor: COLORS[0],
                    backgroundColor: COLORS_BG[0],
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (ctx) => formatCurrency(ctx.parsed.y) } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: (v) => formatCurrency(v) } }
                }
            }
        });
    }

    // ─── Monthly Sales (all-time, bar chart, 3 series) ───

    function formatMonth(monthStr) {
        const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const parts = monthStr.split('-');
        if (parts.length !== 2) return monthStr;
        const idx = parseInt(parts[1], 10) - 1;
        return monthNames[idx] + ' ' + parts[0].slice(-2);
    }

    function initMonthlyChart(data) {
        destroyChart('monthly');
        const ctx = document.getElementById('chart-monthly');
        if (!ctx) return;

        if (!data || !data.length) {
            ctx.parentElement.innerHTML = '<p class="text-center text-muted py-4">' +
                (Joomla.Text._('COM_J2COMMERCE_ANALYTICS_NO_DATA') || 'No data available.') + '</p>';
            return;
        }

        charts.monthly = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => formatMonth(d.month)),
                datasets: [
                    {
                        label: 'Revenue',
                        data: data.map(d => parseFloat(d.revenue)),
                        backgroundColor: COLORS[0],
                        borderColor: COLORS[0],
                        borderWidth: 1,
                        stack: 'stack0'
                    },
                    {
                        label: 'Orders',
                        data: data.map(d => parseInt(d.orders, 10)),
                        backgroundColor: COLORS[1],
                        borderColor: COLORS[1],
                        borderWidth: 1,
                        stack: 'stack0'
                    },
                    {
                        label: 'Items',
                        data: data.map(d => parseInt(d.items, 10)),
                        backgroundColor: COLORS[2],
                        borderColor: COLORS[2],
                        borderWidth: 1,
                        stack: 'stack0'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                if (ctx.datasetIndex === 0) return ctx.dataset.label + ': ' + formatCurrency(ctx.parsed.y);
                                return ctx.dataset.label + ': ' + ctx.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: { font: { size: 10 } },
                        grid: { drawOnChartArea: true }
                    },
                    x: {
                        stacked: true,
                        ticks: { maxRotation: 45, minRotation: 0, font: { size: 10 } }
                    }
                }
            }
        });
    }

    // ─── Yearly Sales (all-time, bar chart, 3 series) ───

    function initYearlyChart(data) {
        destroyChart('yearly');
        const ctx = document.getElementById('chart-yearly');
        if (!ctx) return;

        if (!data || !data.length) {
            ctx.parentElement.innerHTML = '<p class="text-center text-muted py-4">' +
                (Joomla.Text._('COM_J2COMMERCE_ANALYTICS_NO_DATA') || 'No data available.') + '</p>';
            return;
        }

        charts.yearly = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => String(d.year)),
                datasets: [
                    {
                        label: 'Revenue',
                        data: data.map(d => parseFloat(d.revenue)),
                        backgroundColor: COLORS[0],
                        borderColor: COLORS[0],
                        borderWidth: 1,
                        stack: 'stack0'
                    },
                    {
                        label: 'Orders',
                        data: data.map(d => parseInt(d.orders, 10)),
                        backgroundColor: COLORS[1],
                        borderColor: COLORS[1],
                        borderWidth: 1,
                        stack: 'stack0'
                    },
                    {
                        label: 'Items',
                        data: data.map(d => parseInt(d.items, 10)),
                        backgroundColor: COLORS[2],
                        borderColor: COLORS[2],
                        borderWidth: 1,
                        stack: 'stack0'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                if (ctx.datasetIndex === 0) return ctx.dataset.label + ': ' + formatCurrency(ctx.parsed.y);
                                return ctx.dataset.label + ': ' + ctx.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: { font: { size: 10 } },
                        grid: { drawOnChartArea: true }
                    },
                    x: {
                        stacked: true,
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });
    }

    // ─── KPI Updates ───

    function calcChange(current, previous) {
        current = parseFloat(current || 0);
        previous = parseFloat(previous || 0);
        if (previous === 0) {
            return { pct: current > 0 ? 100 : 0, dir: current > 0 ? 'up' : 'flat' };
        }
        const pct = ((current - previous) / previous) * 100;
        const dir = pct > 0 ? 'up' : (pct < 0 ? 'down' : 'flat');
        return { pct: Math.abs(Math.round(pct * 10) / 10), dir };
    }

    function changeHtml(change) {
        if (change.dir === 'flat') return '<span class="text-body-secondary">\u2014</span>';
        const icon = change.dir === 'up' ? 'fa-arrow-up' : 'fa-arrow-down'; // todo brian move span
        return '<span><span class="fa-solid ' + icon + '" aria-hidden="true"></span> ' + change.pct + '%</span>';
    }

    function updateKPIs(data) {
        const prev = data.previousPeriod || {};

        const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        const setHtml = (id, html) => { const el = document.getElementById(id); if (el) el.innerHTML = html; };

        setEl('kpi-revenue', data.formattedRevenue || formatCurrency(data.totalRevenue));
        setEl('kpi-orders', String(parseInt(data.orderCount || 0, 10)));
        setEl('kpi-conversion', parseFloat(data.conversionRate || 0).toFixed(1) + '%');
        setEl('kpi-sessions', String(parseInt(data.totalSessions || 0, 10)));

        setHtml('kpi-revenue-change', changeHtml(calcChange(data.totalRevenue, prev.totalRevenue)));
        setHtml('kpi-orders-change', changeHtml(calcChange(data.orderCount, prev.orderCount)));
        setHtml('kpi-conversion-change', changeHtml(calcChange(data.conversionRate, prev.conversionRate)));
        setHtml('kpi-sessions-change', changeHtml(calcChange(data.totalSessions, prev.totalSessions)));

        updateDateRangeLabel();
    }

    function updateDateRangeLabel() {
        const el = document.getElementById('kpi-date-range');
        if (!el) return;
        const from = document.getElementById('dashboard-from')?.value;
        const to = document.getElementById('dashboard-to')?.value;
        if (!from || !to) return;
        const days = Math.round((new Date(to) - new Date(from)) / 86400000);
        const unit = Joomla.Text._(days === 1 ? 'COM_J2COMMERCE_DASHBOARD_DAY' : 'COM_J2COMMERCE_DASHBOARD_DAYS');
        el.innerHTML = Joomla.Text._('COM_J2COMMERCE_DASHBOARD_DATA_BASED_ON')
            .replace('%s', days)
            .replace('%s', unit);
    }

    // ─── AJAX Refresh (date-filtered only) ───

    async function refreshData() {
        const from = document.getElementById('dashboard-from')?.value;
        const to = document.getElementById('dashboard-to')?.value;
        const opts = getOpts();
        const url = opts.ajaxUrl || 'index.php?option=com_j2commerce&task=dashboard.getData&format=json';

        const refreshBtn = document.getElementById('dashboard-refresh');
        if (refreshBtn) {
            refreshBtn.disabled = true;
            refreshBtn.classList.add('disabled');
        }

        try {
            const formData = new FormData();
            formData.append('from', from || '');
            formData.append('to', to || '');

            const csrfToken = Joomla.getOptions('csrf.token') || '';
            const tokenName = typeof csrfToken === 'string' ? csrfToken : Object.keys(csrfToken)[0];
            if (tokenName) formData.append(tokenName, '1');

            const response = await fetch(url, { method: 'POST', body: formData });
            const json = await response.json();

            if (json.success && json.data) {
                const data = json.data;
                if (data.currencySymbol) opts.currencySymbol = data.currencySymbol;
                if (data.currencyPosition) opts.currencyPosition = data.currencyPosition;

                updateKPIs(data);
                destroyDateFilteredCharts();
                initRevenueChart(data.revenueByDay, from, to);
            } else {
                console.error('Dashboard refresh failed:', json.message);
            }
        } catch (err) {
            console.error('Dashboard fetch error:', err);
        } finally {
            if (refreshBtn) {
                refreshBtn.disabled = false;
                refreshBtn.classList.remove('disabled');
            }
        }
    }

    // ─── Tab shown handler — resize charts when tab becomes visible ───

    function onTabShown(e) {
        const tab = e.target || e.detail?.tab;
        if (!tab) return;

        const targetId = tab.getAttribute('data-bs-target') || tab.getAttribute('aria-controls');
        if (!targetId) return;

        const selector = targetId.startsWith('#') ? targetId : '#' + targetId;
        const canvas = document.querySelector(selector + ' canvas');
        if (!canvas) return;

        const chartId = canvas.id.replace('chart-', '');
        if (charts[chartId]) {
            charts[chartId].resize();
        }
    }

    // ─── Init ───

    function initAll() {
        const opts = Joomla.getOptions('com_j2commerce.dashboard');
        if (!opts) return;

        // Date-filtered charts
        initRevenueChart(opts.revenueByDay, opts.from, opts.to);

        // All-time sales tabs
        initMonthlyChart(opts.monthlySales);
        // Yearly chart inits lazily on tab show, but also init if visible
        initYearlyChart(opts.yearlySales);

        // Refresh button
        document.getElementById('dashboard-refresh')?.addEventListener('click', refreshData);

        // Preset buttons
        document.querySelectorAll('.dashboard-preset').forEach(btn => {
            btn.addEventListener('click', () => {
                const days = parseInt(btn.dataset.days, 10);
                const to = new Date();
                const from = new Date();
                // For 1-day preset, include yesterday so the chart has 2 data points
                const chartDays = days === 1 ? 2 : days;
                from.setDate(from.getDate() - chartDays + 1);

                document.getElementById('dashboard-from').value = localDateStr(from);
                document.getElementById('dashboard-to').value = localDateStr(to);

                document.querySelectorAll('.dashboard-preset').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                refreshData();
            });
        });

        // Tab shown — resize charts in newly visible panes (uitab = joomla-tab element)
        ['dashboardMainTabs', 'dashboardSideTabs'].forEach(id => {
            const el = document.getElementById(id + 'Tabs') || document.getElementById(id);
            if (el) {
                el.addEventListener('shown.bs.tab', onTabShown);
                el.addEventListener('joomla.tab.shown', onTabShown);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();

// Plugin Quick Icons — AJAX count polling
(() => {
    function pollQuickIcons() {
        const opts = Joomla.getOptions('com_j2commerce.quickicons', {});
        const ajaxIcons = opts.ajaxIcons || {};

        Object.entries(ajaxIcons).forEach(([id, url]) => {
            const badge = document.getElementById(`${id}-badge`);
            if (!badge) return;

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    if (!data || data.error) return;
                    const count = data.data?.count ?? data.count;
                    const cls = data.data?.class ?? data.class ?? 'secondary';
                    const text = data.data?.text ?? data.text ?? count;

                    if (count !== undefined) {
                        badge.textContent = text ?? count;
                        badge.className = `badge bg-${cls}`;
                    }

                    const wrap = document.getElementById(`${id}-wrap`);
                    const tile = wrap?.querySelector('[class*="alert-"]');
                    if (tile && cls) {
                        tile.className = tile.className.replace(/alert-\w+/, `alert-${cls}`);
                    }
                })
                .catch(() => {});
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', pollQuickIcons);
    } else {
        pollQuickIcons();
    }
})();

// Dashboard Messages — Swiper carousel with dismiss support
(() => {
    const STORAGE_KEY = 'j2c_dismissed_msgs';

    function getDismissed() {
        const session = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]');
        const forever = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
        return [...session, ...forever];
    }

    function dismissMessage(id, mode) {
        const storage = mode === 'forever' ? localStorage : sessionStorage;
        const list = JSON.parse(storage.getItem(STORAGE_KEY) || '[]');
        if (!list.includes(id)) {
            list.push(id);
            storage.setItem(STORAGE_KEY, JSON.stringify(list));
        }
    }

    function initDashboardMessages() {
        const el = document.getElementById('j2commerce-dashboard-messages');
        if (!el || typeof Swiper === 'undefined') return;

        const dismissed = getDismissed();
        el.querySelectorAll('.swiper-slide').forEach(slide => {
            if (dismissed.includes(slide.dataset.messageId)) {
                slide.remove();
            }
        });

        const remaining = el.querySelectorAll('.swiper-slide');
        if (remaining.length === 0) {
            document.getElementById('j2commerce-dashboard-messages-wrap')?.remove();
            return;
        }

        new Swiper(el, {
            effect: 'fade',
            fadeEffect: { crossFade: true },
            speed: 800,
            autoplay: { delay: 10000, disableOnInteraction: true, pauseOnMouseEnter: true },
            loop: false,
        });
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-dismiss-message]');
        if (!btn) return;

        const slide = btn.closest('.swiper-slide');
        const id = slide?.dataset.messageId;
        const mode = btn.dataset.dismissMessage;
        if (!id) return;

        dismissMessage(id, mode);
        slide.remove();

        const el = document.getElementById('j2commerce-dashboard-messages');
        if (el?.swiper) {
            el.swiper.update();
        }
        if (el && el.querySelectorAll('.swiper-slide').length === 0) {
            document.getElementById('j2commerce-dashboard-messages-wrap')?.remove();
        }
    });

    // Always use DOMContentLoaded — Swiper loads as a separate defer script
    // that may not have executed yet when this IIFE runs
    if (document.readyState === 'loading' || document.readyState === 'interactive') {
        document.addEventListener('DOMContentLoaded', initDashboardMessages);
    } else {
        initDashboardMessages();
    }
})();
