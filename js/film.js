document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('search');
  const filterAnnoDa = document.getElementById('filterAnnoDa');
  const filterAnnoA = document.getElementById('filterAnnoA');
  const filterGenere = document.getElementById('filterGenere');
  const filterRegista = document.getElementById('filterRegista');
  const filterGruppo = document.getElementById('filterGruppo');
  const filterLista = document.getElementById('filterLista');
  const filterPiattaforme = Array.from(document.querySelectorAll('input[name="filterPiattaforme[]"]'));
  const filterVotoDa = document.getElementById('filterVotoDa');
  const filterVotoA = document.getElementById('filterVotoA');
  const filterDataDa = document.getElementById('filterDataDa');
  const filterDataA = document.getElementById('filterDataA');
  const filterDurataDa = document.getElementById('filterDurataDa');
  const filterDurataA = document.getElementById('filterDurataA');
  const filterOrdine = document.getElementById('filterOrdine');
  const filterOrdineDirezione = document.getElementById('filterOrdineDirezione');
  const resetFiltersBtn = document.getElementById('resetFilters');
  const cards = Array.from(document.querySelectorAll('.film-card'));
  const filmList = document.getElementById('filmList');

  function getSortValue(card, key) {
    switch (key) {
      case 'titolo':
        return card.dataset.titolo || '';
      case 'titolo_originale':
        return card.dataset.titoloOriginale || '';
      case 'anno':
        return parseInt(card.dataset.anno || '0', 10);
      case 'regista':
        return card.dataset.regista || '';
      case 'gruppo':
        return card.dataset.gruppoNome || '';
      case 'voto':
        return parseFloat(card.dataset.voto || '');
      case 'voto_medio':
        return parseFloat(card.dataset.votoMedio || '');
      case 'durata':
        return parseInt(card.dataset.durata || '0', 10);
      case 'visto':
        return card.dataset.visto || '';
      case 'piattaforme_aggiornamento':
        return card.dataset.piattaformeAggiornate || '';
      case 'inserimento':
      default:
        return card.dataset.inserito || '';
    }
  }

  function sortCards() {
    if (!filmList) return;
    const ordine = filterOrdine ? filterOrdine.value : 'inserimento';
    const direzione = filterOrdineDirezione ? filterOrdineDirezione.value : 'desc';
    const sorted = [...cards].sort((a, b) => {
      const aValue = getSortValue(a, ordine);
      const bValue = getSortValue(b, ordine);
      const aEmpty = aValue === '' || Number.isNaN(aValue);
      const bEmpty = bValue === '' || Number.isNaN(bValue);
      if (aEmpty && bEmpty) return 0;
      if (aEmpty) return 1;
      if (bEmpty) return -1;
      let comparison = 0;
      if (typeof aValue === 'number' && typeof bValue === 'number') {
        comparison = aValue - bValue;
      } else {
        comparison = String(aValue).localeCompare(String(bValue));
      }
      return direzione === 'asc' ? comparison : -comparison;
    });
    sorted.forEach(card => filmList.appendChild(card));
  }

  function filter() {
    sortCards();
    const q = search.value.trim().toLowerCase();
    const annoDa = parseInt(filterAnnoDa ? filterAnnoDa.value : '', 10);
    const annoA = parseInt(filterAnnoA ? filterAnnoA.value : '', 10);
    const genere = filterGenere.value;
    const regista = filterRegista.value.trim().toLowerCase();
    const gruppo = filterGruppo.value;
    const lista = filterLista.value;
    const piattaformeSelezionate = filterPiattaforme.filter(el => el.checked).map(el => el.value);
    const votoDa = parseFloat(filterVotoDa ? filterVotoDa.value : '');
    const votoA = parseFloat(filterVotoA ? filterVotoA.value : '');
    const dataDa = filterDataDa.value;
    const dataA = filterDataA.value;
    const durataDa = parseInt(filterDurataDa.value || '0', 10);
    const durataA = parseInt(filterDurataA.value || '0', 10);

    cards.forEach(card => {
      const text = (card.dataset.search || '');
      const matchSearch = text.includes(q);
      const cardAnno = parseInt(card.dataset.anno || '0', 10);
      const matchAnnoDa = !filterAnnoDa.value || cardAnno >= annoDa;
      const matchAnnoA = !filterAnnoA.value || cardAnno <= annoA;
      const genres = (card.dataset.generi || '').split(',');
      const matchGenere = !genere || genres.includes(genere);
      const matchRegista = !regista || (card.dataset.regista || '').includes(regista);
      const matchGruppo = !gruppo || card.dataset.gruppo === gruppo;
      const liste = (card.dataset.liste || '').split(',');
      const matchLista = !lista || liste.includes(lista);
      const piattaforme = (card.dataset.piattaforme || '').split(',');
      const matchPiattaforma = piattaformeSelezionate.length === 0 || piattaformeSelezionate.some(value => piattaforme.includes(value));
      const voto = parseFloat(card.dataset.voto || '');
      const matchVotoDa = !filterVotoDa.value || (!Number.isNaN(voto) && voto >= votoDa);
      const matchVotoA = !filterVotoA.value || (!Number.isNaN(voto) && voto <= votoA);
      const visto = card.dataset.visto || '';
      const matchDataDa = !dataDa || (visto && visto >= dataDa);
      const matchDataA = !dataA || (visto && visto <= dataA);
      const durata = parseInt(card.dataset.durata || '0', 10);
      const matchDurataDa = !filterDurataDa.value || durata >= durataDa;
      const matchDurataA = !filterDurataA.value || durata <= durataA;
      const visible = matchSearch && matchAnnoDa && matchAnnoA && matchGenere && matchRegista && matchGruppo && matchLista && matchPiattaforma && matchVotoDa && matchVotoA && matchDataDa && matchDataA && matchDurataDa && matchDurataA;
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

  [search, filterOrdine, filterOrdineDirezione, filterAnnoDa, filterAnnoA, filterGenere, filterRegista, filterGruppo, filterLista, filterVotoDa, filterVotoA, filterDataDa, filterDataA, filterDurataDa, filterDurataA, ...filterPiattaforme].forEach(el => {
    if (!el) return;
    el.addEventListener('input', filter);
    el.addEventListener('change', filter);
  });
  filter();

  if (resetFiltersBtn) {
    resetFiltersBtn.addEventListener('click', () => {
      [search, filterAnnoDa, filterAnnoA, filterGenere, filterRegista, filterGruppo, filterLista, filterVotoDa, filterVotoA, filterDataDa, filterDataA, filterDurataDa, filterDurataA].forEach(el => {
        if (!el) return;
        el.value = '';
      });
      filterPiattaforme.forEach(el => {
        el.checked = false;
      });
      if (filterOrdine) {
        filterOrdine.value = 'inserimento';
      }
      if (filterOrdineDirezione) {
        filterOrdineDirezione.value = 'desc';
      }
      filter();
    });
  }
});
