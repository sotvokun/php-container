<?php

declare(strict_types=1);

namespace Tests\Support;

use FilesystemIterator;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class TemporaryDirectory
{
    private const MARKER = '.sotvokun-container-test';

    private string $path;

    public function __construct()
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sotvokun-container-tests';
        if (!is_dir($base) && !mkdir($base, 0777, true) && !is_dir($base)) {
            throw new LogicException("Unable to create test directory {$base}.");
        }

        $this->path = $base . DIRECTORY_SEPARATOR . bin2hex(random_bytes(16));
        if (!mkdir($this->path, 0777) || !is_dir($this->path)) {
            throw new LogicException("Unable to create test directory {$this->path}.");
        }

        file_put_contents($this->path . DIRECTORY_SEPARATOR . self::MARKER, 'created by sotvokun/container tests');
    }

    public function path(): string
    {
        return $this->path;
    }

    public function generatedClasses(): string
    {
        $path = $this->path . DIRECTORY_SEPARATOR . 'generated';
        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new LogicException("Unable to create generated-class directory {$path}.");
        }

        return $path;
    }

    public function remove(): void
    {
        if (!isset($this->path) || !is_dir($this->path)) {
            return;
        }

        $base = realpath(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sotvokun-container-tests');
        $path = realpath($this->path);
        if ($base === false || $path === false || dirname($path) !== $base || !is_file($path . DIRECTORY_SEPARATOR . self::MARKER)) {
            throw new LogicException('Refusing to remove a directory not created by this test helper.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($path);
    }
}
