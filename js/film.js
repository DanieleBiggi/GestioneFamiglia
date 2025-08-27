document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('search');
  const filterAnno = document.getElementById('filterAnno');
  const filterGenere = document.getElementById('filterGenere');
  const filterRegista = document.getElementById('filterRegista');
  const filterGruppo = document.getElementById('filterGruppo');
  const filterLista = document.getElementById('filterLista');
  const filterDataDa = document.getElementById('filterDataDa');
  const filterDataA = document.getElementById('filterDataA');
  const filterDurataDa = document.getElementById('filterDurataDa');
  const filterDurataA = document.getElementById('filterDurataA');
  const cards = Array.from(document.querySelectorAll('.film-card'));

  function filter() {
    const q = search.value.trim().toLowerCase();
    const anno = filterAnno.value;
    const genere = filterGenere.value;
    const regista = filterRegista.value.trim().toLowerCase();
    const gruppo = filterGruppo.value;
    const lista = filterLista.value;
    const dataDa = filterDataDa.value;
    const dataA = filterDataA.value;
    const durataDa = parseInt(filterDurataDa.value || '0', 10);
    const durataA = parseInt(filterDurataA.value || '0', 10);

    cards.forEach(card => {
      const text = (card.dataset.search || '');
      const matchSearch = text.includes(q);
      const matchAnno = !anno || card.dataset.anno === anno;
      const genres = (card.dataset.generi || '').split(',');
      const matchGenere = !genere || genres.includes(genere);
      const matchRegista = !regista || (card.dataset.regista || '').includes(regista);
      const matchGruppo = !gruppo || card.dataset.gruppo === gruppo;
      const liste = (card.dataset.liste || '').split(',');
      const matchLista = !lista || liste.includes(lista);
      const visto = card.dataset.visto || '';
      const matchDataDa = !dataDa || (visto && visto >= dataDa);
      const matchDataA = !dataA || (visto && visto <= dataA);
      const durata = parseInt(card.dataset.durata || '0', 10);
      const matchDurataDa = !filterDurataDa.value || durata >= durataDa;
      const matchDurataA = !filterDurataA.value || durata <= durataA;
      const visible = matchSearch && matchAnno && matchGenere && matchRegista && matchGruppo && matchLista && matchDataDa && matchDataA && matchDurataDa && matchDurataA;
      if (visible) {
        card.style.removeProperty('display');
      } else {
        card.style.setProperty('display', 'none', 'important');
      }
    });
  }

  [search, filterAnno, filterGenere, filterRegista, filterGruppo, filterLista, filterDataDa, filterDataA, filterDurataDa, filterDurataA].forEach(el => {
    if (el) el.addEventListener('input', filter);
  });
  filter();
});
