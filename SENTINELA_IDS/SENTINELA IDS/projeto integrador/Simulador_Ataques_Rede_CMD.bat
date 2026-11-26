@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul
title Simulador Didatico de Ataques de Rede - Sentinela
color 0B

rem ============================================================
rem  CONFIGURACAO
rem ============================================================
set "RAIZ=%~dp0"
set "BASE=http://127.0.0.1:8765"
rem 1 = so registra alerta se o PC responder ao ping. 0 = registra sempre.
set "EXIGIR_PING=1"

set "RESP=%TEMP%\simulador_resp.txt"
set "HASHF=%TEMP%\simulador_hash.txt"
set "PING_TMP=%TEMP%\simulador_ping.txt"
set "TESTE=%TEMP%\simulador_teste.txt"
set "RE_USER=[A-Za-z0-9_.@-]*"
set "RE_NUM=[0-9][0-9]*"
set "USA_CURL="
where curl.exe >nul 2>&1
if not errorlevel 1 set "USA_CURL=1"
set "JA_INICIOU="
set "TENT_PONTE=0"

rem ============================================================
rem  PROGRAMA DO SENTINELA (scanner): liga sozinho se estiver fechado
rem ============================================================
:PONTE_TESTAR
call :HTTP "%BASE%/status"
if not errorlevel 1 goto LOGIN
if not defined JA_INICIOU (
    echo Iniciando o programa do Sentinela, aguarde...
    if exist "%RAIZ%scanner\iniciar.bat" start "Sentinela Scanner" /min "%RAIZ%scanner\iniciar.bat"
    set "JA_INICIOU=1"
)
set /a TENT_PONTE+=1
if !TENT_PONTE! LSS 10 (
    ping -n 2 127.0.0.1 >nul
    goto PONTE_TESTAR
)
cls
echo Nao foi possivel falar com o programa do Sentinela em %BASE%.
echo.
echo Abra a pasta scanner, de dois cliques em iniciar.bat e deixe a janela aberta.
echo Ele precisa do Python instalado.
echo.
echo [R] Tentar de novo   [ENTER] Sair
set "OP="
set /p "OP=Opcao: "
if /i "!OP!"=="R" (
    set "TENT_PONTE=0"
    goto PONTE_TESTAR
)
goto FIM

rem ============================================================
rem  LOGIN: mesmo usuario e senha do painel Sentinela (index.html)
rem ============================================================
:LOGIN
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
    echo Usuario invalido. Use letras, numeros, ponto, hifen, _ ou @.
    pause
    goto LOGIN_PEDIR
)
call :LER_HASH
if errorlevel 1 (
    echo Nao foi possivel ler a senha. O PowerShell precisa estar disponivel.
    goto FIM
)
call :HTTP "%BASE%/login?user=!SIM_USER!&hash=!SIM_HASH!"
set "R_LINHA="
if exist "%RESP%" set /p R_LINHA=<"%RESP%"
set "R_STAT="
set "R_B="
for /f "tokens=1-3 delims=|" %%A in ("!R_LINHA!") do (
    set "R_STAT=%%A"
    set "R_B=%%B"
)
if "!R_STAT!"=="OK" (
    set "U_NOME=!R_B!"
    goto CARREGAR
)

echo.
if "!R_B!"=="SEM_CONTA" (
    echo Esse usuario ainda nao foi enviado ao programa.
    echo Abra o painel index.html, entre com este usuario uma vez e deixe a pagina aberta.
    echo Depois tente de novo aqui.
) else if "!R_STAT!"=="ERRO" (
    echo Usuario ou senha invalidos.
) else (
    echo O programa do Sentinela nao respondeu.
)
set /a TENT_LOGIN+=1
if !TENT_LOGIN! GEQ 3 (
    echo Numero maximo de tentativas atingido.
    goto FIM
)
pause
goto LOGIN_PEDIR

