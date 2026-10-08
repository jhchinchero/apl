<?php

namespace App\Domains\Ventas\Services;

use App\Domains\Ventas\Models\Sale;
use App\Domains\Ventas\Requests\StoreSaleRequest;
use App\Domains\Caja\Models\CashRegisterSession;
use App\Domains\Clientes\Models\Client;
use App\Domains\Contabilidad\Models\AccountingEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

class SaleService
{
    public function __construct(
        private readonly SaleValidationService $validationService,
        private readonly SaleNumberService $numberService,
        private readonly SalePaymentService $paymentService,
        private readonly SaleStockService $stockService,
        private readonly SaleAccountingService $accountingService,
        private readonly SaleResponseService $responseService,
    ) {
    }

    public function create(
        StoreSaleRequest $request,
        Authenticatable $user,
        string $idCompany
    ): array {
        $data = $this->normalize($request);

        $this->validationService->validateRequestAmounts($data);

        $result = DB::transaction(function () use ($data, $user, $idCompany) {

            $branch = $this->validationService->getBranch(
                $data['branch_id'],
                $idCompany
            );

            $client = $this->validationService->getClient(
                $data['client_id'],
                $idCompany
            );

            $clientBalanceBefore = round((float) ($client->balance ?? 0), 2);

            $this->validationService->validateClientBalance(
                $data['used_balance'],
                $clientBalanceBefore
            );

            $cashSession = $this->paymentService->getCashSessionIfRequired(
                $data['payments'],
                $user,
                $idCompany,
                $data['branch_id']
            );

            $items = $this->stockService->validateItems(
                $data['items'],
                $idCompany,
                $data['branch_id']
            );
            $totalCost = round(
    collect($items)->sum('cost_total'),
    2
);

            $codes = $this->numberService->generateSaleCodes($idCompany);

            $sale = Sale::create([
                'code_sale' => $codes['code_sale'],
                'id_company' => $idCompany,
                'receipt_number' => $codes['receipt_number'],
                'date' => now(),
                'discount' => $data['discount'],
                'total' => $data['total'],
                'pdf_url' => null,
                'user_id' => $user->id,
                'client_id' => $data['client_id'],
                'branch_id' => $data['branch_id'],
            ]);

            $createdPayments = $this->paymentService->createPayments(
                sale: $sale,
                payments: $data['payments'],
                cashSession: $cashSession,
                idCompany: $idCompany,
                branchId: $data['branch_id'],
                userId: $user->id,
            );

            $this->stockService->decreaseAndCreateDetails(
                sale: $sale,
                items: $items
            );

            $clientBalanceAfter = $this->validationService->updateClientBalance(
                $client,
                $data['used_balance']
            );
            
            //AQUI SE CREA CUENTA CONTABLE
            /* $accountingEntry = $this->accountingService->createSaleEntry(
                sale: $sale,
                payments: $createdPayments,
                usedBalance: $data['used_balance'],
                total: $data['total'],
                branchId: $data['branch_id'],
                idCompany: $idCompany,
                userId: $user->id,
            ); */
            $accountingEntry = $this->accountingService->createSaleEntry(
    sale: $sale,
    payments: $createdPayments,
    usedBalance: $data['used_balance'],
    total: $data['total'],
    costOfGoodsSold: $totalCost,
    branchId: $data['branch_id'],
    idCompany: $idCompany,
    userId: $user->id,
);

            return [
                'sale' => $sale,
                'payments' => $createdPayments,
                'accounting_entry' => $accountingEntry,
                'client_balance_before' => $clientBalanceBefore,
                'client_balance_used' => $data['used_balance'],
                'client_balance_after' => $clientBalanceAfter,
            ];
        });

        return $this->responseService->make(
            result: $result,
            subtotal: $data['subtotal'],
            discount: $data['discount'],
            total: $data['total'],
            usedBalance: $data['used_balance'],
            totalFinal: $data['total_final'],
        );
    }

    private function normalize(StoreSaleRequest $request): array
    {
        $subtotal = round((float) $request->input('subtotal'), 2);
        $discount = round((float) $request->input('discount', 0), 2);
        $total = round((float) $request->input('total'), 2);
        $usedBalance = round((float) $request->input('usedBalance', 0), 2);
        $payments = $request->input('payments', []);

        $totalFinal = round(max($total - $usedBalance, 0), 2);

        return [
            'client_id' => (int) $request->input('client_id'),
            'branch_id' => (int) $request->input('branch_id'),
            'items' => $request->input('items', []),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'used_balance' => $usedBalance,
            'total_final' => $totalFinal,
            'payments' => $payments,
        ];
    }
}
