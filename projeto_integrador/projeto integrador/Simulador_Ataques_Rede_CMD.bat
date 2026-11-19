@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul
title Sentinela - Simulador Didatico de Ataques de Rede
mode con: cols=122 >nul 2>&1

rem ============================================================
rem  CONFIGURACAO
rem ============================================================
set "RAIZ=%~dp0"
set "BASE=http://127.0.0.1:8765"
rem 1 = so registra alerta se o PC responder ao ping. 0 = registra sempre.
set "EXIGIR_PING=1"
rem segundos sem digitar ate a lista de PCs se atualizar sozinha
set "ATUALIZA_SEG=15"

set "RESP=%TEMP%\simulador_resp.txt"
set "HASHF=%TEMP%\simulador_hash.txt"
set "PING_TMP=%TEMP%\simulador_ping.txt"
set "TESTE=%TEMP%\simulador_teste.txt"
set "INF=%TEMP%\simulador_in.txt"
set "RE_USER=[A-Za-z0-9_.@-]*"
set "RE_NUM=[0-9][0-9]*"
set "USA_CURL="
where curl.exe >nul 2>&1
if not errorlevel 1 set "USA_CURL=1"
set "JA_INICIOU="
set "TENT_PONTE=0"
set "PEND=0"
set "SETA=>>"

rem ============================================================
rem  CORES (visual azul-marinho + verde neon). Sem ANSI: tudo vazio.
rem ============================================================
set "ANSI="
ver | findstr /c:"Version 10." >nul && set "ANSI=1"
set "ESC="
if defined ANSI for /f %%E in ('echo prompt $E ^| cmd') do set "ESC=%%E"
if not defined ESC set "ANSI="
set "BG=" & set "CB=" & set "VD=" & set "CI=" & set "DM=" & set "BR=" & set "VM=" & set "AM=" & set "RS="
if defined ANSI (
    set "BG=!ESC![48;2;2;12;56m"
    set "CB=!ESC![48;2;24;40;98m"
    set "VD=!ESC![38;2;16;232;160m"
    set "CI=!ESC![38;2;90;190;255m"
    set "DM=!ESC![38;2;40;100;190m"
    set "BR=!ESC![38;2;235;240;255m"
    set "VM=!ESC![38;2;255;110;120m"
    set "AM=!ESC![38;2;255;210;90m"
    set "RS=!ESC![0m!BG!!BR!"
) else (
    color 0B
)

rem ============================================================
rem  SUBROTINAS DE TELA
rem ============================================================
goto PONTE_TESTAR

:LIMPAR
if defined ANSI (
    <nul set /p "=!RS!!ESC![2J!ESC![H"
) else (
    cls
)
exit /b 0

