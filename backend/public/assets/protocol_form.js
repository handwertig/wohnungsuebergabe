(function () {
  // Räume dynamisch
  const roomsWrap = document.getElementById('rooms-wrap');
  const addRoomBtn = document.getElementById('add-room-btn');
  if (roomsWrap && addRoomBtn) {
    addRoomBtn.addEventListener('click', function (e) {
      e.preventDefault();
      const idx = Date.now();
      const tpl = document.getElementById('room-template').innerHTML.replace(/__IDX__/g, idx);
      const div = document.createElement('div');
      div.className = 'card mb-3';
      div.innerHTML = tpl;
      roomsWrap.appendChild(div);
    });
    roomsWrap.addEventListener('click', function (e) {
      if (e.target && e.target.matches('[data-remove-room]')) {
        e.preventDefault();
        const card = e.target.closest('.card');
        if (card) card.remove();
      }
    });
  }

  // Schlüssel dynamisch
  const keysWrap = document.getElementById('keys-wrap');
  const addKeyBtn = document.getElementById('add-key-btn');
  if (keysWrap && addKeyBtn) {
    addKeyBtn.addEventListener('click', function (e) {
      e.preventDefault();
      const idx = Date.now();
      const tpl = document.getElementById('key-template').innerHTML.replace(/__IDX__/g, idx);
      const row = document.createElement('div');
      row.className = 'row g-2 align-items-end mb-2';
      row.innerHTML = tpl;
      keysWrap.appendChild(row);
    });
    keysWrap.addEventListener('click', function (e) {
      if (e.target && e.target.matches('[data-remove-key]')) {
        e.preventDefault();
        const row = e.target.closest('.row');
        if (row) row.remove();
      }
    });
  }
})();
