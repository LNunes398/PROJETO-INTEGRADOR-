@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul
title Simulador Didatico de Ataques de Rede - Sentinela
color 0B

rem ============================================================
rem  CONFIGURACAO
rem  Usuario, senha, banco e porta sao lidos de php\config\db.php.
rem  So altere aqui se quiser forcar algum valor.
rem ============================================================
set "RAIZ=%~dp0..\"
set "DB_PHP=%RAIZ%php\config\db.php"
set "SCHEMA=%RAIZ%schema.sql"
set "DB_HOST=localhost"
set "DB_PORT=3306"
set "DB_NAME=sentinela_ids"
set "DB_USER=root"
set "DB_PASS="
set "MYSQL_EXE="
rem 1 = so registra alerta se o PC responder ao ping. 0 = registra sempre.
set "EXIGIR_PING=1"
rem 1 = pede usuario e senha do painel Sentinela. 0 = pula o login.
set "EXIGIR_LOGIN=1"

set "ERRO=%TEMP%\simulador_erro.txt"
set "SAIDA=%TEMP%\simulador_saida.txt"
set "LISTA=%TEMP%\simulador_maquinas.txt"
set "PING_TMP=%TEMP%\simulador_ping.txt"
set "TENT_IMPORT=0"
set "TESTE=%TEMP%\simulador_teste.txt"
set "RE_USER=[A-Za-z0-9_.@+-]*"
set "RE_NUM=[0-9][0-9]*"
set "RE_TXT=[A-Za-z0-9._: -]*"
set "RE_IP=[0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*"
set "RE_MAC=[0-9A-Fa-f][0-9A-Fa-f]:[0-9A-Fa-f][0-9A-Fa-f]:[0-9A-Fa-f][0-9A-Fa-f]:[0-9A-Fa-f][0-9A-Fa-f]:[0-9A-Fa-f][0-9A-Fa-f]:[0-9A-Fa-f][0-9A-Fa-f]"
set "SIM_USER_CURTO="

if exist "%DB_PHP%" (
    for %%K in (DB_HOST DB_PORT DB_NAME DB_USER DB_PASS) do call :LER_CFG %%K
) else (
    echo Aviso: php\config\db.php nao foi encontrado ao lado deste arquivo.
    echo Usando valores padrao: !DB_USER!@!DB_HOST!:!DB_PORT! banco !DB_NAME!
    echo.
)

rem ============================================================
rem  LOCALIZA O MYSQL
rem ============================================================
:ACHAR_MYSQL
if defined MYSQL_EXE if exist "!MYSQL_EXE!" goto CONECTAR
set "MYSQL_EXE="
for /f "delims=" %%P in ('where mysql.exe 2^>nul') do if not defined MYSQL_EXE set "MYSQL_EXE=%%P"
for %%L in (C D E F G H) do if not defined MYSQL_EXE if exist "%%L:\xampp\mysql\bin\mysql.exe" set "MYSQL_EXE=%%L:\xampp\mysql\bin\mysql.exe"
for %%L in (C D E F G H) do for /d %%D in ("%%L:\wamp64\bin\mysql\mysql*") do if not defined MYSQL_EXE if exist "%%D\bin\mysql.exe" set "MYSQL_EXE=%%D\bin\mysql.exe"
for %%L in (C D E F G H) do for /d %%D in ("%%L:\wamp\bin\mysql\mysql*") do if not defined MYSQL_EXE if exist "%%D\bin\mysql.exe" set "MYSQL_EXE=%%D\bin\mysql.exe"
for %%L in (C D E F G H) do for /d %%D in ("%%L:\laragon\bin\mysql\*") do if not defined MYSQL_EXE if exist "%%D\bin\mysql.exe" set "MYSQL_EXE=%%D\bin\mysql.exe"
for /d %%D in ("C:\Program Files\MySQL\MySQL Server*") do if not defined MYSQL_EXE if exist "%%D\bin\mysql.exe" set "MYSQL_EXE=%%D\bin\mysql.exe"
for /d %%D in ("C:\Program Files\MariaDB*") do if not defined MYSQL_EXE if exist "%%D\bin\mysql.exe" set "MYSQL_EXE=%%D\bin\mysql.exe"
if not defined MYSQL_EXE (
    echo.
    echo mysql.exe nao foi encontrado automaticamente.
    echo No XAMPP ele fica em C:\xampp\mysql\bin\mysql.exe
    set /p "MYSQL_EXE=Caminho completo do mysql.exe (ENTER para sair): "
    if not defined MYSQL_EXE goto FIM
    set "MYSQL_EXE=!MYSQL_EXE:"=!"
    if not exist "!MYSQL_EXE!" (
        echo Arquivo nao encontrado.
        set "MYSQL_EXE="
        goto ACHAR_MYSQL
    )
)

