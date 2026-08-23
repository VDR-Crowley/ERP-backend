<?php

namespace App\Models;

use App\Models\Concerns\HasMockFlag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'contact', 'active', 'is_mock'])]
class Vendedor extends Model
{
    use HasFactory, HasMockFlag;

    /** Nome em português — o inflector padrão do Eloquent pluralizaria errado ("vendedors"). */
    protected $table = 'vendedores';

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_mock' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'seller_id');
    }

    public function vendorStock(): HasMany
    {
        return $this->hasMany(VendorStock::class);
    }
}
