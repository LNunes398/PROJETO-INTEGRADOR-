# Sentinela — Sistema de Detecção de Intrusão (IDS)

Projeto integrador — IFRN Campus Ceará-Mirim, Técnico em Informática,
disciplina Programação para Internet.

## Arquivos

- **index.html** — versão só-front-end do painel (dados só na memória do
  navegador). Um arquivo só: abra direto no navegador, sem precisar de
  servidor. Útil pra apresentar a interface sem precisar configurar banco.
- **schema.sql** — desenho do banco de dados (locais, máquinas, alertas,
  usuários), pronto para importar no MySQL Workbench.
- **php/** — backend real em PHP + MySQL (login, cadastro, painel com
  gráficos, máquinas, alertas, locais e usuários gravando no banco de
  verdade). Veja a seção "Backend PHP" mais abaixo.

## Como usar (versão front-end, index.html)

Abra `index.html` e clique em **Cadastre-se** para criar a sua conta: depois do
cadastro você volta para a tela de login e entra com a conta nova. Não existe usuário pré-cadastrado
(o antigo `root` / `1234` foi removido). As contas ficam salvas no navegador.

- **Painel** — visão geral: máquinas ativas, alertas abertos, total de
  alertas, gráficos de eventos ao longo do tempo e por tipo, e os últimos
  alertas.
- **Máquinas** — ative (manualmente ou detectando o computador atual, com
  permissão), edite ou remova máquinas. Cada uma tem um local (Lab de
  Redes, TI, Secretaria, etc.) e pode ser buscada/filtrada.
- **Alertas** — histórico completo, com busca, filtro por status
  (aberto/resolvido) e exportação para CSV.
- **Sobre** — dados do projeto e dos autores.

### Detecção automática de máquina

Por segurança do navegador, não é possível ler o endereço MAC nem o IP da
rede local (192.168.x.x) automaticamente — isso vale para qualquer site,
não é uma limitação do projeto. A detecção automática traz o que o
navegador permite (sistema operacional, IP público) e você completa o
resto manualmente.

### Banco de dados

O `index.html` (versão front-end) guarda os dados só na memória do
navegador (enquanto a aba está aberta). Quem guarda os dados de verdade,
em MySQL, é o backend em **php/** — descrito abaixo.

## Backend PHP (php/)

Painel de verdade, com login, cadastro de usuário, painel com gráficos,
máquinas, alertas e locais gravados no MySQL — pronto pra rodar em
qualquer XAMPP/WAMP/servidor PHP.

### Como rodar

1. Suba o MySQL e importe o `schema.sql` (cria o banco `sentinela_ids`
   com algumas máquinas e alertas de exemplo — **não há usuário pronto**).
   - **Pelo MySQL Workbench**: File > Open SQL Script... > selecione o
     arquivo > execute o script inteiro (ícone de raio). O Workbench já
     cuida da codificação de acentos automaticamente.
   - **Pelo terminal**: use
     `mysql --default-character-set=utf8mb4 -u root -p < schema.sql`
     — o `--default-character-set=utf8mb4` evita que acentos (ç, ã, é...)
     sejam salvos errados, caso o cliente `mysql` da sua máquina use um
     charset padrão diferente.
2. Abra `php/config/db.php` e confira `host`/`usuário`/`senha` do seu
   MySQL (no XAMPP/WAMP o padrão já costuma funcionar: usuário `root`,
   senha em branco).
3. Coloque a pasta `php/` para ser servida por um servidor PHP:
   - **XAMPP/WAMP**: copie a pasta `php/` para dentro de `htdocs/`
     (ex.: `htdocs/sentinela/`) e acesse `http://localhost/sentinela/`.
   - **Ou, rapidinho, pelo terminal** (com o PHP instalado):
     `php -S localhost:8000 -t php` e acesse `http://localhost:8000/`.
4. Abra `cadastro.php` e crie a **sua conta** (nome, sobrenome, usuário,
   e-mail e senha): ao terminar o cadastro você volta para a tela de login. Não
   existe mais o usuário `root`: cada pessoa tem a sua conta e entra com
   usuário **ou** e-mail. Se você já tinha o banco da versão
   anterior, veja o bloco **MIGRAÇÃO** no final do `schema.sql`.

### Páginas

| Arquivo | Função |
|---|---|
| `login.php` | autenticação |
| `cadastro.php` | criar a sua conta (nome, usuário, e-mail, senha) |
| `logout.php` | encerrar sessão |
| `dashboard.php` | painel — estatísticas, gráficos (alertas por dia e por tipo) e últimos alertas |
| `maquinas.php` / `maquina_form.php` / `maquina_excluir.php` | listar, ativar/editar e remover máquinas |
| `alertas.php` / `alerta_status.php` / `alertas_exportar.php` | listar, buscar, filtrar, marcar como resolvido e exportar CSV |
| `locais.php` / `local_form.php` / `local_excluir.php` | listar, criar, editar e remover locais/setores |
| `usuarios.php` | menu **Usuários**: a pessoa escolhe **Visualizar**, **Editar** ou **Excluir conta**. Visualizar mostra os dados sem permitir alteração; Editar abre o formulário (ID do usuário, nome, sobrenome, e-mail, nova senha); Excluir pede confirmação com a senha. Sempre mostra a mensagem de segurança |
| `notificacoes.php` / `notificacao_acao.php` / `notificacoes_contagem.php` | central de notificações: lista, marcar como lida, apagar; e o endpoint JSON que atualiza o contador a cada 15 s |
| `sobre.php` | dados do projeto |

Os gráficos do painel usam [Chart.js](https://www.chartjs.org/) (via CDN)
com os dados vindos direto do banco (contagem de alertas nos últimos 7
dias e por tipo de evento).

Todas as consultas usam **PDO com prepared statements** (proteção contra
SQL injection) e as senhas são guardadas com **hash bcrypt**
(`password_hash`/`password_verify`).


## Visual (design system v3 — dark + laranja)

O estilo fica em um único arquivo (`php/css/style.css`; o `index.html` leva
a mesma folha embutida). Principais pontos:

- **Tema escuro** com brilho laranja no topo, grade sutil e destaques em serifa.
- Cores centralizadas em variáveis CSS (`--accent`, `--surface`, `--border`...);
  trocar a cor da marca é mudar uma linha.
- Os gráficos (Chart.js) leem essas variáveis, então acompanham o tema.
- Navbar translúcida com marca, indicador "ao vivo", cards com elevação,
  tabelas, badges, modais e toasts refinados, e animações discretas
  (desativadas para quem prefere menos movimento).
- Layout responsivo para tablet e celular.


## Contas e notificações (como funciona)

- **Cada pessoa tem a sua conta.** Os dados do sistema (máquinas, alertas
  e locais) ficam no banco e são **compartilhados**: todos veem e alteram
  o mesmo conjunto.
- **Toda alteração gera notificação para todos os usuários**: máquina
  ativada/editada/removida, alerta resolvido/reaberto, local criado/
  renomeado/removido, conta criada ou removida e dados de conta
  atualizados. Cada usuário recebe a sua própria cópia (tabela
  `notifications`), então ler ou apagar a sua não afeta a dos outros.
- **Alertas novos do sensor** inseridos direto na tabela `alerts` também
  notificam todos, por meio do gatilho `trg_alerts_notificar` do banco.
- **Ciclo de vida:** a notificação aparece como *não lida* (contador na
  barra de navegação e aviso no painel) → o usuário a abre e marca como
  *lida* → só então aparece o botão **Apagar**, que remove de forma
  permanente. Uma notificação não lida não pode ser apagada.
- **Excluir a conta** apaga o acesso e as notificações da pessoa; as
  notificações que ela causou para os outros continuam, com o nome dela.
- Formulários que alteram dados da conta e das notificações usam token
  CSRF, e as senhas ficam salvas como hash (`password_hash`).

> O `index.html` é a demonstração visual: tem o mesmo fluxo de contas
> (cadastro que volta para o login; tela **Usuários** com escolha entre
> visualizar, editar ou excluir a própria conta, sempre com a mensagem de segurança), mas guarda as contas só no
> navegador (localStorage, senha em hash) e **não** usa o banco. Para dados
> compartilhados entre usuários e notificações, use a versão em `php/`.


## Novidades (ataques, hostname e login)

- **Tabela de alertas**: a coluna passou a se chamar **Ataque** (nome do ataque) e a contagem de SYN ficou em **Pacotes**.
- **Modal de detalhes do alerta**: mostra o **ataque detectado** e tem o botão **Entender este ataque**, com o que é, o comportamento normal (SYN, SYN-ACK, ACK) x o do ataque (SYN, SYN, SYN...) e o que foi encontrado naquele caso.
- **Guia de ataques**: botão na tela de Alertas que explica todos os tipos de ataque.
- **Máquinas**: agora guardam **IP, MAC e Hostname**. Na versão PHP o botão *Detectar este computador* usa o servidor (IP, hostname por DNS reverso e MAC pela tabela ARP, que só existe para computadores da mesma rede local). Na versão `index.html`, o navegador não lê MAC nem hostname: o IP local só vem em alguns navegadores e o resto é preenchido manualmente.
- **Cadastro**: depois de criar a conta, a pessoa vai para a **tela de login**.
- Banco já existente: rode `ALTER TABLE machines ADD COLUMN hostname VARCHAR(255) NULL AFTER ip;`.