rem ============================================================
rem  CONECTA NO SERVIDOR MYSQL
rem ============================================================
:CONECTAR
set "DB_FLAG="
set "PASS_ARG="
if defined DB_PASS set PASS_ARG="--password=!DB_PASS!"
set "MYSQL_BASE=-h !DB_HOST! -P !DB_PORT! -u !DB_USER! --default-character-set=utf8mb4"
set "SQL=SELECT 1"
call :SQL
if not errorlevel 1 goto BANCO_CHECK

cls
echo Nao foi possivel conectar ao servidor MySQL em !DB_HOST!:!DB_PORT! com o usuario !DB_USER!.
echo.
type "%ERRO%"
echo.
echo Possiveis causas:
echo  - O MySQL nao esta ligado: abra o XAMPP e clique em Start no MySQL.
echo  - Usuario ou senha diferentes: confira php\config\db.php.
echo  - A porta e outra, por exemplo 3307.
echo.
echo [R] Tentar de novo   [S] Digitar a senha   [P] Digitar a porta   [ENTER] Sair
set "OP="
set /p "OP=Opcao: "
if /i "!OP!"=="R" goto CONECTAR
if /i "!OP!"=="S" (
    set "DB_PASS="
    set /p "DB_PASS=Senha do MySQL: "
    goto CONECTAR
)
if /i "!OP!"=="P" (
    set /p "DB_PORT=Porta do MySQL: "
    goto CONECTAR
)
goto FIM

rem ============================================================
rem  CONFERE SE O BANCO DO PROJETO EXISTE
rem ============================================================
:BANCO_CHECK
set "SQL=SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='!DB_NAME!'"
call :SQL
if "!SQL_RES!"=="1" goto TABELAS_CHECK

cls
echo O banco "!DB_NAME!" NAO existe neste MySQL.
echo.
echo [1] Criar o banco agora, importando o schema.sql do projeto
echo [2] Escolher outro banco que ja exista
echo [3] Sair
echo.
set "OP="
set /p "OP=Opcao: "
if "!OP!"=="1" (
    call :IMPORTAR_SCHEMA
    goto BANCO_CHECK
)
if "!OP!"=="2" (
    echo.
    echo Bancos existentes:
    "%MYSQL_EXE%" !MYSQL_BASE! !PASS_ARG! -N -B -e "SHOW DATABASES" 2>nul
    echo.
    set "NOVO_DB="
    set /p "NOVO_DB=Nome do banco do projeto: "
    if defined NOVO_DB set "DB_NAME=!NOVO_DB!"
    goto BANCO_CHECK
)
goto FIM

rem ============================================================
rem  CONFERE TABELAS, COLUNAS E GATILHO (CORRIGE O QUE FALTAR)
rem ============================================================
:TABELAS_CHECK
set "DB_FLAG=-D !DB_NAME!"
set "FALTAM="
for %%T in (locations users machines alerts notifications) do (
    set "SQL=SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='!DB_NAME!' AND table_name='%%T'"
    call :SQL
    if not "!SQL_RES!"=="1" set "FALTAM=!FALTAM! %%T"
)
if not defined FALTAM goto COLUNAS_CHECK

echo.
echo O banco existe, mas faltam tabelas do projeto:!FALTAM!
if /i not "!DB_NAME!"=="sentinela_ids" (
    echo O banco escolhido nao e o sentinela_ids, entao nao da para importar o schema nele.
    goto FIM
)
set /a TENT_IMPORT+=1
if !TENT_IMPORT! GTR 2 (
    echo Nao consegui criar as tabelas. Veja a mensagem de erro acima.
    goto FIM
)
set "RESP="
set /p "RESP=Importar o schema.sql agora para criar o que falta? (S/N): "
if /i "!RESP!"=="S" (
    call :IMPORTAR_SCHEMA
    goto TABELAS_CHECK
)
goto FIM

