<?php

namespace App\Domains\Cotizacion\Controllers;


use App\Http\Controllers\Controller;

use App\Domains\Inventario\Models\Product;
use App\Domains\Clientes\Models\Client;
use App\Domains\Cotizacion\Models\Quotation;
use App\Domains\Cotizacion\Models\QuotationDetail;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Domains\Cotizacion\Services\CotizacionService;
class QuotationController extends Controller
{
    public function store(
        Request $request,
        CotizacionService $cotizacionService
    ) {
        /*
        |--------------------------------------------------------------------------
        | 1. USUARIO AUTENTICADO
        |--------------------------------------------------------------------------
        */

        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.'
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. EMPRESA DEL USUARIO
        |--------------------------------------------------------------------------
        */

        $idCompany = $user->id_company;

        if (!$idCompany) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asignada.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. VALIDACIÓN
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'date' => [
                'nullable',
                'date'
            ],

            'client_id' => [
                'nullable',
                'integer',
                'exists:clients,id'
            ],

            /*
             * Cliente particular / sin cliente registrado
             */
            'razon_social' => [
                'nullable',
                'string',
                'max:255'
            ],

            'nit' => [
                'nullable',
                'string',
                'max:50'
            ],

            /*
             * Descuento global %
             */
            'descuento' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100'
            ],

            /*
             * Productos
             */
            'items' => [
                'required',
                'array',
                'min:1'
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'exists:products,id'
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'min:0.01'
            ],

            'items.*.price' => [
                'required',
                'numeric',
                'min:0'
            ],

