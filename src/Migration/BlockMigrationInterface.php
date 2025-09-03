<?php

declare(strict_types=1);

namespace n5s\BlockMigrations\Migration;

use WP_Post;

interface BlockMigrationInterface
{
    /**
     * Run the migration for the given post.
     *
     * @param WP_Post $post
     * @return WP_Post The migrated post.
     */
    public function runMigration(WP_Post $post): WP_Post;

    /**
     * Whether the migration should be run for the given post.
     *
     * @param WP_Post $post
     * @return bool
     */
    public function shouldRun(WP_Post $post): bool;

    /**
     * Migration id
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Migration name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Migration description
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * @return array<string>
     */
    public function getQueryArgs(): array;
}
