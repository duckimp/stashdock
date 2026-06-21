<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['github_nickname', 'github_email', 'github_token'])]
#[Hidden(['github_token'])]
class SystemSettings extends Model
{
    protected $table = 'system_settings';
}
