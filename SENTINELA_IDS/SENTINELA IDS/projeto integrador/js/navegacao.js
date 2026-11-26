document.querySelectorAll('.nav-link').forEach(item => {
  item.addEventListener('click', () => {
    document.querySelectorAll('.nav-link').forEach(n => n.classList.remove('active'));
    document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
    item.classList.add('active');
    document.getElementById('view-' + item.dataset.view).classList.add('active');
  });
});

function startClock(){
  const tick = () => document.getElementById('clock').textContent = new Date().toLocaleTimeString('pt-BR');
  tick(); setInterval(tick, 1000);
}

document.querySelectorAll('[data-view-link]').forEach(link => {
  link.addEventListener('click', () => {
    document.querySelector(`.nav-link[data-view="${link.dataset.viewLink}"]`).click();
  });
});

function escapeHtml(str){
  const d = document.createElement('div');
  d.textContent = str == null ? '' : str;
  return d.innerHTML;
}

function renderAll(){
  renderMachines();
  renderAlerts();
  renderAlertsPreview();
  renderLocations();
  renderUsers();
  updateStats();
}
