import json
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

import comandos
import ponte
from config import HOST, PORTA
from estado import S

CABECALHOS_CORS = {
    "Access-Control-Allow-Origin": "*",
    "Access-Control-Allow-Private-Network": "true",
    "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
    "Access-Control-Allow-Headers": "Content-Type",
}
LIMITE_CORPO = 4 * 1024 * 1024


class Handler(BaseHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def responder(self, codigo, corpo=b"", tipo="application/json"):
        self.send_response(codigo)
        if corpo:
            self.send_header("Content-Type", tipo + ("; charset=utf-8" if tipo.startswith("text/") else ""))
        for nome, valor in CABECALHOS_CORS.items():
            self.send_header(nome, valor)
        self.end_headers()
        if corpo:
            self.wfile.write(corpo)

    def texto(self, txt):
        self.responder(200, txt.encode("utf-8"), "text/plain")

    def json(self, obj):
        self.responder(200, json.dumps(obj, ensure_ascii=False).encode("utf-8"))

    def do_GET(self):
        try:
            self._get()
        except Exception:
            self.responder(500, b'{"ok":false}')

    def _get(self):
        u = urlparse(self.path)
        q = {k: v[0] for k, v in parse_qs(u.query).items()}
        rota = u.path

        if rota.startswith("/cmd/"):
            comandos.enviar(rota[5:])
            self.responder(200, b'{"ok":true}')
        elif rota == "/login":
            c, erro = ponte.autenticar(q.get("user"), q.get("hash"))
            self.texto("ERRO|" + erro if erro else ponte.resumo_login(c))
        elif rota == "/maquinas":
            c, erro = ponte.autenticar(q.get("user"), q.get("hash"))
            self.texto("ERRO|" + erro if erro else ponte.maquinas_txt(c))
        elif rota == "/alerta":
            c, erro = ponte.autenticar(q.get("user"), q.get("hash"))
            if erro:
                self.texto("ERRO|" + erro)
                return
            try:
                mid, porta, qtd = int(q.get("maquina", "")), int(q.get("porta", "")), int(q.get("qtd", ""))
                if not (1 <= porta <= 65535 and 1 <= qtd <= 1000000):
                    raise ValueError
            except ValueError:
                self.texto("ERRO|DADOS")
                return
            seq = ponte.registrar_alerta(c, mid, q.get("origem", ""), porta, qtd)
            self.texto("OK|%d" % seq if seq else "ERRO|MAQUINA")
        elif rota in ("/dados", "/alertas"):
            c, erro = ponte.autenticar(q.get("user"), q.get("hash"))
            if erro:
                self.json({"ok": False, "erro": erro})
                return
            try:
                desde = int(q.get("desde", "0"))
            except ValueError:
                desde = 0
            if rota == "/alertas":
                ponte.marcar_ack(c, desde)
            self.json(ponte.dados_json(c, desde))
        elif rota == "/confirmar":
            c, erro = ponte.autenticar(q.get("user"), q.get("hash"))
            if erro:
                self.texto("ERRO|" + erro)
                return
            try:
                seq = int(q.get("seq", ""))
            except ValueError:
                self.texto("ERRO|DADOS")
                return
            self.texto("OK|1" if ponte.confirmado(c, seq) else "OK|0")
        else:
            self.responder(200, json.dumps(S).encode())

    def do_POST(self):
        try:
            u = urlparse(self.path)
            tam = int(self.headers.get("Content-Length") or 0)
            if u.path != "/sincronizar" or tam <= 0 or tam > LIMITE_CORPO:
                self.responder(400, b'{"ok":false}')
                return
            ok = ponte.sincronizar(self.rfile.read(tam).decode("utf-8"))
            self.responder(200, json.dumps({"ok": ok}).encode())
        except Exception:
            self.responder(400, b'{"ok":false}')

    def do_OPTIONS(self):
        self.responder(204)


def iniciar():
    ThreadingHTTPServer((HOST, PORTA), Handler).serve_forever()
