<?php

declare(strict_types=1);

namespace n5s\BlockMigrations\Command;

use Alley\WP_Bulk_Task\Bulk_Task_Side_Effects;
use Alley\WP_Bulk_Task\Progress\PHP_CLI_Progress_Bar;
use Jfcherng\Diff\DiffHelper;
use n5s\BlockMigrations\BlockMigrationRegistry;
use n5s\BlockMigrations\BlockMigrationRunner;
use n5s\BlockMigrations\Migration\BlockMigrationInterface;
use n5s\BlockMigrations\Migration\TestableBlockMigrationInterface;
use n5s\BlockMigrations\Task\BulkTask;
use WP_CLI;
use WP_CLI\Utils;
use WP_Post;
use wpdb;

class BlockMigrationCommand extends AbstractCommand
{
    use Bulk_Task_Side_Effects;

    private const ALL_MIGRATION = 'all';

    private wpdb $wpdb;

    public function __construct()
    {
        parent::__construct();
        $this->wpdb = $GLOBALS['wpdb'];
    }

    /**
     * List block migrations
     *
     * ## EXAMPLES
     *
     *     wp system-migrate list
     *
     * @param array $args
     * @param array $assocArgs
     */
    public function list(array $args, array $assocArgs): void
    {
        $blockMigrations = BlockMigrationRegistry::getInstance()->all();
        if (\count($blockMigrations) === 0) {
            $this->logger->info('No block migrations found');
            return;
        }

        WP_CLI\Utils\format_items('table', array_map(static function (BlockMigrationInterface $migration): array {
            return [
                'name' => $migration->getName(),
                'description' => $migration->getDescription(),
                'class' => $migration::class,
                'has_fixtures' => $migration instanceof TestableBlockMigrationInterface ? 'Yes' : 'No',
            ];
        }, $blockMigrations), ['name', 'description', 'class', 'has_fixtures']);
    }

    /**
     * Migrate one or more blocks
     *
     * ## OPTIONS
     *
     * <migration>...
     * : One or more migrations to run. Use "all" to run all migrations
     *
     * [--ids=<ids>]
     * : Post IDs to convert, if not provided, all posts will be converted
     *
     * [--dry-run]
     * : Preview conversions diff without updating the post
     *
     * [--fixtures]
     * : Use fixtures for testing
     *
     * [--v]
     * : Display verbose output
     *
     * [--vv]
     * : Display very verbose output
     *
     * ## EXAMPLES
     *
     *     wp system-migrate block mind/audio mind/gallery --dry-run
     *
     * @param array $args
     * @param array $assocArgs
     */
    public function run(array $args, array $assocArgs): void
    {
        $blockMigrations = BlockMigrationRegistry::getInstance()->all();
        $migrationIds = array_keys($blockMigrations);

        if (\in_array(self::ALL_MIGRATION, $args, true)) {
            if (\count($args) !== 1) {
                $this->logger->error('The "all" option cannot be used with other migration names');
                exit;
            }
            $args = $migrationIds;
        }

        // Validate migrations
        $validMigrationIds = array_intersect($migrationIds, $args);
        if (\count($validMigrationIds) !== \count($args)) {
            $this->logger->error(\sprintf('The following migration names are not supported: %s', implode(', ', array_diff($args, $validMigrationIds))));
            exit;
        }

        // Flags
        $dryRun = (bool) Utils\get_flag_value($assocArgs, 'dry-run');
        $fixtures = (bool) Utils\get_flag_value($assocArgs, 'fixtures');

        if ($dryRun && $fixtures) {
            $this->logger->error('The "dry-run" and "fixtures" options cannot be used together');
            exit;
        }

        // Get the migrations to run
        $migrationsToRun = array_filter($blockMigrations, static fn (BlockMigrationInterface $migration): bool => \in_array($migration->getName(), $validMigrationIds, true));
        ksort($migrationsToRun);
        $migrationIds = array_keys($migrationsToRun);

        // Create a chained migration
        $migrationsRunner = new BlockMigrationRunner($migrationsToRun);

        $query = $migrationsRunner->getQueryArgs();
        if ($postIds = (string) Utils\get_flag_value($assocArgs, 'ids')) {
            $query['post__in'] = array_map('intval', explode(',', $postIds));
        }

        // If fixtures is not set, all migrations must be testable
        if ($fixtures && !$migrationsRunner->isTestable()) {
            $this->logger->error('Fixtures can only be used with testable migrations');
            exit;
        }

        $query['fixtures'] = static fn (): array => $migrationsRunner->getFixtures();

        $this->pause_side_effects();

        $bulkTask = new BulkTask(
            (string) $migrationsRunner,
            new PHP_CLI_Progress_Bar(\sprintf('Running %s migrations', implode(', ', $migrationIds)))
        );

        $totalProcessed = 0;

        $bulkTask->run(
            $query,
            function (WP_Post $post) use ($migrationsRunner, $dryRun, $fixtures, &$totalProcessed): void {

                $prevPost = clone $post;

                $shortlink = wp_get_shortlink($post->ID);
                $this->logger->notice(str_repeat('=', 80));
                $this->logger->notice(\sprintf('Processing %s "%s" (%s)', $post->post_type, $post->post_title, $shortlink ? $shortlink : $post->ID));
                $this->logger->notice(str_repeat('=', 80));

                $totalProcessed++;

                try {
                    $migrationsRunner->run($post);
                } catch (\Throwable $exception) {
                    WP_DEBUG && throw $exception;

                    $this->logger->error($exception->getMessage());
                    return;
                }

                if ($this->isVeryVerbose()) { // Just so we don't compute a diff for nothing
                    $this->logger->info($this->getDiff($prevPost->post_content, $post->post_content));
                }

                if ($prevPost->post_content === $post->post_content) {
                    $this->logger->info('No update, same content');
                    return;
                }

                // Don't update the post if it's a dry run or fixtures
                if ($dryRun || $fixtures) {
                    return;
                }

                // Update
                $result = $this->wpdb->update($this->wpdb->posts, ['post_content' => $post->post_content], ['ID' => $post->ID]);
                if ($result === false && $this->wpdb->last_error) {
                    $this->logger->error($this->wpdb->last_error);
                    return;
                }

                if (!is_numeric($result)) {
                    $this->logger->error(\sprintf('An error occurred while updating post %d', $post->ID));
                    return;
                }

                clean_post_cache($post->ID);

                $this->logger->info(\sprintf('%s "%s" (%d) has been updated', $post->post_type, $post->post_title, $post->ID));
            },
            $fixtures ? 'fixtures' : 'wp_post'
        );

        $this->logger->info(\sprintf('Processed %d posts', $totalProcessed));

        $bulkTask->cursor->reset();

        $this->resume_side_effects();
    }

    private function getDiff(string $contentBefore, string $contentAfter): string
    {
        return DiffHelper::calculate($contentBefore, $contentAfter, 'Unified', [
            'ignoreWhitespace' => true,
            'ignoreLineEnding' => true,
            'detailLevel' => 'word',
        ]);
    }
}
