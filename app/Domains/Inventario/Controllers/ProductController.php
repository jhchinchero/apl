<?php

namespace App\Domains\Inventario\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventario\Models\Product;
use App\Domains\Inventario\Models\Brand;
use App\Domains\Inventario\Models\Supplier;
use App\Domains\Inventario\Models\Category;
use App\Domains\Inventario\Models\ProductExpiration;
use App\Domains\Inventario\Models\StockProduct;
use App\Domains\Inventario\Models\TypeData;
use App\Domains\Inventario\Models\TypeDataProduct;
use App\Domains\Inventario\Models\Image;
use App\Domains\Inventario\Models\StockMovement;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Seguridad\Models\Access;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;


class ProductController extends Controller
{

     /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | STOCK ADJUSTMENT
    |--------------------------------------------------------------------------
    |
    | Lista productos para realizar ajuste de inventario por sucursal.
    |
    | Ejemplo:
    |
    | GET /products/stock-adjustment
    |
    | Parámetros:
    |
    | branch_id=1
    | per_page=100
    | page=1
    | search=rodamiento
    |
    |--------------------------------------------------------------------------
    */
/**
 * ============================================================
 * LISTAR PRODUCTOS CON STOCK POR SUCURSAL
 * ============================================================
 *
 * No modifica stock.
 * Solo consulta:
 *
 *   stock actual
 *   producto
 *   sucursal
 *
 */
public function stockByBranch(Request $request)
{
    $request->validate([
        'branch_id' => 'required|integer',
        'page'      => 'nullable|integer|min:1',
        'per_page'  => 'nullable|integer|min:1|max:100',
        'search'    => 'nullable|string|max:255',
    ]);

    $user = auth()->user();

    $companyId = $user->id_company;

    $branchId = (int) $request->input('branch_id');

    $perPage = (int) $request->input('per_page', 100);

    $search = trim($request->input('search', ''));


    /*
    |--------------------------------------------------------------------------
    | VALIDAR SUCURSAL
    |--------------------------------------------------------------------------
    */

    $branch = Branch::query()
        ->where('id', $branchId)
        ->where('id_company', $companyId)
        ->where('status', 1)
        ->first();

    if (!$branch) {

        return response()->json([
            'success' => false,
            'message' => 'La sucursal no pertenece a la empresa o está inactiva.',
        ], 422);
    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCTOS
    |--------------------------------------------------------------------------
    */

    $productos = Product::query()

        ->where('products.id_company', $companyId)

        /*
        |--------------------------------------------------------------------------
        | STOCK DE LA SUCURSAL
        |--------------------------------------------------------------------------
        */

        ->leftJoin('product_stock as ps', function ($join) use ($companyId, $branchId) {

            $join->on(
                'ps.product_id',
                '=',
                'products.id'
            )

            ->where(
                'ps.id_company',
                '=',
                $companyId
            )

            ->where(
                'ps.branch_id',
                '=',
                $branchId
            );
        })


        /*
        |--------------------------------------------------------------------------
        | BUSCADOR
        |--------------------------------------------------------------------------
        */

        ->when($search !== '', function ($query) use ($search) {

            $query->where(function ($q) use ($search) {

                $q->where(
                    'products.name',
                    'like',
                    "%{$search}%"
                )

                ->orWhere(
                    'products.code_product',
                    'like',
                    "%{$search}%"
                )

                ->orWhere(
                    'products.product_code',
                    'like',
                    "%{$search}%"
                );
            });
        })


        /*
        |--------------------------------------------------------------------------
        | CAMPOS
        |--------------------------------------------------------------------------
        */

        ->select([

            'products.id',

            'products.code_product',

            'products.product_code',

            'products.name',

            'products.unit_of_measure',

            DB::raw(
                'COALESCE(ps.current_stock, 0) AS stock_actual'
            ),

        ])


        /*
        |--------------------------------------------------------------------------
        | ORDEN
        |--------------------------------------------------------------------------
        */

       /* ->orderBy(
            'products.id',
            'asc'
        )*/
        ->orderByRaw('LENGTH(products.product_code) ASC')
->orderBy('products.product_code', 'asc')


        /*
        |--------------------------------------------------------------------------
        | PAGINACIÓN
        |--------------------------------------------------------------------------
        */

        ->paginate($perPage);


    /*
    |--------------------------------------------------------------------------
    | RESPUESTA
    |--------------------------------------------------------------------------
    */

    return response()->json([

        'success' => true,

        'branch' => [
            'id'   => $branch->id,
            'codigo' => $branch->codigo,
            'name' => $branch->branch_name,
        ],

        'data' => $productos,

    ]);
}
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    | Lista productos de la empresa autenticada.
    |
    | PAGINACIÓN:
    | 10 productos por petición.
    |
    | Filtros:
    | search
    | code_category
    | code_brand
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        try {

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            /*
        |--------------------------------------------------------------------------
        | EMPRESA DEL USUARIO
        |--------------------------------------------------------------------------
        */

            $companyId = $user->id_company;

            /*
        |--------------------------------------------------------------------------
        | PAGINACIÓN
        |--------------------------------------------------------------------------
        */

            $perPage = (int) $request->input('per_page', 10);

            $perPage = min($perPage, 100);

            /*
        |--------------------------------------------------------------------------
        | CONSULTA PRODUCTOS
        |--------------------------------------------------------------------------
        */

            $query = Product::query()
                ->where('id_company', $companyId)
                ->with([
                    'brand',
                    'category',
                    'supplier',
                    'images',
                    'stocks.branch',
                    'expirations',
                    'typeDataProducts.typeData'
                ]);

            /*
        |--------------------------------------------------------------------------
        | BUSCAR
        |--------------------------------------------------------------------------
        */

            /*  if ($request->filled('search')) {

                $search = trim($request->input('search'));

                $query->where(function ($q) use ($search) {

                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('product_code', 'LIKE', "%{$search}%")
                        ->orWhere('model_product', 'LIKE', "%{$search}%");
                });
            } */
            if ($request->filled('search')) {

                $search = trim($request->input('search'));

                $query->where(function ($q) use ($search) {

                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('product_code', 'LIKE', "%{$search}%")
                        ->orWhere('model_product', 'LIKE', "%{$search}%")
                        ->orWhere('code_product', 'LIKE', "%{$search}%");

                    // BUSCAR EN type_data_product.value
                    $q->orWhereHas('typeDataProducts', function ($typeQuery) use ($search) {

                        $typeQuery->whereRaw(
                            'LOWER(value) LIKE ?',
                            ['%' . strtolower($search) . '%']
                        );
                    });
                });
            }

            /*
        |--------------------------------------------------------------------------
        | FILTRO CATEGORÍA
        |--------------------------------------------------------------------------
        */

            /* FILTRO CATEGORÍA */
            if ($request->filled('code_category')) {

                $category = Category::where('id_company', $companyId)
                    ->where('code_category', $request->code_category)
                    ->first();

                if ($category) {
                    $query->where('category_id', $category->id);
                }
            }

            /* FILTRO MARCA */
            if ($request->filled('code_brand')) {

                $brand = Brand::where('id_company', $companyId)
                    ->where('code_brand', $request->code_brand)
                    ->first();

                if ($brand) {
                    $query->where('brand_id', $brand->id);
                }
            }
            /*
        |--------------------------------------------------------------------------
        | ORDEN
        |--------------------------------------------------------------------------
        */

            $query->orderBy('created_at', 'desc');

            /*
        |--------------------------------------------------------------------------
        | PAGINACIÓN
        |--------------------------------------------------------------------------
        */

            $access = Access::where('user_id', $user->id)
                ->where('module_id', 4) //modulo 4 inevtario catalogo
                ->first();

          

            $products = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Productos obtenidos correctamente.',
                'data' => $products,
                'access'=>$access
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener productos.',
                'error' => $e->getMessage()
            ], 500);
        }
    }




    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        /*
    |--------------------------------------------------------------------------
    | USUARIO AUTENTICADO
    |--------------------------------------------------------------------------
    */

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.'
            ], 401);
        }

        /*
    |--------------------------------------------------------------------------
    | EMPRESA
    |--------------------------------------------------------------------------
    */

        $companyId = $user->id_company;

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.'
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | CONVERTIR JSON DE STOCK
    |--------------------------------------------------------------------------
    */

        if ($request->has('stock') && is_string($request->stock)) {

            $stock = json_decode($request->stock, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'message' => 'El campo stock contiene un JSON inválido.'
                ], 422);
            }

            $request->merge([
                'stock' => $stock
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | CONVERTIR JSON DE TYPE DATA
    |--------------------------------------------------------------------------
    */

        if ($request->has('type_data') && is_string($request->type_data)) {

            $typeData = json_decode($request->type_data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'message' => 'El campo type_data contiene un JSON inválido.'
                ], 422);
            }

            $request->merge([
                'type_data' => $typeData
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | VALIDACIÓN
    |--------------------------------------------------------------------------
    */

        $validated = $request->validate([

            'product_model' => [
                'nullable',
                'string',
                'max:50'
            ],

            'code_brand' => [
                'nullable',
                'string',
                'max:50',

                Rule::exists('brands', 'code_brand')
                    ->where(function ($query) use ($companyId) {
                        $query->where('id_company', $companyId);
                    }),
            ],

            'code_supplier' => [
                'nullable',
                'string',
                'max:50',

                Rule::exists('suppliers', 'code_supplier')
                    ->where(function ($query) use ($companyId) {
                        $query->where('id_company', $companyId);
                    }),
            ],

            'code_category' => [
                'nullable',
                'string',
                'max:50',

                Rule::exists('categories', 'code_category')
                    ->where(function ($query) use ($companyId) {
                        $query->where('id_company', $companyId);
                    }),
            ],

            'name' => [
                'required',
                'string',
                'max:100'
            ],

            'lote' => [
                'nullable',
                'string',
                'max:100'
            ],

            'description' => [
                'nullable',
                'string'
            ],

            'status' => [
                'nullable',
                'boolean'
            ],

            'sale_price' => [
                'required',
                'numeric',
                'min:0'
            ],

            'purchase_price' => [
                'required',
                'numeric',
                'min:0'
            ],

            'unit_of_measure' => [
                'required',
                'string',
                'max:50'
            ],

            'product_code' => [
                'nullable',
                'string',
                'max:50'
            ],

            /*
        |--------------------------------------------------------------------------
        | VENCIMIENTO
        |--------------------------------------------------------------------------
        */

            'has_expiration' => [
                'nullable',
                'boolean'
            ],

            'expiration_date' => [
                'nullable',
                'date'
            ],

            /*
        |--------------------------------------------------------------------------
        | STOCK
        |--------------------------------------------------------------------------
        */

            'stock' => [
                'nullable',
                'array'
            ],

            'stock.*.branch_id' => [
                'required',
                'integer',

                Rule::exists('branches', 'id')
                    ->where(function ($query) use ($companyId) {
                        $query->where('id_company', $companyId);
                    }),
            ],

            'stock.*.quantity' => [
                'required',
                'numeric',
                'min:0'
            ],

            /*
        |--------------------------------------------------------------------------
        | TYPE DATA
        |--------------------------------------------------------------------------
        */

            'type_data' => [
                'nullable',
                'array'
            ],

            'type_data.*.type_data_id' => [
                'required',
                'integer'
            ],

            'type_data.*.value' => [
                'required',
                'string',
                'max:255'
            ],

            /*
        |--------------------------------------------------------------------------
        | IMAGE
        |--------------------------------------------------------------------------
        */

            'images' => [
                'nullable',
                'array',
                'max:5'
            ],

            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120'
            ],
        ]);

        /*
    |--------------------------------------------------------------------------
    | VALIDAR VENCIMIENTO
    |--------------------------------------------------------------------------
    */

        $hasExpiration = filter_var(
            $validated['has_expiration'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        if ($hasExpiration && empty($validated['expiration_date'])) {

            return response()->json([
                'success' => false,
                'message' => 'Debe seleccionar la fecha de vencimiento.'
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | BUSCAR MARCA
    |--------------------------------------------------------------------------
    */

        $brandId = null;

        if (!empty($validated['code_brand'])) {

            $brandId = Brand::where(
                'code_brand',
                $validated['code_brand']
            )
                ->where(
                    'id_company',
                    $companyId
                )
                ->value('id');

            if (!$brandId) {
                return response()->json([
                    'success' => false,
                    'message' => 'La marca no existe.'
                ], 422);
            }
        }

        /*
    |--------------------------------------------------------------------------
    | BUSCAR PROVEEDOR
    |--------------------------------------------------------------------------
    */

        $supplierId = null;

        if (!empty($validated['code_supplier'])) {

            $supplierId = Supplier::where(
                'code_supplier',
                $validated['code_supplier']
            )
                ->where(
                    'id_company',
                    $companyId
                )
                ->value('id');

            if (!$supplierId) {
                return response()->json([
                    'success' => false,
                    'message' => 'El proveedor no existe.'
                ], 422);
            }
        }

        /*
    |--------------------------------------------------------------------------
    | BUSCAR CATEGORÍA
    |--------------------------------------------------------------------------
    */

        $categoryId = null;

        if (!empty($validated['code_category'])) {

            $categoryId = Category::where(
                'code_category',
                $validated['code_category']
            )
                ->where(
                    'id_company',
                    $companyId
                )
                ->value('id');

            if (!$categoryId) {
                return response()->json([
                    'success' => false,
                    'message' => 'La categoría no existe.'
                ], 422);
            }
        }

        /*
    |--------------------------------------------------------------------------
    | GENERAR CÓDIGO DEL PRODUCTO
    |--------------------------------------------------------------------------
    */

        $companyProductId = $this->generateCompanyProductId(
            $companyId
        );
        $images = $request->file('images', []);
        /*
    
    |--------------------------------------------------------------------------
    | TRANSACCIÓN
    |--------------------------------------------------------------------------
    */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.'
            ], 401);
        }

        $userId = $user->id;

        try {

            $product = DB::transaction(function () use (
                $validated,
                $companyId,
                $companyProductId,
                $brandId,
                $supplierId,
                $categoryId,
                $hasExpiration,
                $images,
                $userId
            ) {

                /*
            |--------------------------------------------------------------------------
            | 1. PRODUCTO
            |--------------------------------------------------------------------------
            */

                $product = Product::create([

                    'code_product' =>
                    $companyProductId,

                    'id_company' =>
                    $companyId,

                    'model_product' =>
                    $validated['product_model'] ?? null,

                    'brand_id' =>
                    $brandId,

                    'supplier_id' =>
                    $supplierId,

                    'category_id' =>
                    $categoryId,

                    'name' =>
                    $validated['name'],

                    'lote' =>
                    $validated['lote'] ?? null,

                    'description' =>
                    $validated['description'] ?? null,

                    'status' =>
                    $validated['status'] ?? true,

                    'sale_price' =>
                    $validated['sale_price'],

                    'purchase_price' =>
                    $validated['purchase_price'],

                    'unit_of_measure' =>
                    $validated['unit_of_measure'],

                    'product_code' =>
                    $validated['product_code'] ?? null,
                ]);

                /*
            |--------------------------------------------------------------------------
            | 2. VENCIMIENTO
            |--------------------------------------------------------------------------
            */

                if ($hasExpiration) {

                    ProductExpiration::create([

                        'product_id' =>
                        $product->id,

                        'lot' =>
                        $validated['lote'] ?? null,

                        'expiration_date' =>
                        $validated['expiration_date'],
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | 5. IMÁGENES
                |--------------------------------------------------------------------------
                */

                /*
|--------------------------------------------------------------------------
| 5. IMÁGENES
|--------------------------------------------------------------------------
*/

                if (!empty($images)) {

                    foreach ($images as $image) {

                        /*
        |--------------------------------------------------------------------------
        | Extensión original
        |--------------------------------------------------------------------------
        */

                        $extension = strtolower(
                            $image->getClientOriginalExtension()
                        );

                        /*
        |--------------------------------------------------------------------------
        | Nombre único
        |--------------------------------------------------------------------------
        |
        | Ejemplo:
        | EMP-00001_550e8400-e29b-41d4-a716-446655440000.jpg
        |
        */

                        $fileName =
                            $companyId .
                            '_' .
                            Str::uuid() .
                            '.' .
                            $extension;

                        /*
        |--------------------------------------------------------------------------
        | Carpeta de la empresa
        |--------------------------------------------------------------------------
        |
        | storage/app/public/products/EMP-00001
        |
        */

                        $folder = 'products/' . $companyId;

                        /*
        |--------------------------------------------------------------------------
        | Guardar imagen
        |--------------------------------------------------------------------------
        */

                        $path = $image->storeAs(
                            $folder,
                            $fileName,
                            'public'
                        );

                        /*
        |--------------------------------------------------------------------------
        | Guardar registro en BD
        |--------------------------------------------------------------------------
        */

                        Image::create([

                            'product_id' => $product->id,

                            'image_path' => $path,

                        ]);
                    }
                }

                /*
            |--------------------------------------------------------------------------
            | 3. STOCK POR SUCURSAL
            |--------------------------------------------------------------------------
            */

                if (!empty($validated['stock'])) {

                    foreach ($validated['stock'] as $stockData) {

                        StockProduct::create([

                            'id_company' => $companyId,

                            'branch_id' => $stockData['branch_id'],

                            'product_id' => $product->id,

                            'current_stock' => $stockData['quantity'],

                            'status' => true,

                            'price' => $validated['purchase_price'],

                            'location' => null,
                        ]);
                        /*
                |--------------------------------------------------------------------------
                | 4. CREAR MOVIMIENTO INICIAL
                |--------------------------------------------------------------------------
                |
                | Solo creamos movimiento si existe stock inicial.
                |
                */

                        if ($stockData['quantity'] > 0) {

                            StockMovement::create([

                                'id_company' => $companyId,

                                'branch_id' => $stockData['branch_id'],

                                'product_id' => $product->id,

                                'user_id' => $userId,

                                'movement_type' => 'INITIAL_STOCK',

                                'quantity' => $stockData['quantity'],

                                'reference_type' => 'product',

                                'reference_id' => $product->id,

                                'note' => 'Stock inicial al registrar el producto',
                            ]);
                        }
                    }
                }
                /*
            |--------------------------------------------------------------------------
            | 4. TYPE DATA
            |--------------------------------------------------------------------------
            */

                /* 
|-------------------------------------------------------------------------- 
| 4. TYPE DATA
|-------------------------------------------------------------------------- 
*/

                if (!empty($validated['type_data'])) {

                    foreach ($validated['type_data'] as $typeDataItem) {

                        $typeData = TypeData::where(
                            'id',
                            $typeDataItem['type_data_id']
                        )
                            ->where(
                                'id_company',
                                $companyId
                            )
                            ->first();

                        if (!$typeData) {

                            throw new \Exception(
                                'El tipo de dato con ID ' .
                                    $typeDataItem['type_data_id'] .
                                    ' no pertenece a la empresa.'
                            );
                        }

                        TypeDataProduct::create([

                            'id_company' => $companyId,

                            'product_id' => $product->id,

                            'type_data_id' => $typeData->id,

                            // ✅ VALOR QUE VIENE DEL FRONTEND
                            'value' => $typeDataItem['value'],

                            'status' => true,
                        ]);
                    }
                }

                return $product;
            });

            /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */

            return response()->json([

                'success' => true,

                'message' =>
                'Producto registrado correctamente.',

                /*  'data' =>
                $product
                    ->fresh()
                    ->load([
                        'brand',
                        'supplier',
                        'category',
                        'expirations',
                        'stocks',
                        'typeDataProducts',
                    ]) */

            ], 201);
        } catch (\Exception $e) {

            return response()->json([

                'success' => false,

                'message' =>
                'No se pudo registrar el producto.',

                'error' =>
                $e->getMessage()

            ], 422);
        }
    }

    /**
     * Generate global product ID
     *
     * Example:
     * PROD-000001
     * PROD-000002
     */



    /**
     * Generate product ID per company
     *
     * Example:
     *
     * Company A:
     * PRD-00001
     * PRD-00002
     *
     * Company B:
     * PRD-00001
     * PRD-00002
     */
    private function generateCompanyProductId(
        string $companyId
    ): string {

        $lastProduct = Product::where(
            'id_company',
            $companyId
        )
            ->orderByDesc('id')
            ->first();

        $nextNumber = $lastProduct
            ? ((int) str_replace(
                'PRD-',
                '',
                $lastProduct->code_product
            )) + 1
            : 1;

        return 'PRD-' . str_pad(
            $nextNumber,
            5,
            '0',
            STR_PAD_LEFT
        );
    }


    public function index_sale(Request $request)
    {
        try {

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            /*
            |--------------------------------------------------------------------------
            | EMPRESA
            |--------------------------------------------------------------------------
            */

            $companyId = $user->id_company;

            /*
            |--------------------------------------------------------------------------
            | PAGINACIÓN
            |--------------------------------------------------------------------------
            */

            $perPage = (int) $request->input('per_page', 10);

            $perPage = min(max($perPage, 1), 100);

            /*
            |--------------------------------------------------------------------------
            | SUCURSAL SELECCIONADA
            |--------------------------------------------------------------------------
            */

            $branchId = $request->input('branch_id');

            /*
            |--------------------------------------------------------------------------
            | PRODUCTOS
            |--------------------------------------------------------------------------
            */

            $query = Product::query()

                ->where('id_company', $companyId)

                ->with([
                    'brand',
                    'category',
                    'supplier',
                    'images',
                    'stocks.branch',
                    'expirations',
                    'typeDataProducts.typeData'
                ]);

            /*
            |--------------------------------------------------------------------------
            | BÚSQUEDA
            |--------------------------------------------------------------------------
            */

            if ($request->filled('search')) {

                $search = trim($request->input('search'));

                $query->where(function ($q) use ($search) {

                    $q->where('name', 'LIKE', "%{$search}%")

                        ->orWhere('product_code', 'LIKE', "%{$search}%")

                        ->orWhere('model_product', 'LIKE', "%{$search}%")

                        ->orWhere('code_product', 'LIKE', "%{$search}%")

                        ->orWhereHas(
                            'typeDataProducts',
                            function ($typeQuery) use ($search) {

                                $typeQuery->whereRaw(
                                    'LOWER(value) LIKE ?',
                                    ['%' . strtolower($search) . '%']
                                );
                            }
                        );
                });
            }

            /*
            |--------------------------------------------------------------------------
            | CATEGORÍA
            |--------------------------------------------------------------------------
            */

            if ($request->filled('category_id')) {

                $categoryId = $request->input('category_id');

                $query->where('category_id', $categoryId);
            }

            /*
            |--------------------------------------------------------------------------
            | MARCA
            |--------------------------------------------------------------------------
            */

            if ($request->filled('brand_id')) {

                $brandId = $request->input('brand_id');

                $query->where('brand_id', $brandId);
            }

            /*
            |--------------------------------------------------------------------------
            | ORDEN
            |--------------------------------------------------------------------------
            */

            $query->orderBy('created_at', 'desc');

            /*
            |--------------------------------------------------------------------------
            | PAGINACIÓN
            |--------------------------------------------------------------------------
            */

            $products = $query->paginate($perPage);

            /*
            |--------------------------------------------------------------------------
            | TRANSFORMAR PRODUCTOS
            |--------------------------------------------------------------------------
            */

            $products->getCollection()->transform(function ($product) use (
                $branchId
            ) {

                /*
                |--------------------------------------------------------------------------
                | ID REAL DEL PRODUCTO
                |--------------------------------------------------------------------------
                */

                $product->id_product = $product->id;

                /*
                |--------------------------------------------------------------------------
                | CÓDIGO
                |--------------------------------------------------------------------------
                */

                $product->code = $product->code_product;

                /*
                |--------------------------------------------------------------------------
                | PRECIO
                |--------------------------------------------------------------------------
                */

                $product->price = $product->sale_price;

                /*
                |--------------------------------------------------------------------------
                | STOCK DE LA SUCURSAL SELECCIONADA
                |--------------------------------------------------------------------------
                */

                $selectedStock = 0;

                if ($branchId) {

                    $stock = $product->stocks
                        ->first(function ($stock) use ($branchId) {

                            return (string) $stock->branch_id === (string) $branchId;
                        });

                    if ($stock) {

                        $selectedStock = (float) $stock->current_stock;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | STOCK SELECCIONADO
                |--------------------------------------------------------------------------
                */

                $product->selected_branch_stock = $selectedStock;

                /*
                |--------------------------------------------------------------------------
                | INFORMACIÓN DE TODAS LAS SUCURSALES
                |--------------------------------------------------------------------------
                */

                $product->branch_stocks = $product->stocks
                    ->map(function ($stock) {

                        return [
                            'id_stock' => $stock->id,

                            'id_branch' => $stock->branch_id,

                            'branch_name' => optional(
                                $stock->branch
                            )->branch_name,

                            'current_stock' => (float) $stock->current_stock,

                            'price' => (float) $stock->price,

                            'location' => $stock->location,

                            'status' => (bool) $stock->status,
                        ];
                    })
                    ->values();

                /*
                |--------------------------------------------------------------------------
                | TIPOS / CARACTERÍSTICAS
                |--------------------------------------------------------------------------
                */

                $product->types = $product->typeDataProducts
                    ->map(function ($item) {

                        return [
                            'id' => $item->id,

                            'type_data_id' => $item->type_data_id,

                            'label_name' => optional(
                                $item->typeData
                            )->type_name,

                            'type_name' => $item->value,
                        ];
                    })
                    ->values();

                /*
                |--------------------------------------------------------------------------
                | IMAGEN
                |--------------------------------------------------------------------------
                */

                $product->image = null;

                if ($product->images->count() > 0) {

                    $image = $product->images->first();

                    $product->image = asset(
                        'storage/' . $image->image_path
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | STOCK GENERAL
                |--------------------------------------------------------------------------
                |
                | Lo dejamos como el stock de la sucursal seleccionada.
                | Así también puedes utilizar product.stock en Vue.
                |
                */

                $product->stock = $selectedStock;

                return $product;
            });

            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'message' => 'Productos obtenidos correctamente.',

                'data' => $products
            ]);
        } catch (\Throwable $e) {

            return response()->json([

                'success' => false,

                'message' => 'Error al obtener productos.',

                'error' => $e->getMessage()

            ], 500);
        }
    }



    /*
    |--------------------------------------------------------------------------
    | Generate company product code
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | MYC-00001
    |     PROD-000001
    |
    | Another company:
    |
    | MYC-00002
    |     PROD-000001
    |
    */






    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show(
        Request $request,
        string $product_id
    ) {

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        |
        | Search by product_id AND company.
        |
        */

        $product = Product::with([
            'brand',
            'supplier',
            'category',
            'images',
            'expirations',
            'stocks',
            'typeDataProducts',
        ])
            ->where('product_id', $product_id)
            ->where('id_company', $user->id_company)
            ->first();

        if (!$product) {

            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }


        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    /*
|--------------------------------------------------------------------------
| UPDATE
|--------------------------------------------------------------------------
*/

    public function update(
        Request $request,
        int $id
    ) {

        /*
    |--------------------------------------------------------------------------
    | USUARIO
    |--------------------------------------------------------------------------
    */

        $user = $request->user();

        if (!$user) {

            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.'
            ], 401);
        }


        /*
    |--------------------------------------------------------------------------
    | EMPRESA
    |--------------------------------------------------------------------------
    */

        $companyId = $user->id_company;

        if (!$companyId) {

            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.'
            ], 422);
        }

            $access = Access::where('user_id', $user->id)
                ->where('module_id', 4) //modulo 4 inevtario catalogo
                ->first();

            if (!$access->can_edit) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para modificar.',
                    'data' => []
                ], 422);
            }


        /*
    |--------------------------------------------------------------------------
    | PRODUCTO
    |--------------------------------------------------------------------------
    */

        $product = Product::where('id', $id)
            ->where('id_company', $companyId)
            ->first();

        if (!$product) {

            return response()->json([
                'success' => false,
                'message' =>
                'El producto no existe o no pertenece a la empresa.'
            ], 404);
        }


        /*
    |--------------------------------------------------------------------------
    | TYPE DATA
    |--------------------------------------------------------------------------
    */

        if (
            $request->has('type_data') &&
            is_string($request->type_data)
        ) {

            $typeData = json_decode(
                $request->type_data,
                true
            );

            if (json_last_error() !== JSON_ERROR_NONE) {

                return response()->json([
                    'success' => false,
                    'message' =>
                    'El campo type_data contiene un JSON inválido.'
                ], 422);
            }

            $request->merge([
                'type_data' => $typeData
            ]);
        }


        /*
    |--------------------------------------------------------------------------
    | VALIDACIÓN
    |--------------------------------------------------------------------------
    */

        $validated = $request->validate([

            'product_model' => [
                'nullable',
                'string',
                'max:50'
            ],

            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')
                    ->where(function ($query) use ($companyId) {
                        $query->where('id_company', $companyId);
                    }),
            ],

            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')
                    ->where(function ($query) use ($companyId) {
                        $query->where('id_company', $companyId);
                    }),
            ],

            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(function ($query) use ($companyId) {
                        $query->where('id_company', $companyId);
                    }),
            ],
            'name' => [
                'required',
                'string',
                'max:100'
            ],

            'lote' => [
                'nullable',
                'string',
                'max:100'
            ],

            'description' => [
                'nullable',
                'string'
            ],

            'status' => [
                'nullable',
                'boolean'
            ],

            'sale_price' => [
                'required',
                'numeric',
                'min:0'
            ],

            'purchase_price' => [
                'required',
                'numeric',
                'min:0'
            ],

            'unit_of_measure' => [
                'required',
                'string',
                'max:50'
            ],

            'product_code' => [
                'nullable',
                'string',
                'max:50'
            ],


            /*
        |--------------------------------------------------------------------------
        | TYPE DATA
        |--------------------------------------------------------------------------
        */

            'type_data' => [
                'nullable',
                'array'
            ],

            'type_data.*.type_data_id' => [
                'required',
                'integer'
            ],

            'type_data.*.value' => [
                'required',
                'string',
                'max:255'
            ],


            /*
        |--------------------------------------------------------------------------
        | IMÁGENES EXISTENTES
        |--------------------------------------------------------------------------
        */

            'existing_images' => [
                'nullable',
                'array'
            ],

            'existing_images.*' => [
                'integer'
            ],


            /*
        |--------------------------------------------------------------------------
        | NUEVAS IMÁGENES
        |--------------------------------------------------------------------------
        */

            'images' => [
                'nullable',
                'array',
                'max:5'
            ],

            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120'
            ],
        ]);


        /*
    |--------------------------------------------------------------------------
    | MARCA
    |--------------------------------------------------------------------------
    */

        $brandId = null;

        if (!empty($validated['code_brand'])) {

            $brandId = Brand::where(
                'code_brand',
                $validated['code_brand']
            )
                ->where(
                    'id_company',
                    $companyId
                )
                ->value('id');

            if (!$brandId) {

                return response()->json([
                    'success' => false,
                    'message' => 'La marca no existe.'
                ], 422);
            }
        }


        /*
    |--------------------------------------------------------------------------
    | PROVEEDOR
    |--------------------------------------------------------------------------
    */

        $supplierId = null;

        if (!empty($validated['code_supplier'])) {

            $supplierId = Supplier::where(
                'code_supplier',
                $validated['code_supplier']
            )
                ->where(
                    'id_company',
                    $companyId
                )
                ->value('id');

            if (!$supplierId) {

                return response()->json([
                    'success' => false,
                    'message' => 'El proveedor no existe.'
                ], 422);
            }
        }


        /*
    |--------------------------------------------------------------------------
    | CATEGORÍA
    |--------------------------------------------------------------------------
    */

        $categoryId = null;

        if (!empty($validated['code_category'])) {

            $categoryId = Category::where(
                'code_category',
                $validated['code_category']
            )
                ->where(
                    'id_company',
                    $companyId
                )
                ->value('id');

            if (!$categoryId) {

                return response()->json([
                    'success' => false,
                    'message' => 'La categoría no existe.'
                ], 422);
            }
        }


        /*
    |--------------------------------------------------------------------------
    | TYPE DATA
    |--------------------------------------------------------------------------
    */

        $typeDataItems =
            $validated['type_data'] ?? [];


        /*
    |--------------------------------------------------------------------------
    | IMÁGENES EXISTENTES QUE SE MANTIENEN
    |--------------------------------------------------------------------------
    */

        $existingImages =
            $validated['existing_images'] ?? [];


        /*
    |--------------------------------------------------------------------------
    | NUEVAS IMÁGENES
    |--------------------------------------------------------------------------
    */

        $images = $request->file(
            'images',
            []
        );


        /*
    |--------------------------------------------------------------------------
    | TRANSACCIÓN
    |--------------------------------------------------------------------------
    */

        try {

            DB::transaction(function () use (
                $product,
                $validated,
                $companyId,
                $brandId,
                $supplierId,
                $categoryId,
                $typeDataItems,
                $existingImages,
                $images
            ) {


                /*
            |--------------------------------------------------------------------------
            | 1. ACTUALIZAR PRODUCTO
            |--------------------------------------------------------------------------
            */

                $product->update([

                    'model_product' =>
                    $validated['product_model'] ?? null,

                    'brand_id' => $validated['brand_id'] ?? null,

                    'supplier_id' => $validated['supplier_id'] ?? null,

                    'category_id' => $validated['category_id'] ?? null,

                    'name' =>
                    $validated['name'],

                    'lote' =>
                    $validated['lote'] ?? null,

                    'description' =>
                    $validated['description'] ?? null,

                    'status' =>
                    $validated['status'] ?? true,

                    'sale_price' =>
                    $validated['sale_price'],

                    'purchase_price' =>
                    $validated['purchase_price'],

                    'unit_of_measure' =>
                    $validated['unit_of_measure'],

                    'product_code' =>
                    $validated['product_code'] ?? null,
                ]);


                /*
            |--------------------------------------------------------------------------
            | 2. ACTUALIZAR TYPE DATA
            |--------------------------------------------------------------------------
            */

                TypeDataProduct::where(
                    'product_id',
                    $product->id
                )
                    ->where(
                        'id_company',
                        $companyId
                    )
                    ->delete();


                foreach ($typeDataItems as $typeDataItem) {

                    $typeData = TypeData::where(
                        'id',
                        $typeDataItem['type_data_id']
                    )
                        ->where(
                            'id_company',
                            $companyId
                        )
                        ->first();

                    if (!$typeData) {

                        throw new \Exception(
                            'El tipo de dato con ID ' .
                                $typeDataItem['type_data_id'] .
                                ' no pertenece a la empresa.'
                        );
                    }


                    TypeDataProduct::create([

                        'id_company' =>
                        $companyId,

                        'product_id' =>
                        $product->id,

                        'type_data_id' =>
                        $typeData->id,

                        'value' =>
                        $typeDataItem['value'],

                        'status' =>
                        true,
                    ]);
                }


                /*
            |--------------------------------------------------------------------------
            | 3. ELIMINAR IMÁGENES QUE YA NO EXISTEN EN EL FORMULARIO
            |--------------------------------------------------------------------------
            */

                $oldImages = Image::where(
                    'product_id',
                    $product->id
                )->get();


                foreach ($oldImages as $oldImage) {

                    /*
                |--------------------------------------------------------------------------
                | TU TABLA USA "id"
                |--------------------------------------------------------------------------
                */

                    $imageId = (int) $oldImage->id;


                    /*
                |--------------------------------------------------------------------------
                | SI EL ID NO VIENE DEL FRONTEND
                | SIGNIFICA QUE FUE ELIMINADA
                |--------------------------------------------------------------------------
                */

                    if (!in_array(
                        $imageId,
                        $existingImages
                    )) {


                        /*
                    |--------------------------------------------------------------------------
                    | BORRAR ARCHIVO FÍSICO
                    |--------------------------------------------------------------------------
                    */

                        if (
                            !empty($oldImage->image_path) &&
                            Storage::disk('public')->exists(
                                $oldImage->image_path
                            )
                        ) {

                            Storage::disk('public')->delete(
                                $oldImage->image_path
                            );
                        }


                        /*
                    |--------------------------------------------------------------------------
                    | BORRAR REGISTRO
                    |--------------------------------------------------------------------------
                    */

                        $oldImage->delete();
                    }
                }


                /*
            |--------------------------------------------------------------------------
            | 4. AGREGAR NUEVAS IMÁGENES
            |--------------------------------------------------------------------------
            */

                if (!empty($images)) {

                    foreach ($images as $image) {

                        /*
                    |--------------------------------------------------------------------------
                    | EXTENSIÓN
                    |--------------------------------------------------------------------------
                    */

                        $extension = strtolower(
                            $image->getClientOriginalExtension()
                        );


                        /*
                    |--------------------------------------------------------------------------
                    | NOMBRE ÚNICO
                    |--------------------------------------------------------------------------
                    */

                        $fileName =
                            $companyId .
                            '_' .
                            Str::uuid() .
                            '.' .
                            $extension;


                        /*
                    |--------------------------------------------------------------------------
                    | CARPETA
                    |--------------------------------------------------------------------------
                    */

                        $folder =
                            'products/' .
                            $companyId;


                        /*
                    |--------------------------------------------------------------------------
                    | GUARDAR
                    |--------------------------------------------------------------------------
                    */

                        $path = $image->storeAs(
                            $folder,
                            $fileName,
                            'public'
                        );


                        /*
                    |--------------------------------------------------------------------------
                    | REGISTRAR EN BD
                    |--------------------------------------------------------------------------
                    */

                        Image::create([

                            'product_id' =>
                            $product->id,

                            'image_path' =>
                            $path,

                        ]);
                    }
                }
            });


            /*
        |--------------------------------------------------------------------------
        | RECARGAR PRODUCTO
        |--------------------------------------------------------------------------
        */

            $product->refresh();

            $product->load([
                'brand',
                'supplier',
                'category',
                'images',
                'expirations',
                'stocks.branch',
                'typeDataProducts.typeData',
            ]);


            /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */

            return response()->json([

                'success' => true,

                'message' =>
                'Producto actualizado correctamente.',

                'data' =>
                $product

            ], 200);
        } catch (\Throwable $e) {

            return response()->json([

                'success' => false,

                'message' =>
                'No se pudo actualizar el producto.',

                'error' =>
                $e->getMessage()

            ], 422);
        }
    }
    /*
    |--------------------------------------------------------------------------
    | DELETE / DEACTIVATE
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        string $product_id
    ) {

        $user = $request->user();

        $product = Product::where('product_id', $product_id)
            ->where('id_company', $user->id_company)
            ->first();

        if (!$product) {

            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }


        /*
        |--------------------------------------------------------------------------
        | Recommended:
        | Do not physically delete inventory products.
        |--------------------------------------------------------------------------
        */

        $product->update([
            'status' => false
        ]);


        return response()->json([
            'success' => true,
            'message' => 'Product deactivated successfully'
        ]);
    }
}
