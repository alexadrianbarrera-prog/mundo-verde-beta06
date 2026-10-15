#!/usr/bin/env python3
"""
Banco de pruebas del pago con tarjeta (Getnet) — corre TODO el backend real (PHP + MySQL) contra un
Getnet FALSO (mock_getnet.py) y un SMTP falso. No toca tu base ni el Getnet real.

Qué valida y qué NO:
  ✔ La lógica de Mundo Verde: pedidos, estados, webhook, remito, Código Verde, referencias, permisos.
  ✘ NO valida que el contrato real de Getnet (paths/campos) sea el que se supone: eso se prueba
    contra el sandbox de Getnet (ver tests/README.md).

Requisitos: php-cli (con pdo_mysql y curl), python3, cliente mysql y un MySQL/MariaDB local con
usuario root + la contraseña de config/conexion.local.php (la base "mundo_verde" se BORRA y recrea).
Uso:  python3 tests/run_tests.py
"""
import json, os, re, shutil, socket, subprocess, sys, tempfile, time, urllib.request, urllib.error

SRC = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
TMP = tempfile.mkdtemp(prefix="mvtest_")
APP = os.path.join(TMP, "mundo_verde_backend")
PHP_PORT, MOCK_PORT, SMTP_PORT = 18080, 19100, 12525
BACK = f"http://127.0.0.1:{PHP_PORT}/mundo_verde_backend/api"
MOCK = f"http://127.0.0.1:{MOCK_PORT}"
procs, RES = [], []

def leer_pass():
    t = open(os.path.join(SRC, "config", "conexion.local.php"), encoding="utf-8").read()
    return re.search(r"\$pass\s*=\s*'([^']*)'", t).group(1)
DBPASS = leer_pass()

def sql(q, db="mundo_verde"):
    r = subprocess.run(["mysql", "-uroot", f"-p{DBPASS}", "--default-character-set=utf8mb4", "-N", db, "-e", q],
                       capture_output=True, text=True, errors="replace")
    if r.returncode: raise RuntimeError(r.stderr)
    return [l.split("\t") for l in r.stdout.splitlines()]
def one(q):
    r = sql(q); return r[0][0] if r else None

def http(method, url, body=None, token=None, raw=False):
    h = {"Content-Type": "application/json"}
    if token: h["Authorization"] = "Bearer " + token
    rq = urllib.request.Request(url, data=(json.dumps(body).encode() if body is not None else None), headers=h, method=method)
    try:
        with urllib.request.urlopen(rq, timeout=40) as r: code, txt = r.status, r.read().decode()
    except urllib.error.HTTPError as e: code, txt = e.code, e.read().decode()
    try: return code, json.loads(txt)
    except Exception: return code, txt
def api(m, p, b=None, t=None): return http(m, BACK + p, b, t)
def mock(m, p, b=None): return http(m, MOCK + p, b)[1]
def mails():
    s = socket.create_connection(("127.0.0.1", SMTP_PORT + 1)); s.sendall(b"DUMP\n"); d = b""
    while True:
        c = s.recv(65536)
        if not c: break
        d += c
    return json.loads(d)
def mails_reset():
    s = socket.create_connection(("127.0.0.1", SMTP_PORT + 1)); s.sendall(b"RESET\n"); s.recv(10); s.close()
def esperar(cond, seg=8):
    t = time.time()
    while time.time() - t < seg:
        if cond(): return True
        time.sleep(0.2)
    return False

def ok(nombre, cond, detalle=""):
    RES.append((bool(cond), nombre, detalle)); print(("  ✔ " if cond else "  ✘ ") + nombre + ("" if cond else f"   ← {detalle}"))
def seccion(t): print("\n" + t)

# ───────────── infraestructura ─────────────
CFG_BASE = """<?php return [
 'entorno'=>'sandbox',
 'sandbox'=>['client_id'=>'cid_test','client_secret'=>'secreto-de-prueba',
   'auth_url'=>'%(m)s/authentication/oauth2/access_token','api_base'=>'%(m)s'],
 'produccion'=>['client_id'=>'x','client_secret'=>'x','auth_url'=>'x','api_base'=>'x'],
 'api'=>%(api)s,
 'return_url'=>'http://127.0.0.1/pago_resultado.html',
 'webhook_usuario'=>'getnet-user',
 'webhook_password'=>'getnet-pass',
 'webhook_token'=>'token-de-prueba-123',
 %(top)s
];"""
HOOK_URL = f"{BACK}/pagos/webhook?k=token-de-prueba-123"
def php_arr(d): return "[" + ",".join(f"{json.dumps(k)}=>{json.dumps(v)}" for k, v in d.items()) + "]"
def escribir_cfg(api_extra=None, top=None):
    api = {"crear_pago_path": "/payment-intent"}      # sin consulta de estado, como en la doc pública
    api.update(api_extra or {})
    top_php = "".join(f"{json.dumps(k)}=>{json.dumps(v)}," for k, v in (top or {}).items())
    cfg = CFG_BASE % {"m": MOCK, "b": BACK, "api": php_arr(api), "top": top_php}
    if top_php: cfg = cfg.replace("'webhook_token'=>'token-de-prueba-123',", "", 1) if "webhook_token" in (top or {}) else cfg
    open(os.path.join(APP, "config", "getnet.config.php"), "w").write(cfg)

