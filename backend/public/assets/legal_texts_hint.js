(function(){
  document.addEventListener('DOMContentLoaded', function(){
    if (!location.pathname.startsWith('/settings/texts')) return;
    var cardBody = document.querySelector('.card .card-body');
    if (!cardBody) return;
    var info = document.createElement('div');
    info.className = 'alert alert-warning';
    info.innerHTML = 'Hinweis: Der Ersteller der Software übernimmt keine Haftung für die rechtliche Verwendbarkeit der hier hinterlegten Rechtstexte. Bitte prüfen Sie Inhalte mit Ihrer Rechtsberatung.';
    cardBody.prepend(info);
  });
})();
