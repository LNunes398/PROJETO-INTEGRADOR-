document.getElementById('detectar').addEventListener('click', function () {
  var msg = document.getElementById('detectar-msg');
  var f = document.querySelector('form.modal-form');
  msg.style.display = ''; msg.textContent = 'Detectando...';
  fetch('detectar_dispositivo.php', { credentials: 'same-origin', cache: 'no-store' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (d.ip)       f.elements['ip'].value = d.ip;
      if (d.hostname) f.elements['hostname'].value = d.hostname;
      if (d.mac)      f.elements['mac'].value = d.mac;
      if (d.so && !f.elements['so'].value) f.elements['so'].value = d.so;
      if (d.hostname && !f.elements['name'].value) f.elements['name'].value = d.hostname.split('.')[0].toUpperCase();
      var faltou = [];
      if (!d.ip) faltou.push('IP'); if (!d.hostname) faltou.push('hostname'); if (!d.mac) faltou.push('MAC');
      msg.textContent = d.aviso || (faltou.length ? 'Não foi possível obter: ' + faltou.join(', ') + '. Preencha manualmente.' : 'IP, MAC e hostname detectados. Confira antes de salvar.');
    })
    .catch(function () { msg.textContent = 'Falha na detecção. Preencha os campos manualmente.'; });
});
