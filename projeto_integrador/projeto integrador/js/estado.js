let machines = [];
let alerts = [];
let nextMachineId = 1;
let nextAlertId = 1;
let simTimer = null;
let timelineChart, typesChart;
let timelineBuckets = [];
let typeCounts = {};
let machineFilter = 'todas';
let alertStatusFilter = 'todos';

let locations = ['Lab de Redes', 'TI', 'Secretaria', 'Diretoria', 'Sala dos Professores', 'Biblioteca', 'Outro'];

const USERS_KEY = 'sentinela_users';
function loadUsers(){ try { return JSON.parse(localStorage.getItem(USERS_KEY)) || []; } catch(e){ return []; } }
function saveUsers(){ try { localStorage.setItem(USERS_KEY, JSON.stringify(users)); } catch(e){} }
let users = loadUsers();
let currentUser = null;
async function hashSenha(s){
  try {
    const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode('sentinela:' + s));
    return Array.from(new Uint8Array(buf)).map(b => b.toString(16).padStart(2, '0')).join('');
  } catch(e){ return 'x:' + btoa(unescape(encodeURIComponent('sentinela:' + s))); }
}
function novoUuid(){
  if(window.crypto && crypto.randomUUID) return crypto.randomUUID();
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const r = Math.random()*16|0; return (c === 'x' ? r : (r&0x3|0x8)).toString(16); });
}
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const USER_RE  = /^[A-Za-z0-9_.-]{3,50}$/;
