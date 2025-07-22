<?php

declare(strict_types=1);

namespace n5s\BlockMigrations;

use n5s\BlockMigrations\Migration\BlockMigrationInterface;

final class BlockMigrationRegistry
{
    /**
     * @var array<string, BlockMigrationInterface>
     */
    private array $migrations = [];

    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Add a migration.
     */
    public function register(BlockMigrationInterface $migration): void
    {
        $this->migrations[$migration->getId()] = $migration;
    }

    /**
     * Remove by ID.
     */
    public function unregister(string $id): void
    {
        unset($this->migrations[$id]);
    }

    public function has(string $id): bool
    {
        return isset($this->migrations[$id]);
    }

    /**
     * Get a single migration by ID.
     */
    public function get(string $id): ?BlockMigrationInterface
    {
        return $this->migrations[$id] ?? null;
    }

    /**
     * Get all registered migrations.
     *
     * @return array<string, BlockMigrationInterface>
     */
    public function all(): array
    {
        return $this->migrations;
    }

    /**
     * Clear the entire registry.
     */
    public function clear(): void
    {
        $this->migrations = [];
    }
}
