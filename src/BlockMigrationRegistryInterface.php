<?php

declare(strict_types=1);

namespace n5s\BlockMigrations;

use n5s\BlockMigrations\Migration\BlockMigrationInterface;

interface BlockMigrationRegistryInterface
{
    /**
     * Add a migration.
     */
    public function register(BlockMigrationInterface $migration): void;

    /**
     * Remove by ID.
     */
    public function unregister(string $id): void;

    public function has(string $id): bool;

    /**
     * Get a single migration by ID.
     */
    public function get(string $id): ?BlockMigrationInterface;

    /**
     * Get all registered migrations.
     *
     * @return array<string, BlockMigrationInterface>
     */
    public function all(): array;

    /**
     * Clear the entire registry.
     */
    public function clear(): void;
}
