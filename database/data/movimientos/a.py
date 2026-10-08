import json
import pyodbc

from datetime import datetime, date
from decimal import Decimal

# =====================================
# CONFIGURACION
# =====================================

CONTROL_STOCK = "ExportacionSQL/BaseDatosAdministracionDISAAT/ControlStock.json"
ARCHIVO_SALIDA = "HistoriaMovimientos_Analizados.json"

# =====================================
# SQL SERVER
# =====================================

conn_str = (
    r"DRIVER={ODBC Driver 17 for SQL Server};"
    r"SERVER=FERNANDO\SQLEXPRESS;"
    r"UID=sa;"
    r"PWD=1234;"
)

# =====================================
# SERIALIZADOR
# =====================================

def serializer(obj):

    if isinstance(obj, (datetime, date)):
        return obj.isoformat()

    if isinstance(obj, Decimal):
        return float(obj)

    if isinstance(obj, bytes):
        return obj.hex()

    return str(obj)

# =====================================
# CLASIFICADOR
# =====================================

def clasificar(m):

    comentario = (m.get("Comentario") or "").upper().strip()

    if m.get("ORIGEN") is not None and m.get("DESTINO") is not None:

        if (m.get("Cantidad") or 0) < 0:
            return "TRANSFERENCIA_SALIDA"

        return "TRANSFERENCIA_ENTRADA"

    if m.get("OrdenVenta") is not None:

        if (m.get("Cantidad") or 0) < 0:
            return "VENTA"

        return "DEVOLUCION_VENTA"

    if m.get("Proyecto") is not None:
        return "PROYECTO"

    if "INGRESO" in comentario:
        return "INGRESO"

    if "COMPENSA" in comentario:
        return "AJUSTE"

    if "REGULARIZACION" in comentario:
        return "AJUSTE"

    if (m.get("Cantidad") or 0) > 0:
        return "ENTRADA"

    if (m.get("Cantidad") or 0) < 0:
        return "SALIDA"

    return "DESCONOCIDO"

# =====================================
# CONSULTA SQL
# =====================================

QUERY = """
SELECT
    Fecha,
    NroTransferencia,
    Proyecto,
    OrdenVenta,
    NroItem,
    Comentario,
    Cantidad,
    PrecioVenta,
    ROUND(stock1,3) AS PPAL,
    ROUND(stock2,3) AS [EL ALTO],
    ROUND(stock3,3) AS MEXICO,
    (
        SELECT nombre
        FROM BaseDatosAdministracionPersonalDISAAT.dbo.Datospersonal
        WHERE Datospersonal.password =
              HistoriaMovimientos.RESPONSABLE
    ) AS RESPONSABLE,
    ORIGEN,
    DESTINO
FROM BaseDatosAdministracionDISAAT.dbo.HistoriaMovimientos
WHERE NroItem = ?
ORDER BY Fecha, NroTransferencia
"""

# =====================================
# LEER CONTROL STOCK
# =====================================

with open(CONTROL_STOCK, encoding="utf-8") as f:
    control_stock = json.load(f)

# =====================================
# OBTENER ITEMS
# =====================================

items_control = {}

for item in control_stock:

    nro_item = item.get("NroItem")

    if nro_item is not None:

        items_control[int(nro_item)] = item

print(f"Items encontrados: {len(items_control)}")

# =====================================
# CONEXION SQL
# =====================================

conn = pyodbc.connect(conn_str)
cursor = conn.cursor()

# =====================================
# RESULTADO GENERAL
# =====================================

analisis_general = {}

# =====================================
# RECORRER ITEMS
# =====================================

