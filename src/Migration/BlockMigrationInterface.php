<?php

declare(strict_types=1);

namespace n5s\BlockMigrations\Migration;

use WP_Post;

interface BlockMigrationInterface
{
    public function runMigration(WP_Post $post): WP_Post;

    /**
     * @return array<string>
     */
    public function getBlockNames(): array;

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
