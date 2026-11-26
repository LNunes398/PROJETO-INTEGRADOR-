const MON_API = 'http://127.0.0.1:8765';
let monAchados = [], monTimer = null;

function monNomeDe(host, ip){
  const h = (host || '').trim();
  return (h && h !== '(desconhecido)') ? h.split('.')[0].toUpperCase() : 'PC-' + ip.replace(/\./g, '-');
}
function monJaExiste(ip, mac){
  return machines.some(m => m.ip === ip || (mac && mac !== '?' && m.mac && m.mac.toUpperCase() === mac.toUpperCase()));
}
function monAdicionar(a, local){
  const host = (a.host && a.host !== '(desconhecido)') ? a.host : '';
  machines.push({ id: nextMachineId++, name: monNomeDe(a.host, a.ip), ip: a.ip, host, mac: (!a.mac || a.mac === '?') ? '' : a.mac, so: a.so || '', location: local, status: 'online' });
}

document.getElementById('open-add-machine').addEventListener('click', () => {
  showModal(`
    <div class="modal-head"><div class="modal-title">Ativar máquinas</div><button class="modal-close" id="mon-x">&times;</button></div>
    <div class="modal-body" id="mon-root">
      <div class="modal-note" id="mon-st">Conectando ao programa…</div>
      <div class="mon-row">
        <button class="btn-ghost" data-mcmd="iniciar">▶ Iniciar busca</button>
        <button class="btn-ghost" data-mcmd="parar">⏹ Parar</button>
        <button class="btn-ghost" data-mcmd="continuar">⏯ Continuar</button>
        <button class="btn-ghost" data-mcmd="reiniciar">↺ Zerar</button>
        <progress class="mon-bar" id="mon-prog" max="254" value="0"></progress>
      </div>

      <div class="mon-sec">Computadores encontrados na rede</div>
      <div class="mon-scroll"><table class="mon-table">
        <thead><tr><th><input type="checkbox" id="mon-all" title="Selecionar todos"></th><th>IP</th><th>MAC</th><th>Sistema</th><th>Hostname</th></tr></thead>
        <tbody id="mon-sc"><tr><td colspan="5" class="vazio">Nenhum computador encontrado ainda.</td></tr></tbody>
      </table></div>

      <div class="mon-local">
        <label for="mon-local">Local (sala) dos computadores selecionados</label>
        <input id="mon-local" list="mon-locais" placeholder="Ex.: Lab de Redes, Sala 12, Secretaria…" autocomplete="off">
        <datalist id="mon-locais">${locations.map(l => `<option value="${escapeHtml(l)}">`).join('')}</datalist>
      </div>

      <div class="mon-sec">Ou adicionar um computador manualmente</div>
      <div class="mon-grid">
        <input id="mon-ip" placeholder="IP (ex: 192.168.0.10)">
        <input id="mon-mac" placeholder="MAC (ex: AA:BB:CC:DD:EE:FF)">
        <input id="mon-host" placeholder="Hostname (ex: PC-LAB-03)">
        <button class="btn-ghost" id="mon-add">＋ Adicionar</button>
      </div>
      <div class="mon-msg" id="mon-msg"></div>

      <div class="modal-actions">
        <button class="btn-secondary" id="mon-cancel">Fechar</button>
        <button class="btn-primary" id="mon-save">Salvar selecionados</button>
      </div>
    </div>`);
  document.getElementById('modal').classList.add('monitor');
  const $m = id => document.getElementById(id);
  const sel = new Set();
  let assinatura = '';

  const fechar = () => { clearInterval(monTimer); monTimer = null; closeModal(); };
  $m('mon-x').addEventListener('click', fechar);
  $m('mon-cancel').addEventListener('click', fechar);

  const localAtual = () => $m('mon-local').value.trim();
  function resolverLocal(){
    const l = localAtual();
    if(l && !locations.some(x => x.toLowerCase() === l.toLowerCase())) locations.push(l);
    return l ? (locations.find(x => x.toLowerCase() === l.toLowerCase()) || l) : '';
  }

  async function cmd(c){ try{ await fetch(MON_API + '/cmd/' + c); }catch(e){} poll(); }
  document.querySelectorAll('#mon-root [data-mcmd]').forEach(b => b.addEventListener('click', () => cmd(b.dataset.mcmd)));

  function desenhar(){
    const novos = monAchados.filter(a => !monJaExiste(a.ip, a.mac));
    const assin = JSON.stringify(monAchados) + '|' + machines.length;
    if(assin === assinatura) return;
    assinatura = assin;
    $m('mon-sc').innerHTML = monAchados.length ? monAchados.map(a => {
      const ja = monJaExiste(a.ip, a.mac);
      return `<tr><td><input type="checkbox" data-ip="${escapeHtml(a.ip)}" ${ja ? 'disabled' : ''} ${sel.has(a.ip) && !ja ? 'checked' : ''}></td>
        <td class="mono">${escapeHtml(a.ip)}</td><td class="mono">${escapeHtml(a.mac)}</td><td>${escapeHtml(a.so)}</td>
        <td>${escapeHtml(a.host)}${ja ? ' <span class="mon-ja">· já cadastrado</span>' : ''}</td></tr>`;
    }).join('') : '<tr><td colspan="5" class="vazio">Nenhum computador encontrado ainda.</td></tr>';
    $m('mon-all').disabled = !novos.length;
    contar();
  }
  function contar(){ $m('mon-save').textContent = sel.size ? `Salvar ${sel.size} selecionado(s)` : 'Salvar selecionados'; }

  $m('mon-sc').addEventListener('change', e => {
    const ip = e.target.dataset.ip; if(!ip) return;
    e.target.checked ? sel.add(ip) : sel.delete(ip); contar();
  });
  $m('mon-all').addEventListener('change', e => {
    monAchados.forEach(a => { if(!monJaExiste(a.ip, a.mac)) e.target.checked ? sel.add(a.ip) : sel.delete(a.ip); });
    assinatura = ''; desenhar();
  });

  async function poll(){
    if(!$m('mon-root')){ clearInterval(monTimer); monTimer = null; return; }
    try{
      const s = await (await fetch(MON_API + '/status')).json(); monAchados = s.achados;
      $m('mon-st').textContent = `✅ Programa conectado • Seu IP: ${s.meu_ip} • Rede: ${s.rede}.0/24 • Estado: ${s.estado} • Progresso: ${s.proximo-1}/${s.total} • Encontrados: ${monAchados.length}`;
      $m('mon-prog').value = s.proximo - 1;
      desenhar();
    }catch(e){ if($m('mon-st')) $m('mon-st').textContent = '⚠️ Programa offline. Execute scanner/iniciar.bat no Windows (ou adicione manualmente abaixo).'; }
  }
  clearInterval(monTimer); monTimer = setInterval(poll, 1000); poll();

  $m('mon-save').addEventListener('click', () => {
    const escolhidos = monAchados.filter(a => sel.has(a.ip) && !monJaExiste(a.ip, a.mac));
    if(!escolhidos.length){ $m('mon-msg').textContent = '❌ Marque ao menos um computador da lista.'; return; }
    const local = resolverLocal();
    escolhidos.forEach(a => monAdicionar(a, local));
    fechar(); renderMachines(); toggleSimulation();
  });

  $m('mon-add').addEventListener('click', () => {
    const ip = $m('mon-ip').value.trim(), mac = $m('mon-mac').value.trim() || '?', host = $m('mon-host').value.trim() || '(desconhecido)';
    if(!ip){ $m('mon-msg').textContent = '❌ Informe ao menos o IP.'; return; }
    if(monJaExiste(ip, mac)){ $m('mon-msg').textContent = '❌ Esse computador já está cadastrado.'; return; }
    monAdicionar({ ip, mac, host, so: '' }, resolverLocal());
    $m('mon-ip').value = $m('mon-mac').value = $m('mon-host').value = '';
    $m('mon-msg').textContent = '✅ Computador adicionado.'; assinatura = ''; desenhar();
    renderMachines(); toggleSimulation();
  });
});

