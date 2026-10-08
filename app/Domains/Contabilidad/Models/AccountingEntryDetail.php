<?php

namespace App\Domains\Contabilidad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AccountingEntryDetail extends Model
{
use HasFactory;

    protected $table = 'accounting_entry_details';

    protected $fillable = [
        'accounting_entry_id',
        'accounting_account_id',
        'description',
        'debit',
        'credit',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function entry()
    {
        return $this->belongsTo(
            AccountingEntry::class,
            'accounting_entry_id',
            'id'
        );
    }

    public function account()
    {
        return $this->belongsTo(
            AccountingAccount::class,
            'accounting_account_id',
            'id'
        );
    }
}