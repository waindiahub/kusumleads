<?php

class AdminCache
{
    private static function baseDir(): string
    {
        static $base = null;
        if ($base === null) {
            $base = dirname(__DIR__) . '/cache';
            if (!is_dir($base)) {
                @mkdir($base, 0755, true);
            }
        }
        return $base;
    }

    public static function registry(): array
    {
        $base = self::baseDir();
        return [
            'meta_templates' => [
                'label' => 'Meta Templates',
                'path' => $base . '/meta_templates.json',
                'description' => 'Latest synced WhatsApp templates served to the builder.',
                'ttl' => 900,
                'icon' => 'fab fa-whatsapp'
            ],
            'dashboard_snapshots' => [
                'label' => 'Dashboard Snapshots',
                'path' => $base . '/dashboard_snapshots.json',
                'description' => 'Aggregated KPI snapshots powering dashboard widgets.',
                'ttl' => 300,
                'icon' => 'fas fa-chart-line'
            ],
            'export_buffers' => [
                'label' => 'Exports & Reports',
                'path' => $base . '/exports',
                'description' => 'Temporary CSV/Excel exports generated from admin reports.',
                'is_dir' => true,
                'icon' => 'fas fa-file-export'
            ]
        ];
    }

    public static function ensureDirectories(): void
    {
        foreach (self::registry() as $meta) {
            if (!empty($meta['is_dir'])) {
                if (!is_dir($meta['path'])) {
                    @mkdir($meta['path'], 0755, true);
                }
            } else {
                $parent = dirname($meta['path']);
                if (!is_dir($parent)) {
                    @mkdir($parent, 0755, true);
                }
            }
        }
    }

    public static function write(string $segment, $payload): bool
    {
        $registry = self::registry();
        if (!isset($registry[$segment]) || !empty($registry[$segment]['is_dir'])) {
            return false;
        }
        self::ensureDirectories();
        $path = $registry[$segment]['path'];
        $data = [
            'cached_at' => time(),
            'data' => $payload
        ];
        return @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE)) !== false;
    }

    public static function read(string $segment, array $options = []): ?array
    {
        $registry = self::registry();
        if (!isset($registry[$segment]) || !empty($registry[$segment]['is_dir'])) {
            return null;
        }
        $path = $registry[$segment]['path'];
        if (!file_exists($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        $data = json_decode($raw, true);
        if ($data === null) {
            return null;
        }

        $maxAge = $options['max_age'] ?? ($registry[$segment]['ttl'] ?? null);
        $allowStale = $options['allow_stale'] ?? false;
        if (!$allowStale && $maxAge && isset($data['cached_at'])) {
            $age = time() - (int) $data['cached_at'];
            if ($age > $maxAge) {
                return null;
            }
        }

        return $data;
    }

    public static function clear(?string $segment = null): array
    {
        $registry = self::registry();
        $targets = $segment ? [$segment => $registry[$segment] ?? null] : $registry;
        $summary = [
            'files' => 0,
            'bytes' => 0,
            'segments' => []
        ];

        foreach ($targets as $key => $meta) {
            if (!$meta) {
                continue;
            }
            $result = self::purgePath($meta);
            $summary['files'] += $result['files'];
            $summary['bytes'] += $result['bytes'];
            $summary['segments'][$key] = $result;
        }

        return $summary;
    }

    private static function purgePath(array $meta): array
    {
        $files = 0;
        $bytes = 0;

        if (!empty($meta['is_dir'])) {
            if (!is_dir($meta['path'])) {
                return ['files' => 0, 'bytes' => 0];
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($meta['path'], FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    continue;
                }
                $bytes += $file->getSize();
                $files++;
                @unlink($file->getPathname());
            }
        } else {
            if (file_exists($meta['path'])) {
                $bytes += filesize($meta['path']);
                $files++;
                @unlink($meta['path']);
            }
        }

        return ['files' => $files, 'bytes' => $bytes];
    }

    public static function inventory(): array
    {
        $registry = self::registry();
        $inventory = [];

        foreach ($registry as $key => $meta) {
            $entry = [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'icon' => $meta['icon'] ?? 'fas fa-database',
                'size' => 0,
                'size_human' => '0 KB',
                'count' => 0,
                'updated_at' => null,
                'updated_human' => null
            ];

            if (!empty($meta['is_dir'])) {
                if (is_dir($meta['path'])) {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($meta['path'], FilesystemIterator::SKIP_DOTS)
                    );
                    foreach ($iterator as $file) {
                        if ($file->isFile()) {
                            $entry['size'] += $file->getSize();
                            $entry['count']++;
                            $entry['updated_at'] = max($entry['updated_at'] ?? 0, $file->getMTime());
                        }
                    }
                }
            } else {
                if (file_exists($meta['path'])) {
                    $entry['size'] = filesize($meta['path']);
                    $entry['count'] = 1;
                    $entry['updated_at'] = filemtime($meta['path']);
                }
            }

            $entry['size_human'] = self::formatBytes($entry['size']);
            if ($entry['updated_at']) {
                $entry['updated_human'] = date('M j, Y g:i A', $entry['updated_at']);
            }
            $inventory[$key] = $entry;
        }

        return $inventory;
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 KB';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $bytes /= 1024 ** $pow;
        return round($bytes, 1) . ' ' . $units[$pow];
    }
}


