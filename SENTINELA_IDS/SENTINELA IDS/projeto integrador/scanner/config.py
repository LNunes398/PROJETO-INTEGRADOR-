import os

PORTA = 8765
HOST = "127.0.0.1"
TAMANHO_LOTE = 32
ULTIMO_HOST = 254
TIMEOUT_PING_MS = 700
NOWIN = 0x08000000 if os.name == "nt" else 0
ARQUIVO_PROGRESSO = os.path.join(os.path.dirname(os.path.abspath(__file__)), "progresso.json")
ARQUIVO_DADOS = os.path.join(os.path.dirname(os.path.abspath(__file__)), "dados.json")
