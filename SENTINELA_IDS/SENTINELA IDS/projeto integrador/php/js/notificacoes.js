(function () {
  var badge  = document.getElementById('notif-badge');
  var wrap   = document.getElementById('toast-wrap');
  var ultimo = parseInt(wrap && wrap.dataset.ultimo, 10) || 0;
  if (!badge || !wrap) return;

  function mostrarToast(texto) {
    var t = document.createElement('div');
    t.className = 'toast';
    var a = document.createElement('div'); a.className = 'toast-title'; a.textContent = 'Nova notificação';
    var b = document.createElement('div'); b.className = 'toast-body';  b.textContent = texto;
    t.appendChild(a); t.appendChild(b);
    t.style.cursor = 'pointer';
    t.onclick = function () { location.href = 'notificacoes.php'; };
    wrap.appendChild(t);
    setTimeout(function () { t.remove(); }, 6000);
  }

  function atualizar() {
    fetch('notificacoes_contagem.php?ultimo=' + ultimo, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) return;
        badge.textContent = d.nao_lidas > 99 ? '99+' : d.nao_lidas;
        badge.hidden = d.nao_lidas === 0;
        (d.novas || []).forEach(function (n) { mostrarToast(n.texto); });
        if (d.ultimo_id > ultimo) ultimo = d.ultimo_id;
      })
      .catch(function () {});
  }
  setInterval(atualizar, 15000);
})();
