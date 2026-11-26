(function () {
  var CSRF = document.getElementById('cadastro-app').dataset.csrf;
  var API  = 'http://127.0.0.1:8765';
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };

  var abaAuto = false;
  function aba(nome) {
    abaAuto = nome === 'auto';
    document.querySelectorAll('.cm-tab').forEach(function (b) { b.classList.toggle('on', b.dataset.aba === nome); });
    $('pane-manual').classList.toggle('on', nome === 'manual');
    $('pane-auto').classList.toggle('on', abaAuto);
    if (abaAuto) poll();
    try { history.replaceState(null, '', nome === 'auto' ? '#auto' : '#manual'); } catch (e) {}
  }
  document.querySelectorAll('.cm-tab').forEach(function (b) { b.onclick = function () { aba(b.dataset.aba); }; });

  function lerPref(k, pad) { try { var v = localStorage.getItem('sent_cm_' + k); return v === null ? pad : v; } catch (e) { return pad; } }
  function gravarPref(k, v) { try { localStorage.setItem('sent_cm_' + k, v); } catch (e) {} }
  $('opt-auto').checked = lerPref('auto', '1') === '1';
  $('opt-cont').checked = lerPref('cont', '0') === '1';
  $('opt-min').value    = lerPref('min', '5');
  $('opt-local').value  = lerPref('local', '');
  $('opt-auto').onchange  = function () { gravarPref('auto', this.checked ? '1' : '0'); ultimaAssinatura = ''; };
  $('opt-min').onchange   = function () { gravarPref('min', this.value); proximoCiclo = 0; };
  $('opt-local').onchange = function () { gravarPref('local', this.value); };
  $('opt-cont').onchange  = function () {
    gravarPref('cont', this.checked ? '1' : '0'); proximoCiclo = 0;
    if (this.checked && estado !== 'varrendo') cmd('iniciar');
  };

  var achados = [], estado = '', situacao = {}, ultimaAssinatura = '', enviando = false, proximoCiclo = 0;

  function cmd(c) { return fetch(API + '/cmd/' + c).catch(function () {}).then(poll); }
  document.querySelectorAll('[data-cmd]').forEach(function (b) { b.onclick = function () { cmd(b.dataset.cmd); }; });

  function poll() {
    if (!abaAuto && !$('opt-cont').checked) return;
    fetch(API + '/status', { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (s) {
        achados = s.achados || []; estado = s.estado;
        var extra = '';
        if ($('opt-cont').checked && estado === 'concluído') {
          if (!proximoCiclo) proximoCiclo = Date.now() + parseInt($('opt-min').value, 10) * 60000;
          var falta = Math.max(0, Math.round((proximoCiclo - Date.now()) / 1000));
          extra = ' • Próxima busca em ' + Math.floor(falta / 60) + ':' + ('0' + (falta % 60)).slice(-2);
          if (falta === 0) { proximoCiclo = 0; cmd('iniciar'); }
        } else { proximoCiclo = 0; }
        $('sc-status').textContent = '✅ Scanner conectado • Seu IP: ' + s.meu_ip + ' • Rede: ' + s.rede + '.0/24 • Estado: ' + s.estado +
          ' • Progresso: ' + (s.proximo - 1) + '/' + s.total + ' • Encontrados: ' + achados.length + extra;
        $('sc-bar').value = s.proximo - 1;
        desenharAchados();
        if ($('opt-auto').checked) enviar(false);
      })
      .catch(function () {
        $('sc-status').textContent = '⚠️ Scanner offline. Execute scanner/iniciar.bat neste computador.';
      });
  }

  function desenharAchados() {
    $('sc-tb').innerHTML = achados.length ? achados.map(function (a) {
      var sit = situacao[a.ip];
      var txt = sit === 'novo' ? '<span class="cm-sit-novo">✔ Cadastrado agora</span>' : sit === 'existente' ? '<span class="cm-sit-existente">já cadastrado</span>' : '—';
      return '<tr><td class="cm-mono">' + esc(a.ip) + '</td><td class="cm-mono">' + esc(a.mac) + '</td><td>' + esc(a.so) + '</td><td>' + esc(a.host) + '</td><td>' + txt + '</td></tr>';
    }).join('') : '<tr><td colspan="5" style="text-align:center;color:var(--text-faint)">Nenhum computador encontrado ainda.</td></tr>';
  }

  function enviar(forcar) {
    if (enviando || !achados.length) return;
    var ass = JSON.stringify(achados.map(function (a) { return [a.ip, a.mac, a.host]; }));
    if (!forcar && ass === ultimaAssinatura) return;
    enviando = true;
    var fd = new FormData();
    fd.append('csrf', CSRF);
    fd.append('achados', JSON.stringify(achados));
    fd.append('location_id', $('opt-local').value);
    fetch('cadastro_auto_sync.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        enviando = false;
        if (!d.ok) { $('cm-msg').textContent = '❌ ' + (d.erro || 'Falha ao cadastrar.'); return; }
        ultimaAssinatura = ass;
        (d.itens || []).forEach(function (i) { if (situacao[i.ip] !== 'novo') situacao[i.ip] = i.situacao; });
        $('cm-msg').textContent = d.novos > 0 ? '✅ ' + d.novos + ' computador(es) novo(s) cadastrado(s).' : (forcar ? 'Nenhum computador novo: todos já estavam cadastrados.' : $('cm-msg').textContent);
        desenharAchados();
        if (d.novos > 0) atualizarLista();
      })
      .catch(function () { enviando = false; $('cm-msg').textContent = '❌ Não foi possível falar com o servidor.'; });
  }
  $('btn-agora').onclick = function () { if (!achados.length) { $('cm-msg').textContent = 'Nenhum computador encontrado ainda.'; return; } enviar(true); };

  function atualizarLista() {
    var qs = new URLSearchParams(location.search); qs.set('parcial', '1');
    fetch('cadastro_maquinas.php?' + qs.toString(), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.text(); })
      .then(function (h) { $('lista').innerHTML = h; });
  }
  $('lista').addEventListener('change', function (e) {
    if (!e.target.classList.contains('sel-local')) return;
    var fd = new FormData();
    fd.append('csrf', CSRF); fd.append('id', e.target.dataset.id); fd.append('location_id', e.target.value);
    fetch('maquina_local.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.ok) atualizarLista(); else alert(d.erro || 'Não foi possível salvar o local.'); })
      .catch(function () { alert('Não foi possível salvar o local.'); });
  });

  setInterval(poll, 1500);
  if (location.hash === '#auto') aba('auto');
  else if ($('opt-cont').checked) poll();
})();
