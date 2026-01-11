document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('search');
  const filterAnno = document.getElementById('filterAnno');
  const filterGenere = document.getElementById('filterGenere');
  const filterRegista = document.getElementById('filterRegista');
  const filterGruppo = document.getElementById('filterGruppo');
  const filterLista = document.getElementById('filterLista');
  const filterPiattaforma = document.getElementById('filterPiattaforma');
  const filterVotoDa = document.getElementById('filterVotoDa');
  const filterVotoA = document.getElementById('filterVotoA');
  const filterDataDa = document.getElementById('filterDataDa');
  const filterDataA = document.getElementById('filterDataA');
  const filterDurataDa = document.getElementById('filterDurataDa');
  const filterDurataA = document.getElementById('filterDurataA');
  const filterOrdine = document.getElementById('filterOrdine');
  const resetFiltersBtn = document.getElementById('resetFilters');
  const cards = Array.from(document.querySelectorAll('.film-card'));
  const filmList = document.getElementById('filmList');

  function sortCards() {
    if (!filmList) return;
    const ordine = filterOrdine ? filterOrdine.value : 'inserimento';
    const sorted = [...cards].sort((a, b) => {
      if (ordine === 'piattaforme_aggiornamento') {
        const aValue = a.dataset.piattaformeAggiornate || '';
        const bValue = b.dataset.piattaformeAggiornate || '';
        return bValue.localeCompare(aValue);
      }
      const aValue = a.dataset.inserito || '';
      const bValue = b.dataset.inserito || '';
      return bValue.localeCompare(aValue);
    });
    sorted.forEach(card => filmList.appendChild(card));
  }

  function filter() {
    sortCards();
    const q = search.value.trim().toLowerCase();
    const anno = filterAnno.value;
    const genere = filterGenere.value;
    const regista = filterRegista.value.trim().toLowerCase();
    const gruppo = filterGruppo.value;
    const lista = filterLista.value;
    const piattaforma = filterPiattaforma ? filterPiattaforma.value : '';
    const votoDa = parseFloat(filterVotoDa ? filterVotoDa.value : '');
    const votoA = parseFloat(filterVotoA ? filterVotoA.value : '');
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
      const piattaforme = (card.dataset.piattaforme || '').split(',');
      const matchPiattaforma = !piattaforma || piattaforme.includes(piattaforma);
      const voto = parseFloat(card.dataset.voto || '');
      const matchVotoDa = !filterVotoDa.value || (!Number.isNaN(voto) && voto >= votoDa);
      const matchVotoA = !filterVotoA.value || (!Number.isNaN(voto) && voto <= votoA);
      const visto = card.dataset.visto || '';
      const matchDataDa = !dataDa || (visto && visto >= dataDa);
      const matchDataA = !dataA || (visto && visto <= dataA);
      const durata = parseInt(card.dataset.durata || '0', 10);
      const matchDurataDa = !filterDurataDa.value || durata >= durataDa;
      const matchDurataA = !filterDurataA.value || durata <= durataA;
      const visible = matchSearch && matchAnno && matchGenere && matchRegista && matchGruppo && matchLista && matchPiattaforma && matchVotoDa && matchVotoA && matchDataDa && matchDataA && matchDurataDa && matchDurataA;
      if (visible) {
        card.style.removeProperty('display');
      } else {
        card.style.setProperty('display', 'none', 'important');
      }
    });
  }

  document.querySelectorAll('.film-filter-gruppo').forEach(el => {
    el.addEventListener('click', e => {
      e.stopPropagation();
      if (filterGruppo) {
        filterGruppo.value = el.dataset.gruppoId || '';
        filter();
      }
    });
  });

  [search, filterOrdine, filterAnno, filterGenere, filterRegista, filterGruppo, filterLista, filterPiattaforma, filterVotoDa, filterVotoA, filterDataDa, filterDataA, filterDurataDa, filterDurataA].forEach(el => {
    if (!el) return;
    el.addEventListener('input', filter);
    el.addEventListener('change', filter);
  });
  filter();

  if (resetFiltersBtn) {
    resetFiltersBtn.addEventListener('click', () => {
      [search, filterAnno, filterGenere, filterRegista, filterGruppo, filterLista, filterPiattaforma, filterVotoDa, filterVotoA, filterDataDa, filterDataA, filterDurataDa, filterDurataA].forEach(el => {
        if (!el) return;
        el.value = '';
      });
      if (filterOrdine) {
        filterOrdine.value = 'inserimento';
      }
      filter();
    });
  }
});
