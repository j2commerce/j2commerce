/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 * @copyright   (C)2024-2026 J2Commerce, LLC
 * @license     GNU General Public License version 2 or later
 */
'use strict';

(() => {
    // Colors matching mod_j2commerce_chart RGBA palette
    const COLORS = [
        'rgba(75, 192, 192, 1)',    // teal
        'rgba(54, 162, 235, 1)',    // blue
        'rgba(153, 102, 255, 1)',   // purple
        'rgba(255, 159, 64, 1)',    // orange
        'rgba(255, 205, 86, 1)',    // yellow
        'rgba(201, 203, 207, 1)',   // gray
        'rgba(255, 99, 132, 1)',    // red
        'rgba(75, 192, 192, 0.8)'  // teal (lighter)
    ];

    const COLORS_BG = [
        'rgba(75, 192, 192, 0.2)',
        'rgba(54, 162, 235, 0.2)',
        'rgba(153, 102, 255, 0.2)',
        'rgba(255, 159, 64, 0.2)',
        'rgba(255, 205, 86, 0.2)',
        'rgba(201, 203, 207, 0.2)',
        'rgba(255, 99, 132, 0.2)',
        'rgba(75, 192, 192, 0.15)'
    ];

    const FUNNEL_ORDER = [
        'PLG_ACTIONLOG_J2COMMERCE_CART_ADD',
        'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_START',
        'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_LOGIN',
        'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_BILLING',
        'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_SHIPPING',
        'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_PAYMENT',
        'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_CONFIRM',
        'PLG_ACTIONLOG_J2COMMERCE_ORDER_PAYMENT_SUCCESS'
    ];
    const FUNNEL_LABELS = [
        'Add to Cart', 'Start', 'Login', 'Billing', 'Shipping', 'Payment', 'Confirm', 'Complete'
    ];

    let charts = {};

    function getOpts() {
        return Joomla.getOptions('com_j2commerce.analytics') || {};
    }

    function formatCurrency(value) {
        const opts = getOpts();
        const symbol = opts.currencySymbol || '$';
        const position = opts.currencyPosition || 'pre';
        const num = parseFloat(value || 0).toFixed(2);
        return position === 'pre' ? symbol + num : num + symbol;
    }

    /**
     * Format a Y-m-d date string using the component's PHP date_format config.
     * Time tokens (H, h, G, g, i, s, u, e, O, P, T, Z, A, a) are stripped
     * since chart labels only display dates.
     */
    function formatChartDate(dateStr) {
        const opts = getOpts();
        let fmt = (opts.dateFormat || 'Y-m-d');

        // Strip time-related PHP tokens and trailing separators
        fmt = fmt.replace(/[HhGgisueOPTZAa]/g, '').replace(/[\s:]+$/g, '').trim();
        if (!fmt) fmt = 'Y-m-d';

        const parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;

        const y = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10) - 1;
        const day = parseInt(parts[2], 10);
        const date = new Date(y, m, day);

        const months = ['January','February','March','April','May','June',
            'July','August','September','October','November','December'];
        const monthsShort = ['Jan','Feb','Mar','Apr','May','Jun',
            'Jul','Aug','Sep','Oct','Nov','Dec'];
        const dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        const dayNamesShort = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

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
                case 'l': result += dayNames[date.getDay()]; break;
                case 'D': result += dayNamesShort[date.getDay()]; break;
                default: result += c; break;
            }
        }
        return result;
    }

    function destroyCharts() {
        Object.values(charts).forEach(c => { if (c) c.destroy(); });
        charts = {};
    }

    function initRevenueChart(data) {
        const ctx = document.getElementById('chart-revenue');
        if (!ctx || !data || !data.length) return;

        charts.revenue = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(d => formatChartDate(d.day)),
                datasets: [{
                    label: 'Revenue',
                    data: data.map(d => parseFloat(d.revenue)),
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
                    tooltip: {
                        callbacks: {
                            label: (ctx) => formatCurrency(ctx.parsed.y)
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => formatCurrency(value)
                        }
                    }
                }
            }
        });
    }

    function initOrdersChart(data) {
        const ctx = document.getElementById('chart-orders');
        if (!ctx || !data || !data.length) return;

        charts.orders = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => formatChartDate(d.day)),
                datasets: [{
                    label: 'Orders',
                    data: data.map(d => parseInt(d.count, 10)),
                    backgroundColor: COLORS_BG[1],
                    borderColor: COLORS[1],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }

    function initStatusChart(data) {
        const ctx = document.getElementById('chart-status');
        if (!ctx || !data || !data.length) return;

        charts.status = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(d => d.status_name),
                datasets: [{
                    data: data.map(d => parseInt(d.count, 10)),
                    backgroundColor: COLORS.slice(0, data.length),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    function initFunnelChart(data) {
        const ctx = document.getElementById('chart-funnel');
        if (!ctx) return;

        const values = FUNNEL_ORDER.map(key => parseInt(data[key] || 0, 10));

        // Add to Cart keeps teal; Start→Complete uses #0d9347 with increasing opacity
        const green = [13, 147, 71]; // #0d9347 RGB
        const stepCount = FUNNEL_ORDER.length - 1; // 7 green bars
        const bgColors = [COLORS_BG[0]]; // teal for Add to Cart
        const borderColors = [COLORS[0]]; // teal for Add to Cart

        for (let i = 0; i < stepCount; i++) {
            const opacity = 0.2 + (i / (stepCount - 1)) * 0.8; // 0.2 → 1.0
            bgColors.push(`rgba(${green[0]}, ${green[1]}, ${green[2]}, ${opacity})`);
            borderColors.push(`rgba(${green[0]}, ${green[1]}, ${green[2]}, ${Math.min(1, opacity + 0.2)})`);
        }

        charts.funnel = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: FUNNEL_LABELS,
                datasets: [{
                    label: 'Users',
                    data: values,
                    backgroundColor: bgColors,
                    borderColor: borderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 0
                        }
                    }
                }
            }
        });
    }

    const HOUR_LABELS = [
        '12 AM', '1 AM', '2 AM', '3 AM', '4 AM', '5 AM',
        '6 AM', '7 AM', '8 AM', '9 AM', '10 AM', '11 AM',
        '12 PM', '1 PM', '2 PM', '3 PM', '4 PM', '5 PM',
        '6 PM', '7 PM', '8 PM', '9 PM', '10 PM', '11 PM'
    ];

    function initSessionsChart(avgByHour) {
        const ctx = document.getElementById('chart-sessions');
        if (!ctx) return;

        charts.sessions = new Chart(ctx, {
            type: 'line',
            data: {
                labels: HOUR_LABELS,
                datasets: [{
                    label: 'Avg Sessions',
                    data: avgByHour || Array(24).fill(0),
                    borderColor: COLORS[1],
                    backgroundColor: COLORS_BG[1],
                    fill: true,
                    tension: 0.3,
                    pointRadius: 0,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                    x: { ticks: { maxTicksLimit: 8 } }
                }
            }
        });
    }

    function initConversionsChart(avgByHour) {
        const ctx = document.getElementById('chart-conversions');
        if (!ctx) return;

        charts.conversions = new Chart(ctx, {
            type: 'line',
            data: {
                labels: HOUR_LABELS,
                datasets: [{
                    label: 'Avg Conversions',
                    data: avgByHour || Array(24).fill(0),
                    borderColor: COLORS[0],
                    backgroundColor: COLORS_BG[0],
                    fill: true,
                    tension: 0.3,
                    pointRadius: 0,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                    x: { ticks: { maxTicksLimit: 8 } }
                }
            }
        });
    }

    function updateBreakdown(breakdown) {
        if (!breakdown || !breakdown.rates) return;

        const opacities = [0.3, 0.5, 0.75, 1];
        const keys = ['sessions', 'addedToCart', 'reachedCheckout', 'completedOrder'];
        const countKeys = ['totalSessions', 'addedToCart', 'reachedCheckout', 'completedOrder'];
        const container = document.getElementById('breakdown-stats');
        if (!container) return;

        const bars = container.querySelectorAll('.progress-bar');
        const badges = container.querySelectorAll('.badge');

        keys.forEach((key, i) => {
            const rate = breakdown.rates[key] || 0;
            const count = parseInt(breakdown[countKeys[i]] || 0, 10);
            if (bars[i]) {
                bars[i].style.width = rate + '%';
                bars[i].style.backgroundColor = 'rgba(54,162,235,' + opacities[i] + ')';
                bars[i].textContent = rate + '%';
                bars[i].setAttribute('aria-valuenow', rate);
            }
            if (badges[i]) {
                badges[i].textContent = count;
            }
        });

        // Update header badge
        const badgeEl = document.getElementById('badge-breakdown-total');
        if (badgeEl) {
            const completed = parseInt(breakdown.completedOrder || 0, 10);
            badgeEl.textContent = completed;
            badgeEl.className = 'badge text-bg-' + (completed > 0 ? 'success' : 'warning');
        }
    }

    function initDeviceChart(devices) {
        const ctx = document.getElementById('chart-devices');
        if (!ctx || !devices || !devices.length) return;

        const deviceColors = {
            'Desktop': 'rgba(54, 162, 235, 0.8)',
            'Mobile': 'rgba(75, 192, 192, 0.8)',
            'Tablet': 'rgba(153, 102, 255, 0.8)',
            'Unknown': 'rgba(201, 203, 207, 0.8)'
        };

        const labels = devices.map(d => d.device);
        const values = devices.map(d => parseInt(d.count, 10));
        const total = values.reduce((a, b) => a + b, 0);
        const colors = labels.map(l => deviceColors[l] || COLORS[5]);

        charts.devices = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '65%',
                plugins: {
                    legend: { position: 'right', labels: { usePointStyle: true, boxWidth: 10, padding: 12 } },
                    tooltip: { callbacks: { label: (c) => c.label + ': ' + c.parsed + ' (' + Math.round(c.parsed / total * 100) + '%)' } }
                }
            },
            plugins: [{
                id: 'centerText',
                afterDraw(chart) {
                    const { width, height, ctx: context } = chart;
                    context.save();
                    const fontSize = Math.min(width, height) / 5;
                    context.font = `bold ${fontSize}px sans-serif`;
                    context.textAlign = 'center';
                    context.textBaseline = 'middle';
                    context.fillStyle = '#333';
                    const centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
                    const centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2;
                    context.fillText(String(total), centerX, centerY);
                    context.restore();
                }
            }]
        });
    }

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
        if (change.dir === 'flat') {
            return '<span class="text-body-secondary">\u2014</span>';
        }
        const icon = change.dir === 'up' ? 'fa-arrow-up' : 'fa-arrow-down';// todo brian move span
        return '<span><span class="fa-solid ' + icon + '" aria-hidden="true"></span> ' + change.pct + '%</span>';
    }

    function updateKPIs(data) {
        const prev = data.previousPeriod || {};

        // Revenue
        const revenueEl = document.getElementById('kpi-revenue');
        if (revenueEl) revenueEl.textContent = data.formattedRevenue || formatCurrency(data.totalRevenue);

        // Orders
        const ordersEl = document.getElementById('kpi-orders');
        if (ordersEl) ordersEl.textContent = parseInt(data.orderCount || 0, 10);

        // AOV
        const aovEl = document.getElementById('kpi-aov');
        if (aovEl) aovEl.textContent = data.formattedAOV || formatCurrency(data.averageOrderValue);

        // Items
        const itemsEl = document.getElementById('kpi-items');
        if (itemsEl) itemsEl.textContent = parseInt(data.itemsSold || 0, 10);

        // Change arrows
        const revenueChange = calcChange(data.totalRevenue, prev.totalRevenue);
        const ordersChange  = calcChange(data.orderCount, prev.orderCount);
        const aovChange     = calcChange(data.averageOrderValue, prev.averageOrderValue);
        const itemsChange   = calcChange(data.itemsSold, prev.itemsSold);

        const setChange = (id, change) => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = changeHtml(change);
        };

        setChange('kpi-revenue-change', revenueChange);
        setChange('kpi-orders-change', ordersChange);
        setChange('kpi-aov-change', aovChange);
        setChange('kpi-items-change', itemsChange);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function updateTopProducts(products) {
        const tbody = document.querySelector('#analytics-products tbody');
        if (!tbody) return;

        if (!products || !products.length) {
            const noDataText = Joomla.Text._('COM_J2COMMERCE_ANALYTICS_NO_DATA') || 'No data available for the selected period.';
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">' + escapeHtml(noDataText) + '</td></tr>';
            return;
        }

        tbody.innerHTML = products.map((p, i) =>
            `<tr>
                <td>${i + 1}</td>
                <td>${escapeHtml(p.name)}</td>
                <td class="text-end">${parseInt(p.total_qty, 10)}</td>
                <td class="text-end">${escapeHtml(p.formatted_revenue || formatCurrency(p.total_revenue))}</td>
            </tr>`
        ).join('');
    }

    async function refreshData() {
        const from = document.getElementById('analytics-from')?.value;
        const to = document.getElementById('analytics-to')?.value;
        const token = document.getElementById('analytics-token')?.value;
        const opts = getOpts();
        const url = opts.ajaxUrl || 'index.php?option=com_j2commerce&task=analytics.getData&format=json';

        const refreshBtn = document.getElementById('analytics-refresh');
        if (refreshBtn) {
            refreshBtn.disabled = true;
            refreshBtn.classList.add('disabled');
        }

        try {
            const formData = new FormData();
            formData.append('from', from || '');
            formData.append('to', to || '');

            // Get CSRF token — Joomla 6 stores csrf.token as a plain string
            const csrfToken = Joomla.getOptions('csrf.token') || '';
            const tokenName = typeof csrfToken === 'string' ? csrfToken : Object.keys(csrfToken)[0];
            if (tokenName) {
                formData.append(tokenName, '1');
            }

            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });

            const json = await response.json();

            if (json.success && json.data) {
                const data = json.data;

                // Update currency info from AJAX response
                if (data.currencySymbol) opts.currencySymbol = data.currencySymbol;
                if (data.currencyPosition) opts.currencyPosition = data.currencyPosition;

                updateKPIs(data);
                destroyCharts();
                initRevenueChart(data.revenueByDay);
                initOrdersChart(data.ordersByDay);
                initStatusChart(data.statusDistribution);
                initFunnelChart(data.checkoutFunnel || {});
                initSessionsChart(data.sessionsAvgByHour);
                initConversionsChart(data.conversionsAvgByHour);
                updateBreakdown(data.conversionBreakdown);
                initDeviceChart(data.deviceTypes);
                updateTopProducts(data.topProducts);

                // Update session/conversion badge values
                const badgeSessions = document.getElementById('badge-sessions-total');
                if (badgeSessions) {
                    const sTotal = data.sessionsTotal || 0;
                    badgeSessions.textContent = sTotal;
                    badgeSessions.className = 'badge text-bg-' + (sTotal > 0 ? 'success' : 'warning');
                }
                const badgeConversions = document.getElementById('badge-conversions-total');
                if (badgeConversions) {
                    const cTotal = data.conversionsTotal || 0;
                    badgeConversions.textContent = cTotal;
                    badgeConversions.className = 'badge text-bg-' + (cTotal > 0 ? 'success' : 'warning');
                }
            } else {
                console.error('Analytics refresh failed:', json.message);
            }
        } catch (err) {
            console.error('Analytics fetch error:', err);
        } finally {
            if (refreshBtn) {
                refreshBtn.disabled = false;
                refreshBtn.classList.remove('disabled');
            }
        }
    }

    function initAll() {
        const opts = Joomla.getOptions('com_j2commerce.analytics');
        if (!opts) return;

        initRevenueChart(opts.revenueByDay);
        initOrdersChart(opts.ordersByDay);
        initStatusChart(opts.statusDistribution);
        initFunnelChart(opts.checkoutFunnel || {});
        initSessionsChart(opts.sessionsAvgByHour);
        initConversionsChart(opts.conversionsAvgByHour);
        updateBreakdown(opts.conversionBreakdown);
        initDeviceChart(opts.deviceTypes);

        // Refresh button
        document.getElementById('analytics-refresh')?.addEventListener('click', refreshData);

        // Preset buttons
        document.querySelectorAll('.analytics-preset').forEach(btn => {
            btn.addEventListener('click', () => {
                const days = parseInt(btn.dataset.days, 10);
                const to = new Date();
                const from = new Date();
                from.setDate(from.getDate() - days);

                document.getElementById('analytics-from').value = from.toISOString().slice(0, 10);
                document.getElementById('analytics-to').value = to.toISOString().slice(0, 10);

                // Update active state
                document.querySelectorAll('.analytics-preset').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                refreshData();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
