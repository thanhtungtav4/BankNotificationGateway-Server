<?php

declare(strict_types=1);

namespace Server\Support;

final class JsonStore
{
    public function __construct(private readonly string $directory)
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
    }

    /** @return list<array<string,mixed>> */
    public function read(string $name): array
    {
        $path = $this->path($name);
        if (! is_file($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    /** @param array<string,mixed> $record */
    public function append(string $name, array $record): void
    {
        $rows = $this->read($name);
        $rows[] = $record;
        $this->write($name, $rows);
    }

    /** @param list<array<string,mixed>> $rows */
    public function write(string $name, array $rows): void
    {
        file_put_contents($this->path($name), json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function nextId(string $name): int
    {
        $ids = array_map(fn (array $row): int => (int) ($row['id'] ?? 0), $this->read($name));
        return $ids === [] ? 1 : max($ids) + 1;
    }

    private function path(string $name): string
    {
        return $this->directory . '/' . preg_replace('/[^a-z0-9_-]/i', '', $name) . '.json';
    }
}
