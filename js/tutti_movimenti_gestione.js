document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('movimentiTable');
    if (!table) return;

    const filters = {
        free: document.getElementById('filterFree'),
        banca: document.getElementById('filterBanca'),
        data: document.getElementById('filterData'),
        importo: document.getElementById('filterImporto'),
        descrizione: document.getElementById('filterDescrizione'),
        descrizioneExtra: document.getElementById('filterDescrizioneExtra'),
        gruppo: document.getElementById('filterGruppo'),
        etichette: document.getElementById('filterEtichette')
    };

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const noResults = document.getElementById('noResults');

    const normalize = (value) => (value || '').toString().toLowerCase().trim();

    const applyFilters = () => {
        const freeValue = normalize(filters.free.value);
        const bancaValue = normalize(filters.banca.value);
        const dataValue = normalize(filters.data.value);
        const importoValue = normalize(filters.importo.value);
        const descrizioneValue = normalize(filters.descrizione.value);
        const descrizioneExtraValue = normalize(filters.descrizioneExtra.value);
        const gruppoValue = normalize(filters.gruppo.value);
        const etichetteValue = normalize(filters.etichette.value);

        let visibleCount = 0;

        rows.forEach((row) => {
            const banca = normalize(row.dataset.banca);
            const data = normalize(row.dataset.data);
            const importo = normalize(row.dataset.importo);
            const descrizione = normalize(row.dataset.descrizione);
            const descrizioneExtra = normalize(row.dataset.descrizioneExtra);
            const gruppo = normalize(row.dataset.gruppo);
            const etichette = normalize(row.dataset.etichette);
            const note = normalize(row.dataset.note);

            if (bancaValue && !banca.includes(bancaValue)) {
                row.classList.add('d-none');
                return;
            }
            if (dataValue && !data.includes(dataValue)) {
                row.classList.add('d-none');
                return;
            }
            if (importoValue && !importo.includes(importoValue)) {
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
            if (gruppoValue && !gruppo.includes(gruppoValue)) {
                row.classList.add('d-none');
                return;
            }
            if (etichetteValue && !etichette.includes(etichetteValue)) {
                row.classList.add('d-none');
                return;
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
    };

    Object.values(filters).forEach((input) => {
        if (!input) return;
        input.addEventListener('input', applyFilters);
    });
});
