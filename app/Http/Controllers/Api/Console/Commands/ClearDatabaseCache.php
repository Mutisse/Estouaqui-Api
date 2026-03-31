<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearDatabaseCache extends Command
{
    protected $signature = 'cache:db-clear';
    protected $description = 'Clear all database cache tables';

    public function handle()
    {
        // Limpar tabela cache
        DB::table('cache')->delete();
        $this->info('✓ Cache table cleared');

        // Limpar tabela cache_locks
        if (DB::table('cache_locks')->count() > 0) {
            DB::table('cache_locks')->delete();
            $this->info('✓ Cache locks cleared');
        }

        $this->info('All database cache cleared successfully!');
    }
}
