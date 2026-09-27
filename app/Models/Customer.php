<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * CRM: comprador único (pelo nome). Telefone só existe aqui. As vendas se ligam
 * por `sales.buyer == customers.name` (sem FK — o histórico é agregado por nome
 * no front), então nome é único.
 */
#[Fillable(['name', 'phone'])]
class Customer extends Model
{
    //
}
