<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdminBackupController extends Controller
{
    /**
     * Listar todos os backups
     * GET /admin/backups
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', 15);
            $page = (int) $request->input('page', 1);
            $search = $request->input('search');
            $tipo = $request->input('tipo');
            $dataInicio = $request->input('data_inicio');
            $dataFim = $request->input('data_fim');

            $backupPath = storage_path('app/backups');
            $backups = [];

            if (!File::exists($backupPath)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'current_page' => $page,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ]);
            }

            $files = File::files($backupPath);

            foreach ($files as $file) {
                $filename = $file->getFilename();
                $ext = $file->getExtension();

                if ($ext === 'sql' || $ext === 'zip') {
                    $backup = [
                        'id' => $file->getCTime(),
                        'nome' => $filename,
                        'tamanho' => $file->getSize(),
                        'tamanho_formatado' => $this->formatarTamanho($file->getSize()),
                        'tipo' => $this->determinarTipo($filename),
                        'status' => 'completado',
                        'data' => Carbon::createFromTimestamp($file->getMTime())->toDateTimeString(),
                        'created_at' => Carbon::createFromTimestamp($file->getMTime())->toDateTimeString(),
                    ];

                    $backups[] = $backup;
                }
            }

            // Ordenar por data decrescente
            usort($backups, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

            // Aplicar filtros
            if ($search) {
                $backups = array_values(array_filter($backups, fn($b) => stripos($b['nome'], $search) !== false));
            }

            if ($tipo) {
                $backups = array_values(array_filter($backups, fn($b) => $b['tipo'] === $tipo));
            }

            if ($dataInicio) {
                $backups = array_values(array_filter($backups, fn($b) => $b['data'] >= $dataInicio));
            }

            if ($dataFim) {
                $backups = array_values(array_filter($backups, fn($b) => $b['data'] <= $dataFim));
            }

            $total = count($backups);
            $lastPage = max(1, ceil($total / $perPage));
            $offset = ($page - 1) * $perPage;
            $backups = array_slice($backups, $offset, $perPage);

            return response()->json([
                'success' => true,
                'data' => array_values($backups),
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao carregar backups: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar backups: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Criar um novo backup
     * POST /admin/backups
     */
    public function store(Request $request)
    {
        try {
            $tipo = $request->input('tipo', 'manual');

            $backupPath = storage_path('app/backups');
            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0755, true);
            }

            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $backupPath . '/' . $filename;

            $this->gerarBackupDatabase($filepath);

            if (!File::exists($filepath)) {
                throw new \Exception('Falha ao gerar arquivo de backup');
            }

            $size = File::size($filepath);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => time(),
                    'nome' => $filename,
                    'tamanho' => $size,
                    'tamanho_formatado' => $this->formatarTamanho($size),
                    'tipo' => $tipo,
                    'status' => 'completado',
                    'data' => now()->toDateTimeString(),
                    'created_at' => now()->toDateTimeString(),
                ],
                'message' => 'Backup criado com sucesso'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao criar backup: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download de um backup
     * GET /admin/backups/download/{filename}
     */
    public function download($filename)
    {
        try {
            $backupPath = storage_path('app/backups/' . $filename);

            if (!File::exists($backupPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup não encontrado'
                ], 404);
            }

            return response()->download($backupPath, $filename, [
                'Content-Type' => 'application/octet-stream',
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao baixar backup: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao baixar backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar um backup
     * DELETE /admin/backups/{filename}
     */
    public function destroy($filename)
    {
        try {
            $backupPath = storage_path('app/backups/' . $filename);

            if (!File::exists($backupPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup não encontrado'
                ], 404);
            }

            File::delete($backupPath);

            return response()->json([
                'success' => true,
                'message' => 'Backup excluído com sucesso'
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao excluir backup: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restaurar um backup
     * POST /admin/backups/{filename}/restaurar
     */
    public function restaurar($filename)
    {
        try {
            $backupPath = storage_path('app/backups/' . $filename);

            if (!File::exists($backupPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup não encontrado'
                ], 404);
            }

            $sql = File::get($backupPath);

            if (empty($sql)) {
                throw new \Exception('Arquivo de backup vazio');
            }

            DB::unprepared($sql);

            Log::info('Backup restaurado com sucesso: ' . $filename);

            return response()->json([
                'success' => true,
                'message' => 'Backup restaurado com sucesso'
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao restaurar backup: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao restaurar backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estatísticas de backups
     * GET /admin/backups/estatisticas
     */
    public function estatisticas()
    {
        try {
            $backupPath = storage_path('app/backups');
            $total = 0;
            $totalTamanho = 0;
            $ultimoBackup = null;

            if (File::exists($backupPath)) {
                $files = File::files($backupPath);
                $total = count($files);

                foreach ($files as $file) {
                    $totalTamanho += $file->getSize();
                }

                if ($total > 0) {
                    $ultimoFile = collect($files)->sortByDesc(fn($f) => $f->getMTime())->first();
                    $ultimoBackup = Carbon::createFromTimestamp($ultimoFile->getMTime())->toDateTimeString();
                }
            }

            $estatisticas = [
                'total' => $total,
                'total_tamanho' => $totalTamanho,
                'total_tamanho_formatado' => $this->formatarTamanho($totalTamanho),
                'ultimo_backup' => $ultimoBackup,
                'media_tamanho' => $total > 0 ? $this->formatarTamanho($totalTamanho / $total) : '0 B',
            ];

            return response()->json([
                'success' => true,
                'data' => $estatisticas
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao carregar estatísticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar estatísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpar backups antigos
     * DELETE /admin/backups/limpar
     */
    public function limpar(Request $request)
    {
        try {
            $diasManter = (int) $request->input('dias', 30);
            $backupPath = storage_path('app/backups');
            $deletados = 0;

            if (File::exists($backupPath)) {
                $files = File::files($backupPath);
                $dataLimite = Carbon::now()->subDays($diasManter);

                foreach ($files as $file) {
                    $fileDate = Carbon::createFromTimestamp($file->getMTime());
                    if ($fileDate->lt($dataLimite)) {
                        File::delete($file);
                        $deletados++;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "{$deletados} backups antigos removidos com sucesso"
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao limpar backups: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao limpar backups: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Formatar tamanho em bytes para formato legível
     */
    private function formatarTamanho(int $bytes): string
    {
        if ($bytes === 0) return '0 B';

        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes) / log($k));

        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    /**
     * Determinar tipo do backup pelo nome do arquivo
     */
    private function determinarTipo(string $filename): string
    {
        if (str_contains($filename, 'manual')) return 'manual';
        if (str_contains($filename, 'auto')) return 'auto';
        return 'agendado';
    }

    /**
     * Gerar backup do banco de dados
     */
    private function gerarBackupDatabase(string $filepath): void
    {
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port', 3306);

        if (empty($database)) {
            throw new \Exception('Configuração do banco de dados não encontrada');
        }

        // Método alternativo usando PHP puro se mysqldump não estiver disponível
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%s %s > %s 2>&1',
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($database),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            // Fallback: tentar gerar backup com PHP puro
            $this->gerarBackupComPhp($filepath);
        }

        if (!File::exists($filepath) || File::size($filepath) === 0) {
            throw new \Exception('Falha ao gerar backup do banco de dados');
        }
    }

    /**
     * Gerar backup usando PHP puro (fallback quando mysqldump não está disponível)
     */
    private function gerarBackupComPhp(string $filepath): void
    {
        $tables = DB::select('SHOW TABLES');
        $database = config('database.connections.mysql.database');
        $tableKey = "Tables_in_{$database}";

        $sql = "-- Backup gerado em " . now() . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $tableName = $table->$tableKey;

            // Estrutura da tabela
            $createTable = DB::select("SHOW CREATE TABLE {$tableName}");
            $sql .= "-- Estrutura da tabela `{$tableName}`\n";
            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $sql .= $createTable[0]->{'Create Table'} . ";\n\n";

            // Dados da tabela
            $rows = DB::table($tableName)->get();
            if ($rows->count() > 0) {
                $sql .= "-- Inserindo dados na tabela `{$tableName}`\n";
                foreach ($rows->chunk(100) as $chunk) {
                    $values = [];
                    foreach ($chunk as $row) {
                        $rowArray = (array) $row;
                        $escapedValues = array_map(function ($value) {
                            if ($value === null) return 'NULL';
                            return "'" . addslashes($value) . "'";
                        }, array_values($rowArray));
                        $values[] = "(" . implode(',', $escapedValues) . ")";
                    }
                    $columns = array_keys((array) $rows[0]);
                    $columns = array_map(fn($col) => "`{$col}`", $columns);
                    $sql .= "INSERT INTO `{$tableName}` (" . implode(',', $columns) . ") VALUES\n";
                    $sql .= implode(",\n", $values) . ";\n\n";
                }
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        File::put($filepath, $sql);
    }

    /**
     * Obter configurações de backup
     * GET /admin/backups/configuracoes
     */
    public function configuracoes()
    {
        try {
            $configPath = storage_path('app/backups/config.json');
            $defaultConfig = [
                'ativo' => true,
                'frequencia' => 'diario',
                'horario' => '02:00',
                'dia_semana' => 1,
                'dia_mes' => 1,
                'manter_ultimos' => 30,
                'incluir_database' => true,
                'incluir_uploads' => true,
                'incluir_logs' => true,
                'destino' => 'local',
            ];

            if (File::exists($configPath)) {
                $config = json_decode(File::get($configPath), true);
                $config = array_merge($defaultConfig, $config);
            } else {
                $config = $defaultConfig;
            }

            return response()->json([
                'success' => true,
                'data' => $config
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao carregar configurações: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar configurações: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Salvar configurações de backup
     * PUT /admin/backups/configuracoes
     */
    public function atualizarConfiguracoes(Request $request)
    {
        try {
            $validated = $request->validate([
                'ativo' => 'boolean',
                'frequencia' => 'in:diario,semanal,mensal',
                'horario' => 'string',
                'dia_semana' => 'integer|min:0|max:6',
                'dia_mes' => 'integer|min:1|max:28',
                'manter_ultimos' => 'integer|min:1|max:365',
                'incluir_database' => 'boolean',
                'incluir_uploads' => 'boolean',
                'incluir_logs' => 'boolean',
                'destino' => 'in:local,s3,dropbox,google_drive',
            ]);

            $configPath = storage_path('app/backups/config.json');

            if (!File::exists(dirname($configPath))) {
                File::makeDirectory(dirname($configPath), 0755, true);
            }

            File::put($configPath, json_encode($validated, JSON_PRETTY_PRINT));

            return response()->json([
                'success' => true,
                'message' => 'Configurações salvas com sucesso'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao salvar configurações: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar configurações: ' . $e->getMessage()
            ], 500);
        }
    }
}
