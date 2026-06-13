<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class CleanupBackups extends Command
{
    protected $signature = 'backup:cleanup {--days=30}';
    protected $description = 'Remove old backup files';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffTime = now()->subDays($days)->timestamp;

        $this->info("Cleaning up backups older than {$days} days...");

        $backupPath = storage_path('backups');
        if (!is_dir($backupPath)) {
            $this->info("No backups directory found.");
            return self::SUCCESS;
        }

        $files = glob("{$backupPath}/*");
        $deletedCount = 0;
        $freedSpace = 0;

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $fileTime = filemtime($file);
            if ($fileTime < $cutoffTime) {
                $size = filesize($file);
                unlink($file);
                $deletedCount++;
                $freedSpace += $size;
                $this->line("Deleted: " . basename($file));
            }
        }

        $freedFormatted = $this->formatBytes($freedSpace);
        $this->info("Cleaned up {$deletedCount} files, freed {$freedFormatted}");

        return self::SUCCESS;
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