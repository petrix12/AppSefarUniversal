# Tableros de búsqueda del COS

Selección aplicada el 7 de septiembre de 2026 a partir del inventario de metadatos de Monday. La lista está centralizada en `config/cos_snapshot.php`, clave `monday_search_boards`, y la utilizan los dos controladores y el servicio de snapshots.

## Lista mínima: 12 tableros

| Tablero | ID | Motivo |
| --- | --- | --- |
| ANÁLISIS PRELIMINAR | 878831315 | Etapa explícita en las reglas del COS |
| ANALISIS | 625187241 | Etapa explícita en las reglas del COS |
| DESLINDE - SIN INFORME | 6524058079 | Producción de informes de deslinde |
| CNAT LMD - SIN INFORME | 3950637564 | Expedientes pendientes de informe |
| CNAT LMD | 3469085450 | Producción LMD |
| ITALIA | 2213224176 | Producción italiana |
| LEY DE NIETOS | 1845710504 | Expedientes LMD/ley de nietos |
| CONSANGUINIDAD | 1845706367 | Producción por consanguinidad |
| CNAT SEFARDI | 1845701215 | Carta de naturaleza sefardí |
| CNAT GENERAL | 708128239 | Carta de naturaleza general |
| SEFARDI PORTUGAL | 708123651 | Producción portuguesa |
| SEFARDI ESPAÑA | 669590637 | Producción española |

Todos están activos y tienen las columnas `enlace` y `men__desplegable` en el inventario. Los grupos de producción incluyen reparto, asignaciones e informes/proyectos listos. Se priorizan análisis preliminar y análisis en la búsqueda.

## Exclusiones de la búsqueda automática

- ISP, ISP - LMD e ISP - ES - Servicios Residencia/Visa: no tienen la columna `enlace` que utiliza la búsqueda actual.
- CLIENTES ANTIGUOS y CLIENTES RETIRADOS: excluidos del recorrido habitual por su función histórica/de retirada.
- ETIQUETADO NELSON SANGUINETTI (además estaba repetido), ETIQUETADO CORA CHUMACEIRO, ETIQUETADO VENTAS SEFAR, Duplicado de ETIQUETADO CRISANTO y VIVIANA CASTRO: excluidos para priorizar la lista mínima de análisis y producción. Pueden contener etiquetas útiles; no se declara que estén vacíos ni obsoletos.

La lista pasa de 23 entradas (22 IDs únicos) a 12. En una búsqueda sin coincidencias reduce el máximo de consultas por tablero de 23 a 12, aproximadamente un 48 %. No implica una reducción equivalente del tiempo total de carga.

## Alcance y limitación

La selección solo se utiliza cuando falta `monday_id`. Los clientes ya vinculados siguen consultándose por ID aunque el tablero haya quedado fuera. No se borran ni mueven tableros o clientes.

No se descargaron filas ni se comprobó la distribución de clientes. Un cliente sin vínculo que únicamente exista en un tablero excluido no será localizado por este recorrido reducido; necesitará vinculación explícita o ampliar la lista. Investigación genealógica IN SITU, GENEALOGIA PAISES y Nacionalidades concedidas por España quedan como candidatos a incorporar si la operación confirma esa necesidad. No se añaden automáticamente todos los tableros que contienen un enlace.

Después del despliegue se debe regenerar la caché de configuración si está habilitada.
