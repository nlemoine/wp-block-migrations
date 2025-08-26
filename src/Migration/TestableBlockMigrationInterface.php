<?php

declare(strict_types=1);

namespace n5s\BlockMigrations\Migration;

interface TestableBlockMigrationInterface extends BlockMigrationInterface
{
    /**
     * @return iterable<string, string|array{'0': string, '1': string}>
     */
    public function getFixtures(): iterable;
}
