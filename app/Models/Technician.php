<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Technician extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    // Relasi: Tukang punya banyak order maintenance
    public function maintenanceOrders()
    {
        return $this->hasMany(MaintenanceOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
