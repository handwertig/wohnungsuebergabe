(function () {
  /* ========== Bootstrap Tooltips (global) ========== */
  var ttTrig = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  ttTrig.forEach(function (el) { try { new bootstrap.Tooltip(el); } catch(e){} });

  /* ========== HTML5 Client-Validation Hook (unverändert hilfreich) ========== */
  var forms = document.querySelectorAll('.needs-validation');
  Array.prototype.slice.call(forms).forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (!form.checkValidity()) {
        ev.preventDefault(); ev.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });

  /* ========== Unsaved changes warnen ========== */
  var dirty = false;
  document.addEventListener('input', function (e) {
    var f = e.target && e.target.closest('form');
    if (f) dirty = true;
  }, {capture:true});
  document.addEventListener('change', function (e) {
    var f = e.target && e.target.closest('form');
    if (f) dirty = true;
  }, {capture:true});
  window.addEventListener('beforeunload', function (e) {
    if (!dirty) return;
    e.preventDefault();
    e.returnValue = ''; // Chrome/Edge
  });
  // Bei Submit: Dirty zurücksetzen
  document.addEventListener('submit', function (e) { dirty = false; }, {capture:true});

  /* ========== Autofokus & Autocomplete-Hints ========== */
  // erstes sinnvolles Eingabefeld fokussieren
  var firstInput = document.querySelector('form input.form-control, form select.form-select, form textarea');
  if (firstInput && !document.activeElement.closest('form')) { try { firstInput.focus(); } catch(e){} }
  // sinnvolle autocomplete/Inputmode Defaults
  document.querySelectorAll('input[name="meta[tenant_contact][email]"]').forEach(function(el){
    el.setAttribute('autocomplete','email');
    el.setAttribute('inputmode','email');
  });
  document.querySelectorAll('input[name="meta[tenant_contact][phone]"]').forEach(function(el){
    el.setAttribute('autocomplete','tel');
    el.setAttribute('inputmode','tel');
  });
  document.querySelectorAll('input[name="address[postal_code]"], input[name="meta[tenant_new_addr][postal_code]"]').forEach(function(el){
    el.setAttribute('inputmode','numeric');
  });

  /* ========== Info-Icons an Labels (IBAN, Geruch, IST-Zustand) ========== */
  function addInfoIcon(labelEl, title){
    if (!labelEl || labelEl.querySelector('.info-icon')) return;
    var i = document.createElement('i');
    i.className = 'bi bi-info-circle info-icon ms-1';
    i.setAttribute('data-bs-toggle','tooltip');
    i.setAttribute('title', title);
    labelEl.appendChild(i);
    try { new bootstrap.Tooltip(i); } catch(e){}
  }

  // IBAN
  document.querySelectorAll('input[name="meta[bank][iban]"]').forEach(function(inp){
    var lab = inp.closest('.col-md-4, .col-12, .mb-3')?.querySelector('.form-label');
    addInfoIcon(lab, 'IBAN: Bitte Länderkennzeichen + Prüfziffer + Konto-ID angeben. Beispiel: DE89 3704 0044 0532 0130 00');
  });

  // Geruch (alle Raumkarten)
  document.querySelectorAll('input[name$="[smell]"]').forEach(function(inp){
    var lab = inp.closest('.col-md-4, .col-12, .mb-3')?.querySelector('.form-label');
    addInfoIcon(lab, 'Gerüche sachlich dokumentieren: Art, Intensität, evtl. Ursache (z. B. „deutlicher Tabakgeruch, auch nach Lüften wahrnehmbar“).');
  });

  // IST-Zustand (alle Raumkarten)
  document.querySelectorAll('textarea[name$="[state]"]').forEach(function(inp){
    var lab = inp.closest('.col-12, .mb-3')?.querySelector('.form-label');
    addInfoIcon(lab, 'IST‑Zustand vollständig & objektiv: positive wie negative Feststellungen klar und präzise beschreiben.');
  });

  /* ========== Datalist-Suche für Eigentümer/Hausverwaltung ========== */
  function enhanceSelectWithSearch(select, placeholder, listId){
    if (!select || select.dataset.enhanced === '1') return;
    // Input + Datalist vor den Select einfügen
    var wrap = document.createElement('div');
    wrap.className = 'mb-2';
    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control';
    input.placeholder = placeholder || 'Suchen…';
    input.setAttribute('list', listId);
    input.autocomplete = 'off';

    var datalist = document.createElement('datalist');
    datalist.id = listId;

    // Optionen aus Select übernehmen (Text)
    Array.prototype.forEach.call(select.options, function(opt, idx){
      if (!opt.value) return; // Skip "Bitte wählen"
      var o = document.createElement('option');
      o.value = opt.textContent.trim();
      datalist.appendChild(o);
    });

    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(input);
    wrap.appendChild(datalist);

    // Auswahl synchronisieren: bei Eingabe -> passenden Select-Eintrag setzen
    function syncToSelect(){
      var val = (input.value || '').toLowerCase();
      var foundValue = '';
      Array.prototype.forEach.call(select.options, function(opt){
        var text = opt.textContent.trim().toLowerCase();
        if (val && (text === val || text.indexOf(val) !== -1) && !foundValue) {
          foundValue = opt.value;
        }
      });
      select.value = foundValue || ''; // bleibt leer, wenn nichts passt
      dirty = true; // Änderung markieren
    }
    input.addEventListener('change', syncToSelect);
    input.addEventListener('blur', function(){ if (!select.value) syncToSelect(); });

    select.dataset.enhanced = '1';
  }

  // Wizard Schritt 1
  var ownerSel = document.querySelector('select[name="owner_id"]');
  if (ownerSel) enhanceSelectWithSearch(ownerSel, 'Eigentümer suchen…', 'owners-list');

  var mgrSel = document.querySelector('select[name="manager_id"]');
  if (mgrSel) enhanceSelectWithSearch(mgrSel, 'Hausverwaltung suchen…', 'managers-list');

  // Editor (Kopf-Tab)
  var ownerSelEdit = document.querySelector('#tab-kopf select[name="owner_id"]');
  if (ownerSelEdit) enhanceSelectWithSearch(ownerSelEdit, 'Eigentümer suchen…', 'owners-list-edit');

  var mgrSelEdit = document.querySelector('#tab-kopf select[name="manager_id"]');
  if (mgrSelEdit) enhanceSelectWithSearch(mgrSelEdit, 'Hausverwaltung suchen…', 'managers-list-edit');
})();
