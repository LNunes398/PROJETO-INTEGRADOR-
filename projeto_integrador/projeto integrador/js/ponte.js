// Ponte com o programa do Sentinela (scanner): guarda os PCs de cada usuario e
// recebe os alertas enviados pelo simulador em CMD.
const PONTE_API = 'http://127.0.0.1:8765';
let ponteAtiva = false, ponteSeq = 0, ponteTimer = null, ponteDeb = null, ponteAssin = '', ponteRetry = null, ponteSync15 = null, ponteConectou = false;

function pontePar(){
  return 'user=' + encodeURIComponent(currentUser.username) + '&hash=' + encodeURIComponent(currentUser.password);
}

function ponteMaquinas(){
  return machines.map(m => ({ id: m.id, name: m.name, ip: m.ip, host: m.host || '', mac: m.mac || '', so: m.so || '', location: m.location || '', status: m.status }));
}

function ponteSincronizar(imediato){
  if(!ponteAtiva || !currentUser) return;
  const lista = ponteMaquinas();
  const assin = JSON.stringify(lista.map(m => [m.id, m.name, m.ip, m.host, m.mac, m.so, m.location]));
  if(!imediato && assin === ponteAssin) return;
  clearTimeout(ponteDeb);
  ponteDeb = setTimeout(async () => {
    try{
      const r = await fetch(PONTE_API + '/sincronizar', {
        method: 'POST',
        headers: { 'Content-Type': 'text/plain' },
        body: JSON.stringify({
          username: currentUser.username, email: currentUser.email || '',
          name: ((currentUser.name || '') + ' ' + (currentUser.lastName || '')).trim() || currentUser.username,
          hash: currentUser.password, maquinas: lista
        })
      });
      if(r.ok) ponteAssin = assin;
    }catch(e){}
  }, imediato ? 0 : 500);
}

function ponteMontarAlerta(a){
  return { id: nextAlertId++, time: a.time, date: a.date, sourceIp: a.sourceIp, mac: a.mac || '—', so: a.so,
           destMachine: a.destMachine, destIp: a.destIp, type: a.type, port: String(a.port), syn: a.syn, status: 'aberto' };
}

function pontePintar(){
  renderMachines(); renderAlerts(); renderAlertsPreview(); updateCharts(); updateStats();
}

// historico: alertas antigos guardados no programa (sem aviso na tela)
function ponteCarregarHistorico(lista){
  lista.forEach(a => {
    alerts.push(ponteMontarAlerta(a));
    typeCounts[a.type] = (typeCounts[a.type] || 0) + 1;
  });
  alerts.sort((x, y) => y.id - x.id);
}

// alertas novos enviados pelo CMD
function ponteReceber(lista){
  lista.forEach(a => {
    const alerta = ponteMontarAlerta(a);
    alerts.unshift(alerta);
    typeCounts[a.type] = (typeCounts[a.type] || 0) + 1;
    timelineBuckets.push({ label: alerta.time, count: 1 });
    if(timelineBuckets.length > 15) timelineBuckets.shift();
    const maq = machines.find(m => m.id === a.maquinaId);
    if(maq){
      maq.status = 'alerta';
      setTimeout(() => { maq.status = 'online'; renderMachines(); }, 4000);
    }
    showToast(alerta);
  });
}

async function ponteBuscar(){
  if(!ponteAtiva || !currentUser) return;
  try{
    const r = await fetch(PONTE_API + '/alertas?' + pontePar() + '&desde=' + ponteSeq, { cache: 'no-store' });
    const d = await r.json();
    if(!d.ok){
      // o programa nao conhece mais este usuario (ex.: dados.json apagado): envia de novo
      if(d.erro === 'SEM_CONTA') ponteSincronizar(true);
      return;
    }
    ponteSeq = d.seq;
    if(d.alertas.length){ ponteReceber(d.alertas); pontePintar(); }
  }catch(e){
    // programa fechado: volta a tentar conectar sozinho
    ponteAtiva = false;
    clearInterval(ponteTimer);
    ponteIniciar();
  }
}

async function ponteIniciar(){
  clearInterval(ponteTimer);
  clearInterval(ponteSync15);
  clearTimeout(ponteRetry);
  ponteAtiva = false; ponteAssin = '';
  if(!currentUser) return;
  try{
    const r = await fetch(PONTE_API + '/dados?' + pontePar(), { cache: 'no-store' });
    const d = await r.json();
    if(d.ok){
      // painel vazio (pagina recarregada): recupera o que o programa guardou
      if(!machines.length && d.maquinas.length){
        machines = d.maquinas.map(m => ({ id: m.id, name: m.name, ip: m.ip, host: m.host, mac: m.mac, so: m.so, location: m.location, status: m.status === 'offline' ? 'offline' : 'online' }));
        nextMachineId = Math.max(...machines.map(m => m.id)) + 1;
        toggleSimulation();
      }
      if(!ponteConectou){
        if(!alerts.length && d.alertas.length) ponteCarregarHistorico(d.alertas);
      } else {
        // reconexao: entrega o que chegou enquanto o painel estava sem contato
        const novos = d.alertas.filter(a => a.seq > ponteSeq);
        if(novos.length) ponteReceber(novos);
      }
      ponteSeq = d.seq;
      pontePintar();
    }
    ponteConectou = true;
  }catch(e){
    // programa fechado: o painel segue funcionando normalmente e tenta de novo a cada 3 s
    ponteRetry = setTimeout(ponteIniciar, 3000);
    return;
  }
  ponteAtiva = true;
  ponteSincronizar(true);
  ponteTimer = setInterval(ponteBuscar, 2000);
  // a cada 15 s manda os dados atuais dos PCs: ao recarregar a pagina, tudo volta atualizado
  ponteSync15 = setInterval(() => ponteSincronizar(true), 15000);
}

document.addEventListener('visibilitychange', () => {
  if(!document.hidden && currentUser && !ponteAtiva) ponteIniciar();
});
