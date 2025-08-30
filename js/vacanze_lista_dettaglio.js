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
});
