<?php
/**
 * Fuente única de verdad de los RUBROS y sus CATEGORÍAS (subcategorías).
 * Usada por admin.php (desplegables + validación) y productos.php (filtros).
 * Para agregar una categoría nueva, sumala acá y listo.
 *
 * Los valores son "slugs" (sin tildes ni espacios): son lo que se guarda en
 * las columnas `rubro` y `categoria` de la tabla `productos`. Los nombres
 * lindos para mostrar en pantalla se resuelven en el front.
 */
function rubrosCatalogo(): array
{
    return [
        'plantas'   => ['interior', 'plantines', 'arbustos', 'aromaticas'],
        'articulos' => ['suplementos', 'venenos', 'macetas', 'sahumerios_adornos'],
        'talleres'  => ['ceramica', 'mosaiquismo', 'estimulacion_cognitiva', 'excursiones', 'acercamos_tu_idea'],
        'ramos'     => ['ramos_boutonnieres', 'centros_de_mesa', 'arreglos_florales', 'regalos'],
        'servicios' => ['asesoria_virtual', 'asesoria_domicilio', 'asesoria_locales', 'jardineria'],
    ];
}

/** Todas las categorías, en una sola lista plana. */
function categoriasCatalogo(): array
{
    return array_merge(...array_values(rubrosCatalogo()));
}

/** Rubro al que pertenece una categoría (o null si no existe). */
function rubroDeCategoria(string $categoria): ?string
{
    foreach (rubrosCatalogo() as $rubro => $categorias) {
        if (in_array($categoria, $categorias, true)) return $rubro;
    }
    return null;
}
