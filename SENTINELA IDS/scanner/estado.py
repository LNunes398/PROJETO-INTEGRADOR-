import json
import threading

from config import ARQUIVO_PROGRESSO

S = {"estado": "parado", "meu_ip": "", "rede": "", "proximo": 1, "total": 254, "achados": []}

trava = threading.Lock()
parar = threading.Event()


def salvar():
    try:
        with open(ARQUIVO_PROGRESSO, "w") as f:
            json.dump(S, f)
    except Exception:
        pass


def carregar():
    try:
        with open(ARQUIVO_PROGRESSO) as f:
            d = json.load(f)
        if d.get("rede") == S["rede"]:
            S["proximo"], S["achados"] = d["proximo"], d["achados"]
    except Exception:
        pass


def zerar():
    S["proximo"], S["achados"], S["estado"] = 1, [], "parado"
    salvar()
