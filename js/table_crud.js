function initTableManager(table, columns, primaryKey, lookups, boolCols = [], perms = {}) {
    const tbody = document.querySelector('#data-table tbody');
    const searchInput = document.getElementById('search');
    const form = document.getElementById('record-form');
    const addBtn = document.getElementById('addBtn');
    const canInsert = perms.canInsert ?? false;
    const canUpdate = perms.canUpdate ?? false;
    const canDelete = perms.canDelete ?? false;
    if (!canInsert) addBtn.classList.add('d-none');
    const cancelBtn = document.getElementById('cancelBtn');
    const modalTitle = document.querySelector('#recordModal .modal-title');
    const idField = form.querySelector(`input[name="${primaryKey}"]`);
    const deleteModalEl = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const deleteBtn = document.getElementById('deleteBtn');
    let rows = [];
    let recordModal;
    let deleteModal;
    let deleteId = null;

    function load() {
        fetch(`ajax/table_crud.php?action=list&table=${encodeURIComponent(table)}`)
            .then(r => r.json())
            .then(data => { rows = data; render(); });
    }

    function render() {
        tbody.innerHTML = '';
        const q = searchInput.value.toLowerCase();
        rows.filter(r => Object.values(r).some(v => String(v).toLowerCase().includes(q)))
            .forEach(r => {
                const tr = document.createElement('tr');
                columns.forEach(c => {
                    const td = document.createElement('td');
                    let text = r[c];
                    if (lookups[c] && lookups[c][text] !== undefined) {
                        text = lookups[c][text];
                    } else if (boolCols.includes(c)) {
                        text = text == 1 ? 'si' : 'no';
                    }
                    td.textContent = text;
                    tr.appendChild(td);
                });
                if (canUpdate || canDelete) {
                    tr.classList.add('table-row-clickable');
                    tr.style.cursor = 'pointer';
                    tr.addEventListener('click', () => showForm(r));
                }
                tbody.appendChild(tr);
            });
    }

    function showForm(data = null) {
        if ((data && !canUpdate && !canDelete) || (!data && !canInsert)) return;
        form.reset();
        if (!recordModal) {
            recordModal = new bootstrap.Modal(document.getElementById('recordModal'));
        }
        if (data) {
            form.dataset.mode = 'edit';
            modalTitle.textContent = 'Modifica record';
            idField.value = data[primaryKey];
            columns.forEach(c => {
                if (boolCols.includes(c)) {
                    const radios = form.querySelectorAll(`input[name="${c}"]`);
                    radios.forEach(r => r.checked = String(data[c]) === r.value);
                } else {
                    const field = form.querySelector(`[name="${c}"]`);
                    if (field) field.value = data[c];
                }
            });
            if (deleteBtn) {
                deleteBtn.classList.toggle('d-none', !canDelete);
            }
        } else {
            form.dataset.mode = 'insert';
            modalTitle.textContent = 'Nuovo record';
            idField.value = '';
            columns.forEach(c => {
                if (boolCols.includes(c)) {
                    const radios = form.querySelectorAll(`input[name="${c}"]`);
                    radios.forEach(r => r.checked = false);
                }
            });
            if (deleteBtn) {
                deleteBtn.classList.add('d-none');
            }
        }
        recordModal.show();
    }

    addBtn.addEventListener('click', () => showForm());
    cancelBtn.addEventListener('click', () => recordModal.hide());
    searchInput.addEventListener('input', render);

    form.addEventListener('submit', e => {
        e.preventDefault();
        const formData = new FormData(form);
        formData.append('table', table);
        formData.append('action', form.dataset.mode === 'edit' ? 'update' : 'insert');
        if ((form.dataset.mode === 'edit' && !canUpdate) || (form.dataset.mode === 'insert' && !canInsert)) return;
        fetch('ajax/table_crud.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(() => { recordModal.hide(); load(); });
    });

    function confirmDelete(id) {
        deleteId = id;
        if (!deleteModal) {
            deleteModal = new bootstrap.Modal(deleteModalEl);
        }
        deleteModal.show();
    }

    if (deleteBtn) {
        deleteBtn.addEventListener('click', () => {
            confirmDelete(idField.value);
        });
    }

    confirmDeleteBtn.addEventListener('click', () => {
        if (!canDelete) return;
        if (deleteId === null) return;
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('table', table);
        formData.append(primaryKey, deleteId);
        fetch('ajax/table_crud.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(() => { deleteModal.hide(); load(); });
    });

    load();
}
