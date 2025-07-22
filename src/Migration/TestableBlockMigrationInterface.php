<?php

declare(strict_types=1);

namespace n5s\BlockMigrations\Migration;

interface TestableBlockMigrationInterface extends BlockMigrationInterface
{
    /**
     * @return array<string>
     */
    public function getFixtures(): array;
}