rem ============================================================
rem  CARREGA OS PCs DO USUARIO (os mesmos da aba Maquinas do painel)
rem ============================================================
:CARREGAR
call :HTTP "%BASE%/maquinas?user=!SIM_USER!&hash=!SIM_HASH!"
if errorlevel 1 (
    echo O programa do Sentinela nao respondeu.
    goto FIM
)
set "TOTAL_ALL=0"
for /f "usebackq tokens=1-7 delims=|" %%A in ("%RESP%") do (
    set /a TOTAL_ALL+=1
    set "A_ID!TOTAL_ALL!=%%A"
    set "A_NOME!TOTAL_ALL!=%%B"
    set "A_IP!TOTAL_ALL!=%%C"
    set "A_HOST!TOTAL_ALL!=%%D"
    set "A_SO!TOTAL_ALL!=%%E"
    set "A_LOCAL!TOTAL_ALL!=%%F"
    set "A_STATUS!TOTAL_ALL!=%%G"
)
if "!A_ID1!"=="ERRO" set "TOTAL_ALL=0"

set "TOTAL_SALAS=0"
for /l %%N in (1,1,%TOTAL_ALL%) do call :ADD_SALA %%N

if %TOTAL_SALAS% EQU 0 (
    cls
    echo Nenhum PC cadastrado para este usuario no painel.
    echo Cadastre os PCs na aba Maquinas do painel index.html e deixe a pagina aberta.
    echo.
    echo [R] Recarregar   [ENTER] Sair
    set "OP="
    set /p "OP=Opcao: "
    if /i "!OP!"=="R" goto CARREGAR
    goto FIM
)

rem ============================================================
rem  SALA: sempre mostra as salas do usuario para escolher
rem ============================================================
:SALA
set "SALA_FILTRO="
set "SALA_NOME=todas as salas"

