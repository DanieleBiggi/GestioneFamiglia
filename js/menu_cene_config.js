document.addEventListener('DOMContentLoaded', () => {
  const days = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì'];
  const $ = id => document.getElementById(id);
  const canSave = !!window.MENU_CONFIG_CAN_SAVE;
  const state = { master: null, ricette: null };
  const kind = new URLSearchParams(window.location.search).get('section') === 'ricette' ? 'ricette' : 'master';
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
  function validateImport(data, type) {
    if (!data || typeof data !== 'object' || data.version !== 1) throw new Error('Versione JSON non valida');
    if (type === 'master') {
      if (!data.ciclo || data.ciclo.settimane !== 4 || !Number.isInteger(data.ciclo.settimana_riferimento) ||
          !/^\\d{4}-\\d{2}-\\d{2}$/.test(data.ciclo.data_riferimento || '')) throw new Error('Ciclo non valido');
      for (const season of ['Estivo', 'Invernale']) for (const week of ['1','2','3','4']) for (const day of days) {
        const item = data.stagioni?.[season]?.[week]?.[day];
        if (!item || typeof item.cena !== 'string' || !item.cena.trim() || typeof item.contorno !== 'string') throw new Error('Menù incompleto: ' + season + ', settimana ' + week + ', ' + day);
      }
    } else if (!Array.isArray(data.ricette) || data.ricette.some(r => !r || typeof r.id !== 'string' || typeof r.nome !== 'string' || typeof r.istruzioni !== 'string' || !r.nome.trim())) {
      throw new Error('Ricettario non valido');
    }
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
  function masterDishes() {
    const names = new Map();
    for (const season of ['Estivo', 'Invernale']) {
      for (const week of ['1', '2', '3', '4']) {
        for (const day of days) {
          const item = state.master?.stagioni?.[season]?.[week]?.[day];
          if (!item) continue;
          for (const text of [item.cena, item.contorno]) {
            for (const part of (text || '').split(/\\r?\\n|\\s*\\+\\s*|\\s*;\\s*/)) {
              const name = part.trim();
              if (name) names.set(name.toLocaleLowerCase('it-IT'), name);
            }
          }
        }
      }
    }
    return [...names.values()].sort((a, b) => a.localeCompare(b, 'it'));
  }
  function makeRecipeId(name, used) {
    const base = name.toLocaleLowerCase('it-IT').normalize('NFD').replace(/[\\u0300-\\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 70) || 'ricetta';
    let id = base;
    let n = 2;
    while (used.has(id)) id = base + '-' + n++;
    return id;
  }
  function captureRecipes() {
    if (!state.ricette) return;
    state.ricette.ricette = [...$('cfgRecipes').querySelectorAll('[data-recipe]')].map(box => ({
      id: box.dataset.recipeId,
      nome: box.querySelector('[data-field="nome"]').value.trim(),
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
      const dishField = document.createElement('div');
      dishField.className = 'mb-2';
      const dishLabel = label('Piatto del master');
      const select = field('select', 'form-select bg-dark text-white');
      select.dataset.field = 'nome';
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = 'Seleziona un piatto';
      select.append(placeholder);
      const options = masterDishes();
      if (recipe.nome && !options.includes(recipe.nome)) {
        const old = document.createElement('option');
        old.value = recipe.nome;
        old.textContent = recipe.nome + ' (non più nel master)';
        select.append(old);
      }
      options.forEach(name => {
        const option = document.createElement('option');
        option.value = name;
        option.textContent = name;
        select.append(option);
      });
      select.value = recipe.nome;
      dishField.append(dishLabel, select);
      box.dataset.recipeId = recipe.id || '';
      select.addEventListener('change', () => {
        if (!box.dataset.recipeId && select.value) {
          const used = new Set([...root.querySelectorAll('[data-recipe]')].map(el => el.dataset.recipeId).filter(Boolean));
          box.dataset.recipeId = makeRecipeId(select.value, used);
        }
      });
      box.append(dishField);
      const instructions = wrap('Indicazioni per ChatGPT', recipe.istruzioni, true);
      instructions.input.dataset.field = 'istruzioni';
      box.append(instructions.box);
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
  async function request(k) {
    const res = await fetch('ajax/menu_cene_config.php?kind=' + encodeURIComponent(k), { credentials: 'same-origin' });
    const data = await res.json();
    if (!res.ok || !data.success) throw new Error(data.error || 'Errore di caricamento');
    return data.data;
  }
  async function initialize() {
    try {
      [state.master, state.ricette] = await Promise.all([request('master'), request('ricette')]);
      $('masterEditor').hidden = kind !== 'master';
      $('recipesEditor').hidden = kind !== 'ricette';
      $('menuConfigTitle').textContent = 'Configurazione · Menù cene · ' + (kind === 'master' ? 'Master' : 'Ricette');
      if (kind === 'master') renderMaster(); else renderRecipes();
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
  $('cfgAddRecipe')?.addEventListener('click', () => {
    captureRecipes();
    state.ricette.ricette.push({ id: '', nome: '', istruzioni: '' });
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
      validateImport(data, kind);
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