            /*
             * Aclaración solamente para el PDF.
             * NO se guarda en quotation_details.
             */
            'items.*.detail' => [
                'nullable',
                'string',
                'max:500'
            ],
        ]);

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | 4. DATOS DEL CLIENTE
            |--------------------------------------------------------------------------
            */

            $clientName = 'S/N';
            $clientNit  = 'S/N';
            $clientId   = null;

            /*
             * Si viene client_id buscamos el cliente
             * solamente dentro de la empresa del usuario.
             */

            if (!empty($validated['client_id'])) {

                $client = Client::where(
                    'id',
                    $validated['client_id']
                )
                    ->where(
                        'id_company',
                        $idCompany
                    )
                    ->first();

                if (!$client) {

                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'El cliente no existe o no pertenece a su empresa.'
                    ], 404);
                }

                $clientId   = $client->id;
                $clientName = $client->name ?? 'S/N';
                $clientNit  = $client->ciNit ?? 'S/N';

            } else {

                /*
                 * Cliente particular.
                 */

                $clientName = $validated['razon_social'] ?? 'S/N';
                $clientNit  = $validated['nit'] ?? 'S/N';
            }


            /*
            |--------------------------------------------------------------------------
            | 5. GENERAR CÓDIGO DE COTIZACIÓN
            |--------------------------------------------------------------------------
            |
            | El correlativo es independiente por empresa.
            |
            | COT-000001
            | COT-000002
            | COT-000003
            |
            */

            $ultimaCotizacion = Quotation::where(
                'id_company',
                $idCompany
            )
                ->orderByDesc('id')
                ->first();

            $numero = 1;

            if ($ultimaCotizacion) {

                preg_match(
                    '/(\d+)$/',
                    $ultimaCotizacion->code_quotation,
                    $matches
                );

                if (!empty($matches[1])) {
                    $numero = ((int) $matches[1]) + 1;
                }
            }

            $codeQuotation = 'COT-' .
                str_pad(
                    $numero,
                    6,
                    '0',
                    STR_PAD_LEFT
                );


            /*
            |--------------------------------------------------------------------------
            | 6. DESCUENTO
            |--------------------------------------------------------------------------
            */

            $descuentoGlobal = (float) (
                $validated['descuento'] ?? 0
            );


            /*
            |--------------------------------------------------------------------------
            | 7. CREAR CABECERA DE COTIZACIÓN
            |--------------------------------------------------------------------------
            */

            $quotation = Quotation::create([

                'code_quotation' => $codeQuotation,

                'id_company' => $idCompany,

                'date' => $validated['date']
                    ?? now()->format('Y-m-d'),

                'client_id' => $clientId,

                'user_id' => $user->id,

                'total' => 0,

                'pdf_name' => null,

                /*
                 * Guardamos una copia de los datos del cliente.
                 * Esto permite que la cotización conserve
                 * el nombre/NIT aunque posteriormente cambie el cliente.
                 */
                'client_name' => $clientName,

                'client_nit' => $clientNit,
            ]);


            /*
            |--------------------------------------------------------------------------
            | 8. CREAR DETALLES
            |--------------------------------------------------------------------------
            */

            $subtotal = 0;

            /*
             * Este array NO se guarda en BD.
             *
             * Solamente se utiliza para generar el PDF.
             */
            $productosPdf = [];


            foreach ($validated['items'] as $item) {

                /*
                |--------------------------------------------------------------------------
                | Buscar producto perteneciente a la empresa
                |--------------------------------------------------------------------------
                */

                $product = Product::where(
                    'id',
                    $item['product_id']
                )
                    ->where(
                        'id_company',
                        $idCompany
                    )
                    ->first();

                if (!$product) {

                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Uno de los productos no existe o no pertenece a su empresa.',
                        'product_id' => $item['product_id']
                    ], 404);
                }


                /*
                |--------------------------------------------------------------------------
                | Datos
                |--------------------------------------------------------------------------
                */

                $quantity = (float) $item['quantity'];

                $price = (float) $item['price'];

                $lineSubtotal = $quantity * $price;

                $subtotal += $lineSubtotal;


                /*
                |--------------------------------------------------------------------------
                | GUARDAR DETALLE
                |--------------------------------------------------------------------------
                |
                | IMPORTANTE:
                |
                | "detail" NO se guarda.
                |
                */

                QuotationDetail::create([

                    'quotation_id' => $quotation->id,

                    'product_id' => $product->id,

                    'quantity' => $quantity,

                    'price' => $price,

                    'subtotal' => $lineSubtotal,
                ]);


                /*
                |--------------------------------------------------------------------------
                | PREPARAR PRODUCTO PARA PDF
                |--------------------------------------------------------------------------
                */

                $productosPdf[] = [

                    'code' => $product->code
                        ?? $product->id,

                    'desc' => $product->name,

                    /*
                     * Aclaración solamente para el PDF.
                     */
                    'aclaracion' => $item['detail'] ?? '',

                    'cant' => $quantity,

                    'precio' => $price,
                ];
            }


            /*
            |--------------------------------------------------------------------------
            | 9. CALCULAR DESCUENTO
            |--------------------------------------------------------------------------
            */

            $descuentoMonto =
                $subtotal *
                ($descuentoGlobal / 100);


            /*
            |--------------------------------------------------------------------------
            | 10. TOTAL FINAL
            |--------------------------------------------------------------------------
            */

            $total =
                $subtotal -
                $descuentoMonto;


            /*
            |--------------------------------------------------------------------------
            | 11. ACTUALIZAR TOTAL
            |--------------------------------------------------------------------------
            */

            $quotation->update([

                'total' => $total,

            ]);


            /*
            |--------------------------------------------------------------------------
            | 12. GENERAR PDF
            |--------------------------------------------------------------------------
            |
            | Aquí llamamos al CotizacionService.
            |
            */

            $pdf = $cotizacionService->generar(

                $productosPdf,

                $clientName,

                $user->name ?? 'Usuario',

                $descuentoGlobal,

                $quotation->code_quotation,

                $clientNit,

                $idCompany
            );


            /*
            |--------------------------------------------------------------------------
            | 13. GUARDAR RUTA DEL PDF
            |--------------------------------------------------------------------------
            |
            | Ejemplo:
            |
            | quotation/EMP-00001/COT-000001.pdf
            |
            */

            $quotation->update([

                'pdf_name' => $pdf['path'],

            ]);


            /*
            |--------------------------------------------------------------------------
            | 14. CONFIRMAR TRANSACCIÓN
            |--------------------------------------------------------------------------
            */

            DB::commit();


            /*
            |--------------------------------------------------------------------------
            | 15. CARGAR RELACIONES
            |--------------------------------------------------------------------------
            */

            $quotation->load([
                'client',
                'user',
                'details.product',
            ]);


            /*
            |--------------------------------------------------------------------------
            | 16. RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => true,

                'message' => 'Cotización registrada correctamente.',

                'data' => [

                    'quotation' => $quotation,

                    'pdf' => [

                        'url' => $pdf['url'],

                        'filename' => $pdf['filename'],

                        'path' => $pdf['path'],

                    ],

                    'totals' => [

                        'subtotal' => round(
                            $subtotal,
                            2
                        ),

                        'descuento_porcentaje' =>
                            $descuentoGlobal,

                        'descuento_monto' => round(
                            $descuentoMonto,
                            2
                        ),

                        'total' => round(
                            $total,
                            2
                        ),
                    ],
                ],

            ], 201);


        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | ROLLBACK
            |--------------------------------------------------------------------------
            */

            DB::rollBack();

            return response()->json([

                'success' => false,

                'message' => 'No se pudo registrar la cotización.',

                'error' => $e->getMessage(),

            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    | Listar cotizaciones.
    |
    | Filtros:
    |
    | cliente
    | producto
    | fecha_inicio
    | fecha_fin
    |
    | Paginación:
    |
    | page
    | per_page
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        try {

            $user = Auth::user();


            /*
            |--------------------------------------------------------------------------
            | CONSULTA BASE
            |--------------------------------------------------------------------------
            */

            $query = Quotation::with([
                'client',
                'user',
                'details.product'
            ])
                ->where(
                    'id_company',
                    $user->id_company
                )
                ->orderByDesc('id');


            /*
            |--------------------------------------------------------------------------
            | FILTRO CLIENTE
            |--------------------------------------------------------------------------
            |
            | Busca por:
            |
            | - client_name
            | - client_nit
            | - nombre del cliente
            | - CI/NIT del cliente
            |
            */

            if ($request->filled('cliente')) {

                $cliente = trim(
                    $request->input('cliente')
                );


                $query->where(function ($q) use ($cliente) {

                    /*
                    |--------------------------------------------------------------------------
                    | DATOS GUARDADOS EN COTIZACIÓN
                    |--------------------------------------------------------------------------
                    */

                    $q->where(
                        'client_name',
                        'like',
                        "%{$cliente}%"
                    )
                        ->orWhere(
                            'client_nit',
                            'like',
                            "%{$cliente}%"
                        )


                        /*
                        |--------------------------------------------------------------------------
                        | DATOS DEL CLIENTE RELACIONADO
                        |--------------------------------------------------------------------------
                        */

                        ->orWhereHas(
                            'client',
                            function ($clientQuery) use ($cliente) {

                                $clientQuery
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$cliente}%"
                                    )
                                    ->orWhere(
                                        'ciNit',
                                        'like',
                                        "%{$cliente}%"
                                    );
                            }
                        );
                });
            }


            /*
            |--------------------------------------------------------------------------
            | FILTRO PRODUCTO
            |--------------------------------------------------------------------------
            |
            | Busca por:
            |
            | - código
            | - nombre
            |
            */

            if ($request->filled('producto')) {

                $producto = trim(
                    $request->input('producto')
                );


                $query->whereHas(
                    'details.product',
                    function ($productQuery) use ($producto) {

                        $productQuery
                            ->where(
                                'name',
                                'like',
                                "%{$producto}%"
                            )
                            ->orWhere(
                                'code',
                                'like',
                                "%{$producto}%"
                            );
                    }
                );
            }


            /*
            |--------------------------------------------------------------------------
            | FILTRO FECHA INICIO
            |--------------------------------------------------------------------------
            */

            if ($request->filled('fecha_inicio')) {

                $query->whereDate(
                    'date',
                    '>=',
                    $request->input('fecha_inicio')
                );
            }


            /*
            |--------------------------------------------------------------------------
            | FILTRO FECHA FIN
            |--------------------------------------------------------------------------
            */

            if ($request->filled('fecha_fin')) {

                $query->whereDate(
                    'date',
                    '<=',
                    $request->input('fecha_fin')
                );
            }


            /*
            |--------------------------------------------------------------------------
            | PAGINACIÓN
            |--------------------------------------------------------------------------
            |
            | Por defecto:
            |
            | 10 registros
            |
            | Máximo:
            |
            | 100 registros
            |
            */

            $perPage = (int) $request->input(
                'per_page',
                10
            );


            $perPage = min(
                max($perPage, 1),
                100
            );


            /*
            |--------------------------------------------------------------------------
            | OBTENER RESULTADOS
            |--------------------------------------------------------------------------
            |
            | Laravel solamente trae los registros de la página solicitada.
            |
            */

            $quotations = $query->paginate(
                $perPage
            );


            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => true,

                'message' =>
                'Cotizaciones obtenidas correctamente.',

                'data' => $quotations

            ]);
        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | LOG
            |--------------------------------------------------------------------------
            */

            Log::error(
                'Error al obtener cotizaciones',
                [

                    'error' =>
                    $e->getMessage(),

                    'user_id' =>
                    Auth::id(),

                    'id_company' =>
                    Auth::user()?->id_company

                ]
            );


            /*
            |--------------------------------------------------------------------------
            | ERROR
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => false,

                'message' =>
                'Error al obtener las cotizaciones.',

                'error' =>
                $e->getMessage()

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    | Registrar una nueva cotización.
    |--------------------------------------------------------------------------
    */

  

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        try {

            $user = Auth::user();


            $quotation = Quotation::with([

                'company',
                'client',
                'user',
                'details.product'

            ])
                ->where(
                    'id_company',
                    $user->id_company
                )
                ->find($id);


            if (!$quotation) {

                return response()->json([

                    'success' => false,

                    'message' =>
                    'Cotización no encontrada.'

                ], 404);
            }


            return response()->json([

                'success' => true,

                'message' =>
                'Cotización obtenida correctamente.',

                'data' =>
                $quotation

            ]);
        } catch (\Throwable $e) {

            Log::error(
                'Error al obtener cotización',
                [

                    'error' =>
                    $e->getMessage(),

                    'user_id' =>
                    Auth::id()

                ]
            );


            return response()->json([

                'success' => false,

                'message' =>
                'Error al obtener la cotización.',

                'error' =>
                $e->getMessage()

            ], 500);
        }
    }
}


/*POST
{
    "date": "2026-09-07",
    "client_id": 1,
    "razon_social": "Empresa ABC S.R.L.",
    "nit": "123456789",
    "items": [
        {
            "product_id": 1,
            "quantity": 1,
            "price": 150,
            "detail": "Color rojo, talla M"
        },
        {
            "product_id": 1,
            "quantity": 1,
            "price": 150,
            "detail": "Color rojo, talla M"
        }
       
    ]
}


*/
/*
store()
 ├─ valida JSON
 ├─ obtiene usuario + id_company
 ├─ obtiene cliente → razón social + NIT
 ├─ genera código COT-...
 ├─ crea quotation
 ├─ recorre items
 │   ├─ busca producto
 │   ├─ calcula precio/subtotal
 │   ├─ prepara aclaración para PDF
 │   └─ crea quotation_detail (SIN aclaración)
 ├─ actualiza total
 ├─ llama CotizacionService->generar()
 │   ├─ obtiene Company por id_company
 │   ├─ obtiene business_name
 │   ├─ obtiene tax_id
 │   ├─ obtiene logo
 │   └─ genera PDF
 ├─ guarda pdf_name
 └─ devuelve cotización
*/