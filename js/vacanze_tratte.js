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

  const deleteBtn = document.getElementById('deleteAltBtn');
  if(deleteBtn){
    deleteBtn.addEventListener('click', () => {
      if(!confirm('Confermi l\'eliminazione dell\'alternativa? Tutti i dati associati saranno rimossi.')) return;
      deleteBtn.disabled = true;
      const fd = new FormData();
      fd.append('id_viaggio', viaggioId);
      fd.append('id_viaggio_alternativa', altId);
      fetch('ajax/delete_viaggi_alternativa.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(res => {
          if(res.success){
            window.location.href = `vacanze_view.php?id=${viaggioId}`;
          } else {
            alert(res.error || 'Errore');
            deleteBtn.disabled = false;
          }
        })
        .catch(() => {
          alert('Errore di comunicazione');
          deleteBtn.disabled = false;
        });
    });
  }
});

function initMap(){
  const center = alloggi.length && alloggi[0].lat && alloggi[0].lng
    ? {lat:parseFloat(alloggi[0].lat), lng:parseFloat(alloggi[0].lng)}
    : (tratte.length && tratte[0].origine_lat && tratte[0].origine_lng
        ? {lat:parseFloat(tratte[0].origine_lat), lng:parseFloat(tratte[0].origine_lng)}
        : (pasti.length && pasti[0].lat && pasti[0].lng
            ? {lat:parseFloat(pasti[0].lat), lng:parseFloat(pasti[0].lng)}
            : {lat:0, lng:0}));
  const map = new google.maps.Map(document.getElementById('map'), {zoom:6, center});

  alloggi.forEach(a => {
    if(a.lat && a.lng){
      new google.maps.Marker({
        position:{lat:parseFloat(a.lat), lng:parseFloat(a.lng)},
        map,
        icon:'https://maps.google.com/mapfiles/kml/shapes/lodging.png',
        title:a.nome_alloggio || 'Alloggio'
      });
    }
  });

  pasti.forEach(p => {
    if(p.lat && p.lng){
      new google.maps.Marker({
        position:{lat:parseFloat(p.lat), lng:parseFloat(p.lng)},
        map,
        icon:'https://maps.google.com/mapfiles/kml/shapes/dining.png',
        title:p.nome_locale || 'Pasto'
      });
    }
  });

  const directionsService = new google.maps.DirectionsService();
  tratte.forEach(t => {
    const path = [];
    if(t.origine_lat && t.origine_lng){
      const orig = {lat:parseFloat(t.origine_lat), lng:parseFloat(t.origine_lng)};
      path.push(orig);
      new google.maps.Marker({position:orig, map, icon:'http://maps.google.com/mapfiles/ms/icons/green-dot.png', title:t.origine_testo});
    }
    if(t.destinazione_lat && t.destinazione_lng){
      const dest = {lat:parseFloat(t.destinazione_lat), lng:parseFloat(t.destinazione_lng)};
      path.push(dest);
      new google.maps.Marker({position:dest, map, icon:'http://maps.google.com/mapfiles/ms/icons/red-dot.png', title:t.destinazione_testo});
    }
    if(path.length === 2){
      if(t.tipo_tratta === 'auto'){
        directionsService.route({origin:path[0], destination:path[1], travelMode:google.maps.TravelMode.DRIVING}, (res, status) => {
          if(status === google.maps.DirectionsStatus.OK){
            new google.maps.DirectionsRenderer({map, suppressMarkers:true, preserveViewport:true, polylineOptions:{strokeColor:'#FF0000', strokeOpacity:1.0, strokeWeight:2}}).setDirections(res);
          } else {
            new google.maps.Polyline({path, map, geodesic:true, strokeColor:'#FF0000', strokeOpacity:1.0, strokeWeight:2});
          }
        });
      } else {
        new google.maps.Polyline({path, map, geodesic:true, strokeColor:'#FF0000', strokeOpacity:1.0, strokeWeight:2});
      }
    }
  });
}
