(function () {
  var backdrop = document.getElementById('modal-backdrop');
  var modal    = document.getElementById('modal');

  function abrir(titulo, tplId) {
    var tpl = document.getElementById(tplId);
    modal.innerHTML = '<div class="modal-head"><div class="modal-title"></div>' +
      '<button type="button" class="modal-close">&times;</button></div><div class="modal-body"></div>';
    modal.querySelector('.modal-title').textContent = titulo;
    modal.querySelector('.modal-body').appendChild(tpl.content.cloneNode(true));
    modal.querySelector('.modal-close').onclick = fechar;
    var btn = modal.querySelector('.atk-toggle');
    if (btn) btn.onclick = function () {
      var box = modal.querySelector('.atk-explain');
      var aberto = box.style.display !== 'none';
      box.style.display = aberto ? 'none' : '';
      btn.textContent = aberto ? 'Entender este ataque' : 'Esconder explicação';
    };
    backdrop.classList.add('active');
  }
  function fechar() { backdrop.classList.remove('active'); }

  backdrop.addEventListener('click', function (e) { if (e.target === backdrop) fechar(); });
  document.getElementById('abrir-guia').addEventListener('click', function () { abrir('Guia de ataques', 'tpl-guia'); });
  document.querySelectorAll('.alerta-row').forEach(function (tr) {
    tr.addEventListener('click', function () { abrir('Detalhes do alerta', 'tpl-' + tr.dataset.id); });
  });
})();
