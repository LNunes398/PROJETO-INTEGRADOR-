"""Ponte entre o painel (index.html), o simulador em CMD e os dados do usuario.

O painel guarda usuarios e maquinas so no navegador. Aqui o programa guarda uma
copia por usuario em dados.json, para o CMD conseguir ler os PCs e devolver os
alertas que aparecem na aba Alertas.
"""
import hmac
import json
import os
import re
import threading
import time

from config import ARQUIVO_DADOS

trava = threading.RLock()
DADOS = {"contas": {}}
MAX_ALERTAS = 500
MAX_MAQUINAS = 2000
STATUS_OK = ("online", "alerta", "offline")


def carregar():
    global DADOS
    try:
        with open(ARQUIVO_DADOS, encoding="utf-8") as f:
            d = json.load(f)
        if isinstance(d, dict) and isinstance(d.get("contas"), dict):
            DADOS = d
    except Exception:
        pass


def salvar():
    try:
        tmp = ARQUIVO_DADOS + ".tmp"
        with open(tmp, "w", encoding="utf-8") as f:
            json.dump(DADOS, f, ensure_ascii=False)
        os.replace(tmp, ARQUIVO_DADOS)
    except Exception:
        pass


def limpo(txt, tam=100):
    """Texto seguro para o CMD: troca qualquer simbolo perigoso por _."""
    return re.sub(r"[^\w .:\-/]", "_", str(txt if txt is not None else ""))[:tam]


def _achar(user):
    u = (user or "").strip().lower()
    if not u:
        return None
    for c in DADOS["contas"].values():
        if c.get("username", "").lower() == u or (c.get("email") and c["email"].lower() == u):
            return c
    return None


def autenticar(user, hash_):
    """Devolve (conta, erro). erro = '' quando o usuario e a senha conferem."""
    with trava:
        c = _achar(user)
        if c is None:
            return None, "SEM_CONTA"
        a = str(c.get("hash", "")).encode("utf-8")
        b = str(hash_ or "").encode("utf-8")
        if not hmac.compare_digest(a, b):
            return None, "SENHA"
        return c, ""


def sincronizar(corpo):
    d = json.loads(corpo)
    user = str(d.get("username", "")).strip()[:150]
    h = str(d.get("hash", ""))
    if not user or not h:
        return False
    maquinas = []
    for m in (d.get("maquinas") or [])[:MAX_MAQUINAS]:
        if not isinstance(m, dict):
            continue
        try:
            mid = int(m.get("id"))
        except (TypeError, ValueError):
            continue
        status = m.get("status") if m.get("status") in STATUS_OK else "online"
        maquinas.append({
            "id": mid,
            "name": str(m.get("name", ""))[:100],
            "ip": str(m.get("ip", ""))[:45],
            "host": str(m.get("host", ""))[:255],
            "mac": str(m.get("mac", ""))[:17],
            "so": str(m.get("so", ""))[:100],
            "location": str(m.get("location", ""))[:100],
            "status": status,
        })
    with trava:
        chave = user.lower()
        c = DADOS["contas"].get(chave) or {"alertas": [], "seq": 0}
        c.update({
            "username": user,
            "email": str(d.get("email", ""))[:150],
            "name": str(d.get("name", ""))[:120],
            "hash": h,
            "maquinas": maquinas,
        })
        DADOS["contas"][chave] = c
        salvar()
    return True


def resumo_login(c):
    return "OK|%s|%d" % (limpo(c.get("name") or c.get("username")), len(c["maquinas"]))


def maquinas_txt(c):
    """Uma maquina por linha: id|nome|ip|host|so|local|status (facil para o CMD)."""
    linhas = []
    for m in c["maquinas"]:
        linhas.append("|".join([
            str(m["id"]),
            limpo(m["name"]) or "-",
            limpo(m["ip"]) or "-",
            limpo(m["host"]) or "-",
            limpo(m["so"]) or "-",
            limpo(m["location"]) or "Sem local",
            limpo(m["status"]) or "online",
        ]))
    return "\n".join(linhas)


def registrar_alerta(c, mid, origem, porta, qtd):
    with trava:
        maq = next((m for m in c["maquinas"] if m["id"] == mid), None)
        if maq is None:
            return None
        agora = time.localtime()
        c["seq"] = int(c.get("seq", 0)) + 1
        quem = limpo(c.get("name") or c.get("username"), 60)
        c["alertas"].append({
            "seq": c["seq"],
            "maquinaId": mid,
            "time": time.strftime("%H:%M:%S", agora),
            "date": time.strftime("%d/%m/%Y", agora),
            "sourceIp": limpo(origem, 45) or "127.0.0.1",
            "mac": "",
            "so": "Simulador didatico (CMD) - " + quem,
            "destMachine": maq["name"],
            "destIp": maq["ip"],
            "type": "SYN Flood",
            "port": str(porta),
            "syn": int(qtd),
        })
        del c["alertas"][:-MAX_ALERTAS]
        salvar()
        return c["seq"]


def dados_json(c, desde=0):
    with trava:
        return {
            "ok": True,
            "maquinas": c["maquinas"],
            "alertas": [a for a in c["alertas"] if a["seq"] > desde],
            "seq": int(c.get("seq", 0)),
        }
