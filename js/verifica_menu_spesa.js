// Vista di verifica: il menu salvato guida i giorni, le note della spesa guidano le associazioni.
document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('verificaMenuContent');
  const tab = document.getElementById('verificaMenuTab');
  const shopping = document.getElementById('listaSpesaList');
  const form = document.getElementById('listaForm');
  if (!root || !tab) return;
  const days = ['Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato','Domenica'];
  const norm = s => (s || '').toLocaleLowerCase('it').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9 ]/g,' ').replace(/\s+/g,' ').trim();
  const el = (tag, cls, value) => { const n = document.createElement(tag); if(cls)n.className=cls; if(value!==undefined)n.textContent=value; return n; };
  const splitDishes = s => (s || '').split(/\n|\s*\+\s*|\s*;\s*/).map(x=>x.trim()).filter(Boolean);
  const matchDish = (hint, dish) => {
    const a=norm(hint), b=norm(dish);
    if (!a || !b) return false;
    if (a === b || (a.length >= 5 && b.includes(a)) || (b.length >= 5 && a.includes(b))) return true;
    return false;
  };
  const parseNotes = note => {
    const pieces=(note||'').split(';').map(x=>x.trim()).filter(Boolean), refs=[];
    pieces.forEach(piece => {
      const matches=[...piece.matchAll(/lunedi|martedi|mercoledi|giovedi|venerdi|sabato|domenica/gi)];
      if (!matches.length) return;
      const dayIndexes=matches.map(m=>days.findIndex(d=>norm(d)===norm(m[0]))).filter(i=>i>=0);
      if (!dayIndexes.length) return;
      const prefix=piece.slice(0,matches[0].index).replace(/[,\s-]+$/,'').trim();
      const range=piece.includes('-') && dayIndexes.length>=2;
      const chosen=range ? days.slice(Math.min(...dayIndexes),Math.max(...dayIndexes)+1) : dayIndexes.map(i=>days[i]);
      chosen.forEach(day=>refs.push({day, dish:prefix}));
    });
    return refs;
  };
  const fetchJson = url => fetch(url).then(r=>{if(!r.ok)throw Error('HTTP '+r.status);return r.json();}).then(x=>{if(!x.success)throw Error(x.error||'Errore');return x;});
  let menu=[], items=[];
  async function load(){
    root.replaceChildren(el('div','text-muted py-3','Caricamento verifica…'));
    try{
      const [m,s]=await Promise.all([fetchJson('ajax/get_menu_cene.php'),fetchJson('ajax/get_lista_spesa.php')]);
      menu=m.items||[];items=s.items||[];render();
    }catch(e){root.replaceChildren(el('div','alert alert-warning','Impossibile caricare la verifica: '+e.message));}
  }
  function addButton(day,dish){
    const b=el('button','btn btn-sm btn-outline-light','+ Ingrediente');
    b.type='button';
    b.disabled=!form;
    b.addEventListener('click',()=>{
      if(!form)return;
      window.openListaModal();
      form.querySelector('[name="note"]').value=dish+', '+day;
    });
    return b;
  }
  function render(){
    root.replaceChildren();
    const assigned=new Map(), unmatched=[];
    const menuDays=menu.map(m=>({day:m.giorno,dishes:splitDishes(m.piatto)}));
    menuDays.forEach(m=>m.dishes.forEach(d=>assigned.set(m.day+'|'+d,[])));
    items.forEach(item=>{
      const refs=parseNotes(item.note), hits=new Set();
      refs.forEach(ref=>{
        const dayEntry=menuDays.find(m=>norm(m.day)===norm(ref.day));
        if(!dayEntry)return;
        const candidates=dayEntry.dishes.filter(d=>matchDish(ref.dish,d));
        if(candidates.length===1)hits.add(dayEntry.day+'|'+candidates[0]);
        // "Contorno" / "Condimenti" non identificano un piatto in modo univoco.
      });
      hits.forEach(k=>assigned.get(k).push(item));
      if(!hits.size)unmatched.push(item);
    });
    const refsById=new Map();
    assigned.forEach((arr,key)=>arr.forEach(i=>{const x=refsById.get(i.id)||[];x.push(key);refsById.set(i.id,x);}));
    menuDays.forEach(m=>{
      const section=el('section','card bg-dark text-white border-secondary mb-3');
      const body=el('div','card-body');
      body.append(el('h5','card-title mb-3',m.day));
      if(!m.dishes.length)body.append(el('p','text-muted','Nessun piatto previsto.'));
      m.dishes.forEach(d=>{
        const block=el('div','border-top border-secondary pt-3 pb-2');
        const head=el('div','d-flex justify-content-between align-items-center gap-2 mb-2');
        head.append(el('strong','',d),addButton(m.day,d));block.append(head);
        const arr=assigned.get(m.day+'|'+d)||[];
        if(!arr.length)block.append(el('p','text-warning small','Nessun ingrediente associato a questo piatto.'));
        arr.forEach(i=>{
          const line=el('div','py-1');
          line.append(el('span','',i.nome+(i.quantita?' ('+i.quantita+')':'')));
          const others=(refsById.get(i.id)||[]).filter(k=>k!==m.day+'|'+d);
          if(others.length)line.append(el('div','small text-info','Condiviso con: '+others.map(k=>k.replace('|',' · ')).join('; ')+' · quantità totale'));
          block.append(line);
        });
        body.append(block);
      });
      section.append(body);root.append(section);
    });
    if(unmatched.length){
      const section=el('section','card bg-dark text-white border-secondary mb-3'), body=el('div','card-body');
      body.append(el('h5','card-title','Ingredienti senza associazione certa'));
      body.append(el('p','text-muted small','Include condimenti, contorni generici e note non riconosciute. Nessun ingrediente viene nascosto.'));
      unmatched.forEach(i=>body.append(el('div','py-1',i.nome+(i.quantita?' ('+i.quantita+')':'')+(i.note?' · '+i.note:''))));
      section.append(body);root.append(section);
    }
  }
  // Bootstrap may not dispatch shown.bs.tab if its JS bundle is unavailable.\n  // Use click as a fallback, without relying on a second event.\n  tab.addEventListener('click', () => {\n    if (window.bootstrap?.Tab) {\n      window.bootstrap.Tab.getOrCreateInstance(tab).show();\n    } else {\n      document.getElementById('listaSpesaPane')?.classList.remove('show', 'active');\n      document.getElementById('listaSpesaTab')?.classList.remove('active');\n      document.getElementById('verificaMenuPane')?.classList.add('show', 'active');\n      tab.classList.add('active');\n    }\n    load();\n  });\n  document.getElementById('listaSpesaTab')?.addEventListener('click', () => {\n    if (!window.bootstrap?.Tab) {\n      document.getElementById('verificaMenuPane')?.classList.remove('show', 'active');\n      tab.classList.remove('active');\n      document.getElementById('listaSpesaPane')?.classList.add('show', 'active');\n      document.getElementById('listaSpesaTab')?.classList.add('active');\n    }\n  });
});