cls
echo ============================================
echo        SIMULADOR DE ATAQUES DE REDE
echo ============================================
echo Usuario: !U_NOME!
echo PCs cadastrados no painel: %TOTAL_ALL% em %TOTAL_SALAS% sala^(s^)
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
set /p "SALA_SEL=Numero da sala (T = todas, R = recarregar do painel): "
if not defined SALA_SEL goto SALA_PEDIR
if /i "!SALA_SEL!"=="T" goto SALA_APLICAR
if /i "!SALA_SEL!"=="R" goto CARREGAR
call :TESTA SALA_SEL RE_NUM
if errorlevel 1 (
    echo Digite um numero da lista, T ou R.
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
if /i not "!SALA_SEL!"=="T" (
    set "SALA_NOME=!R_NOME%SALA_SEL%!"
    set "SALA_FILTRO=!R_NOME%SALA_SEL%!"
)
call :FILTRAR

rem ============================================================
rem  INICIO: lista os PCs da sala escolhida
rem ============================================================
:INICIO
cls
echo ============================================
echo        SIMULADOR DE ATAQUES DE REDE
echo ============================================
echo Modo didatico: nao realiza ataques reais.
echo Os resultados aparecem na aba Alertas do painel Sentinela.
echo Usuario: !U_NOME!
echo Sala: !SALA_NOME!
echo.
echo PCS NESTA LISTA: %TOTAL%
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
set /p "SEL=Numero do PC (T = todos desta lista, V = voltar as salas, R = recarregar): "
if not defined SEL goto ESCOLHER
if /i "!SEL!"=="T" (
    set "ALVO_INI=1"
    set "ALVO_FIM=%TOTAL%"
    goto GRUPO
)
if /i "!SEL!"=="V" goto SALA
if /i "!SEL!"=="R" goto CARREGAR
call :TESTA SEL RE_NUM
if errorlevel 1 (
    echo Digite um numero da lista, T, V ou R.
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
echo Alertas enviados para a aba Alertas do painel: %GRAVADOS%
if not "%SEM_PING%"=="0" echo Alvos sem resposta ao ping, ignorados: %SEM_PING%
if not "%FALHAS%"=="0" echo Falhas ao enviar ao painel: %FALHAS%
echo Deixe o painel index.html aberto para ver os alertas chegarem.
echo.
echo IMPORTANTE:
echo - Somente os pings de teste foram enviados pela rede.
echo - Os numeros do SYN Flood sao ilustrativos.
echo - Eles nao representam respostas reais dos computadores alvo.
echo ============================================
echo.

set "OUTRA="
set /p "OUTRA=Fazer outra simulacao? (S/N): "
if /i "!OUTRA!"=="S" goto CARREGAR
goto FIM

rem ============================================================
rem  SUBROTINA: pede a senha sem mostrar e guarda so o hash SHA-256
rem  (a senha nunca vai para o arquivo nem para a rede)
rem ============================================================
:LER_HASH
set "SIM_HASH="
del "%HASHF%" >nul 2>&1
powershell -NoProfile -Command "$s = Read-Host 'Senha' -AsSecureString; $p = [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s)); $h = [BitConverter]::ToString([Security.Cryptography.SHA256]::Create().ComputeHash([Text.Encoding]::UTF8.GetBytes('sentinela:' + $p))).Replace('-','').ToLower(); [IO.File]::WriteAllText($env:TEMP + '\simulador_hash.txt', $h)" 2>nul
if exist "%HASHF%" set /p SIM_HASH=<"%HASHF%"
del "%HASHF%" >nul 2>&1
if not defined SIM_HASH exit /b 1
exit /b 0

rem ============================================================
rem  SUBROTINA: GET no programa do Sentinela. Resposta em %RESP%
rem ============================================================
:HTTP
del "%RESP%" >nul 2>&1
if defined USA_CURL (
    curl.exe -s -f -m 10 "%~1" -o "%RESP%" >nul 2>&1
    exit /b !errorlevel!
)
powershell -NoProfile -Command "try { $r = Invoke-WebRequest -UseBasicParsing -TimeoutSec 10 -Uri '%~1'; [IO.File]::WriteAllText($env:TEMP + '\simulador_resp.txt', $r.Content) } catch { exit 1 }" >nul 2>&1
exit /b !errorlevel!

rem ============================================================
rem  SUBROTINA: confere se uma variavel casa com um padrao.
rem  Entrada: nome da variavel e nome da variavel com o padrao.
rem ============================================================
:TESTA
>"%TESTE%" echo(!%~1!
findstr /r /x /c:"!%~2!" "%TESTE%" >nul
exit /b %errorlevel%

rem ============================================================
rem  SUBROTINA: soma a sala do PC N na lista de salas
rem ============================================================
:ADD_SALA
set "LOC=!A_LOCAL%~1!"
set "R_IDX="
for /l %%R in (1,1,%TOTAL_SALAS%) do if "!R_NOME%%R!"=="!LOC!" set "R_IDX=%%R"
if defined R_IDX (
    set /a R_QTD!R_IDX!+=1
    exit /b 0
)
set /a TOTAL_SALAS+=1
set "R_NOME!TOTAL_SALAS!=!LOC!"
set "R_QTD!TOTAL_SALAS!=1"
exit /b 0

rem ============================================================
rem  SUBROTINA: monta a lista de PCs da sala escolhida
rem ============================================================
:FILTRAR
set "TOTAL=0"
for /l %%N in (1,1,%TOTAL_ALL%) do call :FILTRAR_UM %%N
exit /b 0

:FILTRAR_UM
if defined SALA_FILTRO if not "!A_LOCAL%~1!"=="!SALA_FILTRO!" exit /b 0
set /a TOTAL+=1
set "M_ID!TOTAL!=!A_ID%~1!"
set "M_NOME!TOTAL!=!A_NOME%~1!"
set "M_IP!TOTAL!=!A_IP%~1!"
set "M_HOST!TOTAL!=!A_HOST%~1!"
set "M_SO!TOTAL!=!A_SO%~1!"
set "M_LOCAL!TOTAL!=!A_LOCAL%~1!"
set "M_STATUS!TOTAL!=!A_STATUS%~1!"
exit /b 0

rem ============================================================
rem  SUBROTINA: simula e envia o alerta de UMA maquina
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
call :HTTP "%BASE%/alerta?user=!SIM_USER!&hash=!SIM_HASH!&maquina=!ID!&origem=!ORIGEM_IP!&porta=!PORTA!&qtd=!QTD!"
set "R_LINHA="
if exist "%RESP%" set /p R_LINHA=<"%RESP%"
set "R_STAT="
for /f "tokens=1 delims=|" %%A in ("!R_LINHA!") do set "R_STAT=%%A"
if "!R_STAT!"=="OK" (
    set /a GRAVADOS+=1
    echo    Alerta de SYN Flood enviado - veja na aba Alertas do painel.
    goto :eof
)
echo    ERRO ao enviar o alerta ao painel: !R_LINHA!
set /a FALHAS+=1
goto :eof

:FIM
del "%RESP%" >nul 2>&1
del "%HASHF%" >nul 2>&1
del "%TESTE%" >nul 2>&1
del "%PING_TMP%" >nul 2>&1
set "SIM_HASH="
echo.
pause
endlocal
