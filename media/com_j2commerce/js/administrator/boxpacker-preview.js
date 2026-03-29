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

    function addTestItemRow() {
        const tr = document.createElement('tr');
        const fields = [
            { name: 'description', type: 'text', placeholder: 'Item name', value: '' },
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
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-danger btn-remove-test-item';
        btn.innerHTML = '<i class="fa-solid fa-times"></i>';
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

    async function runPreview() {
        const btn = container.querySelector('.btn-preview-packing');
        const testItems = collectTestItems();

        if (testItems.length === 0) {
            resultsDiv.innerHTML = '<div class="alert alert-info">Add at least one sample item to test packing.</div>';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Packing...';

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
            resultsDiv.innerHTML = `<div class="alert alert-danger">Packing preview failed: ${err.message}</div>`;
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-box-open"></i> Preview Packing';
        }
    }

    function renderResults(result) {
        if (!result.success) {
            resultsDiv.innerHTML = `<div class="alert alert-danger">${result.error || 'Unknown error'}</div>`;
            return;
        }

        let html = '';

        const icon = result.unpacked.length === 0 ? 'fa-check text-success' : 'fa-exclamation-triangle text-warning';
        html += `<div class="mb-3"><i class="fa-solid ${icon}"></i> <strong>${result.boxCount} box${result.boxCount !== 1 ? 'es' : ''} needed for ${result.itemCount} item${result.itemCount !== 1 ? 's' : ''}</strong>`;
        if (result.method === 'per_item') {
            html += ' <span class="badge bg-secondary">per-item mode</span>';
        }
        html += '</div>';

        result.boxes.forEach((box, i) => {
            const weightPct = box.maxWeight > 0 ? Math.min(100, (box.totalWeight / box.maxWeight) * 100) : 0;
            const weightColor = weightPct > 90 ? 'bg-danger' : weightPct > 75 ? 'bg-warning' : 'bg-success';
            const volColor = box.volumeUtilisation > 90 ? 'bg-danger' : box.volumeUtilisation > 75 ? 'bg-warning' : 'bg-success';

            html += `<div class="card mb-2">`;
            html += `<div class="card-header py-2 d-flex justify-content-between align-items-center">`;
            html += `<strong>Box ${i + 1}: ${escHtml(box.reference)}</strong>`;
            html += `<span class="text-muted">${box.outerLength} × ${box.outerWidth} × ${box.outerHeight}</span>`;
            html += `</div>`;
            html += `<div class="card-body py-2">`;

            if (box.maxWeight > 0) {
                html += `<div class="mb-2"><small class="text-muted">Weight: ${box.totalWeight} / ${box.maxWeight}</small>`;
                html += `<div class="progress" style="height:6px"><div class="progress-bar ${weightColor}" style="width:${weightPct.toFixed(1)}%"></div></div></div>`;
            } else {
                html += `<div class="mb-2"><small class="text-muted">Weight: ${box.totalWeight}</small></div>`;
            }

            html += `<div class="mb-2"><small class="text-muted">Volume: ${box.volumeUtilisation.toFixed(1)}%</small>`;
            html += `<div class="progress" style="height:6px"><div class="progress-bar ${volColor}" style="width:${box.volumeUtilisation}%"></div></div></div>`;

            html += '<ul class="mb-1 small">';
            box.items.forEach(item => {
                html += `<li>${escHtml(item.description)}</li>`;
            });
            html += '</ul>';

            html += `</div></div>`;
        });

        if (result.unpacked.length > 0) {
            html += '<div class="alert alert-warning">';
            html += `<strong><i class="fa-solid fa-exclamation-triangle"></i> ${result.unpacked.length} item${result.unpacked.length !== 1 ? 's do' : ' does'} not fit in any defined box:</strong><ul class="mb-0 mt-1">`;
            result.unpacked.forEach(item => {
                html += `<li>${escHtml(item.description)} (${item.length} × ${item.width} × ${item.height}, ${item.weight})</li>`;
            });
            html += '</ul></div>';
        }

        resultsDiv.innerHTML = html;
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
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
