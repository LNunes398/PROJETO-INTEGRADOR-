function entrarNoSistema(u){
  currentUser = u;
  document.getElementById('login-screen').classList.remove('active');
  document.getElementById('dash-screen').classList.add('active');
  document.getElementById('greeting-user').textContent = (u.name || u.username).split(' ')[0];
  startClock();
  initCharts();
  renderAll();
  ponteIniciar();
}

document.getElementById('login-form').addEventListener('submit', async function(e){
  e.preventDefault();
  const login = document.getElementById('user').value.trim();
  const hash  = await hashSenha(document.getElementById('pass').value);
  const encontrado = users.find(us =>
    (us.username === login || (us.email && us.email.toLowerCase() === login.toLowerCase())) && us.password === hash);
  if(encontrado){
    entrarNoSistema(encontrado);
  } else {
    const err = document.getElementById('login-error');
    const card = document.getElementById('login-card');
    err.textContent = 'Usuário ou senha incorretos.';
    card.classList.remove('shake'); void card.offsetWidth; card.classList.add('shake');
  }
});
document.getElementById('logout-btn').addEventListener('click', () => location.reload());
