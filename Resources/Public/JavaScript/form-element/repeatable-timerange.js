/**
 * FormEngine element: Repeatable Time Range
 *
 * Each row has a Von/Bis pair of <input type="time"> elements.
 * Stores comma-separated HH:MM-HH:MM in the hidden result input.
 *
 * Runs as a side-effect on import – no invoke() needed.
 */

const updateResult = (wrapper) => {
    const result = wrapper.querySelector('.enr-repeatable-timerange__result');
    if (!result) { return; }

    const values = [];
    wrapper.querySelectorAll('.enr-repeatable-timerange__row').forEach((row) => {
        const from = row.querySelector('[data-enr-from]')?.value?.trim() ?? '';
        const to   = row.querySelector('[data-enr-to]')?.value?.trim() ?? '';
        if (from && to) {
            values.push(from + '-' + to);
        }
    });

    result.value = values.join(',');
};

const attachRowListeners = (row, wrapper) => {
    row.querySelectorAll('[data-enr-from],[data-enr-to]').forEach((el) => {
        el.addEventListener('change', () => updateResult(wrapper));
    });
};

const buildRow = (wrapper) => {
    const template = wrapper.querySelector('.enr-repeatable-timerange__row-template');
    if (!template) { return null; }

    const uid = 'enr' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);

    const tmp = document.createElement('div');
    tmp.appendChild(template.content.cloneNode(true));
    tmp.innerHTML = tmp.innerHTML.replaceAll('__ENR_UID__', uid);

    return tmp.firstElementChild;
};

const initialize = () => {
    if (window.__enrRepeatableTimerangeInitialized) { return; }
    window.__enrRepeatableTimerangeInitialized = true;

    // Attach change listeners to all existing rows
    document.querySelectorAll('.enr-repeatable-timerange').forEach((wrapper) => {
        wrapper.querySelectorAll('.enr-repeatable-timerange__row').forEach((row) => {
            attachRowListeners(row, wrapper);
        });
    });

    document.addEventListener('click', (e) => {
        // Remove row
        const removeBtn = e.target.closest('.enr-repeatable-timerange__remove');
        if (removeBtn) {
            const wrapper = removeBtn.closest('.enr-repeatable-timerange');
            removeBtn.closest('.enr-repeatable-timerange__row').remove();
            updateResult(wrapper);
            return;
        }

        // Add row
        const addBtn = e.target.closest('.enr-repeatable-timerange__add');
        if (addBtn) {
            const wrapper = addBtn.closest('.enr-repeatable-timerange');
            const list    = wrapper.querySelector('.enr-repeatable-timerange__list');
            const row     = buildRow(wrapper);
            if (row) {
                list.appendChild(row);
                attachRowListeners(row, wrapper);
            }
        }
    });
};

initialize();
