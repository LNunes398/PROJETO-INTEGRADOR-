import threading

import comandos
import estado
import ponte
import servidor
from config import PORTA
from rede import meu_ip


def main():
    estado.S["meu_ip"] = meu_ip()
    estado.S["rede"] = ".".join(estado.S["meu_ip"].split(".")[:3])
    estado.carregar()
    ponte.carregar()
    print("Seu IP:", estado.S["meu_ip"], "| Rede:", estado.S["rede"] + ".0/24")
    print("Scanner pronto em http://127.0.0.1:%d (Ctrl+C para sair)" % PORTA)
    threading.Thread(target=comandos.loop, daemon=True).start()
    servidor.iniciar()


if __name__ == "__main__":
    main()
