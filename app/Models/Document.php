<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'title', 'body', 'file_url', 'position','user_role','status', 'sales_rep_dash'];
}
