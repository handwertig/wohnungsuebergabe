(function(){
  var aside = document.getElementById('kt-aside');
  var toggle= document.getElementById('kt-aside-toggle');
  var backdrop = document.getElementById('kt-aside-backdrop');
  function closeAside(){ aside && aside.classList.remove('show'); backdrop && backdrop.classList.remove('show'); }
  function openAside(){  aside && aside.classList.add('show');     backdrop && backdrop.classList.add('show'); }
  toggle && toggle.addEventListener('click', function(){ aside?.classList.contains('show') ? closeAside() : openAside(); });
  backdrop && backdrop.addEventListener('click', closeAside);
})();
