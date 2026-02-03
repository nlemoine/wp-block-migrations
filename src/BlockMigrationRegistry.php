<?php

declare(strict_types=1);

namespace n5s\BlockMigrations;

use n5s\BlockMigrations\Migration\BlockMigrationInterface;

class BlockMigrationRegistry implements BlockMigrationRegistryInterface
{
    /**
     * @var array<string, BlockMigrationInterface>
     */
    protected array $migrations = [];

    private static ?BlockMigrationRegistryInterface $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): static
    {
        return self::$instance ??= new static();
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
