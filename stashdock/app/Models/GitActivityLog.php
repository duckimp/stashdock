<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GitActivityLog extends Model
{
    protected $table = 'git_activities_log';

    protected $fillable = [
        'project_name',
        'activity_type',
        'executed_at',
    ];

    protected $casts = [
        'executed_at' => 'date',
    ];
}
