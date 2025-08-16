document.addEventListener('DOMContentLoaded', () => {
  let current = new Date();
  let selectedType = null;
  let multiMode = false;
  let dragging = false;
  let dragDates = new Set();
  const monthLabel = document.getElementById('monthLabel');
  const calendarContainer = document.getElementById('calendarContainer');
  const editModalEl = document.getElementById('turnoModal');
  const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
  const editForm = document.getElementById('turnoForm');
  const loadingModalEl = document.getElementById('loadingModal');
  const loadingModal = loadingModalEl ? new bootstrap.Modal(loadingModalEl) : null;
  let firstRender = true;

  function loadTurni(year, month){
    fetch(`ajax/turni_get.php?year=${year}&month=${month+1}`)
      .then(r=>r.json())
      .then(data=>renderCalendar(year, month, data.turni || {}, data.eventi || []));
  }

  function renderCalendar(year, month, turni, eventi){
    calendarContainer.innerHTML = '';
    const dateCells = {};
    const singleEvents = {};
    const multiEvents = [];
    eventi.forEach(ev=>{
      if(!ev.data_fine || ev.data_fine===ev.data_evento){
        (singleEvents[ev.data_evento] = singleEvents[ev.data_evento] || []).push(ev);
      }else{
        multiEvents.push(ev);
      }
    });
    const headers = ['LUN','MAR','MER','GIO','VEN','SAB','DOM'];
    const headerRow = document.createElement('div');
    headerRow.className = 'row row-cols-7 g-0 text-center fw-bold';
    headers.forEach(h=>{
      const col = document.createElement('div');
      col.className='col border py-2';
      col.textContent=h;
      col.style="height: fit-content;";
      headerRow.appendChild(col);
    });
    calendarContainer.appendChild(headerRow);

    const first = new Date(year, month, 1);
    const last = new Date(year, month+1, 0);
    const start = (first.getDay()+6)%7; // monday=0
    let day=1;
    let row = document.createElement('div');
    row.className='row row-cols-7 g-0 position-relative week-row';
    for(let i=0;i<start;i++){
      const col=document.createElement('div');
      col.className='col border bg-secondary bg-opacity-10';
      row.appendChild(col);
    }
    while(day<=last.getDate()){
      if(row.children.length===7){
        calendarContainer.appendChild(row);
        row=document.createElement('div');
        row.className='row row-cols-7 g-0 position-relative week-row';
      }
      const col=document.createElement('div');
      col.className='col border position-relative day-cell';
      const dateStr=`${year}-${String(month+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
      const colIndex=row.children.length;
      col.dataset.date=dateStr;
      col.dataset.index=colIndex;
      const dateLabel=document.createElement('div');
      dateLabel.className='date-label small';
      dateLabel.textContent=day;
      col.appendChild(dateLabel);
      const info=turni[dateStr];
      const dayEvents=singleEvents[dateStr]||[];
      const turniContainer=document.createElement('div');
      turniContainer.className='turni-container';
      const items=[];
      if(info){
        info.forEach(t=>{
          const turno=document.createElement('div');
          turno.className='turno';
          turno.textContent=t.descrizione;
          turno.style.background=t.colore_bg;
          turno.style.color=t.colore_testo;
          turno.dataset.id = t.id;
          turno.dataset.date = dateStr;
          turno.dataset.id_tipo = t.id_tipo;
          turno.dataset.ora_inizio = t.ora_inizio;
          turno.dataset.ora_fine = t.ora_fine;
          turno.dataset.bambini = t.id_utenti_bambini || '';
          turno.dataset.note = t.note || '';
          if(t.iniziali_bambini){
            const b=document.createElement('div');
            b.className='bambini';
            b.textContent=t.iniziali_bambini;
            turno.appendChild(b);
          }
          items.push({time:t.ora_inizio, el:turno});
        });
      }
      dayEvents.forEach(ev=>{
        const evento=document.createElement('div');
        evento.className='turno event';
        evento.style.background=ev.colore || '#6c757d';
        evento.style.color=ev.colore_testo || '#ffffff';
        evento.innerHTML=`<a href="eventi_dettaglio.php?id=${ev.id}" class="text-decoration-none">${ev.titolo}</a>`;
        items.push({time:ev.data_evento.slice(11,19), el:evento});
      });
      items.sort((a,b)=>a.time.localeCompare(b.time));
      items.forEach(it=>turniContainer.appendChild(it.el));
      col.appendChild(turniContainer);
      dateCells[dateStr]={cell:col,row:row,index:colIndex};
      const t=new Date();
      if(year===t.getFullYear() && month===t.getMonth() && day===t.getDate()){
        col.classList.add('border-primary');
      }
      row.appendChild(col);
      day++;
    }
    while(row.children.length<7){
      const col=document.createElement('div');
      col.className='col border bg-secondary bg-opacity-10';
      row.appendChild(col);
    }
    calendarContainer.appendChild(row);
    multiEvents.forEach(ev=>{
      const start=new Date(ev.data_evento);
      const end=ev.data_fine?new Date(ev.data_fine):start;
      if(!ev.data_fine || ev.data_fine===ev.data_evento){
        const info=dateCells[ev.data_evento];
        if(info){
          const turno=document.createElement('div');
          turno.className='turno event';
          turno.style.background=ev.colore || '#6c757d';
          turno.style.color=ev.colore_testo;
          const tCol=ev.colore_testo||'#ffffff';
          turno.innerHTML=`<a href="eventi_dettaglio.php?id=${ev.id}" class="text-decoration-none" style="color:${tCol}">${ev.titolo}</a>`;
          info.cell.querySelector('.turni-container').appendChild(turno);
        }
        return;
      }
      let segStart=new Date(start);
      while(segStart<=end){
        const segStartStr=segStart.toISOString().slice(0,10);
        const info=dateCells[segStartStr];
        if(!info){ segStart.setDate(segStart.getDate()+1); continue; }
        const rowEl=info.row;
        const startIdx=info.index;
        const max=new Date(segStart);
        max.setDate(segStart.getDate()+(6-startIdx));
        const segEnd=end<max?end:max;
        const spanDays=Math.round((segEnd-segStart)/86400000)+1;
        const bar=document.createElement('div');
        bar.className='multi-event';
        bar.style.background=ev.colore || '#6c757d';
        const tCol=ev.colore_testo||'#ffffff';
        bar.style.left=(startIdx/7*100)+'%';
        bar.style.width=(spanDays/7*100)+'%';
        bar.innerHTML=`<a href="eventi_dettaglio.php?id=${ev.id}" style="color:${tCol}">${ev.titolo}</a>`;
        rowEl.appendChild(bar);
        rowEl.classList.add('multi-events');
        segStart.setDate(segEnd.getDate()+1);
      }
    });
    monthLabel.textContent = new Intl.DateTimeFormat('it-IT',{month:'long',year:'numeric'}).format(new Date(year,month,1)).toUpperCase();
    if(firstRender){
      window.scrollTo(0, document.body.scrollHeight);
      calendarContainer.scrollTop = calendarContainer.scrollHeight;
      firstRender = false;
    }
  }

  function openEditModal(el){
    if(!editModal) return;
    editForm.reset();
    document.getElementById('turnoId').value = el.dataset.id;
    document.getElementById('turnoDate').textContent = el.dataset.date;
    document.getElementById('turnoTipo').value = el.dataset.id_tipo;
    document.getElementById('turnoOraInizio').value = el.dataset.ora_inizio;
    document.getElementById('turnoOraFine').value = el.dataset.ora_fine;
    const bambini = el.dataset.bambini ? el.dataset.bambini.split(',') : [];
    document.querySelectorAll('#turnoBambini input[type="checkbox"]').forEach(cb=>{
      cb.checked = bambini.includes(cb.value);
    });
    document.getElementById('turnoNote').value = el.dataset.note || '';
    editModal.show();
  }

  calendarContainer.addEventListener('click', e=>{
    if(e.target.closest('a')) return;
    const turnoEl = e.target.closest('.turno');
    if(!multiMode && selectedType===null && turnoEl && !turnoEl.classList.contains('event')){
      openEditModal(turnoEl);
      return;
    }
    if(multiMode) return;
    const cell=e.target.closest('.day-cell');
    if(!cell) return;
    if(selectedType===null){
      if(typeof openEventoModal==='function'){
        openEventoModal(cell.dataset.date);
      }
      return;
    }
    const date=cell.dataset.date;
    const payload = selectedType==='delete' ? {date} : {date,id_tipo:selectedType};
    fetch('ajax/turni_update.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
      .then(r=>r.json())
      .then(res=>{ if(res.success){ loadTurni(current.getFullYear(), current.getMonth()); }});
  });

  function toggleStateB(show){
    document.getElementById('stateA').classList.toggle('d-none', show);
    document.getElementById('stateB').classList.toggle('d-none', !show);
    if(!show){
      multiMode=false;
      selectedType=null;
      dragDates.clear();
      calendarContainer.querySelectorAll('.multi-selected').forEach(c=>c.classList.remove('multi-selected'));
    }
  }

  document.getElementById('btnSingolo').addEventListener('click', ()=>{multiMode=false;toggleStateB(true);});
  document.getElementById('btnMultipla').addEventListener('click', ()=>{multiMode=true;toggleStateB(true);});
  document.getElementById('closeStateB').addEventListener('click', ()=>toggleStateB(false));

  document.getElementById('pillContainer').addEventListener('click', e=>{
    if(e.target.classList.contains('pill')){
      document.querySelectorAll('#pillContainer .pill').forEach(p=>p.classList.remove('active'));
      e.target.classList.add('active');
      selectedType=e.target.dataset.type;
    }
  });

  if(editModalEl){
    document.getElementById('saveTurno').addEventListener('click', ()=>{
      const payload = {
        id: document.getElementById('turnoId').value,
        id_tipo: document.getElementById('turnoTipo').value,
        ora_inizio: document.getElementById('turnoOraInizio').value,
        ora_fine: document.getElementById('turnoOraFine').value,
        id_utenti_bambini: Array.from(document.querySelectorAll('#turnoBambini input:checked')).map(cb=>cb.value).join(','),
        note: document.getElementById('turnoNote').value
      };
      fetch('ajax/turni_update.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
        .then(r=>r.json())
        .then(res=>{ if(res.success){ editModal.hide(); loadTurni(current.getFullYear(), current.getMonth()); }});
    });
  }

  function handleSelection(cell){
    const date = cell.dataset.date;
    if(!date || dragDates.has(date)) return;
    dragDates.add(date);
    cell.classList.add('multi-selected');
  }

  calendarContainer.addEventListener('pointerdown', e=>{
    if(!multiMode || selectedType===null) return;
    const cell=e.target.closest('.day-cell');
    if(!cell) return;
    dragging=true;
    dragDates.clear();
    calendarContainer.querySelectorAll('.multi-selected').forEach(c=>c.classList.remove('multi-selected'));
    handleSelection(cell);
  });

  calendarContainer.addEventListener('pointerover', e=>{
    if(!dragging) return;
    const cell=e.target.closest('.day-cell');
    if(cell) handleSelection(cell);
  });

  document.addEventListener('pointerup', ()=>{
    if(!dragging) return;
    dragging=false;
    const requests=[];
    dragDates.forEach(date=>{
      const payload = selectedType==='delete'?{date}:{date,id_tipo:selectedType};
      requests.push(fetch('ajax/turni_update.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}).then(r=>r.json()));
    });
    Promise.all(requests).then(()=>{ loadTurni(current.getFullYear(), current.getMonth()); });
  });

  document.getElementById('prevMonth').addEventListener('click', ()=>{current.setMonth(current.getMonth()-1);loadTurni(current.getFullYear(),current.getMonth());});
  document.getElementById('nextMonth').addEventListener('click', ()=>{current.setMonth(current.getMonth()+1);loadTurni(current.getFullYear(),current.getMonth());});

  document.getElementById('btnGoogle').addEventListener('click', ()=>{
    loadingModal && loadingModal.show();
    fetch('ajax/turni_sync_google.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({year:current.getFullYear(),month:current.getMonth()})
    })
    .then(r=>r.json())
    .then(res=>alert(res.message||'Operazione completata'))
    .catch(()=>alert('Errore durante la sincronizzazione'))
    .finally(()=>{ loadingModal && loadingModal.hide(); });
  });

  loadTurni(current.getFullYear(), current.getMonth());
});