def preparar():
    shutil.copytree(SRC, APP, ignore=shutil.ignore_patterns("__pycache__"))
    escribir_cfg()
    mp = os.path.join(APP, "api", "controllers", "mailer.php")
    m = open(mp, encoding="utf-8").read()
    assert ": PHPMailer::ENCRYPTION_STARTTLS;" in m
    open(mp, "w", encoding="utf-8").write(m.replace(": PHPMailer::ENCRYPTION_STARTTLS;", ": '';"))
    open(os.path.join(APP, "api", "controllers", "config_mail.php"), "w").write(
        f"<?php return ['host'=>'127.0.0.1','port'=>{SMTP_PORT},'usuario'=>'t@t.test','password'=>'x','from_email'=>'t@t.test','from_name'=>'MV','reply_to'=>'t@t.test'];")
    # la copia de pruebas usa SIEMPRE la base local, aunque falte conexion.produccion.php
    sql("DROP DATABASE IF EXISTS mundo_verde", db="mysql")
    for f in ["mundo_verde.sql", "alter_pedidos.sql", "alter_pedidos_getnet.sql", "migracion_referencia_pago.sql",
              "alter_usuarios.sql", "migracion_registro_corto.sql", "../tests/esquema_test.sql"]:
        dbarg = "" if f == "mundo_verde.sql" else "mundo_verde"   # el primero crea la base; el resto necesita elegirla
        r = subprocess.run(f"mysql -uroot -p'{DBPASS}' --default-character-set=utf8mb4 {dbarg} < {os.path.join(SRC,'sql',f)}",
                           shell=True, capture_output=True, text=True, executable="/bin/bash")
        if r.returncode and "alter_emails" not in f: print("  (aviso SQL)", f, r.stderr.strip()[:120])
    sql("DELETE FROM sesiones"); sql("DELETE FROM usuarios"); sql("DELETE FROM newsletter_suscriptores")
    sql("INSERT INTO configuracion VALUES ('inauguracion_activa','0'),('newsletter_pct','10')")
    sql("DELETE FROM sesiones"); sql("DELETE FROM usuarios")   # el SQL del zip trae usuarios de ejemplo
    for i, (nom, mail, rol, tok) in enumerate([("Ana Test", "ana@test.com", "cliente", "t" * 64),
                                               ("Beto Test", "beto@test.com", "cliente", "u" * 64),
                                               ("Admin", "admin@test.com", "admin", "a" * 64)], 1):
        sql(f"INSERT INTO usuarios (id,nombre,email,password_hash,rol) VALUES ({i},'{nom}','{mail}','x','{rol}')")
        sql(f"INSERT INTO sesiones (usuario_id,token,expira_en) VALUES ({i},'{tok}', NOW() + INTERVAL 1 DAY)")
    for i in range(1, 9):   # 8 suscriptores con Código Verde propio
        sql(f"INSERT INTO newsletter_suscriptores (nombre,mail,codigo_mio,codigo_verde) VALUES ('Sub{i}','sub{i}@test.com','REF{i:05d}','MV-TEST0{i}')")

def levantar():
    env = dict(os.environ, PHP_CLI_SERVER_WORKERS="6")
    procs.append(subprocess.Popen(["python3", os.path.join(SRC, "tests", "mock_getnet.py"), str(MOCK_PORT)]))
    procs.append(subprocess.Popen(["python3", os.path.join(SRC, "tests", "smtp_sink.py"), str(SMTP_PORT)]))
    procs.append(subprocess.Popen(["php", "-d", "opcache.enable=0", "-S", f"127.0.0.1:{PHP_PORT}", os.path.join(APP, "tests", "router.php")],
                                  env=env, stdout=subprocess.DEVNULL, stderr=open(os.path.join(TMP, "php_errors.log"), "w")))
    for _ in range(50):
        try:
            http("GET", MOCK + "/_control/estado"); http("GET", BACK + "/productos")
            mock("POST", "/_control/webhook", {"url": HOOK_URL, "user": "getnet-user", "pass": "getnet-pass"}); return
        except Exception: time.sleep(0.2)
    raise RuntimeError("no levantaron los servidores de prueba")

