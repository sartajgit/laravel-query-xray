<?php

namespace Sartajgit\QueryXray\Models;

use Illuminate\Database\Eloquent\Model;

class QueryFinding extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'bindings' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('query-xray.table_name', 'query_xray_findings');
    }
}