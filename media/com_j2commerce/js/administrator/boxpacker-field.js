/**
 * BoxPacker Field — box definition table management.
 * @package  J2Commerce
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.j2commerce-boxpacker-field').forEach(initBoxPackerField);
});

function initBoxPackerField(container) {
    const hiddenInput = container.querySelector('input[type="hidden"]');
    const tbody = container.querySelector('.boxpacker-boxes-body');
    const noBoxesMsg = container.querySelector('.boxpacker-no-boxes');

    function syncToHidden() {
        const rows = tbody.querySelectorAll('tr');
        const boxes = [];
        rows.forEach(row => {
            const box = {};
            row.querySelectorAll('[data-box-field]').forEach(input => {
                const field = input.dataset.boxField;
                box[field] = field === 'name' ? input.value : parseFloat(input.value) || 0;
            });
            boxes.push(box);
        });
        hiddenInput.value = JSON.stringify(boxes);
        if (noBoxesMsg) {
            noBoxesMsg.style.display = boxes.length === 0 ? '' : 'none';
        }
    }

    function addBoxRow() {
        const index = tbody.querySelectorAll('tr').length;
        const fields = ['name', 'outer_length', 'outer_width', 'outer_height', 'inner_length', 'inner_width', 'inner_height', 'box_weight', 'max_weight'];
        const tr = document.createElement('tr');
        tr.dataset.rowIndex = index;

        fields.forEach(field => {
            const td = document.createElement('td');
            const input = document.createElement('input');
            input.type = field === 'name' ? 'text' : 'number';
            input.className = 'form-control form-control-sm';
            input.dataset.boxField = field;
            if (field === 'name') {
                input.value = 'Box ' + (index + 1);
            } else {
                input.step = '0.1';
                input.min = '0';
                input.value = '';
            }
            td.appendChild(input);
            tr.appendChild(td);
        });

        // Remove button
        const tdBtn = document.createElement('td');
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-danger btn-remove-box';
        btn.innerHTML = '<span class="fa-solid fa-times"></span>';
        tdBtn.appendChild(btn);
        tr.appendChild(tdBtn);

        tbody.appendChild(tr);
        syncToHidden();
    }

    function validateDimensions(row) {
        const get = (f) => parseFloat(row.querySelector(`[data-box-field="${f}"]`)?.value) || 0;
        const pairs = [
            ['inner_length', 'outer_length'],
            ['inner_width', 'outer_width'],
            ['inner_height', 'outer_height'],
        ];
        pairs.forEach(([inner, outer]) => {
            const innerInput = row.querySelector(`[data-box-field="${inner}"]`);
            if (innerInput && get(inner) > get(outer) && get(outer) > 0) {
                innerInput.classList.add('is-invalid');
            } else if (innerInput) {
                innerInput.classList.remove('is-invalid');
            }
        });
    }

    /** 20 commonly used shipping box sizes (inches / pounds). */
    const COMMON_BOXES = [
        { name: 'Small Flat',           outer_length: 10, outer_width: 8,     outer_height: 3,     inner_length: 9.5,  inner_width: 7.5,  inner_height: 2.5,  box_weight: 0.2,  max_weight: 10 },
        { name: 'Book / Media',         outer_length: 12, outer_width: 10,    outer_height: 4,     inner_length: 11.5, inner_width: 9.5,  inner_height: 3.5,  box_weight: 0.25, max_weight: 15 },
        { name: 'Small Cube',           outer_length: 8,  outer_width: 8,     outer_height: 8,     inner_length: 7.5,  inner_width: 7.5,  inner_height: 7.5,  box_weight: 0.2,  max_weight: 15 },
        { name: 'Shoe Box',             outer_length: 14, outer_width: 10,    outer_height: 5,     inner_length: 13.5, inner_width: 9.5,  inner_height: 4.5,  box_weight: 0.3,  max_weight: 15 },
        { name: 'Small Standard',       outer_length: 12, outer_width: 10,    outer_height: 6,     inner_length: 11.5, inner_width: 9.5,  inner_height: 5.5,  box_weight: 0.3,  max_weight: 20 },
        { name: 'Medium Flat',          outer_length: 16, outer_width: 12,    outer_height: 4,     inner_length: 15.5, inner_width: 11.5, inner_height: 3.5,  box_weight: 0.3,  max_weight: 20 },
        { name: 'Medium Standard',      outer_length: 18, outer_width: 14,    outer_height: 8,     inner_length: 17.5, inner_width: 13.5, inner_height: 7.5,  box_weight: 0.5,  max_weight: 30 },
        { name: 'Medium Cube',          outer_length: 14, outer_width: 14,    outer_height: 14,    inner_length: 13.5, inner_width: 13.5, inner_height: 13.5, box_weight: 0.5,  max_weight: 35 },
        { name: 'Medium Tall',          outer_length: 14, outer_width: 14,    outer_height: 24,    inner_length: 13.5, inner_width: 13.5, inner_height: 23.5, box_weight: 0.6,  max_weight: 40 },
        { name: 'Large Standard',       outer_length: 24, outer_width: 18,    outer_height: 10,    inner_length: 23.5, inner_width: 17.5, inner_height: 9.5,  box_weight: 0.7,  max_weight: 45 },
        { name: 'Large Flat',           outer_length: 24, outer_width: 18,    outer_height: 6,     inner_length: 23.5, inner_width: 17.5, inner_height: 5.5,  box_weight: 0.5,  max_weight: 35 },
        { name: 'Large Cube',           outer_length: 18, outer_width: 18,    outer_height: 18,    inner_length: 17.5, inner_width: 17.5, inner_height: 17.5, box_weight: 0.7,  max_weight: 50 },
        { name: 'Large Deep',           outer_length: 24, outer_width: 18,    outer_height: 18,    inner_length: 23.5, inner_width: 17.5, inner_height: 17.5, box_weight: 0.8,  max_weight: 55 },
        { name: 'Extra Large Standard', outer_length: 30, outer_width: 24,    outer_height: 12,    inner_length: 29.5, inner_width: 23.5, inner_height: 11.5, box_weight: 1.0,  max_weight: 60 },
        { name: 'Extra Large Cube',     outer_length: 24, outer_width: 24,    outer_height: 24,    inner_length: 23.5, inner_width: 23.5, inner_height: 23.5, box_weight: 1.0,  max_weight: 65 },
        { name: 'Extra Large Deep',     outer_length: 30, outer_width: 24,    outer_height: 18,    inner_length: 29.5, inner_width: 23.5, inner_height: 17.5, box_weight: 1.2,  max_weight: 65 },
        { name: 'Wardrobe',             outer_length: 24, outer_width: 20,    outer_height: 46,    inner_length: 23.5, inner_width: 19.5, inner_height: 45.5, box_weight: 1.5,  max_weight: 65 },
        { name: 'TV / Mirror Flat',     outer_length: 40, outer_width: 6,     outer_height: 30,    inner_length: 39.5, inner_width: 5.5,  inner_height: 29.5, box_weight: 1.0,  max_weight: 50 },
        { name: 'Long / Tube',          outer_length: 38, outer_width: 6,     outer_height: 6,     inner_length: 37.5, inner_width: 5.5,  inner_height: 5.5,  box_weight: 0.3,  max_weight: 20 },
        { name: 'Heavy Duty',           outer_length: 24, outer_width: 18,    outer_height: 18,    inner_length: 23,   inner_width: 17,   inner_height: 17,   box_weight: 1.5,  max_weight: 120 },
    ];

    function addBoxRowWithData(data) {
        const index = tbody.querySelectorAll('tr').length;
        const fields = ['name', 'outer_length', 'outer_width', 'outer_height', 'inner_length', 'inner_width', 'inner_height', 'box_weight', 'max_weight'];
        const tr = document.createElement('tr');
        tr.dataset.rowIndex = index;

        fields.forEach(field => {
            const td = document.createElement('td');
            const input = document.createElement('input');
            input.type = field === 'name' ? 'text' : 'number';
            input.className = 'form-control form-control-sm';
            input.dataset.boxField = field;
            input.value = data[field] ?? '';
            if (field !== 'name') {
                input.step = '0.1';
                input.min = '0';
            }
            td.appendChild(input);
            tr.appendChild(td);
        });

        const tdBtn = document.createElement('td');
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-danger btn-remove-box';
        btn.innerHTML = '<span class="fa-solid fa-times"></span>';
        tdBtn.appendChild(btn);
        tr.appendChild(tdBtn);

        tbody.appendChild(tr);
    }

    function loadCommonBoxes() {
        const existingCount = tbody.querySelectorAll('tr').length;
        if (existingCount > 0 && !confirm('This will add 20 common box sizes to your existing boxes. Continue?')) {
            return;
        }
        COMMON_BOXES.forEach(box => addBoxRowWithData(box));
        syncToHidden();
    }

    // Event delegation
    container.addEventListener('click', (e) => {
        if (e.target.closest('.btn-add-box')) {
            addBoxRow();
        }
        if (e.target.closest('.btn-remove-box')) {
            e.target.closest('tr').remove();
            syncToHidden();
        }
        if (e.target.closest('.btn-load-common-boxes')) {
            loadCommonBoxes();
        }
    });

    container.addEventListener('input', (e) => {
        if (e.target.closest('.boxpacker-boxes-body')) {
            syncToHidden();
            const row = e.target.closest('tr');
            if (row) validateDimensions(row);
        }
    });
}
