// Settimana selezionata condivisa tra Menù cene e Verifica menù.
window.GestioneMenuWeek = (() => {
  const key = 'gestioneFamiglia.menuWeekStart';
  const monday = date => {
    const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const day = d.getDay();
    d.setDate(d.getDate() + (day === 0 ? -6 : 1 - day));
    return d;
  };
  const format = d => [d.getFullYear(), String(d.getMonth()+1).padStart(2,'0'), String(d.getDate()).padStart(2,'0')].join('-');
  const parse = value => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return null;
    const d = new Date(value + 'T12:00:00');
    return !Number.isNaN(d.getTime()) && format(d) === value ? monday(d) : null;
  };
  const defaultStart = () => {
    const today = new Date();
    const start = monday(today);
    if (today.getDay() === 6 || today.getDay() === 0) start.setDate(start.getDate() + 7);
    return format(start);
  };
  const get = () => {
    try { const saved = localStorage.getItem(key); if (parse(saved)) return format(parse(saved)); } catch (_) {}
    return defaultStart();
  };
  const set = value => {
    const parsed = parse(value);
    if (!parsed) return;
    try { localStorage.setItem(key, format(parsed)); } catch (_) {}
  };
  return { get, set };
})();
