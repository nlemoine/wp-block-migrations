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

        foreach ($fixturesProvider() as $label => $fixtures) {
            $actual = is_array($fixtures) && isset($fixtures[0]) ? $fixtures[0] : $fixtures;
            $expected = is_array($fixtures) && isset($fixtures[1]) ? $fixtures[1] : null;
            $callable(new WP_Post((object) [
                'ID' => 0,
                'post_title' => sprintf('Fixture "%s"', $label),
                'post_type' => 'fixture',
                'post_content' => $actual,
            ]), $expected);
        }
    }
}
