/* =========================================================
   BUSCADOR GLOBAL - Mundo Verde
   Panel de búsqueda que se desliza desde la izquierda (como en
   la referencia de Pötit), con foto + nombre + precio de cada
   resultado y la parte que coincide con lo buscado resaltada
   en negrita.

   Busca dentro de window.CATALOGO_MUNDO_VERDE (definido en
   js/catalogo.js: talleres, ramos, servicios y secciones) MÁS lo
   que devuelve GET /productos apenas carga la página — es decir,
   todo lo que se gestiona desde la tabla "Productos" del panel
   Superadmin (admin.html). Así, cualquier producto que se agregue,
   edite, oculte o borre desde el admin aparece (o desaparece) del
   buscador automáticamente, sin tocar catalogo.js a mano.
   No busca en el texto visible de la página actual.
   Al elegir un resultado, lleva al usuario a la página donde
   está ese producto/servicio y lo resalta.
   ========================================================= */
(function () {
  "use strict";

  // Categorías que en realidad viven en la tabla "productos" de la base
  // (las mismas que administra el panel Superadmin, pestaña "Productos").
  // Todo lo que NO esté acá (Talleres, Ramos, Servicios, títulos de
  // sección) sigue viniendo de window.CATALOGO_MUNDO_VERDE en catalogo.js,
  // porque no está en ninguna tabla que el admin gestione.
  var CATEGORIA_A_PAGINA = {
    "arbustos": "1_2_arbustos.html",
    "aromaticas": "1_2_aromaticas.html",
    "interior": "1_2_interior.html",
    "plantines": "1_2_plantines.html",
    "productos": "1_2_productos.html"
  };

  // Solo para mostrar en el panel (más prolijo que el valor crudo de la
  // base, ej. "arbustos"). No afecta el matching: eso sigue usando la
  // clave real de CATEGORIA_A_PAGINA de arriba.
  var CATEGORIA_ETIQUETA = {
    "arbustos": "Arbustos",
    "aromaticas": "Aromáticas",
    "interior": "Plantas de Interior",
    "plantines": "Plantines florales",
    "productos": "Productos"
  };

  // Convierte lo que devuelve GET /productos (mismos datos que ve el
  // Superadmin en la pestaña "Productos") al formato que usa el panel.
  // Los productos ocultos (activo:false) no se muestran, igual que en
  // el resto del sitio.
  function productosApiACatalogo(productos) {
    return (productos || [])
      .filter(function (p) { return p && p.activo; })
      .map(function (p) {
        var pagina = CATEGORIA_A_PAGINA[p.categoria];
        // Si el producto tiene una categoría que no reconocemos (ej. se
        // agregó una categoría nueva desde el admin y todavía no tiene
        // página asociada acá), lo dejamos afuera del buscador en vez de
        // mandar a un link roto.
        if (!pagina) return null;
        return {
          nombre: p.nombre,
          categoria: (CATEGORIA_ETIQUETA[p.categoria] || p.categoria),
          pagina: pagina,
          imagen: p.imagen || null,
          precio: (typeof p.precio === "number") ? p.precio : null
        };
      })
      .filter(Boolean);
  }

  function normalizar(txt) {
    return (txt || "")
      .toString()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .trim();
  }

  function escaparHtml(txt) {
    return (txt || "").replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  // Devuelve el nombre con la parte que coincide con "query" envuelta en
  // <strong>, conservando mayúsculas/acentos originales del nombre (la
  // normalización se usa solo para ENCONTRAR el índice, nunca para lo
  // que se muestra).
  function resaltarCoincidencia(nombre, query) {
    var nombreNorm = normalizar(nombre);
    var qNorm = normalizar(query);
    if (!qNorm) return escaparHtml(nombre);
    var idx = nombreNorm.indexOf(qNorm);
    if (idx === -1) return escaparHtml(nombre);
    var antes = nombre.slice(0, idx);
    var medio = nombre.slice(idx, idx + qNorm.length);
    var despues = nombre.slice(idx + qNorm.length);
    return escaparHtml(antes) + "<strong>" + escaparHtml(medio) + "</strong>" + escaparHtml(despues);
  }

  function formatearPrecio(precio) {
    if (precio === null || precio === undefined) return "";
    return "$" + precio.toLocaleString("es-AR");
  }

  function iniciar() {
    // Copia mutable: arranca con lo estático (catalogo.js) y en cuanto
    // responde el backend, se reemplazan las categorías de "productos"
    // por los datos reales de la base (ver sincronizarConBaseDeDatos).
    var catalogo = (window.CATALOGO_MUNDO_VERDE || []).slice();
    var inputOrig = document.getElementById("searchInput");
    var btnOrig = document.getElementById("searchBtn");

    if (!inputOrig) return; // esta página no tiene buscador

    // El input y botón del header dejan de manejar su propia lógica:
    // ahora son solo el "gatillo" que abre el panel. Se clonan para
    // sacarles cualquier listener viejo (clonar conserva atributos pero
    // no los addEventListener agregados desde otro script).
    var inputHeader = inputOrig.cloneNode(true);
    inputHeader.readOnly = true; // el que se escribe es el del panel
    inputOrig.parentNode.replaceChild(inputHeader, inputOrig);

    var btnHeader = null;
    if (btnOrig) {
      btnHeader = btnOrig.cloneNode(true);
      btnOrig.parentNode.replaceChild(btnHeader, btnOrig);
    }

    // --- CSS del panel: ya no se agrega por separado. Los estilos
    //     .bg-* ahora viven directamente en styles.css (el sitio ya
    //     lo carga en el <head> de cada página), así que no hace
    //     falta inyectar ningún <link> extra acá. ---

    // --- HTML del panel: ahora vive en buscador-panel.html (antes se
    //     armaba con innerHTML acá mismo). Se trae con fetch y recién
    //     ahí se conecta toda la interacción (buscar, abrir, cerrar,
    //     teclado, sincronizar con la base). Si esta página no puede
    //     traerlo (archivo no subido, backend estático apagado), el
    //     buscador queda inactivo en vez de romper el resto de la
    //     página. ---
    fetch("buscador-panel.html")
      .then(function (res) {
        if (!res.ok) throw new Error("No se encontró buscador-panel.html");
        return res.text();
      })
      .then(function (html) {
        document.body.insertAdjacentHTML("beforeend", html);
        continuarConPanel();
      })
      .catch(function (err) {
        console.warn("[buscador-global] No se pudo cargar buscador-panel.html:", err.message);
      });

    function continuarConPanel() {
    var overlay = document.getElementById("bg-overlay");
    var panel = document.getElementById("bg-panel");
    var inputPanel = document.getElementById("bg-panel-input");
    var btnCerrar = document.getElementById("bg-close");
    var btnBuscarPanel = document.getElementById("bg-panel-search-btn");
    var contenedor = document.getElementById("bg-panel-resultados");

    var indiceActivo = -1;
    var resultadosActuales = [];

    function buscar(query) {
      var q = normalizar(query);
      if (!q) return [];
      return catalogo
        .filter(function (item) {
          return (
            normalizar(item.nombre).indexOf(q) !== -1 ||
            normalizar(item.categoria).indexOf(q) !== -1
          );
        })
        .slice(0, 20);
    }

    function renderResultados(lista, query) {
      contenedor.innerHTML = "";
      indiceActivo = -1;
      resultadosActuales = lista;

      if (!query) return;

      if (lista.length === 0) {
        var vacio = document.createElement("div");
        vacio.className = "bg-vacio";
        vacio.textContent = "Sin resultados en el catálogo";
        contenedor.appendChild(vacio);
        return;
      }

      lista.forEach(function (item, i) {
        var el = document.createElement("button");
        el.type = "button";
        el.className = "bg-item";
        el.setAttribute("data-i", i);

        var fotoHtml = item.imagen
          ? '<img class="bg-foto" src="' + escaparHtml(item.imagen) + '" alt="">'
          : '<div class="bg-foto-vacia">🌿</div>';

        var precioHtml = item.precio
          ? '<span class="bg-precio">' + formatearPrecio(item.precio) + "</span>"
          : "";

        el.innerHTML =
          fotoHtml +
          '<span class="bg-info">' +
            '<span class="bg-nombre">' + resaltarCoincidencia(item.nombre, query) + "</span>" +
            precioHtml +
            '<span class="bg-cat">' + escaparHtml(item.categoria || "") + "</span>" +
          "</span>";

        el.addEventListener("click", function () {
          irAResultado(item);
        });
        contenedor.appendChild(el);
      });
    }

    function abrirPanel() {
      overlay.classList.add("activo");
      panel.classList.add("activo");
      inputPanel.value = inputHeader.value || "";
      renderResultados(buscar(inputPanel.value), inputPanel.value);
      setTimeout(function () { inputPanel.focus(); }, 50);
    }

    function cerrarPanel() {
      overlay.classList.remove("activo");
      panel.classList.remove("activo");
    }

    function irAResultado(item) {
      cerrarPanel();
      inputHeader.value = item.nombre;

      var paginaActual = location.pathname.split("/").pop();
      var destino = item.pagina + "#buscar=" + encodeURIComponent(item.nombre);

      if (paginaActual === item.pagina) {
        // ya estamos en la página: solo resaltamos
        resaltarPorNombre(item.nombre);
        history.replaceState(null, "", "#buscar=" + encodeURIComponent(item.nombre));
      } else {
        window.location.href = destino;
      }
    }

    function actualizarActivo() {
      var items = contenedor.querySelectorAll(".bg-item");
      items.forEach(function (el, i) {
        el.classList.toggle("bg-activo", i === indiceActivo);
      });
      if (indiceActivo >= 0 && items[indiceActivo]) {
        items[indiceActivo].scrollIntoView({ block: "nearest" });
      }
    }

    // --- Abrir el panel desde el ícono/input del header ---
    inputHeader.addEventListener("focus", abrirPanel);
    inputHeader.addEventListener("click", abrirPanel);
    if (btnHeader) btnHeader.addEventListener("click", abrirPanel);

    // --- Interacción dentro del panel ---
    inputPanel.addEventListener("input", function () {
      renderResultados(buscar(inputPanel.value), inputPanel.value);
    });

    inputPanel.addEventListener("keydown", function (e) {
      if (e.key === "ArrowDown") {
        e.preventDefault();
        indiceActivo = Math.min(indiceActivo + 1, resultadosActuales.length - 1);
        actualizarActivo();
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        indiceActivo = Math.max(indiceActivo - 1, 0);
        actualizarActivo();
      } else if (e.key === "Enter") {
        e.preventDefault();
        var elegido =
          indiceActivo >= 0
            ? resultadosActuales[indiceActivo]
            : resultadosActuales[0];
        if (elegido) irAResultado(elegido);
      } else if (e.key === "Escape") {
        cerrarPanel();
      }
    });

    btnBuscarPanel.addEventListener("click", function () {
      var lista = buscar(inputPanel.value);
      renderResultados(lista, inputPanel.value);
      if (lista.length === 1) irAResultado(lista[0]);
    });

    btnCerrar.addEventListener("click", cerrarPanel);
    overlay.addEventListener("click", cerrarPanel);
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && panel.classList.contains("activo")) cerrarPanel();
    });

    // --- Al cargar la página: si venimos de un resultado (#buscar=...),
    //     resaltamos el producto/servicio correspondiente ---
    function resaltarPorNombre(nombre) {
      var objetivo = normalizar(nombre);
      var selectores = [
        ".producto", ".card-pl", ".card-ta", ".card-serv", ".card-tall"
      ];
      var candidatos = document.querySelectorAll(selectores.join(","));
      var encontrado = null;

      candidatos.forEach(function (el) {
        if (encontrado) return;
        var h3 = el.querySelector("h3");
        var texto = h3 ? h3.textContent : el.textContent;
        if (normalizar(texto) === objetivo) encontrado = el;
      });

      // fallback: coincidencia parcial
      if (!encontrado) {
        candidatos.forEach(function (el) {
          if (encontrado) return;
          var h3 = el.querySelector("h3");
          var texto = h3 ? h3.textContent : el.textContent;
          if (normalizar(texto).indexOf(objetivo) !== -1) encontrado = el;
        });
      }

      // fallback: títulos de sección (ej. Ramos)
      if (!encontrado) {
        document.querySelectorAll("h3.nacer-subt").forEach(function (h3) {
          if (encontrado) return;
          if (normalizar(h3.textContent).indexOf(objetivo) !== -1) {
            encontrado = h3;
          }
        });
      }

      if (encontrado) {
        setTimeout(function () {
          encontrado.scrollIntoView({ behavior: "smooth", block: "center" });
          encontrado.classList.add("bg-resaltado");
          setTimeout(function () {
            encontrado.classList.remove("bg-resaltado");
          }, 3000);
        }, 150);
      }
    }

    if (location.hash.indexOf("#buscar=") === 0) {
      var nombreDesdeHash = decodeURIComponent(
        location.hash.replace("#buscar=", "")
      );
      resaltarPorNombre(nombreDesdeHash);
    }

    // --- Sincroniza con lo que hay realmente en la base (tabla
    //     "productos", la misma que edita el Superadmin) ---
    // Usa mvApi (definido en js/api.js) para no repetir la URL del
    // backend acá. Si esta página no cargó js/api.js, o el backend está
    // apagado, el buscador sigue funcionando solo con catalogo.js.
    if (typeof mvApi === "function") {
      mvApi("/productos")
        .then(function (productos) {
          var categoriasVivas = Object.keys(CATEGORIA_A_PAGINA);
          var vivos = productosApiACatalogo(productos);
          // Saca del catálogo estático las categorías que ahora vienen de
          // la base (evita duplicados y productos viejos/borrados) y
          // agrega los productos actuales.
          catalogo = catalogo
            .filter(function (item) { return categoriasVivas.indexOf(item.categoria) === -1; })
            .concat(vivos);
          // Si el usuario ya estaba escribiendo mientras esto cargaba,
          // refrescamos los resultados con los datos al día.
          if (inputPanel.value) renderResultados(buscar(inputPanel.value), inputPanel.value);
        })
        .catch(function () {
          console.warn("[buscador-global] No se pudo sincronizar con la base de datos; se usa el catálogo estático (catalogo.js).");
        });
    }
    } // fin de continuarConPanel
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", iniciar);
  } else {
    iniciar();
  }
})();
