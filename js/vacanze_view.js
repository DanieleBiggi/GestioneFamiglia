document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('docForm');
  if(form){
    form.addEventListener('submit', e => {
      e.preventDefault();
      const fd = new FormData(form);
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
});
