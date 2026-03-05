/**
 * FormEngine element: Repeatable Date
 *
 * Each row contains one TYPO3 flatpickr date input.
 * Stores comma-separated Y-m-d values in the hidden result input.
 *
 * Runs as a side-effect on import – no invoke() needed.
 */

const updateResult = (wrapper) => {
    const result = wrapper.querySelector('.enr-repeatable-date__result');
    if (!result) { return; }

    const values = [];
    wrapper.querySelectorAll('[data-enr-value-source]').forEach((el) => {
        const v = el.value.trim();
        if (v.length >= 10) { values.push(v.substring(0, 10)); }
    });

    result.value = values.join(',');
};

const initDatePickers = (container) => {
    import('@typo3/backend/date-time-picker.js').then(({ default: DateTimePicker }) => {
        container.querySelectorAll('[data-enr-value-source]:not([data-datepicker-initialized])').forEach((el) => {
            DateTimePicker.initialize(el);
            el.addEventListener('formengine.dp.change', () => {
                const w = el.closest('.enr-repeatable-date');
                if (w) { updateResult(w); }
            });
        });
    });
};

const buildRow = (wrapper) => {
    const template = wrapper.querySelector('.enr-repeatable-date__row-template');
    if (!template) { return null; }

    const uid = 'enr' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);

    const tmp = document.createElement('div');
    tmp.appendChild(template.content.cloneNode(true));
    tmp.innerHTML = tmp.innerHTML.replaceAll('__ENR_UID__', uid);

    return tmp.firstElementChild;
};

const initialize = () => {
    if (window.__enrRepeatableDateInitialized) { return; }
    window.__enrRepeatableDateInitialized = true;

    // Initialize date pickers on all existing wrappers
    document.querySelectorAll('.enr-repeatable-date').forEach((wrapper) => {
        initDatePickers(wrapper);
    });

    document.addEventListener('click', (e) => {
        // Calendar open button (date mode)
        const calBtn = e.target.closest('[data-enr-open-picker]');
        if (calBtn) {
            e.preventDefault();
            const el = document.getElementById(calBtn.dataset.enrOpenPicker);
            if (el?._flatpickr) { el._flatpickr.open(); } else { el?.focus(); }
            return;
        }

        // Remove row
        const removeBtn = e.target.closest('.enr-repeatable-date__remove');
        if (removeBtn) {
            const wrapper = removeBtn.closest('.enr-repeatable-date');
            removeBtn.closest('.enr-repeatable-date__row').remove();
            updateResult(wrapper);
            return;
        }

        // Add row
        const addBtn = e.target.closest('.enr-repeatable-date__add');
        if (addBtn) {
            const wrapper = addBtn.closest('.enr-repeatable-date');
            const list    = wrapper.querySelector('.enr-repeatable-date__list');
            const row     = buildRow(wrapper);
            if (row) {
                list.appendChild(row);
                initDatePickers(list);
            }
        }
    });
};

initialize();
