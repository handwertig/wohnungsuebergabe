(function(){
  var aside=document.getElementById('kt-aside'), toggle=document.getElementById('kt-aside-toggle'), backdrop=document.getElementById('kt-aside-backdrop'), body=document.body;
  function open(){ aside?.classList.add('show'); backdrop?.classList.add('show'); body.classList.add('offcanvas-open');}
  function close(){ aside?.classList.remove('show'); backdrop?.classList.remove('show'); body.classList.remove('offcanvas-open');}
  toggle && toggle.addEventListener('click', function(){ aside?.classList.contains('show')?close():open(); });
  backdrop && backdrop.addEventListener('click', close);
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') close();});
  aside && aside.addEventListener('click', function(e){ var a=e.target.closest('a'); if(a && window.matchMedia('(max-width:991.98px)').matches) close(); });
})();
