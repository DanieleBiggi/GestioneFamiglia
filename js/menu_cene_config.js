document.addEventListener('DOMContentLoaded', () => {
  const days = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì'];
  const $ = id => document.getElementById(id);
  const canSave = !!window.MENU_CONFIG_CAN_SAVE;
  const state = { master: null, ricette: null };
  let kind = 'master';
  const alertBox = $('menuConfigAlert');
  const message = (text, error = false) => {
    alertBox.textContent = text;
    alertBox.className = 'alert ' + (error ? 'alert-warning' : 'alert-info');
  };
  const field = (tag, className, value) => {
    const el = document.createElement(tag);
    el.className = className;
    if (value !== undefined) el.value = value;
    el.disabled = !canSave;
    return el;
  };
  const label = text => {
    const el = document.createElement('label');
    el.className = 'form-label';
    el.textContent = text;
    return el;
  };
  const wrap = (text, value, multiline = false) => {
    const box = document.createElement('div');
    box.className = 'mb-2';
    const input = field(multiline ? 'textarea' : 'input', 'form-control bg-dark text-white', value);
    if (multiline) input.rows = 3;
    box.append(label(text), input);
    return { box, input };
  };
  const selectedSeason = () => $('cfgSeason').value;
  const selectedWeek = () => $('cfgWeek').value;
  function captureMaster() {
    const data = state.master;
    if (!data) return;
    data.anno_scolastico = $('cfgYear').value;
    data.ciclo.data_riferimento = $('cfgAnchor').value;
    data.ciclo.settimana_riferimento = Number($('cfgAnchorWeek').value);
    const current = data.stagioni[selectedSeason()][selectedWeek()];
    $('cfgDays').querySelectorAll('[data-day]').forEach(block => {
      current[block.dataset.day] = {
        cena: block.querySelector('[data-field="cena"]').value,
        contorno: block.querySelector('[data-field="contorno"]').value
      };
    });
  }
  function renderMaster() {
    const data = state.master;
    if (!data) return;
    $('cfgYear').value = data.anno_scolastico || '';
    $('cfgAnchor').value = data.ciclo.data_riferimento;
    $('cfgAnchorWeek').value = String(data.ciclo.settimana_riferimento);
    const root = $('cfgDays');
    root.replaceChildren();
    days.forEach(day => {
      const item = data.stagioni[selectedSeason()][selectedWeek()][day];
      const box = document.createElement('div');
      box.dataset.day = day;
      box.className = 'card bg-dark text-white border-secondary p-3 mb-2';
      const heading = document.createElement('h6');
      heading.textContent = day;
      box.append(heading);
      ['cena', 'contorno'].forEach(key => {
        const input = wrap(key === 'cena' ? 'Cena' : 'Contorno', item[key] || '');
        input.input.dataset.field = key;
        box.append(input.box);
      });
      root.append(box);
    });
  }
  function captureRecipes() {
    if (!state.ricette) return;
    state.ricette.ricette = [...$('cfgRecipes').querySelectorAll('[data-recipe]')].map(box => ({
      id: box.querySelector('[data-field="id"]').value.trim(),
      nome: box.querySelector('[data-field="nome"]').value.trim(),
      alias: box.querySelector('[data-field="alias"]').value.split('\n').map(x => x.trim()).filter(Boolean),
      istruzioni: box.querySelector('[data-field="istruzioni"]').value.trim()
    }));
  }
  function renderRecipes() {
    const root = $('cfgRecipes');
    root.replaceChildren();
    (state.ricette?.ricette || []).forEach((recipe, index) => {
      const box = document.createElement('div');
      box.className = 'card bg-dark text-white border-secondary p-3 mb-3';
      box.dataset.recipe = String(index);
      const heading = document.createElement('h6');
      heading.textContent = 'Ricetta ' + (index + 1);
      box.append(heading);
      for (const [key, title, value, multiline] of [
        ['id', 'ID stabile (lettere minuscole, numeri, _ o -)', recipe.id, false],
        ['nome', 'Nome del piatto', recipe.nome, false],
        ['alias', 'Altri nomi equivalenti (uno per riga)', recipe.alias.join('\n'), true],
        ['istruzioni', 'Indicazioni per ChatGPT', recipe.istruzioni, true]
      ]) {
        const input = wrap(title, value, multiline);
        input.input.dataset.field = key;
        box.append(input.box);
      }
      if (canSave) {
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-outline-danger btn-sm';
        remove.textContent = 'Elimina ricetta';
        remove.addEventListener('click', () => {
          captureRecipes();
          state.ricette.ricette.splice(index, 1);
          renderRecipes();
        });
        box.append(remove);
      }
      root.append(box);
    });
  }
  function capture() { if (kind === 'master') captureMaster(); else captureRecipes(); }
  function switchKind(next) {
    capture();
    kind = next;
    document.querySelectorAll('[data-kind]').forEach(b => b.classList.toggle('active', b.dataset.kind === kind));
    $('masterEditor').hidden = kind !== 'master';
    $('recipesEditor').hidden = kind !== 'ricette';
    if (kind === 'master') renderMaster(); else renderRecipes();
    alertBox.className = '';
    alertBox.textContent = '';
  }
  async function request(k) {
    const res = await fetch('ajax/menu_cene_config.php?kind=' + encodeURIComponent(k), { credentials: 'same-origin' });
    const data = await res.json();
    if (!res.ok || !data.success) throw new Error(data.error || 'Errore di caricamento');
    return data.data;
  }
  async function initialize() {
    try {
      [state.master, state.ricette] = await Promise.all([request('master'), request('ricette')]);
      renderMaster();
    } catch (err) { message(err.message, true); }
  }
  $('cfgSeason').addEventListener('change', e => {
    // Change event fires after the select changes: previous season is tracked separately.
    const previous = e.target.dataset.previous || 'Estivo';
    const next = e.target.value;
    e.target.value = previous;
    captureMaster();
    e.target.value = next;
    e.target.dataset.previous = next;
    renderMaster();
  });
  $('cfgWeek').addEventListener('change', e => {
    const previous = e.target.dataset.previous || '1';
    const next = e.target.value;
    e.target.value = previous;
    captureMaster();
    e.target.value = next;
    e.target.dataset.previous = next;
    renderMaster();
  });
  document.querySelectorAll('[data-kind]').forEach(b => b.addEventListener('click', () => switchKind(b.dataset.kind)));
  $('cfgAddRecipe')?.addEventListener('click', () => {
    captureRecipes();
    state.ricette.ricette.push({ id: '', nome: '', alias: [], istruzioni: '' });
    renderRecipes();
  });
  $('cfgExport').addEventListener('click', () => {
    capture();
    const json = JSON.stringify(state[kind], null, 2);
    const url = URL.createObjectURL(new Blob([json], { type: 'application/json' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'menu-cene-' + kind + '.json';
    a.click();
    URL.revokeObjectURL(url);
  });
  $('cfgImport')?.addEventListener('change', async e => {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
      if (file.size > 250000) throw new Error('File troppo grande');
      const data = JSON.parse(await file.text());
      if (!data || typeof data !== 'object' || data.version !== 1) throw new Error('Versione JSON non valida');
      if (kind === 'master' && (!data.stagioni || !data.ciclo)) throw new Error('Master non riconosciuto');
      if (kind === 'ricette' && !Array.isArray(data.ricette)) throw new Error('Ricette non riconosciute');
      if (!confirm('Caricare il JSON nell’editor? Le modifiche non saranno salvate finché non premi Salva.')) return;
      state[kind] = data;
      if (kind === 'master') {
        $('cfgSeason').value = 'Estivo';
        $('cfgWeek').value = '1';
        $('cfgSeason').dataset.previous = 'Estivo';
        $('cfgWeek').dataset.previous = '1';
        renderMaster();
      } else renderRecipes();
      message('JSON caricato nell’editor. Controlla i dati e premi Salva per applicarli.');
    } catch (err) { message('Importazione: ' + err.message, true); }
    finally { e.target.value = ''; }
  });
  $('cfgSave')?.addEventListener('click', async () => {
    capture();
    if (!confirm('Salvare il ' + (kind === 'master' ? 'master' : 'ricettario') + ' della famiglia selezionata?')) return;
    const fd = new FormData();
    fd.set('kind', kind);
    fd.set('csrf_token', window.MENU_CONFIG_CSRF);
    fd.set('json', JSON.stringify(state[kind]));
    try {
      const res = await fetch('ajax/menu_cene_config.php', { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = await res.json();
      if (!res.ok || !data.success) throw new Error(data.error || 'Errore di salvataggio');
      message('Configurazione salvata. Le settimane già generate rimangono invariate.');
    } catch (err) { message(err.message, true); }
  });
  initialize();
});
