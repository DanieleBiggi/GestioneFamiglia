function initTableManager(table, columns, primaryKey, lookups, boolCols = []) {
    const tbody = document.querySelector('#data-table tbody');
    const searchInput = document.getElementById('search');
    const form = document.getElementById('record-form');
    const addBtn = document.getElementById('addBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const idField = form.querySelector(`input[name="${primaryKey}"]`);
    let rows = [];

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
                delBtn.addEventListener('click', () => deleteRow(r[primaryKey]));
                actions.appendChild(editBtn);
                actions.appendChild(delBtn);
                tr.appendChild(actions);
                tbody.appendChild(tr);
            });
    }

    function showForm(data = null) {
        form.reset();
        if (data) {
            form.dataset.mode = 'edit';
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
            idField.value = '';
            columns.forEach(c => {
                if (boolCols.includes(c)) {
                    const radios = form.querySelectorAll(`input[name="${c}"]`);
                    radios.forEach(r => r.checked = false);
                }
            });
        }
        form.classList.remove('d-none');
    }

    addBtn.addEventListener('click', () => showForm());
    cancelBtn.addEventListener('click', () => form.classList.add('d-none'));
    searchInput.addEventListener('input', render);

    form.addEventListener('submit', e => {
        e.preventDefault();
        const formData = new FormData(form);
        formData.append('table', table);
        formData.append('action', form.dataset.mode === 'edit' ? 'update' : 'insert');
        fetch('../ajax/table_crud.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(() => { form.classList.add('d-none'); load(); });
    });

    function deleteRow(id) {
        if (!confirm('Eliminare il record?')) return;
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('table', table);
        formData.append(primaryKey, id);
        fetch('../ajax/table_crud.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(load);
    }

    load();
}
