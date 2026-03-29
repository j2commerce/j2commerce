/**
 * BoxPacker Preview — test packing visualization.
 * @package  J2Commerce
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.j2commerce-boxpacker-preview').forEach(initPreview);
});

function initPreview(container) {
    const field = container.closest('.j2commerce-boxpacker-field');
    if (!field) return;

    const itemsTbody = container.querySelector('.preview-items-body');
    const resultsDiv = container.querySelector('.preview-results');

    function t(key, fallback) {
        return Joomla.Text._(key) || fallback;
    }

    function sprintf(str, ...args) {
        let i = 0;
        return str.replace(/%[ds]/g, () => args[i++] ?? '');
    }

    function el(tag, attrs, children) {
        const node = document.createElement(tag);
        if (attrs) {
            for (const [k, v] of Object.entries(attrs)) {
                if (k === 'className') { node.className = v; }
                else if (k === 'textContent') { node.textContent = v; }
                else if (k === 'style' && typeof v === 'string') { node.style.cssText = v; }
                else { node.setAttribute(k, v); }
            }
        }
        if (children) {
            (Array.isArray(children) ? children : [children]).forEach(c => {
                if (typeof c === 'string') { node.appendChild(document.createTextNode(c)); }
                else if (c) { node.appendChild(c); }
            });
        }
        return node;
    }

    function icon(classes) {
        return el('i', { className: classes, 'aria-hidden': 'true' });
    }

    function addTestItemRow() {
        const tr = document.createElement('tr');
        const fields = [
            { name: 'description', type: 'text', placeholder: t('COM_J2COMMERCE_BOXPACKER_PREVIEW_ITEM_NAME', 'Item name'), value: '' },
            { name: 'length', type: 'number', step: '0.1', min: '0.1', value: '' },
            { name: 'width', type: 'number', step: '0.1', min: '0.1', value: '' },
            { name: 'height', type: 'number', step: '0.1', min: '0.1', value: '' },
            { name: 'weight', type: 'number', step: '0.1', min: '0.1', value: '' },
            { name: 'qty', type: 'number', step: '1', min: '1', value: '1' },
        ];

        fields.forEach(f => {
            const td = document.createElement('td');
            const input = document.createElement('input');
            input.type = f.type;
            input.className = 'form-control form-control-sm';
            input.dataset.testField = f.name;
            if (f.placeholder) input.placeholder = f.placeholder;
            if (f.step) input.step = f.step;
            if (f.min) input.min = f.min;
            input.value = f.value;
            td.appendChild(input);
            tr.appendChild(td);
        });

        const tdBtn = document.createElement('td');
        const btn = el('button', { type: 'button', className: 'btn btn-sm btn-outline-danger btn-remove-test-item' }, [
            icon('fa-solid fa-times')
        ]);
        tdBtn.appendChild(btn);
        tr.appendChild(tdBtn);

        itemsTbody.appendChild(tr);
    }

    function collectTestItems() {
        const items = [];
        itemsTbody.querySelectorAll('tr').forEach(row => {
            const item = {};
            row.querySelectorAll('[data-test-field]').forEach(input => {
                const f = input.dataset.testField;
                item[f] = f === 'description' ? input.value : (parseFloat(input.value) || 0);
            });
            if (item.length > 0 || item.width > 0 || item.height > 0) {
                items.push(item);
            }
        });
        return items;
    }

    function collectBoxes() {
        const boxes = [];
        field.querySelectorAll('.boxpacker-boxes-body tr').forEach(row => {
            const box = {};
            row.querySelectorAll('[data-box-field]').forEach(input => {
                const f = input.dataset.boxField;
                box[f] = f === 'name' ? input.value : (parseFloat(input.value) || 0);
            });
            if (box.outer_length > 0 || box.outer_width > 0 || box.outer_height > 0) {
                boxes.push(box);
            }
        });
        return boxes;
    }

    function clearElement(node) {
        while (node.firstChild) { node.removeChild(node.firstChild); }
    }

    function setButtonLoading(btn, loading) {
        btn.disabled = loading;
        clearElement(btn);
        if (loading) {
            btn.appendChild(el('span', { className: 'spinner-border spinner-border-sm', role: 'status', 'aria-hidden': 'true' }));
            btn.appendChild(document.createTextNode(' ' + t('COM_J2COMMERCE_BOXPACKER_PREVIEW_RUNNING', 'Packing...')));
        } else {
            btn.appendChild(icon('fa-solid fa-box-open'));
            btn.appendChild(document.createTextNode(' ' + t('COM_J2COMMERCE_BOXPACKER_PREVIEW_RUN', 'Preview Packing')));
        }
    }

    function showAlert(container, type, message) {
        clearElement(container);
        container.appendChild(el('div', { className: 'alert alert-' + type, textContent: message }));
    }

    async function runPreview() {
        const btn = container.querySelector('.btn-preview-packing');
        const testItems = collectTestItems();

        if (testItems.length === 0) {
            showAlert(resultsDiv, 'info', t('COM_J2COMMERCE_BOXPACKER_PREVIEW_NO_ITEMS', 'Add at least one sample item to test packing.'));
            return;
        }

        setButtonLoading(btn, true);

        const customBoxes = collectBoxes();

        const formEl = field.closest('form');
        const weightUnitId = formEl?.querySelector('[name*="weight_unit"]')?.value || '1';
        const lengthUnitId = formEl?.querySelector('[name*="dimension_unit"]')?.value || '1';

        const token = field.dataset.token || '';

        const formData = new FormData();
        formData.append('option', 'com_j2commerce');
        formData.append('view', 'shipping');
        formData.append('task', 'shipping.previewPacking');
        formData.append('format', 'json');
        formData.append('test_items', JSON.stringify(testItems));
        formData.append('custom_boxes', JSON.stringify(customBoxes));
        formData.append('weight_unit_id', weightUnitId);
        formData.append('length_unit_id', lengthUnitId);
        formData.append(token, '1');

        try {
            const response = await fetch('index.php', { method: 'POST', body: formData });
            const result = await response.json();
            renderResults(result);
        } catch (err) {
            const msg = sprintf(t('COM_J2COMMERCE_BOXPACKER_PREVIEW_ERROR', 'Packing preview failed: %s'), err.message);
            showAlert(resultsDiv, 'danger', msg);
        } finally {
            setButtonLoading(btn, false);
        }
    }

    function buildProgressBar(pct, colorClass) {
        const outer = el('div', { className: 'progress', style: 'height:6px' });
        outer.appendChild(el('div', { className: 'progress-bar ' + colorClass, style: 'width:' + pct.toFixed(1) + '%' }));
        return outer;
    }

    function renderResults(result) {
        clearElement(resultsDiv);

        if (!result.success) {
            showAlert(resultsDiv, 'danger', result.error || t('COM_J2COMMERCE_BOXPACKER_PREVIEW_UNKNOWN_ERROR', 'Unknown error'));
            return;
        }

        // Summary line
        const iconClass = result.unpacked.length === 0 ? 'fa-check text-success' : 'fa-exclamation-triangle text-warning';
        const summaryText = sprintf(t('COM_J2COMMERCE_BOXPACKER_PREVIEW_BOXES_NEEDED', '%d boxes needed for %d items'), result.boxCount, result.itemCount);

        const summaryDiv = el('div', { className: 'mb-3' });
        summaryDiv.appendChild(icon('fa-solid ' + iconClass));
        summaryDiv.appendChild(document.createTextNode(' '));
        summaryDiv.appendChild(el('strong', {}, [summaryText]));

        if (result.method === 'per_item') {
            summaryDiv.appendChild(document.createTextNode(' '));
            summaryDiv.appendChild(el('span', {
                className: 'badge bg-secondary',
                textContent: t('COM_J2COMMERCE_BOXPACKER_PREVIEW_PER_ITEM_MODE', 'per-item mode')
            }));
        }

        resultsDiv.appendChild(summaryDiv);

        // Box cards
        result.boxes.forEach((box, i) => {
            const weightPct = box.maxWeight > 0 ? Math.min(100, (box.totalWeight / box.maxWeight) * 100) : 0;
            const weightColor = weightPct > 90 ? 'bg-danger' : weightPct > 75 ? 'bg-warning' : 'bg-success';
            const volColor = box.volumeUtilisation > 90 ? 'bg-danger' : box.volumeUtilisation > 75 ? 'bg-warning' : 'bg-success';

            const boxTitle = sprintf(t('COM_J2COMMERCE_BOXPACKER_PREVIEW_BOX_N', 'Box %d: %s'), i + 1, box.reference);
            const dimText = box.outerLength + ' \u00D7 ' + box.outerWidth + ' \u00D7 ' + box.outerHeight;

            const card = el('div', { className: 'card mb-2' });

            // Card header
            const header = el('div', { className: 'card-header py-2 d-flex justify-content-between align-items-center' });
            header.appendChild(el('strong', { textContent: boxTitle }));
            header.appendChild(el('span', { className: 'text-muted', textContent: dimText }));
            card.appendChild(header);

            // Card body
            const body = el('div', { className: 'card-body py-2' });

            // Weight row
            const weightDiv = el('div', { className: 'mb-2' });
            if (box.maxWeight > 0) {
                const weightLabel = sprintf(t('COM_J2COMMERCE_BOXPACKER_PREVIEW_WEIGHT_USED', 'Weight: %s / %s'), box.totalWeight, box.maxWeight);
                weightDiv.appendChild(el('small', { className: 'text-muted', textContent: weightLabel }));
                weightDiv.appendChild(buildProgressBar(weightPct, weightColor));
            } else {
                weightDiv.appendChild(el('small', {
                    className: 'text-muted',
                    textContent: t('COM_J2COMMERCE_BOXPACKER_PREVIEW_WEIGHT', 'Weight') + ': ' + box.totalWeight
                }));
            }
            body.appendChild(weightDiv);

            // Volume row
            const volDiv = el('div', { className: 'mb-2' });
            volDiv.appendChild(el('small', {
                className: 'text-muted',
                textContent: t('COM_J2COMMERCE_BOXPACKER_PREVIEW_VOLUME_USED', 'Volume Used') + ': ' + box.volumeUtilisation.toFixed(1) + '%'
            }));
            volDiv.appendChild(buildProgressBar(box.volumeUtilisation, volColor));
            body.appendChild(volDiv);

            // Items list
            const ul = el('ul', { className: 'mb-1 small' });
            box.items.forEach(item => {
                ul.appendChild(el('li', { textContent: item.description }));
            });
            body.appendChild(ul);

            card.appendChild(body);
            resultsDiv.appendChild(card);
        });

        // Unpacked items
        if (result.unpacked.length > 0) {
            const alert = el('div', { className: 'alert alert-warning' });

            const heading = el('strong');
            heading.appendChild(icon('fa-solid fa-exclamation-triangle'));
            heading.appendChild(document.createTextNode(' ' + t('COM_J2COMMERCE_BOXPACKER_PREVIEW_UNPACKED_MSG', 'These items do not fit in any defined box and will be shipped individually.')));
            alert.appendChild(heading);

            const ul = el('ul', { className: 'mb-0 mt-1' });
            result.unpacked.forEach(item => {
                const dimText = item.length + ' \u00D7 ' + item.width + ' \u00D7 ' + item.height + ', ' + item.weight;
                ul.appendChild(el('li', { textContent: item.description + ' (' + dimText + ')' }));
            });
            alert.appendChild(ul);

            resultsDiv.appendChild(alert);
        }
    }

    // Event delegation
    container.addEventListener('click', (e) => {
        if (e.target.closest('.btn-add-test-item')) {
            addTestItemRow();
        }
        if (e.target.closest('.btn-remove-test-item')) {
            e.target.closest('tr').remove();
        }
        if (e.target.closest('.btn-preview-packing')) {
            runPreview();
        }
    });
}