:COLUNAS_CHECK
set "SQL=SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='!DB_NAME!' AND table_name='machines' AND column_name='hostname'"
call :SQL
if "!SQL_RES!"=="0" (
    echo Atualizando o banco: adicionando machines.hostname...
    set "SQL=ALTER TABLE machines ADD COLUMN hostname VARCHAR(255) NULL AFTER ip"
    call :SQL
)
set "SQL=SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='!DB_NAME!' AND table_name='alerts' AND column_name='status'"
call :SQL
if "!SQL_RES!"=="0" (
    echo Atualizando o banco: adicionando alerts.status...
    set "SQL=ALTER TABLE alerts ADD COLUMN status ENUM('aberto','resolvido') NOT NULL DEFAULT 'aberto' AFTER syn_count"
    call :SQL
)
set "SQL=SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='!DB_NAME!' AND trigger_name='trg_alerts_notificar'"
call :SQL
if "!SQL_RES!"=="0" call :CRIAR_GATILHO

rem ============================================================
rem  LOGIN: mesmo usuario e senha do painel Sentinela
rem ============================================================
:LOGIN
set "SIM_USER="
set "U_NOME="
if "%EXIGIR_LOGIN%"=="0" goto SALA
set "PHP_EXE="
for /f "delims=" %%P in ('where php.exe 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"
for %%L in (C D E F G H) do if not defined PHP_EXE if exist "%%L:\xampp\php\php.exe" set "PHP_EXE=%%L:\xampp\php\php.exe"
for %%L in (C D E F G H) do for /d %%D in ("%%L:\wamp64\bin\php\php*") do if not defined PHP_EXE if exist "%%D\php.exe" set "PHP_EXE=%%D\php.exe"
for %%L in (C D E F G H) do for /d %%D in ("%%L:\laragon\bin\php\php*") do if not defined PHP_EXE if exist "%%D\php.exe" set "PHP_EXE=%%D\php.exe"
if not defined PHP_EXE (
    echo.
    echo php.exe nao foi encontrado. Ele e usado para conferir a senha do painel.
    echo No XAMPP ele fica em C:\xampp\php\php.exe
    set /p "PHP_EXE=Caminho completo do php.exe (ENTER para sair): "
    if not defined PHP_EXE goto FIM
    set "PHP_EXE=!PHP_EXE:"=!"
    if not exist "!PHP_EXE!" (
        echo Arquivo nao encontrado.
        goto FIM
    )
)
set "TENT_LOGIN=0"

:LOGIN_PEDIR
cls
echo ============================================
echo        SIMULADOR DE ATAQUES DE REDE
echo ============================================
echo Entre com o mesmo usuario e senha do painel Sentinela.
echo.
set "SIM_USER="
set /p "SIM_USER=Usuario ou e-mail: "
if not defined SIM_USER goto LOGIN_PEDIR
call :TESTA SIM_USER RE_USER
if errorlevel 1 (
    echo Usuario invalido.
    pause
    goto LOGIN_PEDIR
)
call :LER_SENHA

set "SIM_HASH="
set "U_ID="
set "U_NOME="
set "LOGIN_OK=0"
set "SQL=SELECT CONCAT_WS('|', id, password, name) FROM users WHERE username='!SIM_USER!' OR email='!SIM_USER!' LIMIT 1"
call :SQL
if defined SQL_RES (
    for /f "tokens=1-3 delims=|" %%A in ("!SQL_RES!") do (
        set "U_ID=%%A"
        set "SIM_HASH=%%B"
        set "U_NOME=%%C"
    )
)
if defined SIM_HASH (
    "!PHP_EXE!" -r "exit(password_verify((string)getenv('SIM_SENHA'),(string)getenv('SIM_HASH'))?0:1);" >nul 2>&1
    if not errorlevel 1 set "LOGIN_OK=1"
)
set "SIM_SENHA="
set "SIM_HASH="

if "!LOGIN_OK!"=="1" (
    set "SIM_USER_CURTO=!SIM_USER:~0,50!"
    goto SALA
)
set /a TENT_LOGIN+=1
echo.
echo Usuario ou senha invalidos.
if !TENT_LOGIN! GEQ 3 (
    echo Numero maximo de tentativas atingido.
    goto FIM
)
pause
goto LOGIN_PEDIR

rem ============================================================
rem  SALA: escolhe o local; se houver so uma sala, entra direto
rem ============================================================
:SALA
set "FILTRO_SQL="
set "SALA_NOME=todas as salas"
"%MYSQL_EXE%" !MYSQL_BASE! !PASS_ARG! !DB_FLAG! -N -B -e "SELECT CONCAT_WS('|', COALESCE(l.id,0), COALESCE(l.name,'Sem local'), COUNT(m.id)) FROM machines m LEFT JOIN locations l ON m.location_id = l.id GROUP BY l.id, l.name ORDER BY (l.id IS NULL), l.name;" > "%LISTA%" 2>"%ERRO%"
if errorlevel 1 (
    echo Erro ao ler as salas:
    type "%ERRO%"
    goto FIM
)
set "TOTAL_SALAS=0"
for /f "usebackq tokens=1-3 delims=|" %%A in ("%LISTA%") do (
    set /a TOTAL_SALAS+=1
    set "R_ID!TOTAL_SALAS!=%%A"
    set "R_NOME!TOTAL_SALAS!=%%B"
    set "R_QTD!TOTAL_SALAS!=%%C"
)
del "%LISTA%" >nul 2>&1
if %TOTAL_SALAS% EQU 0 goto INICIO
if %TOTAL_SALAS% EQU 1 (
    set "SALA_SEL=1"
    goto SALA_APLICAR
)

cls
echo ============================================
echo        SIMULADOR DE ATAQUES DE REDE
echo ============================================
if defined U_NOME echo Usuario: !U_NOME!
echo.
echo SALAS COM COMPUTADORES
echo.
echo  N   SALA                           PCs
echo ---- ------------------------------ ----
for /l %%N in (1,1,%TOTAL_SALAS%) do (
    set "L_N=%%N    "
    set "L_SALA=!R_NOME%%N!                              "
    echo !L_N:~0,4! !L_SALA:~0,30! !R_QTD%%N!
)

:SALA_PEDIR
echo.
set "SALA_SEL="
set /p "SALA_SEL=Numero da sala (T = todas, C = cadastrar PC): "
if not defined SALA_SEL goto SALA_PEDIR
if /i "!SALA_SEL!"=="T" goto INICIO
if /i "!SALA_SEL!"=="C" (
    call :CADASTRAR
    goto SALA
)
call :TESTA SALA_SEL RE_NUM
if errorlevel 1 (
    echo Digite um numero da lista, T ou C.
    goto SALA_PEDIR
)
set "SALA_LIMPA="
for /f "tokens=* delims=0" %%Z in ("!SALA_SEL!") do set "SALA_LIMPA=%%Z"
if not defined SALA_LIMPA (
    echo Numero fora da lista.
    goto SALA_PEDIR
)
if not "!SALA_LIMPA:~3,1!"=="" (
    echo Numero fora da lista.
    goto SALA_PEDIR
)
if !SALA_LIMPA! GTR %TOTAL_SALAS% (
    echo Numero fora da lista.
    goto SALA_PEDIR
)
set "SALA_SEL=!SALA_LIMPA!"

:SALA_APLICAR
set "SALA_NOME=!R_NOME%SALA_SEL%!"
set "SALA_ID=!R_ID%SALA_SEL%!"
if "!SALA_ID!"=="0" (
    set "FILTRO_SQL=WHERE m.location_id IS NULL"
) else (
    set "FILTRO_SQL=WHERE m.location_id = !SALA_ID!"
)

rem ============================================================
rem  INICIO: RECUPERA OS PCs DA AREA DE MAQUINAS
rem ============================================================
:INICIO
cls
echo ============================================
echo        SIMULADOR DE ATAQUES DE REDE
echo ============================================
echo Modo didatico: nao realiza ataques reais.
echo Os resultados aparecem na aba Alertas do Sentinela.
echo Banco: !DB_NAME! em !DB_HOST!:!DB_PORT!
if defined U_NOME echo Usuario: !U_NOME!
echo Sala: !SALA_NOME!
echo.

"%MYSQL_EXE%" !MYSQL_BASE! !PASS_ARG! !DB_FLAG! -N -B -e "SELECT CONCAT_WS('|', m.id, m.name, m.ip, COALESCE(NULLIF(m.hostname,''),'-'), COALESCE(NULLIF(m.so,''),'-'), COALESCE(l.name,'-'), m.status) FROM machines m LEFT JOIN locations l ON m.location_id = l.id !FILTRO_SQL! ORDER BY m.id;" > "%LISTA%" 2>"%ERRO%"
if errorlevel 1 (
    echo Erro ao ler a tabela de maquinas:
    type "%ERRO%"
    goto FIM
)

set "TOTAL=0"
for /f "usebackq tokens=1-7 delims=|" %%A in ("%LISTA%") do (
    set /a TOTAL+=1
    set "M_ID!TOTAL!=%%A"
    set "M_NOME!TOTAL!=%%B"
    set "M_IP!TOTAL!=%%C"
    set "M_HOST!TOTAL!=%%D"
    set "M_SO!TOTAL!=%%E"
    set "M_LOCAL!TOTAL!=%%F"
    set "M_STATUS!TOTAL!=%%G"
)
del "%LISTA%" >nul 2>&1

if %TOTAL% EQU 0 (
    echo Nenhuma maquina encontrada nesta selecao da area de Maquinas.
    echo.
    set "RESP="
    set /p "RESP=Cadastrar uma maquina agora? (S/N): "
    if /i "!RESP!"=="S" (
        call :CADASTRAR
        goto INICIO
    )
    goto FIM
)

echo MAQUINAS CADASTRADAS: %TOTAL%
echo.
echo  N   NOME                 IP               HOSTNAME         SO               LOCAL            STATUS
echo ---- -------------------- ---------------- ---------------- ---------------- ---------------- --------
for /l %%N in (1,1,%TOTAL%) do (
    set "L_N=%%N    "
    set "L_NOME=!M_NOME%%N!                    "
    set "L_IP=!M_IP%%N!                "
    set "L_HOST=!M_HOST%%N!                "
    set "L_SO=!M_SO%%N!                "
    set "L_LOCAL=!M_LOCAL%%N!                "
    echo !L_N:~0,4! !L_NOME:~0,20! !L_IP:~0,16! !L_HOST:~0,16! !L_SO:~0,16! !L_LOCAL:~0,16! !M_STATUS%%N!
)

:ESCOLHER
echo.
set "SEL="
set /p "SEL=Numero do PC (T = todos desta lista, C = cadastrar, V = voltar as salas): "
if not defined SEL goto ESCOLHER
if /i "!SEL!"=="T" (
    set "ALVO_INI=1"
    set "ALVO_FIM=%TOTAL%"
    goto GRUPO
)
if /i "!SEL!"=="C" (
    call :CADASTRAR
    goto INICIO
)
if /i "!SEL!"=="V" goto SALA
call :TESTA SEL RE_NUM
if errorlevel 1 (
    echo Digite um numero da lista, T, C ou V.
    goto ESCOLHER
)
set "SEL_LIMPO="
for /f "tokens=* delims=0" %%Z in ("!SEL!") do set "SEL_LIMPO=%%Z"
if not defined SEL_LIMPO (
    echo Numero fora da lista.
    goto ESCOLHER
)
if not "!SEL_LIMPO:~4,1!"=="" (
    echo Numero fora da lista.
    goto ESCOLHER
)
if !SEL_LIMPO! GTR %TOTAL% (
    echo Numero fora da lista.
    goto ESCOLHER
)
set "ALVO_INI=!SEL_LIMPO!"
set "ALVO_FIM=!SEL_LIMPO!"

:GRUPO
echo.
echo GRUPOS DE ATAQUE
echo 1 - Negacao de Servico (DoS)
set "GRUPO=1"
set /p "GRUPO=Escolha o grupo [1]: "
if not "!GRUPO!"=="1" (
    echo Grupo nao reconhecido.
    pause
    goto INICIO
)

echo.
echo ATAQUES DISPONIVEIS - Negacao de Servico
echo 1 - SYN Flood (simulacao)
set "ATAQUE=1"
set /p "ATAQUE=Escolha o ataque [1]: "
if not "!ATAQUE!"=="1" (
    echo Ataque nao reconhecido.
    pause
    goto INICIO
)

echo.
echo SYN FLOOD - SIMULACAO DIDATICA
echo O SYN Flood tenta sobrecarregar um servico com solicitacoes TCP.
echo Nenhum pacote SYN sera enviado ao alvo.
echo.

:QUANTIDADE
set "QTD=1000"
set /p "QTD=Quantos pacotes SYN deseja simular [1000]? "
call :TESTA QTD RE_NUM
if errorlevel 1 (
    echo Digite apenas numeros inteiros.
    goto QUANTIDADE
)
set "QTD_LIMPO="
for /f "tokens=* delims=0" %%Z in ("!QTD!") do set "QTD_LIMPO=%%Z"
if not defined QTD_LIMPO (
    echo A quantidade deve ser maior que zero.
    goto QUANTIDADE
)
set "QTD=!QTD_LIMPO!"
if not "!QTD:~7,1!"=="" (
    echo Limite: 1000000 pacotes simulados.
    goto QUANTIDADE
)
if !QTD! GTR 1000000 (
    echo Limite: 1000000 pacotes simulados.
    goto QUANTIDADE
)

:PORTA
set "PORTA=80"
set /p "PORTA=Porta de destino simulada [80]: "
call :TESTA PORTA RE_NUM
if errorlevel 1 (
    echo Digite apenas numeros.
    goto PORTA
)
set "PORTA_LIMPA="
for /f "tokens=* delims=0" %%Z in ("!PORTA!") do set "PORTA_LIMPA=%%Z"
if not defined PORTA_LIMPA (
    echo Porta deve ficar entre 1 e 65535.
    goto PORTA
)
if not "!PORTA_LIMPA:~5,1!"=="" (
    echo Porta deve ficar entre 1 e 65535.
    goto PORTA
)
if !PORTA_LIMPA! GTR 65535 (
    echo Porta deve ficar entre 1 e 65535.
    goto PORTA
)
set "PORTA=!PORTA_LIMPA!"

rem IP deste computador, usado como origem simulada do ataque
set "ORIGEM_IP="
for /f "tokens=2 delims=:" %%I in ('ipconfig ^| findstr /i "IPv4"') do (
    set "IPX=%%I"
    set "IPX=!IPX: =!"
    if not defined ORIGEM_IP if not "!IPX:~0,8!"=="169.254." set "ORIGEM_IP=!IPX!"
)
if not defined ORIGEM_IP set "ORIGEM_IP=127.0.0.1"

set /a RESPOSTAS=(QTD*80)/100
set /a SEM_RESPOSTA=QTD-RESPOSTAS
set "GRAVADOS=0"
set "SEM_PING=0"
set "FALHAS=0"

echo.
echo Simulando contra os alvos escolhidos...
for /l %%N in (%ALVO_INI%,1,%ALVO_FIM%) do call :SIMULAR_UM %%N

echo.
echo ============================================
echo          RESULTADO DA SIMULACAO
echo ============================================
echo Origem simulada: %ORIGEM_IP%
echo Grupo: Negacao de Servico (DoS)
echo Ataque: SYN Flood   Porta: %PORTA%
echo Pacotes SYN simulados por alvo: %QTD%
echo Respostas simuladas: %RESPOSTAS%
echo Sem resposta no modelo: %SEM_RESPOSTA%
echo.
echo Alertas gravados na aba Alertas: %GRAVADOS%
if not "%SEM_PING%"=="0" echo Alvos sem resposta ao ping, ignorados: %SEM_PING%
if not "%FALHAS%"=="0" echo Falhas ao gravar no banco: %FALHAS%
echo.
echo IMPORTANTE:
echo - Somente os pings de teste foram enviados pela rede.
echo - Os numeros do SYN Flood sao ilustrativos.
echo - Eles nao representam respostas reais dos computadores alvo.
echo ============================================
echo.

set "OUTRA="
set /p "OUTRA=Fazer outra simulacao? (S/N): "
if /i "!OUTRA!"=="S" goto SALA
goto FIM

rem ============================================================
rem  SUBROTINA: roda uma consulta. Entrada: SQL. Saida: SQL_RES
rem ============================================================
:SQL
set "SQL_RES="
"%MYSQL_EXE%" !MYSQL_BASE! !PASS_ARG! !DB_FLAG! -N -B -e "!SQL!" > "%SAIDA%" 2> "%ERRO%"
set "SQL_ERR=!errorlevel!"
if exist "%SAIDA%" set /p SQL_RES=<"%SAIDA%"
exit /b !SQL_ERR!

rem ============================================================
rem  SUBROTINA: le a senha sem mostrar na tela (usa o PowerShell)
rem ============================================================
:LER_SENHA
set "SIM_SENHA="
del "%TEMP%\simulador_pw.txt" >nul 2>&1
powershell -NoProfile -Command "$s = Read-Host 'Senha' -AsSecureString; $b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($s); [IO.File]::WriteAllText($env:TEMP + '\simulador_pw.txt', [Runtime.InteropServices.Marshal]::PtrToStringBSTR($b))" 2>nul
if exist "%TEMP%\simulador_pw.txt" (
    set /p SIM_SENHA=<"%TEMP%\simulador_pw.txt"
    del "%TEMP%\simulador_pw.txt" >nul 2>&1
) else (
    set /p "SIM_SENHA=Senha: "
)
exit /b 0

rem ============================================================
rem  SUBROTINA: le uma variavel de php\config\db.php
rem ============================================================
:LER_CFG
for /f "usebackq tokens=2 delims='" %%V in (`findstr /b /c:"$%~1 " "%DB_PHP%"`) do set "%~1=%%V"
if "!%~1!"==";" set "%~1="
exit /b 0

rem ============================================================
rem  SUBROTINA: importa o schema.sql (cria banco, tabelas e gatilho)
rem ============================================================
:IMPORTAR_SCHEMA
if not exist "!SCHEMA!" (
    echo.
    echo schema.sql nao foi encontrado em: !SCHEMA!
    set "SCHEMA="
    set /p "SCHEMA=Caminho completo do schema.sql (ENTER cancela): "
    if not defined SCHEMA exit /b 1
    set "SCHEMA=!SCHEMA:"=!"
)
if not exist "!SCHEMA!" (
    echo Arquivo nao encontrado.
    set "SCHEMA=%RAIZ%schema.sql"
    exit /b 1
)
echo.
echo Importando schema.sql...
"%MYSQL_EXE%" !MYSQL_BASE! !PASS_ARG! < "!SCHEMA!" 2> "%ERRO%"
if errorlevel 1 (
    echo ERRO ao importar o schema:
    type "%ERRO%"
    pause
    exit /b 1
)
echo Schema importado com sucesso.
exit /b 0

rem ============================================================
rem  SUBROTINA: cria o gatilho que notifica os usuarios a cada alerta
rem ============================================================
:CRIAR_GATILHO
echo Atualizando o banco: criando o gatilho de notificacoes...
set "SQL=CREATE TRIGGER trg_alerts_notificar AFTER INSERT ON alerts FOR EACH ROW INSERT INTO notifications (user_id, type, message, link) SELECT u.id, 'alerta', CONCAT('Novo alerta em ', COALESCE((SELECT m.name FROM machines m WHERE m.id = NEW.machine_id), 'maquina removida'), ': ', NEW.type), 'alertas.php' FROM users u"
call :SQL
if errorlevel 1 (
    echo Nao foi possivel criar o gatilho. Os alertas serao gravados, mas sem notificacao automatica.
    type "%ERRO%"
)
exit /b 0

rem ============================================================
rem  SUBROTINA: confere se uma variavel casa com um padrao.
rem  Entrada: nome da variavel e nome da variavel com o padrao.
rem ============================================================
:TESTA
>"%TESTE%" echo(!%~1!
findstr /r /x /c:"!%~2!" "%TESTE%" >nul
exit /b %errorlevel%

rem ============================================================
rem  SUBROTINA: valida texto simples. Entrada: nome da variavel
rem ============================================================
:SEGURO
call :TESTA %~1 RE_TXT
exit /b %errorlevel%

rem ============================================================
rem  SUBROTINA: cadastra uma maquina direto no banco
rem ============================================================
:CADASTRAR
echo.
echo ---------- CADASTRAR NOVA MAQUINA ----------
echo Use apenas letras sem acento, numeros, ponto, hifen, dois pontos e espaco.

:CAD_NOME
set "C_NOME="
set /p "C_NOME=Nome da maquina (ENTER cancela): "
if not defined C_NOME exit /b 0
call :SEGURO C_NOME
if errorlevel 1 (
    echo Caracteres invalidos no nome.
    goto CAD_NOME
)
if not "!C_NOME:~100,1!"=="" (
    echo O nome pode ter no maximo 100 caracteres.
    goto CAD_NOME
)

:CAD_IP
set "C_IP="
set /p "C_IP=IP da maquina, ex 192.168.0.10: "
if not defined C_IP goto CAD_IP
call :TESTA C_IP RE_IP
if errorlevel 1 (
    echo IP invalido.
    goto CAD_IP
)
if not "!C_IP:~15,1!"=="" (
    echo IP invalido.
    goto CAD_IP
)
set "IP_OK=1"
for /f "tokens=1-4 delims=." %%a in ("!C_IP!") do (
    for %%o in (%%a %%b %%c %%d) do (
        set "OCT=%%o"
        if not "!OCT:~3,1!"=="" set "IP_OK=0"
        if !OCT! GTR 255 set "IP_OK=0"
    )
)
if "!IP_OK!"=="0" (
    echo IP invalido. Cada parte vai de 0 a 255.
    goto CAD_IP
)
set "SQL=SELECT COUNT(*) FROM machines WHERE ip='!C_IP!'"
call :SQL
if not "!SQL_RES!"=="0" (
    echo Ja existe uma maquina cadastrada com esse IP.
    goto CAD_IP
)

:CAD_HOST
set "C_HOST="
set /p "C_HOST=Hostname (opcional): "
if defined C_HOST (
    call :SEGURO C_HOST
    if errorlevel 1 (
        echo Caracteres invalidos no hostname.
        goto CAD_HOST
    )
)

:CAD_MAC
set "C_MAC="
set /p "C_MAC=MAC no formato 08:00:27:1A:2B:3C (opcional): "
if defined C_MAC (
    call :TESTA C_MAC RE_MAC
    if errorlevel 1 (
        echo MAC invalido.
        goto CAD_MAC
    )
)

:CAD_SO
set "C_SO="
set /p "C_SO=Sistema operacional (opcional): "
if defined C_SO (
    call :SEGURO C_SO
    if errorlevel 1 (
        echo Caracteres invalidos no sistema operacional.
        goto CAD_SO
    )
)

echo.
echo Locais disponiveis:
"%MYSQL_EXE%" !MYSQL_BASE! !PASS_ARG! !DB_FLAG! -N -B -e "SELECT CONCAT(id, ' - ', name) FROM locations ORDER BY name" 2>nul
:CAD_LOCAL
set "C_LOC="
set /p "C_LOC=Numero do local (ENTER = sem local): "
set "LOC_SQL=NULL"
if defined C_LOC (
    call :TESTA C_LOC RE_NUM
    if errorlevel 1 (
        echo Digite apenas o numero do local.
        goto CAD_LOCAL
    )
    set "SQL=SELECT COUNT(*) FROM locations WHERE id=!C_LOC!"
    call :SQL
    if not "!SQL_RES!"=="1" (
        echo Esse local nao existe.
        goto CAD_LOCAL
    )
    set "LOC_SQL=!C_LOC!"
)

set "SQL=INSERT INTO machines (name, ip, hostname, mac, so, location_id, status) VALUES ('!C_NOME!', '!C_IP!', NULLIF('!C_HOST!',''), NULLIF('!C_MAC!',''), NULLIF('!C_SO!',''), !LOC_SQL!, 'online')"
call :SQL
if errorlevel 1 (
    echo ERRO ao cadastrar a maquina:
    type "%ERRO%"
) else (
    echo Maquina !C_NOME! cadastrada com sucesso.
)
pause
exit /b 0

rem ============================================================
rem  SUBROTINA: simula e registra o alerta de UMA maquina
rem ============================================================
:SIMULAR_UM
set "N=%~1"
set "ID=!M_ID%N%!"
set "NOME=!M_NOME%N%!"
set "DEST=!M_IP%N%!"
echo.
echo -- Alvo: !NOME! [!DEST!]
if "%EXIGIR_PING%"=="0" goto GRAVAR_ALERTA

echo    Testando conectividade, 4 pings...
ping -n 4 -w 1000 "!DEST!" > "%PING_TMP%" 2>&1
findstr /i "TTL=" "%PING_TMP%" >nul
if errorlevel 1 (
    echo    ALVO NAO DISPONIVEL: sem resposta aos pings. Alerta nao registrado.
    set /a SEM_PING+=1
    del "%PING_TMP%" >nul 2>&1
    goto :eof
)
findstr /i "Packets: Pacotes:" "%PING_TMP%"
del "%PING_TMP%" >nul 2>&1

:GRAVAR_ALERTA
set "SQL=INSERT INTO alerts (machine_id, source_ip, source_so, dest_ip, type, port, syn_count, status) VALUES (!ID!, '!ORIGEM_IP!', 'Simulador didatico (CMD) !SIM_USER_CURTO!', '!DEST!', 'SYN Flood', '!PORTA!', !QTD!, 'aberto')"
call :SQL
if errorlevel 1 (
    echo    ERRO ao gravar o alerta no banco:
    type "%ERRO%"
    set /a FALHAS+=1
    goto :eof
)
set "SQL=UPDATE machines SET status='alerta' WHERE id=!ID!"
call :SQL
set /a GRAVADOS+=1
echo    Alerta de SYN Flood gravado - veja na aba Alertas.
goto :eof

:FIM
del "%ERRO%" >nul 2>&1
del "%SAIDA%" >nul 2>&1
del "%TEMP%\simulador_pw.txt" >nul 2>&1
del "%TESTE%" >nul 2>&1
set "SIM_SENHA="
set "SIM_HASH="
echo.
pause
endlocal
