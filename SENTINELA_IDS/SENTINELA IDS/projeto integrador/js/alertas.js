document.getElementById('alert-search').addEventListener('input', renderAlerts);
document.querySelectorAll('#view-alertas .filter-chip').forEach(chip => {
  chip.addEventListener('click', () => {
    document.querySelectorAll('#view-alertas .filter-chip').forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
    alertStatusFilter = chip.dataset.status;
    renderAlerts();
  });
});

function filteredAlerts(){
  const term = document.getElementById('alert-search').value.trim().toLowerCase();
  return alerts.filter(a => {
    if(alertStatusFilter !== 'todos' && a.status !== alertStatusFilter) return false;
    if(term && !(a.sourceIp.toLowerCase().includes(term) || a.destMachine.toLowerCase().includes(term) || a.type.toLowerCase().includes(term))) return false;
    return true;
  });
}

function renderAlertsPreview(){
  renderBentoAlerts();
  renderBentoSpotlight();
}

function renderBentoAlerts(){
  const wrap = document.getElementById('bento-alerts-list');
  const countEl = document.getElementById('bento-alert-count');
  const abertos = alerts.filter(a => a.status === 'aberto').length;

  if(abertos > 0){ countEl.style.display = ''; countEl.textContent = abertos; }
  else { countEl.style.display = 'none'; }

  if(!alerts.length){
    wrap.innerHTML = `<div class="mini-empty">${machines.length ? 'Nenhum evento detectado ainda.' : 'Ative uma máquina para começar.'}</div>`;
    return;
  }
  wrap.innerHTML = alerts.slice(0, 6).map(a => `
    <div class="mini-item">
      <span class="mini-ico ${a.status}">⚠</span>
      <div class="mini-item-text">
        <div class="mini-item-title">${escapeHtml(a.type)}</div>
        <div class="mini-item-sub">${escapeHtml(a.destMachine)} · ${a.time}</div>
      </div>
    </div>`).join('');
}

function renderBentoSpotlight(){
  const wrap = document.getElementById('bento-spotlight-body');
  const a = alerts[0];
  if(!a){
    wrap.innerHTML = '';
    return;
  }
  wrap.innerHTML = `
    <div class="event-banner">
      <div>
        <div class="event-banner-label">Último evento detectado</div>
        <div class="event-banner-title">${escapeHtml(a.type)}</div>
        <div class="event-banner-meta"><b>${escapeHtml(a.destMachine)}</b> · IP ${escapeHtml(a.sourceIp)}${a.port ? ' · porta ' + escapeHtml(a.port) : ''} · ${a.time}</div>
      </div>
      <button class="btn event-banner-btn" onclick="document.querySelector('[data-view=alertas]').click()">Ver detalhes</button>
    </div>`;
}

function renderAlerts(){
  const body = document.getElementById('alerts-body');
  const list = filteredAlerts();
  if(!list.length){
    body.innerHTML = `<tr class="empty-row"><td colspan="6">Nenhum alerta encontrado.</td></tr>`;
  } else {
    body.innerHTML = '';
    list.forEach(a => {
      const tr = document.createElement('tr');
      tr.style.cursor = 'pointer';
      tr.innerHTML = `<td>${a.time}</td><td>${escapeHtml(a.sourceIp)}</td><td>${escapeHtml(a.destMachine)}</td>
        <td><span class="atk-name">${escapeHtml(a.type)}</span></td><td>${a.syn}</td><td><span class="badge ${a.status}">${a.status}</span></td>`;
      tr.addEventListener('click', () => openAlertModal(a));
      body.appendChild(tr);
    });
  }
  document.getElementById('alert-count-label').textContent = list.length + ' registro' + (list.length === 1 ? '' : 's');
}

function attackKey(type){
  const t = String(type).toLowerCase();
  if(t.includes('flood')) return 'syn_flood';
  if(t.includes('varredura') || t.includes('scan')) return 'port_scan';
  if(t.includes('incompleta') || t.includes('half')) return 'half_open';
  if(t.includes('força bruta') || t.includes('forca bruta') || t.includes('brute')) return 'brute_force';
  return null;
}
function fillTpl(txt, a){
  return txt.split('{origem}').join(a.sourceIp).split('{destino}').join(a.destMachine)
            .split('{porta}').join(a.port).split('{pacotes}').join(a.syn);
}
function seqHtml(arr, cls){
  return '<div class="atk-seq">' + arr.map(p => `<span class="atk-pkt ${cls}">${escapeHtml(p)}</span>`).join('<span class="atk-arrow">›</span>') + '</div>';
}
function explainHtml(info, a){
  return `
    <div class="atk-sec"><h4>O que é</h4><p>${escapeHtml(info.resumo)}</p></div>
    <div class="atk-sec"><h4>${info.normal.titulo}</h4>${seqHtml(info.normal.pacotes,'ok')}<p>${escapeHtml(info.normal.texto)}</p></div>
    <div class="atk-sec"><h4>${info.malicioso.titulo}</h4>${seqHtml(info.malicioso.pacotes,'bad')}<p>${escapeHtml(info.malicioso.texto)}</p></div>
    ${a ? `<div class="atk-sec"><h4>O que o Sentinela encontrou neste caso</h4><div class="atk-found"><p>${escapeHtml(fillTpl(info.achado, a))}</p></div></div>` : ''}
    <div class="atk-sec"><h4>O que fazer</h4><p>${escapeHtml(a ? fillTpl(info.acao, a) : info.acao.split('{porta}').join('afetada'))}</p></div>`;
}