function openPermissionModal(){
  showModal(`
    <div class="modal-head"><div class="modal-title">Permitir detecção automática?</div><button class="modal-close" onclick="closeModal()">&times;</button></div>
    <div class="modal-body">
      <ul class="permission-list">
        <li>Sistema operacional e navegador</li>
        <li>Resolução de tela e núcleos do processador</li>
        <li>IP público (precisa de internet)</li>
      </ul>
      <div class="modal-note">Por segurança do navegador, não é possível ler o MAC nem o IP da rede
      local (192.168.x.x) automaticamente. Complete esses campos manualmente depois.</div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="closeModal()">Cancelar</button>
        <button class="btn-primary" id="allow-detect">Permitir e detectar</button>
      </div>
    </div>`);
  document.getElementById('allow-detect').addEventListener('click', runDetection);
}

function detectLocalIp(){

  return new Promise(resolve => {
    try{
      const pc = new RTCPeerConnection({ iceServers: [] });
      pc.createDataChannel('');
      const fim = v => { try{ pc.close(); }catch(e){} resolve(v); };
      pc.onicecandidate = e => {
        if(!e || !e.candidate) return fim('');
        const m = /(\d{1,3}(?:\.\d{1,3}){3})/.exec(e.candidate.candidate);
        if(m) fim(m[1]);
      };
      pc.createOffer().then(o => pc.setLocalDescription(o)).catch(() => fim(''));
      setTimeout(() => fim(''), 1500);
    }catch(e){ resolve(''); }
  });
}

