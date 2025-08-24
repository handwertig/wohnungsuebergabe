(function(){
  function flash(container, type, msg){
    var div = document.createElement('div');
    div.className = 'alert alert-' + (type || 'info');
    div.textContent = msg;
    container.prepend(div);
    setTimeout(function(){ div.remove(); }, 6000);
  }

  document.addEventListener('click', function(e){
    var a = e.target.closest('a[href^="/protocols/send"]');
    if(!a) return;
    // Nur im Editor abfangen (wir brauchen den Form-Container, um den Alert zu zeigen)
    if (!location.pathname.startsWith('/protocols/edit')) return;

    e.preventDefault();
    var url = new URL(a.href, location.origin);
    // wir lassen den Endpunkt JSON liefern und zeigen im UI einen Alert
    fetch(url, {credentials:'same-origin'})
      .then(r=>r.json()).then(function(j){
        var main = document.querySelector('.kt-main') || document.body;
        if (j && j.ok){ flash(main, 'success', 'E-Mail wurde versendet.'); }
        else { flash(main, 'danger', (j && j.error) ? j.error : 'Versand fehlgeschlagen.'); }
      }).catch(function(err){
        var main = document.querySelector('.kt-main') || document.body;
        flash(main, 'danger', 'Versandfehler: ' + err);
      });
  });
})();
