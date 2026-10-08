<?php
namespace App\Domains\Clientes\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Clientes\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class ClientController extends Controller
{
    /**
     * List clients of the authenticated company.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $clients = Client::query()
            ->where('id_company', $user->id_company)
            ->when($request->search, function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code_client', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('ciNit', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('code_client')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    /**
     * Create client.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Company is obtained from authenticated user.
        // NEVER trust id_company from frontend.
        $idCompany = $user->id_company;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'ciNit' => [
                'required',
                'string',
                'max:255',
                Rule::unique('clients', 'ciNit')
                    ->where(function ($query) use ($idCompany) {
                        return $query->where(
                            'id_company',
                            $idCompany
                        );
                    }),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:255',
            ],
            'address' => [
                'nullable',
                'string',
                'max:255',
            ],
            'person_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'balance' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'status' => [
                'nullable',
                'boolean',
            ],
        ]);

        $client = DB::transaction(function () use (
            $validated,
            $idCompany
        ) {
            /*
             * Get last client of this company.
             */
            $lastClient = Client::withTrashed()
                ->where('id_company', $idCompany)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            /*
             * Determine next number.
             */
            if ($lastClient) {
                $lastNumber = (int) str_replace(
                    'CLI-',
                    '',
                    $lastClient->code_client
                );
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            /*
             * Generate visible code_client.
             */
            $clientcode_client = 'CLI-' . str_pad(
                $nextNumber,
                5,
                '0',
                STR_PAD_LEFT
            );

            /*
             * Create client.
             */
            return Client::create([
                'code_client' => $clientcode_client,
                // Comes from authenticated user
                'id_company' => $idCompany,
                'status' => $validated['status'] ?? true,
                'name' => $validated['name'],
                'ciNit' => $validated['ciNit'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'person_reference' => $validated['person_reference'] ?? null,
                'balance' => $validated['balance'] ?? 0,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Client created successfully.',
            'data' => $client,
        ], 201);
    }

    /**
     * Show client.
     */
    public function show(string $id)
    {
        $user = Auth::user();

        $client = Client::query()
            ->where('id_company', $user->id_company)
            ->where('code_client', $id)
            ->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $client,
        ]);
    }

    /**
     * Update client.
     */
    public function update(Request $request, string $id)
    {
        $user = Auth::user();

        $client = Client::query()
            ->where('id_company', $user->id_company)
            ->where('code_client', $id)
            ->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'ciNit' => [
                'required',
                'string',
                'max:255',
                Rule::unique('clients', 'ciNit')
                    ->where(function ($query) use ($user) {
                        return $query->where(
                            'id_company',
                            $user->id_company
                        );
                    })
                    ->ignore($client->id),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:255',
            ],
            'address' => [
                'nullable',
                'string',
                'max:255',
            ],
            'person_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'balance' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'status' => [
                'nullable',
                'boolean',
            ],
        ]);

        // Never allow changing company or client code_client.
        unset(
            $validated['id_company'],
            $validated['code_client']
        );

        $client->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Client updated successfully.',
            'data' => $client,
        ]);
    }

    /**
     * Soft delete client.
     */
    public function destroy(string $id)
    {
        $user = Auth::user();

        $client = Client::query()
            ->where('id_company', $user->id_company)
            ->where('code_client', $id)
            ->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        $client->delete();

        return response()->json([
            'success' => true,
            'message' => 'Client deleted successfully.',
        ]);
    }

    /**
     * Restore client.
     */
    public function restore(string $id)
    {
        $user = Auth::user();

        $client = Client::withTrashed()
            ->where('id_company', $user->id_company)
            ->where('code_client', $id)
            ->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        $client->restore();

        return response()->json([
            'success' => true,
            'message' => 'Client restored successfully.',
            'data' => $client,
        ]);
    }
}