document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('movimentiTable');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const filters = {
        free: document.getElementById('filterFree'),
        banca: document.getElementById('filterBanca'),
        dataDa: document.getElementById('filterDataDa'),
        dataA: document.getElementById('filterDataA'),
        importoDa: document.getElementById('filterImportoDa'),
        importoA: document.getElementById('filterImportoA'),
        descrizione: document.getElementById('filterDescrizione'),
        descrizioneExtra: document.getElementById('filterDescrizioneExtra'),
        gruppo: document.getElementById('filterGruppo'),
        etichette: document.getElementById('filterEtichette')
    };

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const noResults = document.getElementById('noResults');
    const filterForm = document.getElementById('filtersForm');
    const clearFiltersButton = document.getElementById('clearFilters');
    const selectAll = document.getElementById('selectAllRows');
    const bulkButton = document.getElementById('bulkUpdateButton');
    const bulkForm = document.getElementById('bulkUpdateForm');
    const gruppoToggle = document.getElementById('bulkUpdateGruppoToggle');
    const gruppoSelect = document.getElementById('bulkUpdateGruppo');
    const descrizioneExtraToggle = document.getElementById('bulkUpdateDescrizioneExtraToggle');
    const descrizioneExtraInput = document.getElementById('bulkUpdateDescrizioneExtra');
    const noteToggle = document.getElementById('bulkUpdateNoteToggle');
    const noteInput = document.getElementById('bulkUpdateNote');
    const visibleCountEl = document.getElementById('visibleCount');
    const gruppoLabels = table.dataset.gruppi ? JSON.parse(table.dataset.gruppi) : {};

    const normalize = (value) => (value || '').toString().toLowerCase().trim();

    const parseDateValue = (value, endOfDay = false) => {
        if (!value) return null;
        const suffix = endOfDay ? 'T23:59:59' : 'T00:00:00';
        const parsed = Date.parse(`${value}${suffix}`);
        return Number.isNaN(parsed) ? null : parsed;
    };

    const updateVisibleCount = (visibleCount) => {
        if (!visibleCountEl) return;
        visibleCountEl.textContent = `Visualizzati ${visibleCount} di ${rows.length}`;
    };

    const applyFilters = () => {
        const freeValue = normalize(filters.free.value);
        const bancaValue = normalize(filters.banca.value);
        const dataDaValue = parseDateValue(filters.dataDa.value);
        const dataAValue = parseDateValue(filters.dataA.value, true);
        const importoDaValue = filters.importoDa.value !== '' ? Number(filters.importoDa.value) : null;
        const importoAValue = filters.importoA.value !== '' ? Number(filters.importoA.value) : null;
        const descrizioneValue = normalize(filters.descrizione.value);
        const descrizioneExtraValue = normalize(filters.descrizioneExtra.value);
        const gruppoValue = normalize(filters.gruppo.value);
        const etichetteValue = normalize(filters.etichette.value);

        let visibleCount = 0;

        rows.forEach((row) => {
            const banca = normalize(row.dataset.banca);
            const descrizione = normalize(row.dataset.descrizione);
            const descrizioneExtra = normalize(row.dataset.descrizioneExtra);
            const gruppoId = normalize(row.dataset.gruppoId);
            const etichette = normalize(row.dataset.etichette);
            const note = normalize(row.dataset.note);
            const rowDate = row.dataset.dateTs ? Number(row.dataset.dateTs) * 1000 : null;
            const importoNumero = row.dataset.importo !== undefined ? Number(row.dataset.importo) : null;

            if (bancaValue && !banca.includes(bancaValue)) {
                row.classList.add('d-none');
                return;
            }
            if (dataDaValue && rowDate && rowDate < dataDaValue) {
                row.classList.add('d-none');
                return;
            }
            if (dataAValue && rowDate && rowDate > dataAValue) {
                row.classList.add('d-none');
                return;
            }
            if (importoDaValue !== null && importoNumero !== null && importoNumero < importoDaValue) {
                row.classList.add('d-none');
                return;
            }
            if (importoAValue !== null && importoNumero !== null && importoNumero > importoAValue) {
                row.classList.add('d-none');
                return;
            }
            if (descrizioneValue && !descrizione.includes(descrizioneValue)) {
                row.classList.add('d-none');
                return;
            }
            if (descrizioneExtraValue && !descrizioneExtra.includes(descrizioneExtraValue)) {
                row.classList.add('d-none');
                return;
            }
            if (gruppoValue) {
                if (gruppoValue === '__none__' && gruppoId !== '') {
                    row.classList.add('d-none');
                    return;
                }
                if (gruppoValue !== '__none__' && gruppoId !== gruppoValue) {
                    row.classList.add('d-none');
                    return;
                }
            }
            if (etichetteValue) {
                if (etichetteValue === '__none__' && etichette !== '') {
                    row.classList.add('d-none');
                    return;
                }
                if (etichetteValue !== '__none__' && !etichette.includes(etichetteValue)) {
                    row.classList.add('d-none');
                    return;
                }
            }
            if (
                freeValue &&
                !descrizione.includes(freeValue) &&
                !descrizioneExtra.includes(freeValue) &&
                !note.includes(freeValue)
            ) {
                row.classList.add('d-none');
                return;
            }

            row.classList.remove('d-none');
            visibleCount += 1;
        });

        if (noResults) {
            noResults.classList.toggle('d-none', visibleCount > 0);
        }
        updateVisibleCount(visibleCount);
    };

    const sortState = {
        key: null,
        direction: 'asc'
    };

    const getSortValue = (row, key) => {
        switch (key) {
            case 'banca':
                return normalize(row.dataset.banca);
            case 'data':
                return row.dataset.dateTs ? Number(row.dataset.dateTs) : 0;
            case 'importo':
                return row.dataset.importo ? Number(row.dataset.importo) : 0;
            case 'descrizione':
                return normalize(row.dataset.descrizione);
            case 'descrizione-extra':
                return normalize(row.dataset.descrizioneExtra);
            case 'gruppo':
                return normalize(row.dataset.gruppo);
            case 'etichette':
                return normalize(row.dataset.etichette);
            default:
                return '';
        }
    };

    const applySort = () => {
        if (!sortState.key || !tbody) return;
        rows.sort((a, b) => {
            const valueA = getSortValue(a, sortState.key);
            const valueB = getSortValue(b, sortState.key);
            if (valueA < valueB) return sortState.direction === 'asc' ? -1 : 1;
            if (valueA > valueB) return sortState.direction === 'asc' ? 1 : -1;
            return 0;
        });
        rows.forEach((row) => tbody.appendChild(row));
    };

    const updateSortIndicators = () => {
        table.querySelectorAll('th.sortable').forEach((th) => {
            th.classList.remove('sorted-asc', 'sorted-desc');
            if (th.dataset.sort === sortState.key) {
                th.classList.add(sortState.direction === 'asc' ? 'sorted-asc' : 'sorted-desc');
            }
        });
    };

    table.querySelectorAll('th.sortable').forEach((th) => {
        th.addEventListener('click', () => {
            const key = th.dataset.sort;
            if (sortState.key === key) {
                sortState.direction = sortState.direction === 'asc' ? 'desc' : 'asc';
            } else {
                sortState.key = key;
                sortState.direction = 'asc';
            }
            applySort();
            updateSortIndicators();
        });
    });

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            applyFilters();
            applySort();
        });
    }

    const setDefaultDateRange = () => {
        if (!filters.dataDa || !filters.dataA) return;
        const today = new Date();
        const endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        const startDate = new Date(endDate);
        startDate.setMonth(startDate.getMonth() - 2);
        const toDateInput = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };
        filters.dataDa.value = toDateInput(startDate);
        filters.dataA.value = toDateInput(endDate);
    };

    if (clearFiltersButton) {
        clearFiltersButton.addEventListener('click', () => {
            Object.values(filters).forEach((input) => {
                if (!input) return;
                if (input.tagName === 'SELECT') {
                    input.value = '';
                } else {
                    input.value = '';
                }
            });
            setDefaultDateRange();
            applyFilters();
            applySort();
        });
    }

    const updateBulkButtonState = () => {
        if (!bulkButton) return;
        const anyChecked = rows.some((row) => row.querySelector('.row-select')?.checked);
        bulkButton.disabled = !anyChecked;
    };

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            rows.forEach((row) => {
                const checkbox = row.querySelector('.row-select');
                if (checkbox) {
                    checkbox.checked = selectAll.checked;
                }
            });
            updateBulkButtonState();
        });
    }

    rows.forEach((row) => {
        const checkbox = row.querySelector('.row-select');
        if (!checkbox) return;
        checkbox.addEventListener('change', () => {
            if (!checkbox.checked && selectAll) {
                selectAll.checked = false;
            }
            updateBulkButtonState();
        });
    });

    const toggleInput = (toggle, input) => {
        if (!toggle || !input) return;
        toggle.addEventListener('change', () => {
            input.disabled = !toggle.checked;
        });
    };

    toggleInput(gruppoToggle, gruppoSelect);
    toggleInput(descrizioneExtraToggle, descrizioneExtraInput);
    toggleInput(noteToggle, noteInput);

    if (bulkForm) {
        bulkForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const selectedRows = rows.filter((row) => row.querySelector('.row-select')?.checked);
            if (selectedRows.length === 0) return;

            const fields = {};
            if (gruppoToggle?.checked) {
                fields.id_gruppo_transazione = gruppoSelect?.value ?? '';
            }
            if (descrizioneExtraToggle?.checked) {
                fields.descrizione_extra = descrizioneExtraInput?.value ?? '';
            }
            if (noteToggle?.checked) {
                fields.note = noteInput?.value ?? '';
            }

            const payload = {
                rows: selectedRows.map((row) => ({
                    id: row.dataset.id,
                    tabella: row.dataset.tabella
                })),
                fields
            };

            try {
                const response = await fetch('ajax/update_movimenti_massivo.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                if (!data.success) {
                    return;
                }

                selectedRows.forEach((row) => {
                    if (fields.id_gruppo_transazione !== undefined) {
                        const gruppoId = fields.id_gruppo_transazione;
                        const gruppoLabel = gruppoId !== '' ? gruppoLabels[gruppoId] || `ID ${gruppoId}` : '';
                        const badge = row.querySelector('.cell-gruppo .badge');
                        row.dataset.gruppoId = gruppoId;
                        row.dataset.gruppo = gruppoLabel;
                        if (badge) {
                            badge.textContent = gruppoId !== '' ? `Gruppo: ${gruppoLabel}` : 'Senza gruppo';
                            badge.classList.toggle('bg-info', gruppoId !== '');
                            badge.classList.toggle('text-dark', gruppoId !== '');
                            badge.classList.toggle('bg-secondary', gruppoId === '');
                        }
                    }
                    if (fields.descrizione_extra !== undefined) {
                        const cell = row.querySelector('.cell-descrizione-extra');
                        row.dataset.descrizioneExtra = fields.descrizione_extra;
                        if (cell) {
                            cell.textContent = fields.descrizione_extra;
                        }
                    }
                    if (fields.note !== undefined) {
                        row.dataset.note = fields.note;
                    }
                });

                updateBulkButtonState();
                if (selectAll) selectAll.checked = false;
                applyFilters();
                applySort();

                const modalEl = document.getElementById('bulkUpdateModal');
                if (modalEl && window.bootstrap) {
                    const modalInstance = window.bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                }
            } catch (error) {
                console.error(error);
            }
        });
    }

    setDefaultDateRange();
    applyFilters();
    applySort();
});
