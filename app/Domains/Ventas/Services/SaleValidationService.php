<?php

namespace App\Domains\Ventas\Services;

use App\Domains\Clientes\Models\Client;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SaleValidationService
{
    public function validateRequestAmounts(array $data): void
    {
        if ($data['discount'] > $data['subtotal']) {
            throw new RuntimeException(
                'El descuento no puede ser mayor al subtotal.'
            );
        }

        if ($data['used_balance'] > $data['total']) {
            throw new RuntimeException(
                'El saldo utilizado no puede ser mayor al total de la venta.'
            );
        }

        $totalPayments = 0;

        foreach ($data['payments'] as $payment) {
            $method = strtoupper($payment['payment_method']);

            if ($method === 'BALANCE') {
                throw new RuntimeException(
                    'El saldo del cliente debe utilizarse mediante usedBalance y no mediante payments.'
                );
            }

            $totalPayments += round((float) $payment['amount'], 2);
        }

        $totalPayments = round($totalPayments, 2);

        if ($data['total_final'] > 0 && count($data['payments']) === 0) {
            throw new RuntimeException(
                'Debe registrar un método de pago para cubrir el importe restante.'
            );
        }

        if (abs($totalPayments - $data['total_final']) > 0.01) {
            throw new RuntimeException(
                'La suma de los pagos no coincide con el total pendiente.'
            );
        }
    }

    public function getBranch(int $branchId, string $idCompany): object
    {
        $branch = DB::table('branches')
            ->where('id', $branchId)
            ->where('id_company', $idCompany)
            ->where('status', true)
            ->first();

        if (!$branch) {
            throw new RuntimeException(
                'La sucursal seleccionada no existe, está inactiva o no pertenece a la empresa.'
            );
        }

        return $branch;
    }

    public function getClient(int $clientId, string $idCompany): Client
    {
        $client = Client::query()
            ->where('id', $clientId)
            ->where('id_company', $idCompany)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if (!$client) {
            throw new RuntimeException(
                'El cliente no existe o no pertenece a la empresa.'
            );
        }

        return $client;
    }

    public function validateClientBalance(
        float $usedBalance,
        float $clientBalance
    ): void {
        if ($usedBalance > $clientBalance) {
            throw new RuntimeException(
                'Saldo insuficiente. Disponible: ' .
                $clientBalance .
                '. Solicitado: ' .
                $usedBalance .
                '.'
            );
        }
    }

    public function updateClientBalance(
        Client $client,
        float $usedBalance
    ): float {
        $current = round((float) ($client->balance ?? 0), 2);
        $newBalance = round($current - $usedBalance, 2);

        if ($usedBalance > 0) {
            $client->balance = $newBalance;
            $client->save();
        }

        return $newBalance;
    }
}
