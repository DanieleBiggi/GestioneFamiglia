function initUserManager(table, formColumns, primaryKey, lookups, boolCols = [], perms = {}) {
    const tbody = document.querySelector('#data-table tbody');
    const searchInput = document.getElementById('search');
    const userlevelFilter = document.getElementById('userlevelFilter');
    const familyFilter = document.getElementById('familyFilter');
    const form = document.getElementById('record-form');
    const addBtn = document.getElementById('addBtn');
    const modalTitle = document.querySelector('#recordModal .modal-title');
    const idField = form.querySelector(`input[name="${primaryKey}"]`);
    const deleteModalEl = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const familiesModalEl = document.getElementById('familiesModal');
    const familiesList = document.getElementById('familiesList');
    const familiesForm = document.getElementById('families-form');
    const canInsert = perms.canInsert ?? false;
    const canUpdate = perms.canUpdate ?? false;
    const canDelete = perms.canDelete ?? false;
    const canManageFamilies = perms.canManageFamilies ?? false;
    if (!canInsert) addBtn.classList.add('d-none');
    let rows = [];
    let recordModal, deleteModal, familiesModal;
    let deleteId = null;
    let currentUserId = null;
    function load() {
        const params = new URLSearchParams();
        params.append('action', 'list');
        params.append('search', searchInput.value);
        params.append('userlevelid', userlevelFilter.value);
        params.append('id_famiglia', familyFilter.value);
        fetch('ajax/gestione_utenti.php?' + params.toString())
            .then(r => r.json())
            .then(data => { rows = data; render(); });
    }
    function render() {
        tbody.innerHTML = '';
        rows.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${r.username ?? ''}</td>
                <td>${r.nome ?? ''}</td>
                <td>${r.cognome ?? ''}</td>
                <td>${r.email ?? ''}</td>
                <td>${r.famiglia_attuale ?? ''}</td>
                <td>${r.famiglie ?? ''}</td>
            `;
            const actions = document.createElement('td');
            if (canManageFamilies) {
                const famBtn = document.createElement('button');
                famBtn.className = 'btn btn-sm btn-link text-white me-2';
                famBtn.innerHTML = '<i class="bi bi-people"></i>';
                famBtn.addEventListener('click', () => manageFamilies(r[primaryKey]));
                actions.appendChild(famBtn);
            }
            if (canUpdate) {
                if (r.passcode_locked_until) {
                    const unlockBtn = document.createElement('button');
                    unlockBtn.className = 'btn btn-sm btn-link text-warning me-2';
                    unlockBtn.innerHTML = '<i class="bi bi-unlock"></i>';
                    unlockBtn.addEventListener('click', () => unlockPasscode(r[primaryKey]));
                    actions.appendChild(unlockBtn);
                }
                const editBtn = document.createElement('button');
                editBtn.className = 'btn btn-sm btn-link text-white me-2';
                editBtn.innerHTML = '<i class="bi bi-pencil"></i>';
                editBtn.addEventListener('click', () => showForm(r));
                actions.appendChild(editBtn);
            }
            if (canDelete) {
                const delBtn = document.createElement('button');
                delBtn.className = 'btn btn-sm btn-link text-danger';
                delBtn.innerHTML = '<i class="bi bi-trash"></i>';
                delBtn.addEventListener('click', () => confirmDelete(r[primaryKey]));
                actions.appendChild(delBtn);
            }
            tr.appendChild(actions);
            tbody.appendChild(tr);
        });
    }
    function showForm(data = null) {
        if ((data && !canUpdate) || (!data && !canInsert)) return;
        form.reset();
        if (!recordModal) recordModal = new bootstrap.Modal(document.getElementById('recordModal'));
        if (data) {
            form.dataset.mode = 'edit';
            modalTitle.textContent = 'Modifica utente';
            idField.value = data[primaryKey];
            formColumns.forEach(c => {
                if (boolCols.includes(c)) {
                    const radios = form.querySelectorAll(`input[name="${c}"]`);
                    radios.forEach(r => r.checked = String(data[c]) === r.value);
                } else {
                    const field = form.querySelector(`[name="${c}"]`);
                    if (field) field.value = data[c] ?? '';
                }
            });
        } else {
            form.dataset.mode = 'insert';
            modalTitle.textContent = 'Nuovo utente';
            idField.value = '';
            formColumns.forEach(c => {
                if (boolCols.includes(c)) {
                    const radios = form.querySelectorAll(`input[name="${c}"]`);
                    radios.forEach(r => r.checked = false);
                }
            });
        }
        recordModal.show();
    }
    addBtn.addEventListener('click', () => showForm());
    document.getElementById('cancelBtn').addEventListener('click', () => recordModal.hide());
    form.addEventListener('submit', e => {
        e.preventDefault();
        const fd = new FormData(form);
        fd.append('table', table);
        fd.append('action', form.dataset.mode === 'edit' ? 'update' : 'insert');
        fetch('ajax/table_crud.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(() => { recordModal.hide(); load(); });
    });
    function confirmDelete(id) {
        deleteId = id;
        if (!deleteModal) deleteModal = new bootstrap.Modal(deleteModalEl);
        deleteModal.show();
    }
    confirmDeleteBtn.addEventListener('click', () => {
        if (!canDelete || deleteId === null) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('table', table);
        fd.append(primaryKey, deleteId);
        fetch('ajax/table_crud.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(() => { deleteModal.hide(); load(); });
    });
    function unlockPasscode(id) {
        const fd = new FormData();
        fd.append('action', 'unlock_passcode');
        fd.append('id', id);
        fetch('ajax/gestione_utenti.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(() => load());
    }
    function manageFamilies(id) {
        currentUserId = id;
        if (!familiesModal) familiesModal = new bootstrap.Modal(familiesModalEl);
        familiesList.innerHTML = '';
        fetch(`ajax/gestione_utenti.php?action=families&id=${id}`)
            .then(r => r.json())
            .then(data => {
                data.forEach(f => {
                    const div = document.createElement('div');
                    div.className = 'mb-2';
                    const chkId = `fam_${f.id_famiglia}`;
                    div.innerHTML = `
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" id="${chkId}" value="${f.id_famiglia}" ${f.userlevelid ? 'checked' : ''}>
                          <label class="form-check-label" for="${chkId}">${f.nome_famiglia}</label>
                        </div>
                        <select class="form-select form-select-sm bg-dark text-white border-secondary mt-1" data-family="${f.id_famiglia}">
                          ${Object.entries(lookups.userlevelid || {}).map(([ulid, ulname]) => `<option value="${ulid}">${ulname}</option>`).join('')}
                        </select>
                    `;
                    familiesList.appendChild(div);
                    const sel = div.querySelector('select');
                    if (f.userlevelid) sel.value = f.userlevelid;
                });
                familiesModal.show();
            });
    }
    familiesForm.addEventListener('submit', e => {
        e.preventDefault();
        const fd = new FormData();
        fd.append('action', 'save_families');
        fd.append('id', currentUserId);
        familiesList.querySelectorAll('input[type="checkbox"]').forEach(chk => {
            if (chk.checked) {
                const sel = familiesList.querySelector(`select[data-family="${chk.value}"]`);
                fd.append('famiglie[]', chk.value);
                fd.append('userlevels[]', sel.value);
            }
        });
        fetch('ajax/gestione_utenti.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(() => { familiesModal.hide(); load(); });
    });
    searchInput.addEventListener('input', load);
    userlevelFilter.addEventListener('change', load);
    familyFilter.addEventListener('change', load);
    load();
}
