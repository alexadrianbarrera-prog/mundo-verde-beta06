#!/usr/bin/env python3
"""Servidor SMTP falso (acepta AUTH PLAIN/LOGIN): guarda los mails recibidos para que las pruebas los cuenten."""
import json, socketserver, sys, threading
MAILS = []

class S(socketserver.StreamRequestHandler):
    def w(self, s): self.wfile.write((s + "\r\n").encode())
    def handle(self):
        self.w("220 sink ESMTP"); rcpt = []
        while True:
            l = self.rfile.readline()
            if not l: return
            c = l.decode(errors="ignore").strip(); u = c.upper()
            if u.startswith("EHLO") or u.startswith("HELO"):
                self.wfile.write(b"250-sink\r\n250 AUTH PLAIN LOGIN\r\n")
            elif u.startswith("AUTH LOGIN"):
                self.w("334 VXNlcm5hbWU6"); self.rfile.readline(); self.w("334 UGFzc3dvcmQ6"); self.rfile.readline(); self.w("235 ok")
            elif u.startswith("AUTH PLAIN"): self.w("235 ok")
            elif u.startswith("MAIL FROM"): rcpt = []; self.w("250 ok")
            elif u.startswith("RCPT TO"): rcpt.append(c[8:].strip("<> ")); self.w("250 ok")
            elif u == "DATA":
                self.w("354 go"); data = []
                while True:
                    d = self.rfile.readline().decode(errors="ignore")
                    if d.strip() == ".": break
                    data.append(d)
                MAILS.append({"to": rcpt, "data": "".join(data)}); self.w("250 queued")
            elif u == "QUIT": self.w("221 bye"); return
            else: self.w("250 ok")

class Ctl(socketserver.StreamRequestHandler):   # puerto de control: "COUNT", "DUMP", "RESET"
    def handle(self):
        cmd = self.rfile.readline().decode().strip()
        if cmd == "RESET": MAILS.clear()
        self.wfile.write(json.dumps([{"to": m["to"], "subject": next((x for x in m["data"].split("\n") if x.lower().startswith("subject:")), "")} for m in MAILS]).encode())

class T(socketserver.ThreadingMixIn, socketserver.TCPServer): allow_reuse_address = True; daemon_threads = True
if __name__ == "__main__":
    p = int(sys.argv[1]) if len(sys.argv) > 1 else 2525
    threading.Thread(target=T(("127.0.0.1", p + 1), Ctl).serve_forever, daemon=True).start()
    T(("127.0.0.1", p), S).serve_forever()
