document.addEventListener('DOMContentLoaded', () => {
  const docForm = document.getElementById('docForm');
  if(docForm){
    docForm.addEventListener('submit', e => {
      e.preventDefault();
      const fd = new FormData(docForm);
      fd.append('id_viaggio', viaggioId);
      fetch('ajax/add_viaggi_documento.php', { method: 'POST', body: fd })
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

  const altForm = document.getElementById('altForm');
  if(altForm){
    altForm.addEventListener('submit', e => {
      e.preventDefault();
      const fd = new FormData(altForm);
      fd.append('id_viaggio', viaggioId);
      fetch('ajax/add_viaggi_alternativa.php', { method: 'POST', body: fd })
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
});
