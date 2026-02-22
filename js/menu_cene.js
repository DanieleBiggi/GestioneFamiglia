document.addEventListener('DOMContentLoaded', () => {
  const grid = document.getElementById('menuGrid');
  const editForm = document.getElementById('editMenuForm');
  const importForm = document.getElementById('importMenuForm');
  const weekInfo = document.getElementById('weekInfo');
  const weekPicker = document.getElementById('weekPicker');
  const prevWeekBtn = document.getElementById('prevWeekBtn');
  const nextWeekBtn = document.getElementById('nextWeekBtn');
  const promptBtn = document.getElementById('generatePromptBtn');
  const exportMenuBtn = document.getElementById('exportMenuBtn');
  const promptModalEl = document.getElementById('promptModal');
  const promptTextarea = document.getElementById('generatedPrompt');
  const copyPromptBtn = document.getElementById('copyPromptBtn');
  let lastPayload = null;

  function render(items) {
    if (!grid) return;
    grid.innerHTML = '';
    items.forEach(item => {
      const card = document.createElement('div');
      card.className = 'menu-card d-flex flex-column';
      card.dataset.id = item.id;
      card.dataset.giorno = item.giorno;
      card.dataset.piatto = item.piatto || '';

      const header = document.createElement('div');
      header.className = 'd-flex justify-content-between align-items-start mb-2';
      const title = document.createElement('div');
      title.className = 'fw-semibold text-uppercase small';
      title.textContent = item.giorno;
      header.appendChild(title);

      if (MENU_CENE_CAN_EDIT) {
        const editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.className = 'btn btn-sm btn-outline-light p-1 edit-day-btn';
        editBtn.dataset.id = item.id;
        editBtn.dataset.giorno = item.giorno;
        editBtn.dataset.piatto = item.piatto || '';
        editBtn.innerHTML = '<i class="bi bi-pencil"></i>';
        header.appendChild(editBtn);
      }

      const body = document.createElement('div');
      body.className = 'flex-grow-1 text-break small';
      body.innerHTML = item.piatto ? item.piatto.replace(/\n/g, '<br>') : '<span class="text-muted">Nessun piatto</span>';

      const meta = document.createElement('div');
      meta.className = 'mt-2 d-flex flex-column gap-1';

      if (item.turni?.length) {
        const turniBlock = document.createElement('div');
        turniBlock.className = 'small';
        const title = document.createElement('div');
        title.className = 'text-warning fw-semibold';
        title.textContent = 'Turni (18-22)';
        turniBlock.appendChild(title);

        item.turni.forEach(t => {
          const row = document.createElement('div');
          row.textContent = `${formatTimeRange(t.ora_inizio, t.ora_fine)} - ${t.descrizione}`;
          turniBlock.appendChild(row);
        });
        meta.appendChild(turniBlock);
      }

      if (item.eventi?.length) {
        const eventiBlock = document.createElement('div');
        eventiBlock.className = 'small';
        const title = document.createElement('div');
        title.className = 'text-info fw-semibold';
        title.textContent = 'Eventi (18-22)';
        eventiBlock.appendChild(title);

        item.eventi.forEach(ev => {
          const row = document.createElement('div');
          row.textContent = `${formatTimeRange(ev.ora_evento, ev.ora_fine)} - ${ev.titolo}`;
          eventiBlock.appendChild(row);
        });
        meta.appendChild(eventiBlock);
      }

      card.appendChild(header);
      card.appendChild(body);
      if (meta.childElementCount) {
        card.appendChild(meta);
      }
      grid.appendChild(card);
    });
  }

  function refreshMenu() {
    const weekStart = getSelectedWeekStart();
    const url = new URL('ajax/get_menu_cene.php', window.location.href);
    if (weekStart) {
      url.searchParams.set('week_start', weekStart);
    }

    fetch(url)
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          lastPayload = res;
          render(res.items);
          updateWeekInfo(res.week);
        }
      });
  }

  function updateWeekInfo(week) {
    if (!weekInfo || !week) return;
    const formatter = new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: '2-digit' });
    weekInfo.textContent = `Settimana ${week.number} (${formatter.format(new Date(week.start))} - ${formatter.format(new Date(week.end))})`;

    if (weekPicker) {
      weekPicker.value = toWeekInputValue(new Date(week.start));
    }
  }

  function formatTimeRange(start, end) {
    const display = [];
    if (start) display.push(start.slice(0,5));
    if (end) display.push(end.slice(0,5));
    return display.length ? display.join(' - ') : 'Orario non indicato';
  }

  function getSelectedWeekStart() {
    if (!weekPicker?.value) return formatDateForApi(startOfISOWeek(new Date()));
    const parsed = weekInputToDate(weekPicker.value);
    return parsed ? formatDateForApi(parsed) : formatDateForApi(startOfISOWeek(new Date()));
  }

  function weekInputToDate(value) {
    const [yearStr, weekStr] = value.split('-W');
    const year = Number(yearStr);
    const week = Number(weekStr);
    if (!year || !week) return null;

    const simple = new Date(Date.UTC(year, 0, 1 + (week - 1) * 7));
    const dayOfWeek = simple.getUTCDay() || 7; // 1 (Mon) - 7 (Sun)
    const monday = new Date(simple);
    monday.setUTCDate(simple.getUTCDate() - dayOfWeek + 1);
    return monday;
  }

  function startOfISOWeek(date) {
    const cloned = new Date(date);
    const day = cloned.getDay();
    const diff = (day === 0 ? -6 : 1) - day; // adjust to Monday
    cloned.setDate(cloned.getDate() + diff);
    cloned.setHours(0, 0, 0, 0);
    return cloned;
  }

  function toWeekInputValue(date) {
    const [year, week] = getISOWeek(date);
    return `${year}-W${String(week).padStart(2, '0')}`;
  }

  function getISOWeek(date) {
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const dayNum = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    const weekNum = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    return [d.getUTCFullYear(), weekNum];
  }

  function formatDateForApi(date) {
    return date.toISOString().slice(0, 10);
  }

  function buildPrompt() {
    if (!lastPayload) return '';
    const week = lastPayload.week;
    const nextWeek = lastPayload.nextWeek;
    const menuText = lastPayload.items
      .map(item => `${item.giorno.toLowerCase()}: ${item.piatto || 'nessun piatto indicato'}`)
      .join(', ');

    const formatter = new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: '2-digit' });
    const weekLabel = week ? `${week.number}ª settimana (${formatter.format(new Date(week.start))} - ${formatter.format(new Date(week.end))})` : '';
    const nextWeekLabel = nextWeek ? `${nextWeek.number}ª settimana (${formatter.format(new Date(nextWeek.start))} - ${formatter.format(new Date(nextWeek.end))})` : '';

    const turniRilevanti = [];
    if (nextWeek?.turni) {
      Object.values(nextWeek.turni).forEach(dayTurni => {
        dayTurni.forEach(t => {
          const time = formatTimeRange(t.ora_inizio, t.ora_fine);
          turniRilevanti.push(`${t.giorno} ${formatter.format(new Date(t.data))}: ${time} (${t.descrizione})`);
        });
      });
    }

    const regole = 'quando c\'è un turno che finisce tra le 18 e le 21 serve un piatto preparabile in anticipo o veloce (es. frittata o pollo ai ferri) e quando c\'è un turno che comincia tra le 18 e le 22 serve un piatto adatto anche all\'asporto (es. pizza, frittata, torta salata).';

    return `genera il menù considerando che nella settimana selezionata ${weekLabel} ho mangiato ${menuText}. ` +
      `Considera i turni della prossima settimana ${nextWeekLabel} e applica queste regole: ${regole} ` +
      `Turni rilevanti: ${turniRilevanti.length ? turniRilevanti.join('; ') : 'nessun turno tra le fasce orarie indicate.'}`;
  }

  async function exportMenu() {
    if (!lastPayload?.items?.length) {
      alert('Nessun menù disponibile da esportare');
      return;
    }

    const rows = lastPayload.items
      .filter(item => (item.piatto || '').trim() !== '')
      .map(item => {
        const piatto = item.piatto.replace(/\n+/g, ' ').trim();
        const giorno = (item.giorno || '').toLocaleLowerCase('it-IT').trim();
        return `${piatto} -${giorno}-`;
      });

    if (!rows.length) {
      alert('Nessun piatto disponibile da esportare');
      return;
    }

    try {
      await navigator.clipboard.writeText(rows.join('\n'));
      if (exportMenuBtn) {
        exportMenuBtn.textContent = 'Copiato!';
        setTimeout(() => { exportMenuBtn.textContent = 'Esporta menù'; }, 1500);
      }
    } catch (_) {
      alert('Impossibile copiare il menù negli appunti');
    }
  }

  grid?.addEventListener('click', e => {
    const btn = e.target.closest('.edit-day-btn');
    if (btn) {
      openEditMenuModal({
        id: btn.dataset.id,
        giorno: btn.dataset.giorno,
        piatto: btn.dataset.piatto
      });
    }
  });

  editForm?.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(editForm);
    fetch('ajax/update_menu_cena.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          bootstrap.Modal.getInstance(document.getElementById('editMenuModal'))?.hide();
          refreshMenu();
        } else {
          alert(res.error || 'Errore durante il salvataggio');
        }
      });
  });

  importForm?.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(importForm);
    fetch('ajax/import_menu_cene.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          bootstrap.Modal.getInstance(document.getElementById('importMenuModal'))?.hide();
          importForm.reset();
          refreshMenu();
        } else {
          alert(res.error || 'Errore durante l\'import');
        }
      });
  });

  promptBtn?.addEventListener('click', () => {
    if (!promptTextarea || !promptModalEl) return;
    promptTextarea.value = buildPrompt();
    new bootstrap.Modal(promptModalEl).show();
  });

  exportMenuBtn?.addEventListener('click', async () => {
    await exportMenu();
  });

  copyPromptBtn?.addEventListener('click', async () => {
    if (!promptTextarea?.value) return;
    try {
      await navigator.clipboard.writeText(promptTextarea.value);
      copyPromptBtn.textContent = 'Copiato!';
      setTimeout(() => { copyPromptBtn.textContent = 'Copia prompt'; }, 1500);
    } catch (_) {
      alert('Impossibile copiare il prompt');
    }
  });

  weekPicker?.addEventListener('change', () => {
    refreshMenu();
  });

  prevWeekBtn?.addEventListener('click', () => {
    const current = weekPicker?.value ? weekInputToDate(weekPicker.value) : startOfISOWeek(new Date());
    if (!current || !weekPicker) return;
    current.setUTCDate(current.getUTCDate() - 7);
    weekPicker.value = toWeekInputValue(current);
    refreshMenu();
  });

  nextWeekBtn?.addEventListener('click', () => {
    const current = weekPicker?.value ? weekInputToDate(weekPicker.value) : startOfISOWeek(new Date());
    if (!current || !weekPicker) return;
    current.setUTCDate(current.getUTCDate() + 7);
    weekPicker.value = toWeekInputValue(current);
    refreshMenu();
  });

  if (weekPicker && !weekPicker.value) {
    weekPicker.value = toWeekInputValue(new Date());
  }

  refreshMenu();
});

function openEditMenuModal(item) {
  const form = document.getElementById('editMenuForm');
  const modalEl = document.getElementById('editMenuModal');
  if (form && modalEl) {
    form.reset();
    form.querySelector('[name="id"]').value = item?.id || '';
    form.querySelector('[name="giorno"]').value = item?.giorno || '';
    form.querySelector('[name="piatto"]').value = item?.piatto || '';
    new bootstrap.Modal(modalEl).show();
  }
}

function openImportMenuModal() {
  const form = document.getElementById('importMenuForm');
  const modalEl = document.getElementById('importMenuModal');
  if (form && modalEl) {
    form.reset();
    new bootstrap.Modal(modalEl).show();
  }
}
