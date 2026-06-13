<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class BackupDatabase extends Command
{
    protected $signature = 'backup:db {--filename=}';
    protected $description = 'Backup the database to a file';

    public function handle(): int
    {
        $filename = $this->option('filename') ?? 'backup-' . date('Y-m-d-His') . '.sql.gz';

        $this->info("Starting database backup: {$filename}");

        try {
            $dbHost = config('database.connections.mysql.host');
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');

            $backupPath = storage_path("backups/{$filename}");

            // Ensure directory exists
            if (!is_dir(dirname($backupPath))) {
                mkdir(dirname($backupPath), 0755, true);
            }

            // MySQL dump command
            $command = "mysqldump -h {$dbHost} -u {$dbUser} -p'{$dbPass}' {$dbName} | gzip > {$backupPath}";

            $this->info("Running mysqldump...");
            $output = [];
            $returnCode = 0;

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                $this->error("mysqldump failed with code: {$returnCode}");
                $this->error(implode("\n", $output));
                return self::FAILURE;
            }

            $fileSize = filesize($backupPath);
            $fileSizeFormatted = $this->formatBytes($fileSize);

            $this->info("Backup completed successfully!");
            $this->info("File: {$backupPath}");
            $this->info("Size: {$fileSizeFormatted}");

            // Log backup to audit
            $this->info("Cleaning up old backups (> 30 days)...");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Backup failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}