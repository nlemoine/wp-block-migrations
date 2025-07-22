<?php

declare(strict_types=1);

namespace n5s\BlockMigrations\Migration;

interface PrioritizedBlockMigrationInterface
{
    public function getPriority(): int;
}
