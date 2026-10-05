#!/usr/bin/env python3
"""
Vacía el contenido de <div class="anuncios-track"> en todos los .html de la
carpeta (los anuncios pasan a cargarse desde la base vía api.js).

Uso (desde la carpeta del sitio):
    python limpiar_anuncios.py          # solo muestra qué cambiaría
    python limpiar_anuncios.py --aplicar  # escribe los cambios (hace .bak)

Tip: commiteá en git antes de aplicar.
"""
import re, sys, pathlib, shutil

APLICAR = "--aplicar" in sys.argv
# Desde <div class="anuncios-track"> hasta su </div> de cierre (el track no
# tiene divs adentro, solo <p> y comentarios).
PATRON = re.compile(r'(<div\s+class="anuncios-track"\s*>)(.*?)(</div>)', re.S)

for f in sorted(pathlib.Path(".").glob("*.html")):
    if f.name == "admin.html":
        continue
    txt = f.read_text(encoding="utf-8")
    m = PATRON.search(txt)
    if not m:
        continue
    nuevo = PATRON.sub(r'\1\n<!-- anuncios: se cargan desde Admin → Anuncios (js/api.js) -->\n\3', txt, count=1)
    n_p = len(re.findall(r'<p\s+class="anuncios"', m.group(2)))
    print(f"{f.name}: {n_p} <p> de anuncios {'eliminados' if APLICAR else 'se eliminarían'}")
    if APLICAR:
        shutil.copy(f, f.with_suffix(".html.bak"))
        f.write_text(nuevo, encoding="utf-8")
