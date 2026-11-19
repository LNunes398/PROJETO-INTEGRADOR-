const fmtData = iso => iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle:'short', timeStyle:'short' }) : '—';
const nomeCompleto = u => ((u.name || '') + ' ' + (u.lastName || '')).trim() || u.username;
const setTxt = (id, v) => { document.getElementById(id).textContent = v == null ? '' : v; };
const hideEl = id => { const el = document.getElementById(id); if(el){ el.style.display = 'none'; } };
function showMsg(id, texto){ const el = document.getElementById(id); el.textContent = texto; el.style.display = ''; }

function showAcct(modo, opts){
  opts = opts || {};
  if(!currentUser) return;
  if(!currentUser.uuid){ currentUser.uuid = novoUuid(); saveUsers(); }
  document.querySelectorAll('.acct-pane').forEach(p => p.classList.remove('active'));
  document.getElementById(modo === 'menu' ? 'acct-menu' : 'acct-' + modo).classList.add('active');
  ['v-ok','e-erro','d-erro'].forEach(hideEl);

  if(modo === 'visualizar'){
    setTxt('v-user', currentUser.username); setTxt('v-name', currentUser.name || '');
    setTxt('v-last', currentUser.lastName || ''); setTxt('v-email', currentUser.email || '');
    setTxt('v-created', fmtData(currentUser.createdAt)); setTxt('v-updated', fmtData(currentUser.updatedAt || currentUser.createdAt));
    setTxt('v-uuid', currentUser.uuid);
    if(opts.ok) document.getElementById('v-ok').style.display = '';
  }
  if(modo === 'editar'){
    document.getElementById('e-user').value = currentUser.username;
    document.getElementById('e-name').value = currentUser.name || '';
    document.getElementById('e-last').value = currentUser.lastName || '';
    document.getElementById('e-email').value = currentUser.email || '';
    ['e-cur','e-pass','e-pass2'].forEach(i => document.getElementById(i).value = '');
    setTxt('e-created', fmtData(currentUser.createdAt)); setTxt('e-updated', fmtData(currentUser.updatedAt || currentUser.createdAt));
    setTxt('e-uuid', currentUser.uuid);
  }
  if(modo === 'excluir'){
    setTxt('d-user', currentUser.username); setTxt('d-name', nomeCompleto(currentUser)); setTxt('d-email', currentUser.email || '');
    document.getElementById('d-pass').value = '';
  }
}

document.addEventListener('click', (e) => {
  const el = e.target.closest('[data-acct]');
  if(el) showAcct(el.dataset.acct);
});

document.querySelector('.nav-link[data-view="usuarios"]').addEventListener('click', () => showAcct('menu'));
function renderUsers(){ showAcct('menu'); }

document.getElementById('acct-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const user = document.getElementById('e-user').value.trim();
  const name = document.getElementById('e-name').value.trim();
  const last = document.getElementById('e-last').value.trim();
  const email = document.getElementById('e-email').value.trim();
  const cur = document.getElementById('e-cur').value;
  const nova = document.getElementById('e-pass').value;
  const nova2 = document.getElementById('e-pass2').value;

  let erro = '';
  if(name.length < 2 || last.length < 2) erro = 'Informe seu nome e sobrenome.';
  else if(!USER_RE.test(user)) erro = 'O ID do usuário precisa ter de 3 a 50 caracteres (letras, números, ponto, hífen ou _).';
  else if(!EMAIL_RE.test(email)) erro = 'Informe um e-mail válido.';
  else if(nova && nova.length < 4) erro = 'A nova senha precisa ter pelo menos 4 caracteres.';
  else if(nova !== nova2) erro = 'A verificação não bate com a nova senha.';
  else if(nova && (await hashSenha(cur)) !== currentUser.password) erro = 'Para criar uma nova senha, informe corretamente a senha atual.';
  else if(users.some(u => u.id !== currentUser.id && u.username.toLowerCase() === user.toLowerCase())) erro = 'Esse ID de usuário já está em uso.';
  else if(users.some(u => u.id !== currentUser.id && u.email && u.email.toLowerCase() === email.toLowerCase())) erro = 'Esse e-mail já está em uso.';
  if(erro){ showMsg('e-erro', erro); return; }

  currentUser.username = user; currentUser.name = name; currentUser.lastName = last; currentUser.email = email;
  if(nova) currentUser.password = await hashSenha(nova);
  currentUser.updatedAt = new Date().toISOString();
  saveUsers();
  document.getElementById('greeting-user').textContent = name.split(' ')[0];
  showAcct('visualizar', { ok: true });
});

document.getElementById('del-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  if((await hashSenha(document.getElementById('d-pass').value)) !== currentUser.password){
    showMsg('d-erro', 'Senha incorreta. A conta não foi excluída.'); return;
  }
  if(!confirm('Excluir sua conta definitivamente? Esta ação não pode ser desfeita.')) return;
  users = users.filter(u => u.id !== currentUser.id);
  saveUsers();
  alert('Sua conta foi excluída.');
  location.reload();
});
