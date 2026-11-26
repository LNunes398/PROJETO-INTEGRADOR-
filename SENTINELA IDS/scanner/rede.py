import re
import socket
import subprocess
import uuid

from config import NOWIN, TIMEOUT_PING_MS
from estado import S


def meu_ip():
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        s.connect(("8.8.8.8", 80))
        return s.getsockname()[0]
    except Exception:
        return "127.0.0.1"
    finally:
        s.close()


def rodar(args):
    return subprocess.run(args, capture_output=True, text=True, creationflags=NOWIN, timeout=6).stdout


def mac_local():
    h = "%012X" % uuid.getnode()
    return "-".join(h[i:i + 2] for i in range(0, 12, 2))


def mac_de(ip):
    if ip == S["meu_ip"]:
        return mac_local()
    try:
        a = re.search(r"([0-9a-f]{2}(?:-[0-9a-f]{2}){5})", rodar(["arp", "-a", ip]), re.I)
        if a:
            return a.group(1).upper().replace("-", ":")
    except Exception:
        pass
    return "?"


def hostname_de(ip):
    try:
        return socket.gethostbyaddr(ip)[0]
    except Exception:
        return "(desconhecido)"


def sistema_por_ttl(ttl):
    if 64 < ttl <= 128:
        return "Windows"
    if ttl <= 64:
        return "Linux/Android/macOS"
    return "Roteador/Outro"


def sonda(ip):
    try:
        saida = rodar(["ping", "-n", "1", "-w", str(TIMEOUT_PING_MS), ip])
    except Exception:
        return None
    m = re.search(r"TTL=(\d+)", saida, re.I)
    if not m or "bytes=" not in saida.lower():
        return None
    return {
        "ip": ip,
        "mac": mac_de(ip),
        "so": sistema_por_ttl(int(m.group(1))) + " (estimado)",
        "host": hostname_de(ip),
    }