:BANNER
call :LIMPAR
echo.
echo  !DM!!SETA! sentinela simulador --modo didatico --sem-ataque-real!RS!
echo  !BG!!CI!+----------------------------------------------------------+!RS!
echo  !CB!!VD!  [^>_]  !BR!SENTINELA!DM!  -  !CI!SIMULADOR DE ATAQUES DE REDE          !RS!
echo  !BG!!CI!+----------------------------------------------------------+!RS!
exit /b 0
rem ============================================================
rem  PROGRAMA DO SENTINELA (scanner): liga sozinho se estiver fechado
rem ============================================================
:PONTE_TESTAR
call :HTTP "%BASE%/status"
if not errorlevel 1 goto LOGIN
if not defined JA_INICIOU (
    call :BANNER
    echo.
    echo  !CI!!SETA!!RS! iniciando o programa do Sentinela, aguarde...
    if exist "%RAIZ%scanner\iniciar.bat" start "Sentinela Scanner" /min "%RAIZ%scanner\iniciar.bat"
    set "JA_INICIOU=1"
)
set /a TENT_PONTE+=1
if !TENT_PONTE! LSS 10 (
    ping -n 2 127.0.0.1 >nul
    goto PONTE_TESTAR
)
call :BANNER
echo.
echo  !VM![X]!RS! Nao foi possivel falar com o programa do Sentinela em %BASE%.
echo.
echo      Abra a pasta scanner, de dois cliques em iniciar.bat e deixe a janela aberta.
echo      Ele precisa do Python instalado.
echo.
echo  !VD![R]!RS! Tentar de novo    !CI![ENTER]!RS! Sair
set "OP="
set /p "OP= !VD!!SETA!!RS! opcao: "
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
call :LIMPAR
echo.
echo  !DM!!SETA! sentinela auth --painel index.html!RS!
echo  !BG!!CI!+----------------------------------------------------------+!RS!
echo  !CB!!BR!                                                            !RS!
echo  !CB!!BR!               !BR!SENTINELA - ACESSO SOLICITADO                !RS!
echo  !CB!!BR!                                                            !RS!
echo  !CB!!BR!       !VD![^>_]!DM! ------------ !VD!^(OK^)!DM! ------------ !CI![ PAINEL ]       !RS!
echo  !CB!!BR!                                                            !RS!
echo  !CB!!BR!         !CI!Mesmo usuario e senha do painel Sentinela          !RS!
echo  !CB!!BR!                                                            !RS!
echo  !BG!!CI!+----------------------------------------------------------+!RS!
echo.
set "SIM_USER="
set /p "SIM_USER= !VD!!SETA!!RS! usuario ou e-mail: "
if not defined SIM_USER goto LOGIN_PEDIR
call :TESTA SIM_USER RE_USER
if errorlevel 1 (
    echo  !VM![X]!RS! Usuario invalido. Use letras, numeros, ponto, hifen, _ ou @.
    pause
    goto LOGIN_PEDIR
)
call :LER_HASH
if errorlevel 1 (
    echo  !VM![X]!RS! Nao foi possivel ler a senha. O PowerShell precisa estar disponivel.
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
    echo  !AM![*]!RS! Esse usuario ainda nao foi enviado ao programa.
    echo      Abra o painel index.html, entre com este usuario uma vez e deixe a pagina aberta.
    echo      Depois tente de novo aqui.
) else if "!R_STAT!"=="ERRO" (
    echo  !VM![X]!RS! Usuario ou senha invalidos.
) else (
    echo  !VM![X]!RS! O programa do Sentinela nao respondeu.
)
set /a TENT_LOGIN+=1
if !TENT_LOGIN! GEQ 3 (
    echo  Numero maximo de tentativas atingido.
    goto FIM
)
pause
goto LOGIN_PEDIR

rem ============================================================
rem  CARREGA OS PCs DO USUARIO (os mesmos da aba Maquinas do painel)
rem ============================================================
:CARREGAR
call :LER_MAQUINAS
if errorlevel 1 (
    echo  !VM![X]!RS! O programa do Sentinela nao respondeu.
    goto FIM
)

if %TOTAL_SALAS% EQU 0 (
    call :BANNER
    echo.
    echo  !AM![*]!RS! Nenhum PC cadastrado para este usuario no painel.
    echo      Cadastre os PCs na aba Maquinas do painel index.html e deixe a pagina aberta.
    echo.
    echo  !VD![R]!RS! Recarregar    !CI![ENTER]!RS! Sair
    set "OP="
    set /p "OP= !VD!!SETA!!RS! opcao: "
    if /i "!OP!"=="R" goto CARREGAR
    goto FIM
)

rem ============================================================
rem  SALA: sempre mostra as salas do usuario para escolher
rem  (a lista se atualiza sozinha a cada ATUALIZA_SEG segundos)
rem ============================================================
:SALA
set "SALA_FILTRO="
set "SALA_NOME=todas as salas"

