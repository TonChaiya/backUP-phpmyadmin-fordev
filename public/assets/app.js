(() => {
    'use strict';

    const toggle = document.querySelector('[data-nav-toggle]');
    const navigation = document.querySelector('[data-primary-nav]');

    if (toggle && navigation) {
        document.documentElement.classList.add('js');
        toggle.hidden = false;

        const close = () => {
            toggle.setAttribute('aria-expanded', 'false');
            navigation.dataset.open = 'false';
        };

        toggle.addEventListener('click', () => {
            const open = toggle.getAttribute('aria-expanded') !== 'true';
            toggle.setAttribute('aria-expanded', String(open));
            navigation.dataset.open = String(open);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                close();
                toggle.focus();
            }
        });

        window.addEventListener('resize', () => {
            if (window.matchMedia('(min-width: 961px)').matches) close();
        });
    }

    const historySearch = document.querySelector('[data-history-search]');
    const historyFilter = document.querySelector('[data-history-filter]');
    const historyResult = document.querySelector('[data-history-result]');
    const historyRows = [...document.querySelectorAll('[data-history-row]')];

    if (historyRows.length > 0 && historySearch && historyFilter) {
        const filterHistory = () => {
            const query = historySearch.value.trim().toLocaleLowerCase();
            const status = historyFilter.value;
            let visible = 0;

            historyRows.forEach((row) => {
                const matchesQuery = query === '' || (row.dataset.searchValue || '').includes(query);
                const matchesStatus = status === 'all' || row.dataset.historyStatus === status;
                row.hidden = !(matchesQuery && matchesStatus);
                if (!row.hidden) visible++;
            });

            if (historyResult) historyResult.textContent = `${visible} รายการ`;
        };

        historySearch.addEventListener('input', filterHistory);
        historyFilter.addEventListener('change', filterHistory);
    }
})();
