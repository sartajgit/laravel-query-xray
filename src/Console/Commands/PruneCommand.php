<?php

namespace Sartajgit\QueryXray\Console\Commands;

use Illuminate\Console\Command;
use Sartajgit\QueryXray\Models\QueryFinding;

class PruneCommand extends Command
{
    protected $signature = 'query-xray:prune {--days= : Override the configured retention period}';

    protected $description = 'Delete Query X-Ray findings older than the configured retention period.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('query-xray.retention_days', 7));

        if ($days <= 0) {
            $this->warn('Retention days must be greater than 0. Nothing was pruned.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $count = QueryFinding::where('occurred_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info("No findings older than {$days} day(s) found. Nothing to prune.");

            return self::SUCCESS;
        }

        QueryFinding::where('occurred_at', '<', $cutoff)->delete();

        $this->info("Pruned {$count} finding(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}