def reset_estado():
    time.sleep(0.8)   # el remito sale DESPUÉS de responderle a Getnet: se espera a que termine el anterior
    mock("POST", "/_control/reset"); mails_reset()
    sql("DELETE FROM pedido_items"); sql("DELETE FROM pedidos")
    sql("UPDATE newsletter_suscriptores SET codigo_usado_en=NULL, codigo_usado_pedido_id=NULL")

# ───────────── helpers de pedido ─────────────
ANA, BETO, ADMIN = "t" * 64, "u" * 64, "a" * 64
_ref = [0]
def pedido(items=None, email="inv@test.com", forma="tarjeta", codigo=None, token=None, ref=None, entrega="retiro", extra=None):
    _ref[0] += 1
    b = {"items": items or [{"codigo": "ARB-01", "cantidad": 2}], "forma_entrega": entrega, "forma_pago": forma,
         "cliente": {"nombre": "Cliente", "email": email, "celular": "1111"},
         "retiro": {"nombre": "Quien Retira", "dni": "30111222"} if entrega == "retiro" else {},
         "envio": {"domicilio": "Calle 1", "localidad": "Quilmes", "cp": "1878"} if entrega == "envio" else {},
         "codigo_verde": codigo or ""}
    if forma in ("tarjeta", "transferencia"):
        b["referencia_pago"] = ref or f"MV-1310-{_ref[0]:04d}"
    if extra: b.update(extra)
    return api("POST", "/pedidos", b, token)
def pagar(pid, token=None): return api("POST", "/pagos/tarjeta", {"pedido_id": pid}, token)
def estado(pid): return one(f"SELECT estado FROM pedidos WHERE id={pid}")
def webhook(gid, status, veces=1, **over): return mock("POST", "/_control/estado", dict({"id": gid, "status": status, "webhook": True, "veces": veces}, **over))
def aprobar(gid, **o): return webhook(gid, "Authorized", **o)
def rechazar(gid, **o): return webhook(gid, "Denied", **o)
def hook_directo(body, k="token-de-prueba-123", basic=("getnet-user", "getnet-pass")):
    """POST al webhook del backend como lo haría Getnet (o un atacante), eligiendo qué credenciales mandar."""
    import base64
    h = {"Content-Type": "application/json"}
    if basic: h["Authorization"] = "Basic " + base64.b64encode(f"{basic[0]}:{basic[1]}".encode()).decode()
    url = f"{BACK}/pagos/webhook" + (f"?k={k}" if k else "")
    rq = urllib.request.Request(url, data=json.dumps(body).encode(), headers=h, method="POST")
    try:
        with urllib.request.urlopen(rq, timeout=20) as r: return r.status
    except urllib.error.HTTPError as e: return e.code
def intent(pid): return mock("GET", "/_control/estado")["pagos"][gid_de(pid)]
def gid_de(pid): return one(f"SELECT getnet_id FROM pedidos WHERE id={pid}")
def codigo_libre(c): return one(f"SELECT codigo_usado_en IS NULL FROM newsletter_suscriptores WHERE codigo_verde='{c}'") == "1"
def nuevo_pagado(**kw):
    c, p = pedido(**kw); pid = p["id"]; pagar(pid, kw.get("token")); return pid

# ───────────── pruebas ─────────────
def estado_api(pid, ref): return api("GET", f"/pagos/estado?id={pid}&ref={ref}")[1].get("estado")

