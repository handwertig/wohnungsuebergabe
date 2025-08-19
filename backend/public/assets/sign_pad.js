(function () {
  function initPad(canvas) {
    const ctx = canvas.getContext('2d');
    let drawing = false, prev = null;

    function resize() {
      const data = canvas.toDataURL(); // preserve
      canvas.width = canvas.clientWidth;
      canvas.height = 160;
      if (data && data.length > 100) {
        const img = new Image(); img.onload = () => ctx.drawImage(img, 0, 0); img.src = data;
      }
      ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#222';
      ctx.fillStyle = '#fff'; ctx.fillRect(0,0,canvas.width,canvas.height);
    }
    window.addEventListener('resize', resize, false);
    resize();

    function pos(e) {
      const r = canvas.getBoundingClientRect();
      const x = (e.touches ? e.touches[0].clientX : e.clientX) - r.left;
      const y = (e.touches ? e.touches[0].clientY : e.clientY) - r.top;
      return {x,y};
    }

    function start(e){ e.preventDefault(); drawing = true; prev = pos(e); }
    function move(e){
      if (!drawing) return;
      const p = pos(e);
      ctx.beginPath(); ctx.moveTo(prev.x, prev.y); ctx.lineTo(p.x, p.y); ctx.stroke();
      prev = p;
    }
    function end(){ drawing = false; }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', end);
    canvas.addEventListener('mouseleave', end);
    canvas.addEventListener('touchstart', start, {passive:false});
    canvas.addEventListener('touchmove', move, {passive:false});
    canvas.addEventListener('touchend', end);
  }

  document.querySelectorAll('canvas[data-sign-pad]').forEach(initPad);

  // Export alle Pads vor Submit
  const form = document.getElementById('sign-form');
  if (form) {
    form.addEventListener('submit', function(e){
      document.querySelectorAll('canvas[data-sign-pad]').forEach(function(canvas){
        const target = document.getElementById(canvas.dataset.targetHidden);
        if (target) target.value = canvas.toDataURL('image/png');
      });
    });
  }
})();