function openAlertModal(a){
  const key = attackKey(a.type), info = key ? ATTACK_INFO[key] : null;
  showModal(`
    <div class="modal-head"><div class="modal-title">Detalhes do alerta</div><button class="modal-close" onclick="closeModal()">&times;</button></div>
    <div class="modal-body">
      <div class="kv"><span class="kv-k">Ataque detectado</span><span class="kv-v"><span class="atk-name">${escapeHtml(info ? info.nome : a.type)}</span></span></div>
      <div class="kv"><span class="kv-k">Data / horário</span><span class="kv-v">${a.date} ${a.time}</span></div>
      <div class="kv"><span class="kv-k">IP de origem</span><span class="kv-v">${escapeHtml(a.sourceIp)}</span></div>
      <div class="kv"><span class="kv-k">MAC de origem</span><span class="kv-v">${escapeHtml(a.mac)}</span></div>
      <div class="kv"><span class="kv-k">SO de origem</span><span class="kv-v">${escapeHtml(a.so)}</span></div>
      <div class="kv"><span class="kv-k">Máquina de destino</span><span class="kv-v">${escapeHtml(a.destMachine)}</span></div>
      <div class="kv"><span class="kv-k">Porta</span><span class="kv-v">${escapeHtml(a.port)}</span></div>
      <div class="kv"><span class="kv-k">Pacotes SYN</span><span class="kv-v">${a.syn}</span></div>
      <div class="kv"><span class="kv-k">Status</span><span class="kv-v"><span class="badge ${a.status}">${a.status}</span></span></div>
      ${info ? `<button class="btn-secondary atk-toggle" id="atk-toggle">Entender este ataque</button><div id="atk-explain" style="display:none;">${explainHtml(info, a)}</div>` : ''}
      <div class="modal-actions">
        <button class="btn-primary" id="toggle-status">${a.status === 'aberto' ? 'Marcar como resolvido' : 'Reabrir alerta'}</button>
      </div>
    </div>`, true);
  const tg = document.getElementById('atk-toggle');
  if(tg) tg.addEventListener('click', () => {
    const box = document.getElementById('atk-explain');
    const aberto = box.style.display !== 'none';
    box.style.display = aberto ? 'none' : '';
    tg.textContent = aberto ? 'Entender este ataque' : 'Esconder explicação';
  });
  document.getElementById('toggle-status').addEventListener('click', () => {
    a.status = a.status === 'aberto' ? 'resolvido' : 'aberto';
    closeModal(); renderAlerts(); renderAlertsPreview(); updateStats();
  });
}

document.getElementById('open-attack-guide').addEventListener('click', () => {
  showModal(`
    <div class="modal-head"><div class="modal-title">Guia de ataques</div><button class="modal-close" onclick="closeModal()">&times;</button></div>
    <div class="modal-body">
      <div class="modal-note">Tipos de ataque que o Sentinela identifica. Abra cada um para ver como é o comportamento normal e como o ataque se diferencia dele.</div>
      ${Object.values(ATTACK_INFO).map(inf => `<details class="atk-item"><summary>${escapeHtml(inf.nome)}</summary>${explainHtml(inf, null)}</details>`).join('')}
    </div>`, true);
});

document.getElementById('export-csv').addEventListener('click', () => {
  if(!alerts.length){ alert('Não há alertas para exportar ainda.'); return; }
  const header = ['Data','Horário','IP origem','MAC origem','SO origem','Máquina destino','IP destino','Ataque','Porta','Pacotes SYN','Status'];
  const rows = alerts.map(a => [a.date, a.time, a.sourceIp, a.mac, a.so, a.destMachine, a.destIp, a.type, a.port, a.syn, a.status]);
  const csv = [header, ...rows].map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(';')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = 'alertas-sentinela.csv';
  link.click();
});

function showToast(a){
  const wrap = document.getElementById('toast-wrap');
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.innerHTML = `<div class="toast-title">Ataque detectado — ${escapeHtml(a.type)}</div><div class="toast-body">${escapeHtml(a.sourceIp)} → ${escapeHtml(a.destMachine)} · ${a.syn} pacotes SYN</div>`;
  wrap.appendChild(toast);
  setTimeout(() => toast.remove(), 6000);
}

function updateStats(){
  const open = alerts.filter(a => a.status === 'aberto').length;
  document.getElementById('stat-open').textContent = open;
  document.getElementById('stat-alerts').textContent = alerts.length;
  document.getElementById('stat-last').textContent = alerts[0] ? alerts[0].time : '—';
  document.getElementById('nav-alert-count').textContent = alerts.length;
}
