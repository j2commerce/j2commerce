/**
 * Filter group edit screen.
 *
 * The per-value Color picker is read only by the Color Swatches input type, so it follows
 * the group's Input Type selection. The subform prefixes its children with a row index, which
 * puts them outside the scope showon resolves against, hence the script.
 */

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('filtergroup-form');

    if (!form) {
        return;
    }

    const inputType = form.querySelector('[name="jform[filter_input_type]"]');
    const subform = form.querySelector('joomla-field-subform');

    if (!inputType || !subform) {
        return;
    }

    const colorRows = (scope) => Array.from(scope.querySelectorAll('[name$="[filter_color]"]'))
        .map((field) => field.closest('.control-group') || field.parentElement)
        .filter(Boolean);

    const apply = (scope) => {
        const show = inputType.value === 'color';

        colorRows(scope).forEach((row) => row.classList.toggle('d-none', !show));
    };

    inputType.addEventListener('change', () => apply(subform));

    // Rows added after load start in whatever state the group is currently set to.
    subform.addEventListener('subform-row-add', (event) => apply(event.detail.row));

    apply(subform);
});
