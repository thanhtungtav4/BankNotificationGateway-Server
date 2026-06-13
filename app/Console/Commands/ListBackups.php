<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class ListBackups extends Command
{
    protected $signature = 'backup:list';
    protected $description = 'List all backup files';

    public function handle(): int
    {
        $backupPath = storage_path('backups');

        if (!is_dir($backupPath)) {
            $this->info("No backups directory found.");
            return self::SUCCESS;
        }

        $files = glob("{$backupPath}/*");
        $files = array_filter($files, 'is_file');

        if (empty($files)) {
            $this->info("No backup files found.");
            return self::SUCCESS;
        }

        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $this->table(
            ['Filename', 'Size', 'Created', 'Age'],
            array_map(function ($file) {
                $stat = stat($file);
                return [
                    basename($file),
                    $this->formatBytes(filesize($file)),
                    date('Y-m-d H:i:s', $stat['mtime']),
                    $this->formatAge($stat['mtime']),
                ];
            }, $files)
        );

        $totalSize = array_sum(array_map('filesize', $files));
        $this->info("Total: " . count($files) . " files, " . $this->formatBytes($totalSize));

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

    private function formatAge(int $timestamp): string
    {
        $age = time() - $timestamp;
        if ($age < 60) {
            return "{$age}s ago";
        }
        if ($age < 3600) {
            return floor($age / 60) . "m ago";
        }
        if ($age < 86400) {
            return floor($age / 3600) . "h ago";
        }
        return floor($age / 86400) . "d ago";
    }
}