function initUserManager(table, formColumns, primaryKey, lookups, boolCols = [], perms = {}) {
    const list = document.getElementById('userList');
    const searchInput = document.getElementById('search');
    const userlevelFilter = document.getElementById('userlevelFilter');
    const familyFilter = document.getElementById('familyFilter');
    const showInactive = document.getElementById('showInactive');
    const addBtn = document.getElementById('addBtn');
    const familiesModalEl = document.getElementById('familiesModal');
    const familiesList = document.getElementById('familiesList');
    const familiesForm = document.getElementById('families-form');
    const canInsert = perms.canInsert ?? false;
    const canUpdate = perms.canUpdate ?? false;
    const canManageFamilies = perms.canManageFamilies ?? false;
    if (!canInsert) addBtn.classList.add('d-none');
    let rows = [];
    let familiesModal;
    let currentUserId = null;
    function load() {
        const params = new URLSearchParams();
        params.append('action', 'list');
        params.append('search', searchInput.value);
        params.append('userlevelid', userlevelFilter.value);
        params.append('id_famiglia', familyFilter.value);
        params.append('showInactive', showInactive.checked ? '1' : '0');
        fetch('ajax/gestione_utenti.php?' + params.toString())
            .then(r => r.json())
            .then(data => { rows = data; render(); });
    }
    function render() {
        list.innerHTML = '';
        const showAll = showInactive.checked || searchInput.value.trim() !== '';
        rows.forEach(r => {
            if (!showAll && !r.attivo) return;
            const card = document.createElement('div');
            card.className = 'movement user-card d-flex justify-content-between align-items-start text-white mb-2';

            const info = document.createElement('div');
            info.className = 'flex-grow-1';
            info.innerHTML = `
                <div class="fw-semibold">${r.username ?? ''}</div>
                <div class="small">${r.nome ?? ''} ${r.cognome ?? ''}</div>
                <div class="small">${r.email ?? ''}</div>
                <div class="small">${r.famiglia_attuale ?? ''}</div>
            `;
            card.appendChild(info);

            const actions = document.createElement('div');
            actions.className = 'ms-2 text-nowrap';
            if (canManageFamilies) {
                const famBtn = document.createElement('button');
                famBtn.className = 'btn btn-sm btn-link text-white me-2';
                famBtn.innerHTML = '<i class="bi bi-people"></i>';
                famBtn.addEventListener('click', e => { e.stopPropagation(); manageFamilies(r[primaryKey]); });
                actions.appendChild(famBtn);
            }
            if (canUpdate && r.passcode_locked_until) {
                const unlockBtn = document.createElement('button');
                unlockBtn.className = 'btn btn-sm btn-link text-warning me-2';
                unlockBtn.innerHTML = '<i class="bi bi-unlock"></i>';
                unlockBtn.disabled = true;
                actions.appendChild(unlockBtn);
            }
            const statusIcon = document.createElement('i');
            statusIcon.className = r.attivo ? 'bi bi-check-circle-fill text-success' : 'bi bi-x-circle-fill text-danger';
            actions.appendChild(statusIcon);
            card.appendChild(actions);

            if (canUpdate) {
                card.addEventListener('click', () => {
                    window.location.href = `gestione_utenti_dettaglio.php?id=${r[primaryKey]}`;
                });
            }
            list.appendChild(card);
        });
    }
    addBtn.addEventListener('click', () => {
        window.location.href = 'gestione_utenti_dettaglio.php';
    });
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
                    if (f.userlevelid !== null && f.userlevelid !== undefined) {
                        sel.value = String(f.userlevelid);
                    }
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
    showInactive.addEventListener('input', load);
    load();
}
