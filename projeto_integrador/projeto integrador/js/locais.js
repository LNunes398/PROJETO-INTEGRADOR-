function renderLocations(){
  const body = document.getElementById('locations-body');
  if(!locations.length){
    body.innerHTML = `<tr class="empty-row"><td colspan="3">Nenhum local cadastrado.</td></tr>`;
    return;
  }
  body.innerHTML = locations.map(loc => {
    const total = machines.filter(m => m.location === loc).length;
    return `<tr>
      <td><span class="loc-tag">${escapeHtml(loc)}</span></td>
      <td>${total}</td>
      <td style="text-align:right; white-space:nowrap;">
        <a href="javascript:void(0)" class="pagehead-link" onclick="openLocationForm('${escapeHtml(loc)}')">editar</a>
        &nbsp;·&nbsp;
        <a href="javascript:void(0)" class="pagehead-link" onclick="removeLocation('${escapeHtml(loc)}')">remover</a>
      </td>
    </tr>`;
  }).join('');
}

function openLocationForm(nomeAtual){
  const isEdit = !!nomeAtual;
  showModal(`
    <div class="modal-head"><div class="modal-title">${isEdit ? 'Editar local' : 'Novo local'}</div><button class="modal-close" onclick="closeModal()">&times;</button></div>
    <div class="modal-body">
      <div class="form-msg erro" id="loc-erro" style="display:none;"></div>
      <form class="modal-form" id="location-form">
        <div class="field"><label>nome do local</label><input type="text" id="loc-name" placeholder="Ex: Laboratório de Informática"></div>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="closeModal()">Cancelar</button>
          <button type="submit" class="btn-primary">${isEdit ? 'Salvar' : 'Criar'}</button>
        </div>
      </form>
    </div>`);
  if(isEdit) document.getElementById('loc-name').value = nomeAtual;

  document.getElementById('location-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const nome = document.getElementById('loc-name').value.trim();
    const erroEl = document.getElementById('loc-erro');
    if(!nome){ erroEl.textContent = 'Informe o nome do local.'; erroEl.style.display = ''; return; }
    if(nome !== nomeAtual && locations.includes(nome)){ erroEl.textContent = 'Já existe um local com esse nome.'; erroEl.style.display = ''; return; }
    if(isEdit){
      locations = locations.map(l => l === nomeAtual ? nome : l);
      machines.forEach(m => { if(m.location === nomeAtual) m.location = nome; });
    } else {
      locations.push(nome);
    }
    closeModal(); renderLocations(); renderMachines();
  });
}

function removeLocation(nome){
  if(!confirm(`Remover o local "${nome}"? As máquinas vinculadas ficam sem local, mas não são apagadas.`)) return;
  locations = locations.filter(l => l !== nome);
  machines.forEach(m => { if(m.location === nome) m.location = ''; });
  renderLocations(); renderMachines();
}

document.getElementById('open-add-location').addEventListener('click', () => openLocationForm(null));
