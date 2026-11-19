function showModal(html, wide){
  const m = document.getElementById('modal');
  m.className = 'modal' + (wide ? ' wide' : '');
  m.innerHTML = html;
  document.getElementById('modal-backdrop').classList.add('active');
}
function closeModal(){ document.getElementById('modal-backdrop').classList.remove('active'); }
document.getElementById('modal-backdrop').addEventListener('click', (e) => { if(e.target.id === 'modal-backdrop') closeModal(); });
