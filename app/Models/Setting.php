<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value', 'created_by'];

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
