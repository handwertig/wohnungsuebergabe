(function () {
  var KEY = 'theme-pref';          // 'dark' | 'light' | ''
  var html = document.documentElement;
  var btn  = document.getElementById('kt-theme-toggle');

  // beim Laden gespeicherte Präferenz anwenden
  try {
    var pref = localStorage.getItem(KEY) || '';
    if (pref === 'dark') html.setAttribute('data-theme', 'dark');
    else if (pref === 'light') html.setAttribute('data-theme', 'light');
    else html.removeAttribute('data-theme');
  } catch(e){}

  if (btn) {
    btn.addEventListener('click', function () {
      var cur = html.getAttribute('data-theme') || '';
      var next = (cur === 'dark') ? 'light' : 'dark';
      if (next) html.setAttribute('data-theme', next); else html.removeAttribute('data-theme');
      try { localStorage.setItem(KEY, next); } catch(e){}
    });
  }
})();
