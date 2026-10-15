#!/usr/bin/env python3
"""Getnet FALSO que imita el contrato de la documentación oficial de Web Checkout (Argentina):
  POST /authentication/oauth2/access_token   → token (solo form-urlencoded)
  POST /payment-intent                       → { payment_intent_id, trade_name, redirect_url }
  GET  /payment-intent/{id}                  → (opcional: la doc pública no muestra consulta; sirve para probar ese camino)
  Aviso (webhook): POST a la URL configurada, con "Authorization: Basic usuario:clave" y el cuerpo de la doc
  (payment_intent_id, order_id, payment.amount, payment.currency, payment.result.status).

Control desde las pruebas:
  POST /_control/webhook {"url","user","pass"}          dónde y con qué credenciales avisa (NO lo borra reset)
  POST /_control/config  {"fallar_crear","fallar_consulta","auth_falla"}
  POST /_control/estado  {"id","status","webhook":true,"veces":1,"store":true,
                          "amount","order_id","currency","omit":["order_id","amount"]}
  POST /_control/reset · GET /_control/estado
"""
import base64, json, sys, threading, urllib.parse, urllib.request
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

PAGOS, CFG, HOOK, CONT = {}, {}, {}, {"auth": 0, "crear": 0, "consulta": 0}
LOCK = threading.Lock()

def cuerpo_aviso(p, status, over):
    amount = over.get("amount", p["payment"]["amount"])
    pay = {"method": "credit", "amount": amount, "currency": over.get("currency", p["payment"]["currency"]),
           "result": {"payment_id": "pay_" + p["id"], "status": status, "transaction_datetime": "2026-10-14T12:00:00.000Z"}}
    out = {"payment_intent_id": p["id"], "checkout_id": "chk_" + p["id"], "order_id": over.get("order_id", p["order_id"]),
           "mode": "instant", "customer": p.get("customer", {}), "payment": pay}
    for o in over.get("omit", []):
        if o == "amount": pay.pop("amount", None)
        else: out.pop(o, None)
    return out

class H(BaseHTTPRequestHandler):
    def log_message(self, *a): pass
    def _json(self, code, obj):
        b = json.dumps(obj).encode()
        self.send_response(code); self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(b))); self.end_headers(); self.wfile.write(b)
    def _body(self):
        n = int(self.headers.get("Content-Length") or 0); return self.rfile.read(n).decode() if n else ""

    def do_POST(self):
        p = self.path.split("?")[0]; raw = self._body()
        if p == "/authentication/oauth2/access_token":
            CONT["auth"] += 1
            if "application/x-www-form-urlencoded" not in (self.headers.get("Content-Type") or ""):
                return self._json(400, {"error": "Invalid Content Type"})
            f = urllib.parse.parse_qs(raw)
            if CFG.get("auth_falla") or f.get("client_secret") != ["secreto-de-prueba"]:
                return self._json(401, {"error": "invalid_client"})
            return self._json(200, {"access_token": "tok-test", "scope": "name-scope:r", "token_type": "Bearer", "expires_in": 3599})
        if p == "/payment-intent":
            if self.headers.get("Authorization") != "Bearer tok-test": return self._json(401, {"error": "unauthorized"})
            CONT["crear"] += 1
            if CFG.get("fallar_crear"): return self._json(500, {"error": "boom"})
            d = json.loads(raw)
            if not (d.get("order_id") and isinstance(d.get("payment", {}).get("amount"), int) and d["payment"].get("currency")):
                return self._json(400, {"error": "campos obligatorios faltantes"})
            with LOCK:
                pid = "pi_%04d" % (len(PAGOS) + 1)
                PAGOS[pid] = dict(d, id=pid, status="Pending")
            return self._json(201, {"payment_intent_id": pid, "trade_name": "Mock Shop", "redirect_url": "http://mock.getnet.test/hosted-web-checkout/" + pid})
        if p == "/_control/webhook": HOOK.update(json.loads(raw)); return self._json(200, HOOK)
        if p == "/_control/config": CFG.update(json.loads(raw)); return self._json(200, CFG)
        if p == "/_control/reset":
            PAGOS.clear(); CFG.clear(); [CONT.__setitem__(k, 0) for k in CONT]; return self._json(200, {"ok": True})
        if p == "/_control/estado":
            d = json.loads(raw); pago = PAGOS[d["id"]]
            if d.get("store", True): pago["status"] = d["status"]
            hook = None
            if d.get("webhook"):
                for _ in range(int(d.get("veces", 1))):
                    body = json.dumps(cuerpo_aviso(pago, d["status"], d)).encode()
                    h = {"Content-Type": "application/json"}
                    basic = d.get("basic", HOOK["user"] + ":" + HOOK["pass"])
                    if basic is not None: h["Authorization"] = "Basic " + base64.b64encode(basic.encode()).decode()
                    rq = urllib.request.Request(d.get("url", HOOK["url"]), data=body, headers=h, method="POST")
                    try: hook = urllib.request.urlopen(rq, timeout=20).status
                    except urllib.error.HTTPError as e: hook = e.code
            return self._json(200, {"status": pago["status"], "webhook_http": hook})
        self._json(404, {"error": "no existe"})

    def do_GET(self):
        p = self.path.split("?")[0]
        if p.startswith("/payment-intent/"):
            if self.headers.get("Authorization") != "Bearer tok-test": return self._json(401, {"error": "unauthorized"})
            CONT["consulta"] += 1
            if CFG.get("fallar_consulta"): return self._json(500, {"error": "boom"})
            pid = p.split("/")[-1]
            if pid not in PAGOS: return self._json(404, {"error": "no existe"})
            return self._json(200, cuerpo_aviso(PAGOS[pid], PAGOS[pid]["status"], {}))
        if p == "/_control/estado": return self._json(200, {"pagos": PAGOS, "cont": CONT, "cfg": CFG})
        self._json(404, {"error": "no existe"})

if __name__ == "__main__":
    ThreadingHTTPServer(("127.0.0.1", int(sys.argv[1]) if len(sys.argv) > 1 else 9100), H).serve_forever()
