document.getElementById('open-signup').addEventListener('click', () => {
  showModal(`
    <div class="modal-head"><div class="modal-title">Criar conta</div><button class="modal-close" onclick="closeModal()">&times;</button></div>
    <div class="modal-body">
      <div class="form-msg erro" id="signup-erro" style="display:none;"></div>
      <form class="modal-form" id="signup-form">
        <div class="field"><label>nome</label><input type="text" id="su-name" placeholder="seu nome" autocomplete="off"></div>
        <div class="field"><label>sobrenome</label><input type="text" id="su-last" placeholder="seu sobrenome" autocomplete="off"></div>
        <div class="field"><label>usuário</label><input type="text" id="su-user" placeholder="ex: analista01" autocomplete="off"></div>
        <div class="field"><label>e-mail</label><input type="email" id="su-email" placeholder="voce@exemplo.com" autocomplete="off"></div>
        <div class="field"><label>senha</label><input type="password" id="su-pass" placeholder="mínimo 4 caracteres" autocomplete="off"></div>
        <div class="field"><label>confirmar senha</label><input type="password" id="su-pass2" placeholder="repita a senha" autocomplete="off"></div>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="closeModal()">Cancelar</button>
          <button type="submit" class="btn-primary">Criar conta</button>
        </div>
      </form>
    </div>`);

  document.getElementById('signup-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name  = document.getElementById('su-name').value.trim();
    const last  = document.getElementById('su-last').value.trim();
    const user  = document.getElementById('su-user').value.trim();
    const email = document.getElementById('su-email').value.trim();
    const pass  = document.getElementById('su-pass').value;
    const pass2 = document.getElementById('su-pass2').value;
    const erroEl = document.getElementById('signup-erro');
    let erro = '';
    if(!name || !last || !user || !email || !pass || !pass2) erro = 'Preencha todos os campos.';
    else if(name.length < 2 || last.length < 2) erro = 'Informe seu nome e sobrenome.';
    else if(!USER_RE.test(user)) erro = 'O usuário precisa ter de 3 a 50 caracteres (letras, números, ponto, hífen ou _).';
    else if(!EMAIL_RE.test(email)) erro = 'Informe um e-mail válido.';
    else if(pass.length < 4) erro = 'A senha precisa ter pelo menos 4 caracteres.';
    else if(pass !== pass2) erro = 'As senhas não coincidem.';
    else if(users.some(u => u.username.toLowerCase() === user.toLowerCase())) erro = 'Esse usuário já existe.';
    else if(users.some(u => u.email && u.email.toLowerCase() === email.toLowerCase())) erro = 'Esse e-mail já está cadastrado.';
    if(erro){ erroEl.textContent = erro; erroEl.style.display = ''; return; }

    const agora = new Date().toISOString();
    const novo = { id: Date.now(), uuid: novoUuid(), name, lastName: last, username: user, email, password: await hashSenha(pass), createdAt: agora, updatedAt: agora };
    users.push(novo);
    saveUsers();
    closeModal();

    const ok = document.getElementById('login-ok');
    ok.textContent = 'Conta criada com sucesso! Entre com seu usuário e senha.';
    ok.style.display = '';
    document.getElementById('user').value = user;
    document.getElementById('pass').value = '';
    document.getElementById('pass').focus();
  });
});
