<?php

declare(strict_types=1);

namespace n5s\BlockMigrations;

use Closure;
use n5s\BlockMigrations\Migration\BlockMigrationInterface;
use n5s\BlockMigrations\Migration\TestableBlockMigrationInterface;
use n5s\BlockMigrations\Migration\PrioritizedBlockMigrationInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use RuntimeException;
use Stringable;
use WP_Post;
use wpdb;

final class BlockMigrationRunner implements LoggerAwareInterface, Stringable
{
    use LoggerAwareTrait;

    /**
     * @param BlockMigrationInterface[] $migrations
     * @param callable|null $postUpdater Hook to persist WP_Post
     */
    public function __construct(
        private array $migrations,
        private ?Closure $postUpdater = null
    ) {

        $this->postUpdater ??= $this->updatePost(...);
        $this->sortMigrations();
    }

    /**
     * Default post updater
     *
     * @param WP_Post $post
     *
     * @return bool
     */
    private function updatePost(WP_Post $post): bool
    {
        /** @var wpdb */
        global $wpdb;
        return $wpdb->update(
            $wpdb->posts,
            [
                'post_content' => $post->post_content,
            ],
            ['ID' => $post->ID],
            ['%s'],
            ['%d']
        ) !== false;
    }

    /**
     * Run all migrations
     */
    public function run(WP_Post $post): WP_Post
    {
        foreach ($this->migrations as $migration) {
            $migration->runMigration($post);
        }

        return $post;
    }

    /**
     * Merge queries from all migrations
     *
     * @param array $args
     *
     * @return array
     */
    public function getQueryArgs(array $args = []): array
    {
        // Merge all migrations queries
        $query = array_merge_recursive(...array_values(array_map(static fn (BlockMigrationInterface $migration): array => $migration->getQueryArgs(), $this->migrations)));

        // Remove duplicates
        return array_merge(array_map(static fn (array|string|bool|float|int $value) => \is_array($value) ? array_unique($value) : $value, $query), $args);
    }

    public function isTestable(): bool
    {
        return array_all($this->migrations, static fn (BlockMigrationInterface $migration): bool => $migration instanceof TestableBlockMigrationInterface);
    }

    /**
     * Get migration fixtures
     *
     * @return iterable<string|array{'0': string, '1': string}>
     */
    public function getFixtures(): iterable
    {
        if (!$this->isTestable()) {
            throw new RuntimeException(sprintf('All migrations must implement the %s interface to use fixtures', TestableBlockMigrationInterface::class));
        }

        yield from array_merge(...array_values(array_map(static fn (TestableBlockMigrationInterface $migration): iterable => iterator_to_array($migration->getFixtures()), $this->migrations)));
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return hash('xxh128', json_encode(array_keys($this->migrations)));
    }

    /**
     * Sorts migrations by priority.
     */
    private function sortMigrations(): void
    {
        usort($this->migrations, static function (BlockMigrationInterface $a, BlockMigrationInterface $b): int {
            $priorityA = $a instanceof PrioritizedBlockMigrationInterface ? $a->getPriority() : 0;
            $priorityB = $b instanceof PrioritizedBlockMigrationInterface ? $b->getPriority() : 0;

            return $priorityB <=> $priorityA;
        });
    }
}
