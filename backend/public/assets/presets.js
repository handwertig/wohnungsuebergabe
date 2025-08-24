(function(){
  function ensureDatalist(id, values){
    if (document.getElementById(id)) return;
    var dl = document.createElement('datalist'); dl.id = id;
    values.forEach(function(v){ var o=document.createElement('option'); o.value=v; dl.appendChild(o); });
    document.body.appendChild(dl);
  }
  function bind(selector, listId){
    document.querySelectorAll(selector).forEach(function(inp){
      if (!inp.getAttribute('list')) inp.setAttribute('list', listId);
    });
  }
  document.addEventListener('DOMContentLoaded', function(){
    ensureDatalist('room-presets', ['Schlafzimmer','Wohnzimmer','Küche','Bad','WC','Kinderzimmer','Arbeitszimmer','Studio','Flur','Keller','Abstellraum','Balkon','Terrasse','Garage','Stellplatz']);
    ensureDatalist('key-presets',  ['Hausschlüssel','Wohnungsschlüssel','Briefkastenschlüssel','Kellerschlüssel','Garagenschlüssel','Sonstige Schlüssel']);
    bind('input[name^="rooms["][name$="[name]"]', 'room-presets');
    bind('input[name^="keys["][name$="[label]"]', 'key-presets');
  });
})();
