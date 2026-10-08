import json

# =========================
# CARGAR ARCHIVOS JSON
# =========================

with open("ExportacionSQL/BaseDatosAdministracionDISAAT/OrdenesVenta.json", "r", encoding="utf-8") as f:
    ordenes_venta = json.load(f)

with open("ExportacionSQL/BaseDatosAdministracionDISAAT/HistoriaMovimientos.json", "r", encoding="utf-8") as f:
    movimientos = json.load(f)

# =========================
# MAPA DE ORDENES
# =========================

mapa_ordenes = {o["NUMERO"]: o for o in ordenes_venta}

# =========================
# AGRUPAR PRODUCTOS POR ORDEN
# =========================

agrupado = {}

for mov in movimientos:

    # regla: si OrdenVenta es null usar NroTransferencia
    clave = mov["OrdenVenta"] if mov.get("OrdenVenta") is not None else mov["NroTransferencia"]

    orden = mapa_ordenes.get(clave)

    if not orden:
        continue

    producto = {
        "NroItem": mov.get("NroItem"),
        "Comentario": mov.get("Comentario"),
        "Cantidad": mov.get("Cantidad"),
        "PrecioVenta": mov.get("PrecioVenta"),
        "Responsable": mov.get("Responsable")
    }

    if clave not in agrupado:
        agrupado[clave] = {
            "orden": {
                "NUMERO": orden["NUMERO"],
                "FECHA": orden["FECHA"],
                "CLIENTE": orden["CLIENTE"],
                "TOTAL Bs": orden["TOTAL Bs"],
                "ALMACEN": orden["ALMACEN"]
            },
            "productos": [],
            "total_productos": 0
        }

    agrupado[clave]["productos"].append(producto)
    agrupado[clave]["total_productos"] += 1

# =========================
# CONVERTIR A LISTA FINAL
# =========================

resultado = list(agrupado.values())

# =========================
# GUARDAR JSON
# =========================

with open("resultado_agrup.json", "w", encoding="utf-8") as f:
    json.dump(resultado, f, indent=4, ensure_ascii=False)

print("✔ JSON agrupado generado correctamente")