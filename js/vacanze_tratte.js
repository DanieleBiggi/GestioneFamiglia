document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('altEditForm');
  if(form){
    form.addEventListener('submit', e => {
      e.preventDefault();
      const fd = new FormData(form);
      fd.append('id_viaggio_alternativa', altId);
      fetch('ajax/update_viaggi_alternativa.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(res => {
          if(res.success){
            window.location.reload();
          } else {
            alert(res.error || 'Errore');
          }
        });
    });
  }

  document.querySelectorAll('.duplicate').forEach(el => {
    el.addEventListener('click', e => {
      e.preventDefault();
      e.stopPropagation();
      const href = el.getAttribute('data-href');
      if (href) window.location.href = href;
    });
  });
});
