<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SessionsPurge extends Command
{
    protected $signature = 'sessions:purge {--minutes= : Minutos de inactividad para considerar expirada una sesion}';

    protected $description = 'Elimina sesiones expiradas y registros huerfanos del lanzador (driver database)';

    public function handle(): int
    {
        if (config('session.driver') !== 'database') {
            $this->warn('El driver de sesion no es "database"; nada que purgar.');

            return self::SUCCESS;
        }

        $minutes = (int) ($this->option('minutes') ?? config('session.lifetime', 120));
        $cutoff = now()->getTimestamp() - ($minutes * 60);

        $sesiones = DB::table('sessions')->where('last_activity', '<', $cutoff)->delete();

        $huerfanas = DB::table('lanzador_sesiones')
            ->whereNotIn('session_id', fn ($query) => $query->select('id')->from('sessions'))
            ->delete();

        $this->info("Sesiones expiradas eliminadas: {$sesiones}. Registros del lanzador huerfanos eliminados: {$huerfanas}.");

        return self::SUCCESS;
    }
}
