import time

import varredura
from config import ULTIMO_HOST
from estado import S, parar, trava, zerar

pendente = None


def enviar(comando):
    global pendente
    with trava:
        pendente = comando


def executar(comando):
    if comando == "iniciar":
        varredura.parar_e_esperar()
        zerar()
        varredura.iniciar()
    elif comando == "continuar":
        if not varredura.esta_rodando() and S["proximo"] <= ULTIMO_HOST:
            varredura.iniciar()
    elif comando == "parar":
        parar.set()
    elif comando == "reiniciar":
        varredura.parar_e_esperar()
        zerar()


def loop():
    global pendente
    while True:
        with trava:
            comando, pendente = pendente, None
        if comando:
            executar(comando)
        time.sleep(0.2)
