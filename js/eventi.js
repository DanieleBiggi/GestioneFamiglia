document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('search');
  if (search) {
  const cards = Array.from(document.querySelectorAll('.event-card'));
  function filter() {
    const q = search.value.trim().toLowerCase();
    cards.forEach(card => {
      const text = card.dataset.search || '';
      const visible = text.includes(q);
      if (visible) {
        card.style.removeProperty('display');
      } else {
        card.style.setProperty('display', 'none', 'important');
      }
    });
  }
    
      search.addEventListener('input', filter);
    
    filter();
    }

  const form = document.getElementById('eventoForm');
  form?.addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(form);
    fetch('ajax/add_evento.php', {method:'POST', body:formData})
      .then(r=>r.json())
      .then(res=>{ if(res.success){ location.reload(); } else { alert(res.error||'Errore'); }});
  });
});

function openEventoModal(date){
  const form = document.getElementById('eventoForm');
  if(form){
    form.reset();
    if(date){
      form.querySelector('[name="data_evento"]').value = date;
    }
    new bootstrap.Modal(document.getElementById('eventoModal')).show();
  }
}
