function initTableManager(table, columns, primaryKey) {
    const tbody = document.querySelector('#data-table tbody');
    const searchInput = document.getElementById('search');
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
                    td.textContent = r[c];
                    tr.appendChild(td);
                });
                const actions = document.createElement('td');
                const editBtn = document.createElement('button');
                editBtn.textContent = 'Modifica';
                editBtn.addEventListener('click', () => editRow(r));
                const delBtn = document.createElement('button');
                delBtn.textContent = 'Elimina';
                delBtn.addEventListener('click', () => deleteRow(r[primaryKey]));
                actions.appendChild(editBtn);
                actions.appendChild(delBtn);
                tr.appendChild(actions);
                tbody.appendChild(tr);
            });
    }

    searchInput.addEventListener('input', render);

    document.getElementById('add-form').addEventListener('submit', e => {
        e.preventDefault();
        const formData = new FormData(e.target);
        formData.append('action', 'insert');
        formData.append('table', table);
        fetch('../ajax/table_crud.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(() => { e.target.reset(); load(); });
    });

    function editRow(row) {
        const data = {};
        columns.forEach(c => {
            data[c] = prompt(`Modifica ${c}`, row[c]);
        });
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('table', table);
        formData.append(primaryKey, row[primaryKey]);
        columns.forEach(c => {
            if (c === primaryKey) return;
            formData.append(c, data[c]);
        });
        fetch('../ajax/table_crud.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(load);
    }

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
