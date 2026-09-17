// Catálogo del buscador global - Mundo Verde
// ─────────────────────────────────────────────────────────────
// OJO: este archivo YA NO tiene los productos individuales de
// Arbustos, Aromáticas, Plantas de Interior, Plantas de Exterior
// ni Productos para el cuidado de plantas. Esos 5 rubros son
// justo los que administra el panel Superadmin (pestaña
// "Productos", tabla `productos` de la base) y buscador-global.js
// los trae en vivo con GET /productos apenas carga la página, así
// que lo que se agrega/edita/oculta/borra desde el admin se refleja
// solo en el buscador, sin tener que tocar este archivo a mano.
//
// Acá quedan SOLO las cosas que no viven en esa tabla: Talleres,
// Ramos, Servicios y los títulos de sección/categoría (que son
// simples anclas a cada página, no productos de la base). Si se
// agrega o saca un taller, un ramo o una sección nueva, hay que
// actualizar esta lista a mano.
window.CATALOGO_MUNDO_VERDE = [
  {
    "nombre": "Alimentación y Fermentos",
    "pagina": "2_0_talleres.html",
    "categoria": "Talleres"
  },
  {
    "nombre": "AMBIETACIONES PARA EVENTOS",
    "pagina": "4_0_ramos.html",
    "categoria": "Ramos"
  },
  {
    "nombre": "ARBUSTOS",
    "pagina": "1_2_arbustos.html",
    "categoria": "Categoría"
  },
  {
    "nombre": "AROMATICAS",
    "pagina": "1_2_aromaticas.html",
    "categoria": "Categoría"
  },
  {
    "nombre": "ARREGLOS FLORALES PARA EVENTOS",
    "pagina": "4_0_ramos.html",
    "categoria": "Ramos"
  },
  {
    "nombre": "ASESORIA A DOMICILIO",
    "pagina": "3_1_serv_dom.html",
    "categoria": "Categoría"
  },
  {
    "nombre": "ASESORIA PARA LOCALES COMERCIALES",
    "pagina": "3_1_serv_com.html",
    "categoria": "Categoría"
  },
  {
    "nombre": "ASESORIA VIRTUAL + SERVICE",
    "pagina": "3_1_serv_vir.html",
    "categoria": "Categoría"
  },
  {
    "nombre": "Botiquín Herbal",
    "pagina": "2_0_talleres.html",
    "categoria": "Talleres"
  },
  {
    "nombre": "CENTROS DE MESA",
    "pagina": "4_0_ramos.html",
    "categoria": "Ramos"
  },
  {
    "nombre": "Cerámica",
    "pagina": "2_1_tall_cer.html",
    "categoria": "Categoría"
  },
  {
    "nombre": "Encuentro de Estrellas",
    "pagina": "2_0_talleres.html",
    "categoria": "Talleres"
  },
  {
    "nombre": "Estimulación Cognitiva",
    "pagina": "2_1_tall_EstCog.html",
    "categoria": "Categoría"
  },
  {
    "nombre": "Excursiones",
    "pagina": "2_1_tall_exc.html",
    "categoria": "Categoría"
  },
  {
    "nombre": "Fungicultivo",
    "pagina": "2_0_talleres.html",
    "categoria": "Talleres"
  },
  {
    "nombre": "INTERIOR",
    "pagina": "1_2_interior.html",
    "categoria": "Categoría"
  },
  {
    "nombre": "Mosaico",
    "pagina": "2_1_tall_mos.html",
    "categoria": "Categoría"
  },
  {
    "nombre": "PLANTINES",
    "pagina": "1_2_plantines.html",
    "categoria": "Categoría"
  },
  {
    "nombre": "PRODUCTOS",
    "pagina": "1_2_productos.html",
    "categoria": "Categoría"
  },
  {
    "nombre": "RAMOS PARA NOVIES & BOUTONNIÈRE",
    "pagina": "4_0_ramos.html",
    "categoria": "Ramos"
  },
  {
    "nombre": "RAMOS PARA REGALO",
    "pagina": "4_0_ramos.html",
    "categoria": "Ramos"
  },
  {
    "nombre": "Rito de Útero",
    "pagina": "2_0_talleres.html",
    "categoria": "Talleres"
  },
  {
    "nombre": "SERVICE DE JARDINERIA",
    "pagina": "3_1_serv_jar.html",
    "categoria": "Categoría"
  },
  {
    "nombre": "Talleres",
    "pagina": "2_1_tall_tall.html",
    "categoria": "Categoría"
  },
  {
    "nombre": "Telar",
    "pagina": "2_0_talleres.html",
    "categoria": "Talleres"
  },
  {
    "nombre": "Yoga",
    "pagina": "2_0_talleres.html",
    "categoria": "Talleres"
  }
];
