document.addEventListener('DOMContentLoaded', () => {
  let current = new Date();
  let selectedType = null;
  const monthLabel = document.getElementById('monthLabel');
  const calendarContainer = document.getElementById('calendarContainer');

  function loadTurni(year, month){
    fetch(`ajax/turni_get.php?year=${year}&month=${month+1}`)
      .then(r=>r.json())
      .then(data=>renderCalendar(year, month, data));
  }

  function renderCalendar(year, month, turni){
    calendarContainer.innerHTML = '';
    const headers = ['LUN','MAR','MER','GIO','VEN','SAB','DOM'];
    const headerRow = document.createElement('div');
    headerRow.className = 'row row-cols-7 g-0 text-center fw-bold';
    headers.forEach(h=>{
      const col = document.createElement('div');
      col.className='col border py-2';
      col.textContent=h;
      headerRow.appendChild(col);
    });
    calendarContainer.appendChild(headerRow);

    const first = new Date(year, month, 1);
    const last = new Date(year, month+1, 0);
    const start = (first.getDay()+6)%7; // monday=0
    let day=1;
    let row = document.createElement('div');
    row.className='row row-cols-7 g-0';
    for(let i=0;i<start;i++){
      const col=document.createElement('div');
      col.className='col border bg-secondary bg-opacity-10';
      row.appendChild(col);
    }
    while(day<=last.getDate()){
      if(row.children.length===7){
        calendarContainer.appendChild(row);
        row=document.createElement('div');
        row.className='row row-cols-7 g-0';
      }
      const col=document.createElement('div');
      col.className='col border p-2 position-relative day-cell';
      const dateStr=`${year}-${String(month+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
      col.dataset.date=dateStr;
      col.innerHTML=`<div class="small text-start">${day}</div>`;
      const info=turni[dateStr];
      if(info){
        col.dataset.idTipo=info.id_tipo;
        col.insertAdjacentHTML('beforeend', `<div class="text-center">${info.descrizione}</div>`);
        col.style.background=info.colore_bg;
        col.style.color=info.colore_testo;
      }
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
    monthLabel.textContent = new Intl.DateTimeFormat('it-IT',{month:'long',year:'numeric'}).format(new Date(year,month,1)).toUpperCase();
  }

  calendarContainer.addEventListener('click', e=>{
    const cell=e.target.closest('.day-cell');
    if(!cell || selectedType===null) return;
    const date=cell.dataset.date;
    const payload = selectedType==='delete' ? {date} : {date,id_tipo:selectedType};
    fetch('ajax/turni_update.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
      .then(r=>r.json())
      .then(res=>{ if(res.success){ loadTurni(current.getFullYear(), current.getMonth()); }});
  });

  function toggleStateB(show){
    document.getElementById('stateA').classList.toggle('d-none', show);
    document.getElementById('stateB').classList.toggle('d-none', !show);
  }

  document.getElementById('btnSingolo').addEventListener('click', ()=>toggleStateB(true));
  document.getElementById('closeStateB').addEventListener('click', ()=>{selectedType=null;toggleStateB(false);});

  document.getElementById('pillContainer').addEventListener('click', e=>{
    if(e.target.classList.contains('pill')){
      document.querySelectorAll('#pillContainer .pill').forEach(p=>p.classList.remove('active'));
      e.target.classList.add('active');
      selectedType=e.target.dataset.type;
    }
  });

  document.getElementById('prevMonth').addEventListener('click', ()=>{current.setMonth(current.getMonth()-1);loadTurni(current.getFullYear(),current.getMonth());});
  document.getElementById('nextMonth').addEventListener('click', ()=>{current.setMonth(current.getMonth()+1);loadTurni(current.getFullYear(),current.getMonth());});

  loadTurni(current.getFullYear(), current.getMonth());
});
