document.getElementById('machine-search').addEventListener('input', renderMachines);

function renderFilterChips(){
  const row = document.getElementById('filter-row');
  const present = [...new Set(machines.map(m => m.location).filter(Boolean))];
  if(!present.length){ row.innerHTML = ''; return; }
  let html = `<button class="filter-chip ${machineFilter === 'todas' ? 'active' : ''}" data-loc="todas">Todas</button>`;
  present.forEach(loc => {
    html += `<button class="filter-chip ${machineFilter === loc ? 'active' : ''}" data-loc="${escapeHtml(loc)}">${escapeHtml(loc)}</button>`;
  });
  row.innerHTML = html;
  row.querySelectorAll('.filter-chip').forEach(chip => {
    chip.addEventListener('click', () => { machineFilter = chip.dataset.loc; renderMachines(); });
  });
}

function renderMachines(){
  renderFilterChips();
  const container = document.getElementById('machines-container');
  const term = document.getElementById('machine-search').value.trim().toLowerCase();

  let visible = machineFilter === 'todas' ? machines : machines.filter(m => m.location === machineFilter);
  if(term){
    visible = visible.filter(m => m.name.toLowerCase().includes(term) || m.ip.toLowerCase().includes(term) || (m.host || '').toLowerCase().includes(term));
  }

  if(!machines.length){
    container.innerHTML = `<div class="panel"><div class="empty-state">Nenhuma máquina ativada ainda.<br>Clique em "+ Ativar máquina" para começar.</div></div>`;
  } else if(!visible.length){
    container.innerHTML = `<div class="panel"><div class="empty-state">Nenhuma máquina encontrada com esse filtro.</div></div>`;
  } else {
    const grid = document.createElement('div');
    grid.className = 'machines-grid';
    visible.forEach(m => {
      const card = document.createElement('div');
      card.className = 'machine-card' + (m.status === 'alerta' ? ' is-alert' : '');
      card.innerHTML = `
        <div class="machine-card-top">
          <span class="machine-card-name">${escapeHtml(m.name)}</span>
          <span class="dot ${m.status === 'alerta' ? 'alerta' : 'online'}"></span>
        </div>
        <div class="machine-card-loc"><span class="loc-tag">${escapeHtml(m.location || 'sem local')}</span></div>
        <div class="machine-card-meta">
          <b>IP:</b> ${escapeHtml(m.ip)}<br>
          <b>HOST:</b> ${escapeHtml(m.host || 'não informado')}<br>
          <b>MAC:</b> ${escapeHtml(m.mac || 'não informado')}<br>
          <b>SO:</b> ${escapeHtml(m.so || 'não informado')}
        </div>
        <div class="machine-card-actions">
          <button class="icon-btn" data-edit="${m.id}">Editar</button>
          <button class="icon-btn danger" data-remove="${m.id}">Remover</button>
        </div>`;
      grid.appendChild(card);
    });
    container.innerHTML = '';
    container.appendChild(grid);
    container.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => openMachineForm(machines.find(m => m.id === Number(b.dataset.edit)))));
    container.querySelectorAll('[data-remove]').forEach(b => b.addEventListener('click', () => {
      machines = machines.filter(m => m.id !== Number(b.dataset.remove));
      renderMachines(); toggleSimulation();
    }));
  }
  document.getElementById('stat-machines').textContent = machines.length;
  document.getElementById('nav-machine-count').textContent = machines.length;
  renderBentoMachines();
  renderLocations();
  if(typeof ponteSincronizar === 'function') ponteSincronizar();
}

function renderBentoMachines(){
  const wrap = document.getElementById('bento-machines-list');
  if(!machines.length){
    wrap.innerHTML = `<div class="mini-empty">Nenhuma máquina cadastrada ainda.</div>`;
    return;
  }
  const ordenadas = [...machines].sort((a, b) => (b.status === 'alerta') - (a.status === 'alerta'));
  wrap.innerHTML = ordenadas.slice(0, 5).map(m => `
    <div class="mini-item">
      <span class="dot ${m.status}"></span>
      <div class="mini-item-text">
        <div class="mini-item-title">${escapeHtml(m.name)}</div>
        <div class="mini-item-sub">${escapeHtml(m.ip)}${m.location ? ' · ' + escapeHtml(m.location) : ''}</div>
      </div>
      <span class="mini-item-tag ${m.status}">${m.status}</span>
    </div>`).join('');
}