for nro_item in sorted(items_control.keys()):

    try:

        print(f"Procesando Item {nro_item}")

        cursor.execute(QUERY, nro_item)

        columnas = [
            col[0]
            for col in cursor.description
        ]

        movimientos = []

        for fila in cursor.fetchall():

            movimientos.append(
                dict(zip(columnas, fila))
            )

        if not movimientos:

            continue

        stock_ppal = 0
        stock_elalto = 0
        stock_mexico = 0

        resultado = []

        for mov in movimientos:

            cantidad = float(
                mov.get("Cantidad") or 0
            )

            tipo = clasificar(mov)

            origen = mov.get("ORIGEN")
            destino = mov.get("DESTINO")

            motivo = ""

            if tipo == "TRANSFERENCIA_SALIDA":

                motivo = (
                    f"Transferencia salida "
                    f"Origen={origen} Destino={destino}"
                )

                if origen == 1:
                    stock_ppal += cantidad

                elif origen == 2:
                    stock_elalto += cantidad

                elif origen == 3:
                    stock_mexico += cantidad

            elif tipo == "TRANSFERENCIA_ENTRADA":

                motivo = (
                    f"Transferencia entrada "
                    f"Origen={origen} Destino={destino}"
                )

                if destino == 1:
                    stock_ppal += cantidad

                elif destino == 2:
                    stock_elalto += cantidad

                elif destino == 3:
                    stock_mexico += cantidad

            elif tipo == "VENTA":

                motivo = (
                    f"OrdenVenta="
                    f"{mov.get('OrdenVenta')}"
                )

                stock_ppal += cantidad

            elif tipo == "DEVOLUCION_VENTA":

                motivo = (
                    f"Devolución OV="
                    f"{mov.get('OrdenVenta')}"
                )

                stock_ppal += cantidad

            elif tipo == "PROYECTO":

                motivo = (
                    f"Proyecto="
                    f"{mov.get('Proyecto')}"
                )

                stock_ppal += cantidad

            else:

                motivo = (
                    mov.get("Comentario") or ""
                ).strip()

                stock_ppal += cantidad

            resultado.append({

                "Fecha": mov.get("Fecha"),

                "NroTransferencia":
                    mov.get("NroTransferencia"),

                "TipoMovimiento":
                    tipo,

                "MotivoClasificacion":
                    motivo,

                "Cantidad":
                    cantidad,

                "Comentario":
                    (mov.get("Comentario") or "").strip(),

                "Responsable":
                    (mov.get("RESPONSABLE") or "").strip(),

                "OrdenVenta":
                    mov.get("OrdenVenta"),

                "Proyecto":
                    mov.get("Proyecto"),

                "Origen":
                    origen,

                "Destino":
                    destino,

                "PrecioVenta":
                    mov.get("PrecioVenta"),

                "StockCalculado": {

                    "PPAL":
                        stock_ppal,

                    "EL_ALTO":
                        stock_elalto,

                    "MEXICO":
                        stock_mexico
                },

                "StockOriginal": {

                    "PPAL":
                        mov.get("PPAL"),

                    "EL_ALTO":
                        mov.get("EL ALTO"),

                    "MEXICO":
                        mov.get("MEXICO")
                }
            })

        ultimo = movimientos[-1]

        dif_ppal = stock_ppal - float(
            ultimo.get("PPAL") or 0
        )

        dif_alto = stock_elalto - float(
            ultimo.get("EL ALTO") or 0
        )

        dif_mex = stock_mexico - float(
            ultimo.get("MEXICO") or 0
        )

        estado = "OK"

        if (
            abs(dif_ppal) > 0.001 or
            abs(dif_alto) > 0.001 or
            abs(dif_mex) > 0.001
        ):
            estado = "NO_CUADRA"

        analisis_general[str(nro_item)] = {

            "Item": nro_item,

            "Estado": estado,

            "Resumen": {

                "StockCalculado": {

                    "PPAL": stock_ppal,
                    "EL_ALTO": stock_elalto,
                    "MEXICO": stock_mexico
                },

                "StockUltimoRegistro": {

                    "PPAL":
                        ultimo.get("PPAL"),

                    "EL_ALTO":
                        ultimo.get("EL ALTO"),

                    "MEXICO":
                        ultimo.get("MEXICO")
                },

                "Diferencias": {

                    "PPAL":
                        dif_ppal,

                    "EL_ALTO":
                        dif_alto,

                    "MEXICO":
                        dif_mex
                }
            },

            "Movimientos": resultado
        }

        print(f"OK {nro_item}")

    except Exception as e:

        print(
            f"ERROR ITEM {nro_item}: {e}"
        )

cursor.close()
conn.close()

# =====================================
# GUARDAR JSON FINAL
# =====================================

with open(
    ARCHIVO_SALIDA,
    "w",
    encoding="utf-8"
) as f:

    json.dump(
        analisis_general,
        f,
        indent=4,
        ensure_ascii=False,
        default=serializer
    )

print(
    f"\n✅ Archivo generado: {ARCHIVO_SALIDA}"
)