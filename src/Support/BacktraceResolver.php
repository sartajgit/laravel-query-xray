<?php

namespace Sartajgit\QueryXray\Support;

class BacktraceResolver
{
    /** @var string[] */
    protected array $ignoredPaths;

    /** @var string[] */
    protected array $ignoredFiles;

    public function __construct(array $extraIgnoredPaths = [])
    {
        $this->ignoredPaths = array_values(array_filter(array_merge([
            $this->normalize(dirname(__DIR__)),
            $this->normalize(base_path('vendor')),
        ], $extraIgnoredPaths)));

        $this->ignoredFiles = [
            $this->normalize(base_path('public/index.php')),
            $this->normalize(base_path('artisan')),
            $this->normalize(base_path('server.php')),
        ];
    }

    /**
     * @return array{file: string, line: int}|null
     */
    public function resolve(): ?array
    {
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 50);

        foreach ($frames as $frame) {
            if (! isset($frame['file'], $frame['line'])) {
                continue;
            }

            if ($this->isIgnored($frame['file'])) {
                continue;
            }

            $file = $frame['file'];
            $line = (int) $frame['line'];

            if ($this->isCompiledView($file)) {
                $resolved = $this->resolveCompiledViewSource($file);

                if ($resolved !== null) {
                    return [
                        'file' => $this->relative($resolved),
                        'line' => 0,
                    ];
                }

                continue;
            }

            return [
                'file' => $this->relative($file),
                'line' => $line,
            ];
        }

        return null;
    }

    protected function isCompiledView(string $file): bool
    {
        $normalized = $this->normalize($file);
        $viewsCache = $this->normalize(storage_path('framework/views'));

        return strpos($normalized, $viewsCache.DIRECTORY_SEPARATOR) === 0;
    }

    protected function resolveCompiledViewSource(string $compiledFile): ?string
    {
        if (! is_readable($compiledFile)) {
            return null;
        }

        $handle = fopen($compiledFile, 'r');

        if ($handle === false) {
            return null;
        }

        $firstLine = fgets($handle);
        fclose($handle);

        if ($firstLine === false) {
            return null;
        }

        if (preg_match('/\/\*\*?compiled from (.+?)\*\//', $firstLine, $matches)) {
            $sourcePath = trim($matches[1]);

            return is_readable($sourcePath) ? $sourcePath : null;
        }

        return null;
    }

    protected function isIgnored(string $file): bool
    {
        $file = $this->normalize($file);

        if (in_array($file, $this->ignoredFiles, true)) {
            return true;
        }

        foreach ($this->ignoredPaths as $path) {
            if (strpos($file, $path.DIRECTORY_SEPARATOR) === 0) {
                return true;
            }
        }

        return false;
    }

    protected function relative(string $file): string
    {
        $base = $this->normalize(base_path()).DIRECTORY_SEPARATOR;
        $file = $this->normalize($file);

        return strpos($file, $base) === 0 ? substr($file, strlen($base)) : $file;
    }

    protected function normalize(string $path): string
    {
        $real = realpath($path);

        return rtrim($real !== false ? $real : $path, DIRECTORY_SEPARATOR);
    }
}