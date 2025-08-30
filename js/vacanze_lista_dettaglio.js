document.addEventListener('DOMContentLoaded', () => {
  const reviewForm = document.getElementById('reviewForm');
  if (reviewForm) {
    reviewForm.addEventListener('submit', e => {
      e.preventDefault();
      const fd = new FormData(reviewForm);
      fd.append('id_viaggio', viaggioId);
      fetch('ajax/add_viaggi_feedback.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            window.location.reload();
          } else {
            alert(res.error || 'Errore');
          }
        });
    });
  }

  const askForm = document.getElementById('askForm');
  if (askForm) {
    askForm.addEventListener('submit', e => {
      e.preventDefault();
      const fd = new FormData(askForm);
      fd.append('id_viaggio', viaggioId);
      fetch('ajax/add_viaggi_feedback.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            window.location.reload();
          } else {
            alert(res.error || 'Errore');
          }
        });
    });
  }

  const altCards = document.querySelectorAll('.alt-card');
  const detailDiv = document.getElementById('altDettagli');

  const escapeMap = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  };
  const escapeHtml = str => str ? str.replace(/[&<>"']/g, m => escapeMap[m]) : '';

  function renderDetails(data){
    let html = '';
    html += `<h5 class="mb-3">${escapeHtml(data.breve_descrizione)}</h5>`;
    html += '<h6>Tratte</h6>';
    if(data.tratte.length === 0){
      html += '<p class="text-muted">Nessuna tratta.</p>';
    } else {
      html += '<ul class="list-group mb-3">';
      data.tratte.forEach(t => {
        const titolo = escapeHtml(t.descrizione || t.tipo_tratta);
        let route = '';
        if(t.origine_testo || t.destinazione_testo){
          route = `<div class="small text-muted">${escapeHtml(t.origine_testo || '')} → ${escapeHtml(t.destinazione_testo || '')}</div>`;
        }
        html += `<li class="list-group-item d-flex justify-content-between"><div><div>${titolo}</div>${route}</div><div>€${t.totale}</div></li>`;
      });
      html += '</ul>';
    }
    html += '<h6>Alloggi</h6>';
    if(data.alloggi.length === 0){
      html += '<p class="text-muted">Nessun alloggio.</p>';
    } else {
      html += '<ul class="list-group mb-3">';
      data.alloggi.forEach(a => {
        html += `<li class="list-group-item d-flex justify-content-between"><span>${escapeHtml(a.nome_alloggio || 'Alloggio')}</span><span>€${a.totale}</span></li>`;
      });
      html += '</ul>';
    }
    html += `<div class="small">Trasporti: €${data.totale_trasporti}</div>`;
    html += `<div class="small">Alloggi: €${data.totale_alloggi}</div>`;
    html += `<div class="fw-bold">Totale: €${data.totale_viaggio}</div>`;
    detailDiv.innerHTML = html;
  }

  function loadAlt(altId, el){
    fetch(`ajax/get_viaggi_alternativa.php?id_viaggio=${viaggioId}&id_alternativa=${altId}`)
      .then(r => r.json())
      .then(res => {
        if(res.success){
          renderDetails(res);
          altCards.forEach(card => card.querySelector('.card').classList.remove('border-primary'));
          if(el){ el.querySelector('.card').classList.add('border-primary'); }
        } else {
          detailDiv.innerHTML = `<p class="text-danger">${escapeHtml(res.error || 'Errore')}</p>`;
        }
      });
  }

  altCards.forEach(card => {
    card.addEventListener('click', e => {
      e.preventDefault();
      loadAlt(card.dataset.alt, card);
    });
  });

  if(altCards.length > 0){
    loadAlt(altCards[0].dataset.alt, altCards[0]);
  }
});

