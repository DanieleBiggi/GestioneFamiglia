function initTableManager(table, columns, primaryKey, lookups, boolCols = []) {
    const tbody = document.querySelector('#data-table tbody');
    const searchInput = document.getElementById('search');
    const form = document.getElementById('record-form');
    const addBtn = document.getElementById('addBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const modalTitle = document.querySelector('#recordModal .modal-title');
    const idField = form.querySelector(`input[name="${primaryKey}"]`);
    const deleteModalEl = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    let rows = [];
    let recordModal;
    let deleteModal;
    let deleteId = null;

    function load() {
        fetch(`../ajax/table_crud.php?action=list&table=${encodeURIComponent(table)}`)
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
                const actions = document.createElement('td');
                const editBtn = document.createElement('button');
                editBtn.className = 'btn btn-sm btn-link text-white me-2';
                editBtn.innerHTML = '<i class="bi bi-pencil"></i>';
                editBtn.addEventListener('click', () => showForm(r));
                const delBtn = document.createElement('button');
                delBtn.className = 'btn btn-sm btn-link text-danger';
                delBtn.innerHTML = '<i class="bi bi-trash"></i>';
                delBtn.addEventListener('click', () => confirmDelete(r[primaryKey]));
                actions.appendChild(editBtn);
                actions.appendChild(delBtn);
                tr.appendChild(actions);
                tbody.appendChild(tr);
            });
    }

    function showForm(data = null) {
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
        fetch('../ajax/table_crud.php', { method: 'POST', body: formData })
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

    confirmDeleteBtn.addEventListener('click', () => {
        if (deleteId === null) return;
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('table', table);
        formData.append(primaryKey, deleteId);
        fetch('../ajax/table_crud.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(() => { deleteModal.hide(); load(); });
    });

    load();
}
