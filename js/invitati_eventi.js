document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('search');
  const cards = Array.from(document.querySelectorAll('.invitato-card'));
  function filter(){
    const q = search.value.trim().toLowerCase();
    cards.forEach(card => {
      const text = card.dataset.search || '';
      if(text.includes(q)){
        card.style.removeProperty('display');
      } else {
        card.style.setProperty('display','none','important');
      }
    });
  }
  search.addEventListener('input', filter);
  filter();
  const showInactive = document.getElementById('showInactive');
  showInactive?.addEventListener('change', () => {
    const params = new URLSearchParams(window.location.search);
    if (showInactive.checked) {
      params.set('show_inactive', '1');
    } else {
      params.delete('show_inactive');
    }
    window.location.search = params.toString();
  });
  const form = document.getElementById('invitatoForm');
  form?.addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(form);
    fetch('ajax/add_invitato_evento.php', {method:'POST', body:formData})
      .then(r=>r.json())
      .then(res=>{ if(res.success){ location.reload(); } else { alert(res.error||'Errore'); }});
  });
});
function openInvitatoModal(){
  const form = document.getElementById('invitatoForm');
  if(form){ form.reset(); new bootstrap.Modal(document.getElementById('invitatoModal')).show(); }
}
