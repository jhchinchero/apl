<?php

namespace App\Domains\Inventario\Imports;

use App\Domains\Empresa\Models\Branch;
use App\Domains\Inventario\Models\Product;
use App\Domains\Inventario\Models\StockMovement;
use App\Domains\Inventario\Models\StockProduct;

use Illuminate\Support\Facades\DB;
use Throwable;

use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Maatwebsite\Excel\Row;

class StockImport implements OnEachRow, WithHeadingRow
{
    protected string $companyId;
    protected int $userId;
    protected string $mode;

    protected array $branches = [];

    protected array $result = [
        'success' => false,
        'status' => 'ERROR',
        'mode' => null,
        'total_rows' => 0,
        'registered' => 0,
        'not_found' => 0,
        'invalid' => 0,
        'errors' => [],
        'products_not_found' => [],
    ];

    public function __construct(
        string $companyId,
        int $userId,
        string $mode
    ) {
        $this->companyId = $companyId;
        $this->userId = $userId;
        $this->mode = strtoupper(trim($mode));

        $this->result['mode'] = $this->mode;

        HeadingRowFormatter::default('none');

        /*
        |--------------------------------------------------------------------------
        | CARGAR SUCURSALES DE LA EMPRESA
        |--------------------------------------------------------------------------
        */

        $branches = Branch::where(
            'id_company',
            $this->companyId
        )->get();

        foreach ($branches as $branch) {

            $code = $this->normalizeBranchCode(
                $branch->code_branch
            );

            if ($code !== '') {

                $this->branches[$code] = $branch;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PROCESAR FILA
    |--------------------------------------------------------------------------
    */

    public function onRow(Row $row): void
    {
        $this->result['total_rows']++;

        $productCode = null;

        try {

            /*
            |--------------------------------------------------------------------------
            | VALIDAR MODO
            |--------------------------------------------------------------------------
            */

            if (!in_array(
                $this->mode,
                ['INCREMENT', 'ADJUST'],
                true
            )) {

                $this->result['invalid']++;

                $this->addError(
                    'INVALID_MODE',
                    'Modo de importación inválido.',
                    [
                        'mode' => $this->mode,
                        'excel_row' => $row->getRowIndex(),
                    ]
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | CONVERTIR FILA A ARRAY
            |--------------------------------------------------------------------------
            */

            $rowData = collect(
                $row->toArray()
            )
                ->mapWithKeys(
                    function ($value, $key) {

                        return [
                            strtoupper(
                                trim((string) $key)
                            ) => $value
                        ];
                    }
                )
                ->toArray();

            /*
            |--------------------------------------------------------------------------
            | IGNORAR FILA VACÍA
            |--------------------------------------------------------------------------
            */

            $hasData = collect($rowData)->contains(
                function ($value) {

                    return $value !== null
                        &&
                        trim((string) $value) !== '';
                }
            );

            if (!$hasData) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | 1. OBTENER CODIGO DEL EXCEL
            |--------------------------------------------------------------------------
            |
            | EXCEL:
            |
            | CODIGO = PRD-00001
            |
            */

            $productCode = trim(
                (string) (
                    $rowData['CODIGO'] ?? ''
                )
            );

            if ($productCode === '') {

                $this->result['invalid']++;

                $this->addError(
                    'INVALID_PRODUCT_CODE',
                    'La fila no contiene CODIGO.',
                    [
                        'excel_row' =>
                        $row->getRowIndex(),
                    ]
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | 2. BUSCAR PRODUCTO ÚNICAMENTE POR products.product_code
            |--------------------------------------------------------------------------
            |
            | IMPORTANTE:
            |
            | NO usamos:
            |
            | code_product
            | model_product
            | name
            | id recibido del Excel
            |
            | SOLAMENTE:
            |
            | products.product_code
            |
            */

            $product = Product::where(
                'id_company',
                $this->companyId
            )
                ->where(
                    'product_code',
                    $productCode
                )
                ->first();

            /*
            |--------------------------------------------------------------------------
            | SI NO EXISTE EL PRODUCTO
            |--------------------------------------------------------------------------
            */

            if (!$product) {

                $this->result['not_found']++;

                $error = [
                    'type' => 'PRODUCT_NOT_FOUND',

                    'message' =>
                    "No existe un producto con product_code: {$productCode}.",

                    'data' => [
                        'excel_row' =>
                        $row->getRowIndex(),

                        'product_code' =>
                        $productCode,

                        'model' =>
                        $rowData['MODELO'] ?? null,

                        'description' =>
                        $rowData['DESCRIPCION'] ?? null,
                    ],
                ];

                $this->result['errors'][] = $error;

                $this->result['products_not_found'][] = $error;

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | AQUÍ TENEMOS EL ID REAL DEL PRODUCTO
            |--------------------------------------------------------------------------
            |
            | Ejemplo:
            |
            | Excel:
            | product_code = PRD-00001
            |
            | products:
            | id = 25
            |
            | Desde este punto usamos:
            |
            | $product->id
            |
            */

            $productId = $product->id;

            /*
            |--------------------------------------------------------------------------
            | PRECIO
            |--------------------------------------------------------------------------
            */

            $price = null;

            if (array_key_exists(
                'PRECIO',
                $rowData
            )) {

                $priceValue =
                    $rowData['PRECIO'];

                if (is_string($priceValue)) {

                    $priceValue =
                        trim($priceValue);

                    $priceValue =
                        str_replace(
                            [
                                'Bs.',
                                'Bs',
                                '$',
                                ',',
                            ],
                            '',
                            $priceValue
                        );

                    $priceValue =
                        trim($priceValue);
                }

                if (
                    $priceValue !== null
                    &&
                    $priceValue !== ''
                ) {

                    if (!is_numeric($priceValue)) {

                        $this->result['invalid']++;

                        $this->addError(
                            'INVALID_PRICE',
                            "El precio de {$productCode} no es válido.",
                            [
                                'excel_row' =>
                                $row->getRowIndex(),

                                'product_code' =>
                                $productCode,

                                'price' =>
                                $priceValue,
                            ]
                        );

                        return;
                    }

                    $price =
                        (float) $priceValue;

                    if ($price < 0) {

                        $this->result['invalid']++;

                        $this->addError(
                            'INVALID_PRICE',
                            "El precio de {$productCode} no puede ser negativo.",
                            [
                                'excel_row' =>
                                $row->getRowIndex(),

                                'product_code' =>
                                $productCode,

                                'price' =>
                                $price,
                            ]
                        );

                        return;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | ACTUALIZAR products.sale_price
                    |--------------------------------------------------------------------------
                    */

                    $product->update([
                        'sale_price' => $price,
                    ]);

                    $product->refresh();
                }
            }

            /*
            |--------------------------------------------------------------------------
            | COLUMNAS QUE NO SON SUCURSALES
            |--------------------------------------------------------------------------
            */

            $fixedColumns = [
                'CODIGO',
                'MODELO',
                'DESCRIPCION',
                'PRECIO',
            ];

            /*
            |--------------------------------------------------------------------------
            | RECORRER COLUMNAS DE SUCURSALES
            |--------------------------------------------------------------------------
            */

            foreach ($rowData as $column => $quantityValue) {

                /*
                |--------------------------------------------------------------------------
                | IGNORAR COLUMNAS FIJAS
                |--------------------------------------------------------------------------
                */

                if (
                    in_array(
                        $column,
                        $fixedColumns,
                        true
                    )
                ) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | NORMALIZAR CÓDIGO DE SUCURSAL
                |--------------------------------------------------------------------------
                */

                $branchCode =
                    $this->normalizeBranchCode(
                        $column
                    );

                /*
                |--------------------------------------------------------------------------
                | BUSCAR LA SUCURSAL EN LAS SUCURSALES
                |--------------------------------------------------------------------------
                |
                | Excel:
                |
                | BR-00001
                |
                | obtiene:
                |
                | $branch->id
                |
                */

                if (
                    $branchCode === ''
                    ||
                    !isset(
                        $this->branches[$branchCode]
                    )
                ) {

                    /*
                    | No es una sucursal de esta empresa.
                    |
                    | Por ejemplo:
                    | MARCA
                    | PROVEEDOR
                    | UNIDAD
                    | etc.
                    */

                    continue;
                }

                $branch =
                    $this->branches[$branchCode];

                /*
                |--------------------------------------------------------------------------
                | AQUÍ TENEMOS EL ID REAL DE LA SUCURSAL
                |--------------------------------------------------------------------------
                */

                $branchId =
                    $branch->id;

                /*
                |--------------------------------------------------------------------------
                | CANTIDAD VACÍA
                |--------------------------------------------------------------------------
                */

                if (
                    $quantityValue === null
                    ||
                    trim(
                        (string) $quantityValue
                    ) === ''
                ) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | LIMPIAR CANTIDAD
                |--------------------------------------------------------------------------
                */

                if (is_string($quantityValue)) {

                    $quantityValue =
                        str_replace(
                            ',',
                            '',
                            trim($quantityValue)
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | VALIDAR CANTIDAD
                |--------------------------------------------------------------------------
                */

                if (!is_numeric($quantityValue)) {

                    $this->result['invalid']++;

                    $this->addError(
                        'INVALID_QUANTITY',
                        "La cantidad de {$branchCode} no es válida.",
                        [
                            'excel_row' =>
                            $row->getRowIndex(),

                            'product_code' =>
                            $productCode,

                            'product_id' =>
                            $productId,

                            'branch_code' =>
                            $branchCode,

                            'branch_id' =>
                            $branchId,

                            'quantity' =>
                            $quantityValue,
                        ]
                    );

                    continue;
                }

                $quantity =
                    (float) $quantityValue;

                /*
                |--------------------------------------------------------------------------
                | NO PERMITIR NEGATIVOS
                |--------------------------------------------------------------------------
                */

                if ($quantity < 0) {

                    $this->result['invalid']++;

                    $this->addError(
                        'INVALID_QUANTITY',
                        "La cantidad de {$branchCode} no puede ser negativa.",
                        [
                            'excel_row' =>
                            $row->getRowIndex(),

                            'product_code' =>
                            $productCode,

                            'product_id' =>
                            $productId,

                            'branch_code' =>
                            $branchCode,

                            'branch_id' =>
                            $branchId,

                            'quantity' =>
                            $quantity,
                        ]
                    );

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | INCREMENTAR CON CERO
                |--------------------------------------------------------------------------
                |
                | En INCREMENT:
                |
                | 0 = no hacer nada
                |
                */

                if (
                    $this->mode === 'INCREMENT'
                    &&
                    $quantity == 0
                ) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | TRANSACCIÓN
                |--------------------------------------------------------------------------
                */

                try {

                    DB::transaction(
                        function () use (
                            $productId,
                            $product,
                            $branchId,
                            $branch,
                            $quantity,
                            $productCode,
                            $row
                        ) {

                            /*
                            |--------------------------------------------------------------------------
                            | 3. BUSCAR STOCK POR:
                            |
                            | id_company
                            | product_id
                            | branch_id
                            |--------------------------------------------------------------------------
                            |
                            | AQUÍ ESTÁ LA RELACIÓN CORRECTA:
                            |
                            | product_code
                            |       ↓
                            | products.id
                            |       ↓
                            | product_stock.product_id
                            |
                            | branch_code
                            |       ↓
                            | branches.id
                            |       ↓
                            | product_stock.branch_id
                            |
                            */

                            $stock =
                                StockProduct::where(
                                    'id_company',
                                    $this->companyId
                                )
                                ->where(
                                    'product_id',
                                    $productId
                                )
                                ->where(
                                    'branch_id',
                                    $branchId
                                )
                                ->lockForUpdate()
                                ->first();

                            /*
                            |--------------------------------------------------------------------------
                            | CREAR STOCK SI NO EXISTE
                            |--------------------------------------------------------------------------
                            */

                            if (!$stock) {

                                $stock =
                                    StockProduct::create([
                                        'id_company' =>
                                        $this->companyId,

                                        'branch_id' =>
                                        $branchId,

                                        'product_id' =>
                                        $productId,

                                        'current_stock' =>
                                        0,

                                        'status' =>
                                        true,

                                        'price' =>
                                        $product->sale_price ?? 0,

                                        'location' =>
                                        null,
                                    ]);

                                /*
                                |--------------------------------------------------------------------------
                                | VOLVER A OBTENER CON BLOQUEO
                                |--------------------------------------------------------------------------
                                */

                                $stock =
                                    StockProduct::where(
                                        'id',
                                        $stock->id
                                    )
                                    ->lockForUpdate()
                                    ->firstOrFail();
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | STOCK ACTUAL
                            |--------------------------------------------------------------------------
                            */

                            $stockAnterior =
                                (float) (
                                    $stock->current_stock ?? 0
                                );

                            /*
                            |--------------------------------------------------------------------------
                            | PRECIO ACTUAL
                            |--------------------------------------------------------------------------
                            */

                            $stockPrice =
                                $product->sale_price ?? 0;

                            /*
                            |--------------------------------------------------------------------------
                            | MODO INCREMENT
                            |--------------------------------------------------------------------------
                            |
                            | Excel = cantidad a SUMAR.
                            |
                            | BD 25
                            | Excel 10
                            |
                            | FINAL 35
                            |
                            */

                            if (
                                $this->mode === 'INCREMENT'
                            ) {

                                $stockNuevo =
                                    $stockAnterior
                                    +
                                    $quantity;

                                /*
                                |--------------------------------------------------------------------------
                                | ACTUALIZAR product_stock
                                |--------------------------------------------------------------------------
                                */

                                $stock->update([
                                    'current_stock' =>
                                    $stockNuevo,

                                    'price' =>
                                    $stockPrice,
                                ]);

                                /*
                                |--------------------------------------------------------------------------
                                | MOVIMIENTO
                                |--------------------------------------------------------------------------
                                */

                                StockMovement::create([
                                    'id_company' =>
                                    $this->companyId,

                                    'branch_id' =>
                                    $branchId,

                                    'product_id' =>
                                    $productId,

                                    'user_id' =>
                                    $this->userId,

                                    'movement_type' =>
                                    'ENTRY',

                                    'quantity' =>
                                    $quantity,

                                    'reference_type' =>
                                    'product',

                                    'reference_id' =>
                                    $productId,

                                    'note' =>
                                    'Ingreso de stock desde Excel. '
                                        . 'Stock anterior: '
                                        . $stockAnterior
                                        . ' | Cantidad: '
                                        . $quantity
                                        . ' | Stock final: '
                                        . $stockNuevo,
                                ]);

                                $this->result['registered']++;

                                return;
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | MODO ADJUST
                            |--------------------------------------------------------------------------
                            |
                            | Excel = STOCK FINAL
                            |
                            | BD 25
                            | Excel 10
                            |
                            | FINAL 10
                            |
                            | BD 25
                            | Excel 0
                            |
                            | FINAL 0
                            |
                            */

                            if (
                                $this->mode === 'ADJUST'
                            ) {

                                $stockNuevo = $quantity;

                                $diferencia =
                                    $stockNuevo -
                                    $stockAnterior;

                                $stock->update([
                                    'current_stock' =>
                                    $stockNuevo,

                                    'price' =>
                                    $stockPrice,
                                ]);

                                if (abs($diferencia) > 0.000001) {

                                    StockMovement::create([
                                        'id_company' =>
                                        $this->companyId,

                                        'branch_id' =>
                                        $branchId,

                                        'product_id' =>
                                        $productId,

                                        'user_id' =>
                                        $this->userId,

                                        'movement_type' =>
                                        'ADJUSTMENT',

                                        // IMPORTANTE: conservar el signo
                                        'quantity' =>
                                        $diferencia,

                                        'reference_type' =>
                                        'product',

                                        'reference_id' =>
                                        $productId,

                                        'note' =>
                                        'Ajuste de stock desde Excel. '
                                            . 'Stock anterior: '
                                            . $stockAnterior
                                            . ' | Stock final: '
                                            . $stockNuevo
                                            . ' | Diferencia: '
                                            . $diferencia,
                                    ]);
                                }

                                $this->result['registered']++;

                                return;
                            }

                            throw new \Exception(
                                "Modo inválido: {$this->mode}"
                            );
                        }
                    );
                } catch (Throwable $e) {

                    $this->result['invalid']++;

                    $this->addError(
                        'STOCK_ERROR',
                        "No se pudo actualizar stock.",
                        [
                            'excel_row' =>
                            $row->getRowIndex(),

                            'product_code' =>
                            $productCode,

                            'product_id' =>
                            $productId,

                            'branch_code' =>
                            $branchCode,

                            'branch_id' =>
                            $branchId,

                            'quantity' =>
                            $quantity,

                            'mode' =>
                            $this->mode,

                            'error' =>
                            $e->getMessage(),
                        ]
                    );
                }
            }
        } catch (Throwable $e) {

            $this->result['invalid']++;

            $this->addError(
                'ROW_ERROR',
                'Error procesando la fila.',
                [
                    'excel_row' =>
                    $row->getRowIndex(),

                    'product_code' =>
                    $productCode,

                    'error' =>
                    $e->getMessage(),
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZAR BRANCH CODE
    |--------------------------------------------------------------------------
    */

    protected function normalizeBranchCode(
        string $code
    ): string {

        $code =
            strtoupper(
                trim($code)
            );

        if ($code === '') {
            return '';
        }

        /*
        |--------------------------------------------------------------------------
        | BR00001
        | BR_00001
        | BR 00001
        | BR-00001
        |--------------------------------------------------------------------------
        */

        $clean =
            preg_replace(
                '/[^A-Z0-9]/',
                '',
                $code
            );

        if (
            $clean !== ''
            &&
            str_starts_with(
                $clean,
                'BR'
            )
        ) {

            $number =
                substr(
                    $clean,
                    2
                );

            if (
                $number !== ''
                &&
                ctype_digit($number)
            ) {

                return
                    'BR-'
                    .
                    str_pad(
                        $number,
                        5,
                        '0',
                        STR_PAD_LEFT
                    );
            }
        }

        return $code;
    }

    /*
    |--------------------------------------------------------------------------
    | ERROR
    |--------------------------------------------------------------------------
    */

    protected function addError(
        string $type,
        string $message,
        array $data = []
    ): void {

        $this->result['errors'][] = [
            'type' =>
            $type,

            'message' =>
            $message,

            'data' =>
            $data,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RESULTADO
    |--------------------------------------------------------------------------
    */

    public function getResult(): array
    {
        $registered =
            $this->result['registered'];

        $notFound =
            $this->result['not_found'];

        $invalid =
            $this->result['invalid'];

        if (
            $registered > 0
            &&
            $notFound === 0
            &&
            $invalid === 0
        ) {

            $status = 'SUCCESS';
        } elseif (
            $registered > 0
        ) {

            $status = 'PARTIAL';
        } else {

            $status = 'ERROR';
        }

        $this->result['status'] =
            $status;

        $this->result['success'] =
            $registered > 0;

        return $this->result;
    }
}
