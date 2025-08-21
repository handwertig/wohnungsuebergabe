(function(){
  var aside = document.querySelector('.kt-aside');
  document.getElementById('kt-aside-toggle')?.addEventListener('click', function(){
    aside?.classList.toggle('show');
  });

  // Theme toggle (manual)
  var key='theme-pref'; var html=document.documentElement;
  var btn=document.getElementById('kt-theme-toggle');
  if(btn){
    btn.addEventListener('click', function(){
      var cur=html.getAttribute('data-theme')||'';
      var next = (cur==='dark') ? '' : 'dark';
      if(next) html.setAttribute('data-theme',next); else html.removeAttribute('data-theme');
      localStorage.setItem(key,next);
    });
    var pref=localStorage.getItem(key); if(pref){ if(pref) html.setAttribute('data-theme',pref); }
  }
})();
