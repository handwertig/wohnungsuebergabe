(function(){
  function toggleDesc(input){
    if (!input) return;
    var row = input.closest('.row, .mb-2, .card-body') || document;
    var name = input.getAttribute('name');
    if (!name || name.indexOf('[label]') === -1) return;
    var descName = name.replace('[label]', '[desc]');
    var desc = row.querySelector('input[name="'+descName+'"]');
    if (!desc) return;
    if ((input.value||'').trim().toLowerCase() === 'sonstige schlüssel') {
      desc.classList.remove('d-none'); desc.placeholder = "Beschreibung (z. B. Kellerabteil 3)";
    } else { desc.value=''; desc.classList.add('d-none'); }
  }
  document.addEventListener('input', function(e){
    var t=e.target;
    if (t && t.matches('input[name^="keys["][name$="[label]"]')) toggleDesc(t);
  }, true);
  document.querySelectorAll('input[name^="keys["][name$="[label]"]').forEach(toggleDesc);
})();