def pruebas():
    seccion("1. Flujo feliz: pedido → pago → aprobación (contrato de la doc de Getnet)")
    reset_estado()
    c, p = pedido(email="feliz@test.com"); pid = p.get("id")
    ok("el pedido con tarjeta se crea (201) y queda pendiente", c == 201 and p.get("estado") == "pendiente", f"{c} {p}")
    ok("con tarjeta NO sale remito al crear el pedido", not esperar(lambda: len(mails()) > 0, 1.5), mails())
    c, r = pagar(pid)
    ok("pagos/tarjeta devuelve la redirect_url de Getnet", c == 200 and str(r.get("url", "")).startswith("http://mock.getnet.test/hosted-web-checkout/"), f"{c} {r}")
    gp = intent(pid)
    ok("se guardó el payment_intent_id en el pedido", gid_de(pid) == "pi_0001", gid_de(pid))
    ok("order_id = referencia del pedido (así concilia el aviso)", gp.get("order_id") == p["referencia_pago"], gp.get("order_id"))
    ok("monto en centavos: 2×$5.000 → 1000000", gp["payment"]["amount"] == 1000000, gp["payment"])
    ok("moneda ARS", gp["payment"]["currency"] == "ARS")
    ok("mode = instant", gp.get("mode") == "instant")
    ok("el link de pago vence en 1h (expires_at)", gp.get("expires_at") == "1h", gp.get("expires_at"))
    ok("success_url y error_url vuelven a pago_resultado con id y referencia",
       all(f"id={pid}" in gp["configurations"][k] and p["referencia_pago"] in gp["configurations"][k] for k in ("success_url", "error_url")), gp["configurations"])
    ok("viaja el mail y el nombre del cliente", gp["customer"].get("email") == "feliz@test.com" and gp["customer"].get("name") == "Cliente", gp["customer"])
    ok("viaja una línea de producto por el total", gp["product"][0]["value"] == 1000000 and gp["product"][0]["quantity"] == 1, gp["product"])
    ok("mientras Getnet no avisa, el estado sigue pendiente", estado_api(pid, p["referencia_pago"]) == "pendiente")
    h = aprobar(gp["id"])
    ok("el aviso de Getnet (Basic + ?k=) responde 200", h.get("webhook_http") == 200, h)
    ok("el pedido pasa a pagado", estado(pid) == "pagado", estado(pid))
    ok("pagos/estado informa pagado (lo que lee pago_resultado.html)", estado_api(pid, p["referencia_pago"]) == "pagado")
    ok("sale UN remito al mail del cliente", esperar(lambda: len(mails()) >= 1) and len(mails()) == 1 and "feliz@test.com" in mails()[0]["to"], mails())

    seccion("2. Idempotencia y reintentos del aviso (Getnet reintenta hasta 72 h)")
    aprobar(gp["id"], veces=3); time.sleep(1.5)
    ok("3 avisos más no duplican el remito", len(mails()) == 1, len(mails()))
    ok("el pedido sigue pagado", estado(pid) == "pagado")
    rechazar(gp["id"])
    ok("un 'Denied' tardío NO pisa un pedido ya pagado", estado(pid) == "pagado", estado(pid))

    seccion("3. Seguridad del aviso (webhook)")
    reset_estado(); c, p = pedido(); pagar(p["id"]); g = gid_de(p["id"])
    cuerpo = {"payment_intent_id": g, "order_id": p["referencia_pago"],
              "payment": {"amount": 1000000, "currency": "ARS", "result": {"status": "Authorized"}}}
    ok("sin Authorization Basic → 401", hook_directo(cuerpo, basic=None) == 401)
    ok("Basic con clave incorrecta → 401", hook_directo(cuerpo, basic=("getnet-user", "mala")) == 401)
    ok("Basic con usuario incorrecto → 401", hook_directo(cuerpo, basic=("otro", "getnet-pass")) == 401)
    ok("sin ?k= (aunque el Basic esté bien) → 401", hook_directo(cuerpo, k=None) == 401)
    ok("?k= incorrecto → 401", hook_directo(cuerpo, k="mal") == 401)
    ok("ningún intento no autorizado cambió el pedido", estado(p["id"]) == "pendiente")
    ok("pago desconocido → 200 sin cambios (para que Getnet no reintente)",
       hook_directo({"payment_intent_id": "no-existe", "order_id": "MV-0000-0000", "payment": {"amount": 1, "result": {"status": "Authorized"}}}) == 200)
    ok("cuerpo sin payment_intent_id ni order_id → 400", hook_directo({"payment": {}}) == 400)
    webhook(g, "Pending")
    ok("un estado en curso ('Pending') no cambia el pedido", estado(p["id"]) == "pendiente")
    ok("el aviso correcto sí lo confirma", hook_directo(cuerpo) == 200 and estado(p["id"]) == "pagado")

    seccion("4. Pago rechazado libera el Código Verde")
    reset_estado()
    c, p = pedido(codigo="MV-TEST01"); pid = p["id"]
    ok("total con Código Verde = $9.000 (10% de $10.000), calculado por el servidor", c == 201 and p["total"] == 9000, p)
    pagar(pid); gp = intent(pid)
    ok("Getnet recibió 900000 centavos", gp["payment"]["amount"] == 900000, gp["payment"])
    ok("el código quedó consumido mientras el pedido está pendiente", not codigo_libre("MV-TEST01"))
    rechazar(gp["id"])
    ok("pago rechazado (Denied) → pedido cancelado", estado(pid) == "cancelado")
    ok("…y el Código Verde vuelve a estar libre", codigo_libre("MV-TEST01"))
    ok("…y NO sale remito", len(mails()) == 0, mails())
    ok("pagos/estado informa cancelado", estado_api(pid, p["referencia_pago"]) == "cancelado")

    seccion("5. Problema 1 — abandonó Getnet y vuelve a intentar con su Código Verde")
    reset_estado()
    c, a = pedido(codigo="MV-TEST02", email="aban@test.com"); pagar(a["id"])   # entra a Getnet y NO paga
    ok("el código está retenido por el pedido abandonado", not codigo_libre("MV-TEST02"))
    ok("validar-codigo lo ofrece de nuevo (lo ve el carrito)", api("GET", "/newsletter/validar-codigo?codigo=MV-TEST02")[1].get("valido") is True)
    ok("el pedido abandonado quedó cancelado", estado(a["id"]) == "cancelado", estado(a["id"]))
    reset_estado()
    c, a = pedido(codigo="MV-TEST02"); pagar(a["id"])
    c, b = pedido(codigo="MV-TEST02")
    ok("un pedido nuevo con el MISMO código ahora entra (201) con el 10%", c == 201 and b.get("total") == 9000, f"{c} {b}")
    ok("el pedido viejo se cancela y el código pasa al nuevo", estado(a["id"]) == "cancelado" and one(f"SELECT codigo_usado_pedido_id FROM newsletter_suscriptores WHERE codigo_verde='MV-TEST02'") == str(b["id"]))
    seccion("   …y si el pago del pedido viejo SÍ se hizo pero el aviso llega tarde")
    reset_estado()
    c, a = pedido(codigo="MV-TEST04", email="tarde@test.com"); pagar(a["id"]); ga = gid_de(a["id"])
    c, b = pedido(codigo="MV-TEST04")          # sin endpoint de consulta no hay forma de saberlo: cancela a
    ok("(preparación) el pedido A quedó cancelado y B tiene el código", estado(a["id"]) == "cancelado" and c == 201)
    aprobar(ga)
    ok("Getnet autoriza tarde → el pedido A vuelve a pagado (la plata entró)", estado(a["id"]) == "pagado", estado(a["id"]))
    ok("…y sale su remito", esperar(lambda: len(mails()) == 1), mails())
    seccion("   …y el estado del código al consultar newsletter")
    reset_estado()
    c, a = pedido(codigo="MV-TEST05"); pagar(a["id"])
    r = api("GET", "/newsletter/mi-estado?mail=sub5@test.com")[1]
    ok("mi-estado ya no lo muestra como usado", r.get("codigo_verde_usado") is False, r)

    seccion("6. Pedidos de tarjeta vencidos (limpieza automática y cron)")
    reset_estado()
    c, viejo = pedido(codigo="MV-TEST06"); pagar(viejo["id"])
    c, reciente = pedido(codigo="MV-TEST07"); pagar(reciente["id"])
    c, transf = pedido(forma="transferencia"); c, efec = pedido(forma="efectivo", email="")
    for i in (viejo["id"], transf["id"], efec["id"]): sql(f"UPDATE pedidos SET creado_en = NOW() - INTERVAL 3 HOUR WHERE id={i}")
    sql(f"UPDATE pedidos SET creado_en = NOW() - INTERVAL 10 MINUTE WHERE id={reciente['id']}")
    pedido(email="otro@test.com")      # cualquier pedido nuevo dispara la limpieza
    ok("el de tarjeta de hace 3 h se cancela y libera su código", estado(viejo["id"]) == "cancelado" and codigo_libre("MV-TEST06"), estado(viejo["id"]))
    ok("el de tarjeta de hace 10 min NO se toca", estado(reciente["id"]) == "pendiente")
    ok("transferencia y efectivo viejos NO se tocan", estado(transf["id"]) == "pendiente" and estado(efec["id"]) == "pendiente")
    c, v2 = pedido(codigo="MV-TEST08"); pagar(v2["id"]); sql(f"UPDATE pedidos SET creado_en = NOW() - INTERVAL 2 HOUR WHERE id={v2['id']}")
    r = subprocess.run(["php", os.path.join(APP, "api", "cron_pagos.php")], env=dict(os.environ, MV_LOCAL="1"), capture_output=True, text=True)
    ok("el cron por consola también los cierra", estado(v2["id"]) == "cancelado" and "cerrados: 1" in r.stdout, r.stdout + r.stderr)
    ok("el cron no responde por web", http("GET", f"http://127.0.0.1:{PHP_PORT}/mundo_verde_backend/api/cron_pagos.php")[0] == 404)

    seccion("7. Problema 2 — mail obligatorio para pagar con tarjeta")
    reset_estado()
    c, r = pedido(email="")
    ok("invitado SIN mail → 400 con mensaje claro", c == 400 and "mail" in json.dumps(r).lower(), f"{c} {r}")
    c, r = pedido(email="esto-no-es-un-mail")
    ok("mail inválido → 400", c == 400, f"{c} {r}")
    ok("no se creó ningún pedido en esos intentos", one("SELECT COUNT(*) FROM pedidos") == "0")
    c, r = pedido(email="", token=ANA)
    ok("usuario logueado sin mail en el body usa el de su cuenta (201)", c == 201 and one(f"SELECT cliente_email FROM pedidos WHERE id={r['id']}") == "ana@test.com", f"{c} {r}")
    pagar(r["id"], ANA); gp = intent(r["id"])
    ok("Getnet recibe el mail de la cuenta", gp["customer"]["email"] == "ana@test.com", gp["customer"])
    c, r = pedido(forma="efectivo", email="")
    ok("efectivo sin mail sigue funcionando (no se exige)", c == 201, f"{c} {r}")
    c, r = pedido(forma="transferencia", email="")
    ok("transferencia sin mail sigue funcionando", c == 201, f"{c} {r}")
    ok("el mail vacío se guarda como NULL (no como texto vacío)", one(f"SELECT cliente_email IS NULL FROM pedidos WHERE id={r['id']}") == "1")

    seccion("8. Problema 3 — número de pedido único")
    reset_estado()
    c, x = pedido(forma="transferencia", ref="MV-1310-7777")
    c, t = pedido(ref="MV-1310-7777")
    ok("tarjeta con un número YA usado → el backend asigna otro", c == 201 and t["referencia_pago"] != "MV-1310-7777" and re.fullmatch(r"MV-\d{4}-\d{4}", t["referencia_pago"]), t)
    c, t2 = pedido(ref="MV-1310-8888")
    ok("tarjeta con un número libre → se respeta", t2["referencia_pago"] == "MV-1310-8888", t2)
    body = {"items": [{"codigo": "ARB-01", "cantidad": 1}], "forma_entrega": "retiro", "forma_pago": "tarjeta",
            "cliente": {"email": "sinref@test.com"}, "retiro": {"nombre": "X", "dni": "30111222"}}
    c, t4 = api("POST", "/pedidos", body)
    ok("tarjeta sin número del front → el backend genera uno válido", c == 201 and re.fullmatch(r"MV-\d{4}-\d{4}", t4.get("referencia_pago") or ""), f"{c} {t4}")
    refs = [one(f"SELECT referencia_pago FROM pedidos WHERE id={i}") for i in (t["id"], t2["id"], t4["id"])]
    ok("ninguna referencia de tarjeta se repite", len(set(refs)) == 3, refs)
    pagar(t["id"])
    ok("a Getnet viaja la referencia FINAL (order_id asignado por el backend)", intent(t["id"])["order_id"] == t["referencia_pago"])
    ok("pagos/estado funciona con la referencia nueva", api("GET", f"/pagos/estado?id={t['id']}&ref={t['referencia_pago']}")[0] == 200)
    c, tr = pedido(forma="transferencia", ref="MV-1310-7777")
    ok("transferencia: se respeta el número del front (el cliente ya lo usó como concepto)", tr["referencia_pago"] == "MV-1310-7777", tr)

    seccion("9. Permisos y estados inválidos en pagos/tarjeta")
    reset_estado()
    c, mio = pedido(token=ANA, email="ana@test.com")
    ok("otro usuario logueado NO puede pagar el pedido de Ana (403)", pagar(mio["id"], BETO)[0] == 403)
    ok("un anónimo NO puede pagar el pedido de un usuario logueado (403)", pagar(mio["id"])[0] == 403)
    ok("el admin sí puede", pagar(mio["id"], ADMIN)[0] == 200)
    c, ef = pedido(forma="efectivo")
    ok("pedido que no es de tarjeta → 409", pagar(ef["id"])[0] == 409)
    ok("pedido inexistente → 404", pagar(999999)[0] == 404)
    ok("pedido_id inválido → 400", api("POST", "/pagos/tarjeta", {})[0] == 400)
    aprobar(gid_de(mio["id"]))
    ok("pedido ya pagado no admite otro pago (409)", pagar(mio["id"], ANA)[0] == 409)
    ok("pagos/estado con referencia que no corresponde → 404 (no se puede enumerar)", api("GET", f"/pagos/estado?id={mio['id']}&ref=MV-0000-0000")[0] == 404)
    c, inv = pedido(extra={"descuentos": {"total": 1, "descuento": 9999, "inauguracion_pct": 0}})
    ok("el total NO se puede manipular desde el navegador (descuentos del front ignorados)", inv.get("total") == 10000, inv)
    c, r = pedido(items=[{"codigo": "NO-EXISTE", "cantidad": 1}])
    ok("producto inexistente → 400", c == 400)

    seccion("10. Caídas de Getnet")
    reset_estado()
    c, p = pedido(); mock("POST", "/_control/config", {"fallar_crear": True})
    c, r = pagar(p["id"])
    ok("si Getnet falla al crear el pago → 502 con mensaje genérico", c == 502 and "boom" not in json.dumps(r), f"{c} {r}")
    ok("el pedido sigue pendiente y sin id de Getnet (se puede reintentar)", estado(p["id"]) == "pendiente" and gid_de(p["id"]) in (None, "NULL", ""))
    mock("POST", "/_control/config", {"fallar_crear": False})
    ok("reintentar sobre el MISMO pedido funciona", pagar(p["id"])[0] == 200 and gid_de(p["id"]) is not None)
    mock("POST", "/_control/config", {"auth_falla": True}); c, p2 = pedido()
    import glob
    for f in glob.glob("/tmp/mv_getnet_token_*"): os.remove(f)
    c, r = pagar(p2["id"])
    ok("credenciales inválidas → 502 genérico (no se filtra el detalle)", c == 502 and "invalid_client" not in json.dumps(r), f"{c} {r}")
    mock("POST", "/_control/config", {"auth_falla": False})

    seccion("11. Verificación de referencia, monto y moneda en el aviso")
    reset_estado(); c, p = pedido(); pagar(p["id"]); g = gid_de(p["id"])
    webhook(g, "Authorized", amount=100)
    ok("monto del aviso ≠ total del pedido → NO se marca pagado", estado(p["id"]) == "pendiente", estado(p["id"]))
    webhook(g, "Authorized", order_id="MV-0000-0000")
    ok("referencia (order_id) distinta → NO se marca pagado", estado(p["id"]) == "pendiente")
    webhook(g, "Authorized", currency="USD")
    ok("moneda distinta de ARS → NO se marca pagado", estado(p["id"]) == "pendiente")
    ok("aprobado SIN monto en el aviso → 400 (Getnet reintenta) y no se aprueba",
       webhook(g, "Authorized", omit=["amount"]).get("webhook_http") == 400 and estado(p["id"]) == "pendiente")
    ok("aprobado SIN order_id: se concilia por payment_intent_id pero se exige la referencia → 400",
       webhook(g, "Authorized", omit=["order_id"]).get("webhook_http") == 400 and estado(p["id"]) == "pendiente")
    webhook(g, "Authorized")
    ok("con todo correcto → pagado", estado(p["id"]) == "pagado")
    reset_estado(); c, p = pedido(); pagar(p["id"])
    webhook(gid_de(p["id"]), "Approved")
    ok("pago por QR ('Approved') también confirma el pedido", estado(p["id"]) == "pagado")
    reset_estado(); c, p = pedido(); pagar(p["id"])
    ok("aviso por order_id cuando el id no coincide (se concilia por la referencia)",
       hook_directo({"payment_intent_id": "otro-id", "order_id": p["referencia_pago"], "payment": {"amount": 1000000, "currency": "ARS", "result": {"status": "Authorized"}}}) == 200
       and estado(p["id"]) == "pagado")
    escribir_cfg({"monto": "decimal"}); reset_estado()
    c, p = pedido(); pagar(p["id"])
    ok("modo 'decimal': Getnet recibe 10000.0 en vez de centavos", intent(p["id"])["payment"]["amount"] in (10000, 10000.0) and not isinstance(intent(p["id"])["payment"]["amount"], str))
    escribir_cfg({"crear_pago_path": "COMPLETAR"}); reset_estado()
    c, p = pedido(); c, r = pagar(p["id"])
    ok("si falta completar el path en la config → 502 (no pega a una URL inventada)", c == 502, f"{c} {r}")
    ok("…y lo deja escrito en el log del servidor", "falta completar api.crear_pago_path" in open(os.path.join(TMP, "php_errors.log")).read())
    escribir_cfg()

    seccion("12. Barreras del aviso configurables")
    escribir_cfg(top={"webhook_token": ""}); reset_estado(); c, p = pedido(); pagar(p["id"])
    cuerpo = {"payment_intent_id": gid_de(p["id"]), "order_id": p["referencia_pago"], "payment": {"amount": 1000000, "currency": "ARS", "result": {"status": "Authorized"}}}
    ok("solo con Basic (sin token): sin credenciales → 401", hook_directo(cuerpo, k=None, basic=None) == 401)
    ok("solo con Basic (sin token): con credenciales correctas → 200 y pagado", hook_directo(cuerpo, k=None) == 200 and estado(p["id"]) == "pagado")
    escribir_cfg(top={"webhook_token": "", "webhook_usuario": "", "webhook_password": ""}); reset_estado(); c, p = pedido(); pagar(p["id"])
    cuerpo["payment_intent_id"] = gid_de(p["id"]); cuerpo["order_id"] = p["referencia_pago"]
    ok("SIN ninguna barrera configurada se rechaza todo (401), incluso con datos válidos", hook_directo(cuerpo, k=None, basic=None) == 401 and estado(p["id"]) == "pendiente")
    escribir_cfg()

    seccion("13. Si Getnet ofrece consulta de estado (consultar_pago_path): se confirma contra Getnet")
    escribir_cfg({"consultar_pago_path": "/payment-intent/{id}"}); reset_estado()
    c, p = pedido(); pagar(p["id"]); g = gid_de(p["id"])
    webhook(g, "Authorized", store=False)    # el aviso dice Authorized pero Getnet (consulta) sigue en Pending
    ok("aviso 'Authorized' que la consulta a Getnet NO confirma → no se aprueba", estado(p["id"]) == "pendiente", estado(p["id"]))
    mock("POST", "/_control/config", {"fallar_consulta": True})
    ok("consulta caída → 502 (Getnet reintenta) y el pedido sigue pendiente", webhook(g, "Authorized").get("webhook_http") == 502 and estado(p["id"]) == "pendiente")
    mock("POST", "/_control/config", {"fallar_consulta": False})
    ok("con Getnet disponible el reintento lo confirma", webhook(g, "Authorized").get("webhook_http") == 200 and estado(p["id"]) == "pagado")
    seccion("   …Problema 1 con consulta: si el pedido viejo SÍ estaba pagado")
    reset_estado()
    c, a = pedido(codigo="MV-TEST03", email="pago@test.com"); pagar(a["id"])
    mock("POST", "/_control/estado", {"id": gid_de(a["id"]), "status": "Authorized", "webhook": False})   # cobró pero el aviso no llegó
    c, b = pedido(codigo="MV-TEST03")
    ok("el código NO se reasigna (409 'ya fue utilizado')", c == 409 and "utilizado" in json.dumps(b), f"{c} {b}")
    ok("el pedido viejo se confirma como pagado", estado(a["id"]) == "pagado", estado(a["id"]))
    ok("…y sale su remito", esperar(lambda: len(mails()) == 1), mails())
    seccion("   …y un pedido vencido que en realidad se pagó")
    reset_estado()
    c, a = pedido(email="venc@test.com"); pagar(a["id"]); sql(f"UPDATE pedidos SET creado_en = NOW() - INTERVAL 3 HOUR WHERE id={a['id']}")
    mock("POST", "/_control/estado", {"id": gid_de(a["id"]), "status": "Authorized", "webhook": False})
    pedido(email="x@test.com")
    ok("la limpieza NO cancela un pedido que Getnet ya autorizó: lo marca pagado", estado(a["id"]) == "pagado", estado(a["id"]))
    escribir_cfg()

# ───────────── main ─────────────
if __name__ == "__main__":
    try:
        preparar(); levantar(); pruebas()
    finally:
        for p in procs: p.terminate()
        if "--keep" not in sys.argv: shutil.rmtree(TMP, ignore_errors=True)
    bien = sum(1 for r in RES if r[0]); mal = [r for r in RES if not r[0]]
    print(f"\n{'='*60}\nRESULTADO: {bien}/{len(RES)} pruebas OK" + (f" — {len(mal)} FALLARON" if mal else ""))
    for _, n, d in mal: print("  ✘", n, "|", str(d)[:200])
    sys.exit(1 if mal else 0)
