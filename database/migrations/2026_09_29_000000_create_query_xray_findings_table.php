<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('query-xray.table_name', 'query_xray_findings');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();      // n_plus_one | slow_query | duplicate_query | unoptimized_query
            $table->string('issue', 60)->nullable();    // select_star | leading_wildcard_like | missing_limit
            $table->string('fingerprint', 32)->index();
            $table->text('sql');
            $table->json('bindings')->nullable();
            $table->unsignedInteger('count')->default(1);
            $table->float('time_ms')->nullable();
            $table->string('connection', 60)->nullable();
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->string('http_method', 10)->nullable();
            $table->string('url', 2048)->nullable();
            $table->text('suggestion')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('query-xray.table_name', 'query_xray_findings'));
    }
};