function toggleSimulation(){
  if(machines.length && !simTimer) scheduleNext();
  else if(!machines.length && simTimer){ clearTimeout(simTimer); simTimer = null; }
}
function randomFrom(arr){ return arr[Math.floor(Math.random() * arr.length)]; }
function scheduleNext(){
  simTimer = setTimeout(() => { if(machines.length) generateAlert(); scheduleNext(); }, 4000 + Math.random() * 5000);
}

function generateAlert(){
  const machine = randomFrom(machines);
  const source = randomFrom(SOURCE_POOL);
  const type = randomFrom(ATTACK_TYPES);
  const syn = 6 + Math.floor(Math.random() * 25);
  const now = new Date();

  const alert = {
    id: nextAlertId++,
    time: now.toLocaleTimeString('pt-BR'), date: now.toLocaleDateString('pt-BR'),
    sourceIp: source.ip, mac: source.mac, so: source.so,
    destMachine: machine.name, destIp: machine.ip,
    type, port: '445 (SMB)', syn, status: 'aberto'
  };
  alerts.unshift(alert);
  typeCounts[type] = (typeCounts[type] || 0) + 1;
  timelineBuckets.push({ label: alert.time, count: 1 });
  if(timelineBuckets.length > 15) timelineBuckets.shift();

  machine.status = 'alerta';
  renderMachines();
  setTimeout(() => { machine.status = 'online'; renderMachines(); }, 4000);

  renderAlerts(); renderAlertsPreview(); updateCharts(); updateStats(); showToast(alert);
}