call :BANNER
set "L=  Usuario: !U_NOME!                                                            "
echo  !CB!!BR!!L:~0,60!!RS!
set "L=  PCs cadastrados no painel: %TOTAL_ALL% em %TOTAL_SALAS% sala(s)                                  "
echo  !CB!!BR!!L:~0,60!!RS!
set "L=  Atualizado as !ULT_ATU! - recarrega sozinho a cada !ATUALIZA_SEG!s                    "
echo  !CB!!DM!!L:~0,60!!RS!
echo  !BG!!CI!+----------------------------------------------------------+!RS!
echo.
echo  !CI! N    SALA                           PCs!RS!
echo  !DM!---- ------------------------------ ----!RS!
for /l %%N in (1,1,%TOTAL_SALAS%) do (
    set "L_N=%%N    "
    set "L_SALA=!R_NOME%%N!                              "
    echo  !VD!!L_N:~0,4!!RS! !BR!!L_SALA:~0,30!!RS! !CI!!R_QTD%%N!!RS!
)

:SALA_PEDIR
echo.
<nul set /p "= !VD!!SETA!!RS! sala (numero, T = todas, R = recarregar): "
call :LER_TEMPO SALA_SEL %ATUALIZA_SEG%
if errorlevel 2 (
    call :LER_MAQUINAS
    if !TOTAL_SALAS! EQU 0 goto CARREGAR
    goto SALA
)
if not defined SALA_SEL goto SALA_PEDIR
if /i "!SALA_SEL!"=="T" goto SALA_APLICAR
if /i "!SALA_SEL!"=="R" goto CARREGAR
call :TESTA SALA_SEL RE_NUM
if errorlevel 1 (
    echo  !AM![*]!RS! Digite um numero da lista, T ou R.
    goto SALA_PEDIR
)
set "SALA_LIMPA="
for /f "tokens=* delims=0" %%Z in ("!SALA_SEL!") do set "SALA_LIMPA=%%Z"
if not defined SALA_LIMPA (
    echo  !AM![*]!RS! Numero fora da lista.
    goto SALA_PEDIR
)
if not "!SALA_LIMPA:~3,1!"=="" (
    echo  !AM![*]!RS! Numero fora da lista.
    goto SALA_PEDIR
)
if !SALA_LIMPA! GTR %TOTAL_SALAS% (
    echo  !AM![*]!RS! Numero fora da lista.
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
rem  INICIO: lista os PCs da sala escolhida (atualiza sozinho)
rem ============================================================
:INICIO
call :BANNER
set "L=  Usuario: !U_NOME!                                                            "
echo  !CB!!BR!!L:~0,60!!RS!
set "L=  Sala: !SALA_NOME!                                                            "
echo  !CB!!BR!!L:~0,60!!RS!
set "L=  PCs nesta lista: %TOTAL%   |   atualizado as !ULT_ATU!, a cada !ATUALIZA_SEG!s                    "
echo  !CB!!VD!!L:~0,60!!RS!
echo  !BG!!CI!+----------------------------------------------------------+!RS!
echo  !DM!Modo didatico: nenhum ataque real. Os resultados vao para a aba Alertas do painel.!RS!
echo.
echo  !CI! N    NOME                 IP               HOSTNAME         SO               LOCAL            STATUS!RS!
echo  !DM!---- -------------------- ---------------- ---------------- ---------------- ---------------- --------!RS!
for /l %%N in (1,1,%TOTAL%) do (
    set "L_N=%%N    "
    set "L_NOME=!M_NOME%%N!                    "
    set "L_IP=!M_IP%%N!                "
    set "L_HOST=!M_HOST%%N!                "
    set "L_SO=!M_SO%%N!                "
    set "L_LOCAL=!M_LOCAL%%N!                "
    set "SC=!VD!"
    if "!M_STATUS%%N!"=="offline" set "SC=!VM!"
    if "!M_STATUS%%N!"=="alerta" set "SC=!AM!"
    echo  !VD!!L_N:~0,4!!RS! !BR!!L_NOME:~0,20! !L_IP:~0,16! !L_HOST:~0,16! !L_SO:~0,16! !L_LOCAL:~0,16!!RS! !SC!!M_STATUS%%N!!RS!
)

:ESCOLHER
echo.
<nul set /p "= !VD!!SETA!!RS! PC (numero, T = todos, V = voltar as salas, R = recarregar): "
call :LER_TEMPO SEL %ATUALIZA_SEG%
if errorlevel 2 goto ATUALIZAR_PCS
if not defined SEL goto ESCOLHER
if /i "!SEL!"=="T" (
    set "ALVO_INI=1"
    set "ALVO_FIM=%TOTAL%"
    goto GRUPO
)
if /i "!SEL!"=="V" goto SALA
if /i "!SEL!"=="R" goto ATUALIZAR_PCS
call :TESTA SEL RE_NUM
if errorlevel 1 (
    echo  !AM![*]!RS! Digite um numero da lista, T, V ou R.
    goto ESCOLHER
)
set "SEL_LIMPO="
for /f "tokens=* delims=0" %%Z in ("!SEL!") do set "SEL_LIMPO=%%Z"
if not defined SEL_LIMPO (
    echo  !AM![*]!RS! Numero fora da lista.
    goto ESCOLHER
)
if not "!SEL_LIMPO:~4,1!"=="" (
    echo  !AM![*]!RS! Numero fora da lista.
    goto ESCOLHER
)
if !SEL_LIMPO! GTR %TOTAL% (
    echo  !AM![*]!RS! Numero fora da lista.
    goto ESCOLHER
)
set "ALVO_INI=!SEL_LIMPO!"
set "ALVO_FIM=!SEL_LIMPO!"
goto GRUPO

rem recarrega os PCs do painel mantendo a sala escolhida
:ATUALIZAR_PCS
call :LER_MAQUINAS
if !TOTAL_SALAS! EQU 0 goto CARREGAR
call :FILTRAR
if !TOTAL! EQU 0 goto SALA
goto INICIO

:GRUPO
echo.
echo  !CI!GRUPOS DE ATAQUE!RS!
echo   !VD!1!RS! - Negacao de Servico ^(DoS^)
set "GRUPO=1"
set /p "GRUPO= !VD!!SETA!!RS! grupo [1]: "
if not "!GRUPO!"=="1" (
    echo  !AM![*]!RS! Grupo nao reconhecido.
    pause
    goto INICIO
)

echo.
echo  !CI!ATAQUES DISPONIVEIS - Negacao de Servico!RS!
echo   !VD!1!RS! - SYN Flood ^(simulacao^)
set "ATAQUE=1"
set /p "ATAQUE= !VD!!SETA!!RS! ataque [1]: "
if not "!ATAQUE!"=="1" (
    echo  !AM![*]!RS! Ataque nao reconhecido.
    pause
    goto INICIO
)

echo.
echo  !CI!SYN FLOOD - SIMULACAO DIDATICA!RS!
echo  O SYN Flood tenta sobrecarregar um servico com solicitacoes TCP.
echo  !VD!Nenhum pacote SYN sera enviado ao alvo.!RS!
echo.

:QUANTIDADE
set "QTD=1000"
set /p "QTD= !VD!!SETA!!RS! pacotes SYN a simular [1000]: "
call :TESTA QTD RE_NUM
if errorlevel 1 (
    echo  !AM![*]!RS! Digite apenas numeros inteiros.
    goto QUANTIDADE
)
set "QTD_LIMPO="
for /f "tokens=* delims=0" %%Z in ("!QTD!") do set "QTD_LIMPO=%%Z"
if not defined QTD_LIMPO (
    echo  !AM![*]!RS! A quantidade deve ser maior que zero.
    goto QUANTIDADE
)
set "QTD=!QTD_LIMPO!"
if not "!QTD:~7,1!"=="" (
    echo  !AM![*]!RS! Limite: 1000000 pacotes simulados.
    goto QUANTIDADE
)
if !QTD! GTR 1000000 (
    echo  !AM![*]!RS! Limite: 1000000 pacotes simulados.
    goto QUANTIDADE
)

:PORTA
set "PORTA=80"
set /p "PORTA= !VD!!SETA!!RS! porta de destino simulada [80]: "
call :TESTA PORTA RE_NUM
if errorlevel 1 (
    echo  !AM![*]!RS! Digite apenas numeros.
    goto PORTA
)
set "PORTA_LIMPA="
for /f "tokens=* delims=0" %%Z in ("!PORTA!") do set "PORTA_LIMPA=%%Z"
if not defined PORTA_LIMPA (
    echo  !AM![*]!RS! Porta deve ficar entre 1 e 65535.
    goto PORTA
)
if not "!PORTA_LIMPA:~5,1!"=="" (
    echo  !AM![*]!RS! Porta deve ficar entre 1 e 65535.
    goto PORTA
)
if !PORTA_LIMPA! GTR 65535 (
    echo  !AM![*]!RS! Porta deve ficar entre 1 e 65535.
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
rem limpa o que sobrou de uma simulacao anterior
for /l %%N in (1,1,%PEND%) do set "P_OK%%N="
set "PEND=0"
set "GRAVADOS=0"
set "SEM_PING=0"
set "MAX_SEQ=0"
set "CONF=0"

echo.
echo  !CI!!SETA! simulando contra os alvos escolhidos...!RS!
for /l %%N in (%ALVO_INI%,1,%ALVO_FIM%) do call :SIMULAR_UM %%N

echo.
echo  !BG!!CI!+----------------------------------------------------------+!RS!
echo  !CB!!BR!                                                            !RS!
echo  !CB!!BR!                   !BR!RESULTADO DA SIMULACAO                   !RS!
echo  !CB!!BR!                                                            !RS!
set "L=  Origem simulada: %ORIGEM_IP%                                                  "
echo  !CB!!BR!!L:~0,60!!RS!
set "L=  Ataque: SYN Flood (DoS)    Porta: %PORTA%                                  "
echo  !CB!!BR!!L:~0,60!!RS!
set "L=  Pacotes SYN simulados por alvo: %QTD%                                       "
echo  !CB!!BR!!L:~0,60!!RS!
set "L=  Respostas simuladas: %RESPOSTAS%   Sem resposta: %SEM_RESPOSTA%                          "
echo  !CB!!BR!!L:~0,60!!RS!
set "L=  Alertas prontos para enviar: !PEND!                                           "
echo  !CB!!VD!!L:~0,60!!RS!
if not "%SEM_PING%"=="0" (
    set "L=  Alvos sem resposta ao ping, ignorados: %SEM_PING%                              "
    echo  !CB!!AM!!L:~0,60!!RS!
)
echo  !CB!!BR!                                                            !RS!
echo  !BG!!CI!+----------------------------------------------------------+!RS!
echo  !DM!- Somente os pings de teste foram enviados pela rede.!RS!
echo  !DM!- Os numeros do SYN Flood sao ilustrativos, nao sao respostas reais dos alvos.!RS!
echo  !DM!- O resultado SO vai para a aba Alertas depois que voce clicar em ENVIAR.!RS!

if !PEND! EQU 0 (
    echo.
    echo  !AM![*]!RS! Nenhum alerta para enviar: nenhum alvo respondeu ao ping.
    goto OUTRA
)

:ENVIAR_MENU
echo.
echo  !VD![E]!RS! ENVIAR !PEND! alerta^(s^) para a aba Alertas      !AM![D]!RS! Descartar
set "OP="
set /p "OP= !VD!!SETA!!RS! opcao: "
if /i "!OP!"=="E" goto ENVIAR
if /i "!OP!"=="D" (
    echo  !AM![*]!RS! Resultado descartado: nada foi enviado ao painel.
    goto OUTRA
)
echo  !AM![*]!RS! Digite E para enviar ou D para descartar.
goto ENVIAR_MENU

rem ============================================================
rem  ENVIAR: manda os alertas e confirma que o painel recebeu
rem ============================================================
:ENVIAR
echo.
echo  !CI!!SETA! enviando alertas ao painel...!RS!
for /l %%N in (1,1,%PEND%) do call :ENVIAR_UM %%N
set /a FALTAM=PEND-GRAVADOS
if !GRAVADOS! GTR 0 call :CONFIRMAR
if !FALTAM! GTR 0 echo  !VM![X]!RS! !FALTAM! alerta^(s^) nao foi^(ram^) enviado^(s^).

:POS_ENVIO
echo.
if !FALTAM! GTR 0 echo  !VD![E]!RS! Reenviar os pendentes
if "!CONF!"=="0" if !GRAVADOS! GTR 0 echo  !VD![V]!RS! Verificar de novo no painel
echo  !CI![ENTER]!RS! Terminar
set "OP="
set /p "OP= !VD!!SETA!!RS! opcao: "
if /i "!OP!"=="E" if !FALTAM! GTR 0 goto ENVIAR
if /i "!OP!"=="V" (
    call :CONFIRMAR
    goto POS_ENVIO
)

:OUTRA
echo.
set "OUTRA="
set /p "OUTRA= !VD!!SETA!!RS! fazer outra simulacao? (S/N): "
if /i "!OUTRA!"=="S" goto CARREGAR
goto FIM

rem ============================================================
rem  SUBROTINA: le os PCs do painel (via programa) e monta as salas.
rem  Se o programa nao responder, mantem a lista anterior (errorlevel 1).
rem ============================================================
:LER_MAQUINAS
call :HTTP "%BASE%/maquinas?user=!SIM_USER!&hash=!SIM_HASH!"
if errorlevel 1 exit /b 1
set "A_ID1="
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
set "ULT_ATU=%TIME:~0,8%"
exit /b 0

rem ============================================================
rem  SUBROTINA: espera uma linha digitada por N segundos.
rem  Devolve errorlevel 2 se ninguem digitou nada (hora de atualizar).
rem  Cada tecla digitada zera a contagem, entao nunca atualiza no meio da digitacao.
rem ============================================================
:LER_TEMPO
set "%~1="
del "%INF%" >nul 2>&1
powershell -NoProfile -Command "$t=[Diagnostics.Stopwatch]::StartNew();$s='';while($t.Elapsed.TotalSeconds -lt %~2){if([Console]::KeyAvailable){$k=[Console]::ReadKey($true);$t.Restart();if($k.Key -eq 'Enter'){[IO.File]::WriteAllText($env:TEMP+'\simulador_in.txt',$s);[Console]::WriteLine();exit 0}elseif($k.Key -eq 'Backspace'){if($s.Length -gt 0){$s=$s.Substring(0,$s.Length-1);[Console]::Write([string][char]8+' '+[string][char]8)}}elseif($k.KeyChar -ne [char]0){$s+=$k.KeyChar;[Console]::Write($k.KeyChar)}}else{Start-Sleep -Milliseconds 40}};exit 2" 2>nul
set "RC=!errorlevel!"
if "!RC!"=="2" exit /b 2
if "!RC!"=="0" (
    if exist "%INF%" set /p %~1=<"%INF%"
    del "%INF%" >nul 2>&1
    exit /b 0
)
rem PowerShell indisponivel: leitura comum, sem atualizacao automatica
set /p "%~1="
exit /b 0

rem ============================================================
rem  SUBROTINA: pede a senha sem mostrar e guarda so o hash SHA-256
rem  (a senha nunca vai para o arquivo nem para a rede)
rem ============================================================
:LER_HASH
set "SIM_HASH="
del "%HASHF%" >nul 2>&1
powershell -NoProfile -Command "$s = Read-Host '>> senha' -AsSecureString; $p = [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s)); $h = [BitConverter]::ToString([Security.Cryptography.SHA256]::Create().ComputeHash([Text.Encoding]::UTF8.GetBytes('sentinela:' + $p))).Replace('-','').ToLower(); [IO.File]::WriteAllText($env:TEMP + '\simulador_hash.txt', $h)" 2>nul
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
rem  SUBROTINA: simula UMA maquina e deixa o alerta PENDENTE.
rem  Nada vai para o painel aqui: o envio e feito pelo botao ENVIAR.
rem ============================================================
:SIMULAR_UM
set "N=%~1"
set "ID=!M_ID%N%!"
set "NOME=!M_NOME%N%!"
set "DEST=!M_IP%N%!"
echo.
echo  !CI!-- alvo:!RS! !NOME! [!DEST!]
if "%EXIGIR_PING%"=="0" goto SIM_PENDENTE

echo     testando conectividade, 4 pings...
ping -n 4 -w 1000 "!DEST!" > "%PING_TMP%" 2>&1
findstr /i "TTL=" "%PING_TMP%" >nul
if errorlevel 1 (
    echo     !AM!ALVO NAO DISPONIVEL: sem resposta aos pings. Alerta nao sera gerado.!RS!
    set /a SEM_PING+=1
    del "%PING_TMP%" >nul 2>&1
    goto :eof
)
findstr /i "Packets: Pacotes:" "%PING_TMP%"
del "%PING_TMP%" >nul 2>&1

:SIM_PENDENTE
set /a PEND+=1
set "P_ID!PEND!=!ID!"
set "P_NOME!PEND!=!NOME!"
set "P_IP!PEND!=!DEST!"
echo     !VD![OK]!RS! SYN Flood simulado - alerta pronto para enviar.
goto :eof

rem ============================================================
rem  SUBROTINA: envia o alerta pendente N ao painel (pula os ja enviados)
rem ============================================================
:ENVIAR_UM
set "N=%~1"
if defined P_OK!N! exit /b 0
call :HTTP "%BASE%/alerta?user=!SIM_USER!&hash=!SIM_HASH!&maquina=!P_ID%N%!&origem=!ORIGEM_IP!&porta=!PORTA!&qtd=!QTD!"
set "R_LINHA="
if exist "%RESP%" set /p R_LINHA=<"%RESP%"
set "R_STAT="
set "R_SEQ="
for /f "tokens=1,2 delims=|" %%A in ("!R_LINHA!") do (
    set "R_STAT=%%A"
    set "R_SEQ=%%B"
)
if "!R_STAT!"=="OK" (
    set "P_OK!N!=1"
    set /a GRAVADOS+=1
    if defined R_SEQ if !R_SEQ! GTR !MAX_SEQ! set "MAX_SEQ=!R_SEQ!"
    echo     !VD![OK]!RS! !P_NOME%N%! [!P_IP%N%!] - alerta #!R_SEQ! enviado.
    exit /b 0
)
echo     !VM![X]!RS! !P_NOME%N%! [!P_IP%N%!] - ERRO ao enviar: !R_LINHA!
exit /b 0

rem ============================================================
rem  SUBROTINA: espera ate 15 s o painel confirmar que recebeu o alerta
rem  (o painel aberto busca alertas novos a cada 2 s e avisa o programa)
rem ============================================================
:CONFIRMAR
set "CONF=0"
echo.
echo  !CI!!SETA! confirmando que o painel recebeu...!RS!
for /l %%T in (1,1,15) do if "!CONF!"=="0" call :CONF_UM
if "!CONF!"=="1" (
    echo  !VD![OK] CONFIRMADO:!RS! !GRAVADOS! alerta^(s^) ja aparece^(m^) na aba Alertas do painel.
) else (
    echo  !AM![*] Alertas salvos no programa, mas o painel ainda nao confirmou.!RS!
    echo      Deixe o painel index.html aberto e logado com este usuario:
    echo      eles entram na aba Alertas assim que o painel conectar.
)
exit /b 0

:CONF_UM
call :HTTP "%BASE%/confirmar?user=!SIM_USER!&hash=!SIM_HASH!&seq=!MAX_SEQ!"
set "R_LINHA="
if exist "%RESP%" set /p R_LINHA=<"%RESP%"
set "C1="
set "C2="
for /f "tokens=1,2 delims=|" %%A in ("!R_LINHA!") do (
    set "C1=%%A"
    set "C2=%%B"
)
if "!C1!"=="OK" if "!C2!"=="1" (
    set "CONF=1"
    exit /b 0
)
ping -n 2 127.0.0.1 >nul
exit /b 0

:FIM
del "%RESP%" >nul 2>&1
del "%HASHF%" >nul 2>&1
del "%TESTE%" >nul 2>&1
del "%PING_TMP%" >nul 2>&1
del "%INF%" >nul 2>&1
set "SIM_HASH="
echo.
pause
endlocal