async function runDetection(){
  const ua = navigator.userAgent || '';
  let so = 'Sistema não identificado';
  if(/Windows NT 10/.test(ua)) so = 'Windows 10/11';
  else if(/Windows/.test(ua)) so = 'Windows';
  else if(/Mac OS X/.test(ua)) so = 'macOS';
  else if(/Android/.test(ua)) so = 'Android';
  else if(/iPhone|iPad/.test(ua)) so = 'iOS';
  else if(/Linux/.test(ua)) so = 'Linux';

  let ip = '';
  try{
    const res = await fetch('https://api.ipify.org?format=json');
    const data = await res.json();
    ip = data.ip;
  }catch(e){  }

  const localIp = await detectLocalIp();
  openMachineForm(null, { so, ip, localIp });
}

function openMachineForm(machine, detected){
  const isEdit = !!machine;
  const locOptions = locations.map(l => `<option value="${l}" ${machine && machine.location === l ? 'selected' : ''}>${l}</option>`).join('');
  showModal(`
    <div class="modal-head"><div class="modal-title">${isEdit ? 'Editar máquina' : 'Ativar máquina'}</div><button class="modal-close" onclick="closeModal()">&times;</button></div>
    <div class="modal-body">
      ${detected ? '<div class="modal-note">Dados detectados pelo navegador. Confira e complete o que faltar.</div>' : ''}
      <form class="modal-form" id="machine-form">
        <div class="field"><label>nome da máquina</label><input type="text" id="m-name" placeholder="VM-MONITORADA-01" required></div>
        <div class="field"><label>local</label><select id="m-location"><option value="">Selecione um local</option>${locOptions}</select></div>
        <div class="field"><label>IP</label><input type="text" id="m-ip" placeholder="192.168.56.10" required></div>
        <div class="field"><label>Hostname (nome na rede)</label><input type="text" id="m-host" placeholder="pc-lab01.escola.local"></div>
        <div class="field"><label>MAC (opcional)</label><input type="text" id="m-mac" placeholder="08:00:27:1A:2B:3C"></div>
        <div class="field"><label>Sistema operacional (opcional)</label><input type="text" id="m-so" placeholder="Windows 10 / Ubuntu..."></div>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="closeModal()">Cancelar</button>
          <button type="submit" class="btn-primary">${isEdit ? 'Salvar' : 'Ativar'}</button>
        </div>
      </form>
    </div>`);

  if(isEdit){
    document.getElementById('m-name').value = machine.name;
    document.getElementById('m-ip').value = machine.ip;
    document.getElementById('m-host').value = machine.host || '';
    document.getElementById('m-mac').value = machine.mac || '';
    document.getElementById('m-so').value = machine.so || '';
  } else if(detected){
    document.getElementById('m-name').value = 'ESTE-COMPUTADOR';
    document.getElementById('m-ip').value = detected.localIp || detected.ip || '';
    document.getElementById('m-host').value = detected.host || '';
    document.getElementById('m-so').value = detected.so || '';
  }

  document.getElementById('machine-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const name = document.getElementById('m-name').value.trim();
    const ip = document.getElementById('m-ip').value.trim();
    if(!name || !ip) return;
    const mac = document.getElementById('m-mac').value.trim();
    const host = document.getElementById('m-host').value.trim();
    const so = document.getElementById('m-so').value.trim();
    const location = document.getElementById('m-location').value;
    if(isEdit){
      Object.assign(machine, { name, ip, host, mac, so, location });
    } else {
      machines.push({ id: nextMachineId++, name, ip, host, mac, so, location, status: 'online' });
    }
    closeModal(); renderMachines(); toggleSimulation();
  });
}
