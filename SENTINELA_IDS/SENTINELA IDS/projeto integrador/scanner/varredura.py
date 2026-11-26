import threading
from concurrent.futures import ThreadPoolExecutor

from config import TAMANHO_LOTE, ULTIMO_HOST
from estado import S, parar, salvar
from rede import sonda

thread = None


def varrer():
    parar.clear()
    S["estado"] = "varrendo"
    with ThreadPoolExecutor(TAMANHO_LOTE) as ex:
        while S["proximo"] <= ULTIMO_HOST and not parar.is_set():
            inicio = S["proximo"]
            lote = list(range(inicio, min(inicio + TAMANHO_LOTE, ULTIMO_HOST + 1)))
            for r in ex.map(lambda n: sonda("%s.%d" % (S["rede"], n)), lote):
                if r:
                    S["achados"].append(r)
            S["proximo"] = lote[-1] + 1
            salvar()
    S["estado"] = "concluído" if S["proximo"] > ULTIMO_HOST else "parado"
    salvar()


def esta_rodando():
    return thread is not None and thread.is_alive()


def iniciar():
    global thread
    thread = threading.Thread(target=varrer, daemon=True)
    thread.start()


def parar_e_esperar():
    parar.set()
    if esta_rodando():
        thread.join()
