<?php

declare(strict_types=1);

namespace n5s\BlockMigrations\Task;

use Alley\WP_Bulk_Task\Bulk_Task;
use WP_CLI;
use WP_Post;

class BulkTask extends Bulk_Task
{
    /**
     * WP_BUlk_Task uses snake case method names
     *
     * @param array $args
     * @param callable $callable
     *
     * @return void
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function run_fixtures_query(array $args, callable $callable): void
    {
        $fixturesProvider = $args['fixtures'] ?? null;

        if ($fixturesProvider === null || !is_callable($fixturesProvider)) {
            WP_CLI::warning('Migration not found.');
            return;
        }

        $items = $fixturesProvider();

        foreach ($items as $index => $content) {
            $callable(new WP_Post((object) [
                'ID' => 0,
                'post_title' => sprintf('Fixture %d', $index),
                'post_type' => 'fixture',
                'post_content' => $content,
            ]));
        }
    }
}
