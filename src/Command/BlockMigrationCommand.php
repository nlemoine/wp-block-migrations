<?php

declare(strict_types=1);

namespace n5s\BlockMigrations\Command;

use Alley\WP_Bulk_Task\Bulk_Task_Side_Effects;
use Alley\WP_Bulk_Task\Progress\PHP_CLI_Progress_Bar;
use n5s\BlockMigrations\BlockMigrationRegistry;
use n5s\BlockMigrations\BlockMigrationRegistryInterface;
use n5s\BlockMigrations\BlockMigrationRunner;
use n5s\BlockMigrations\Migration\BlockMigrationInterface;
use n5s\BlockMigrations\Migration\TestableBlockMigrationInterface;
use n5s\BlockMigrations\Task\BulkTask;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;
use WP_CLI\Formatter;
use WP_CLI\Utils;
use WP_Post;
use wpdb;

class BlockMigrationCommand extends AbstractCommand
{
    use Bulk_Task_Side_Effects;

    private const ALL_MIGRATION = 'all';

    private wpdb $wpdb;

    private BlockMigrationRegistryInterface $registry;

    public function __construct(?BlockMigrationRegistryInterface $registry = null)
    {
        parent::__construct();
        $this->wpdb = $GLOBALS['wpdb'];
        $this->registry = $registry ?? BlockMigrationRegistry::getInstance();
    }

    /**
     * List block migrations
     *
     * [--fields=<fields>]
     * : Limit the output to specific row fields. Defaults to name,description,class,has_fixtures
     *
     * [--format=<format>]
     * : Render output in a particular format.
     *
     * @param array $args
     * @param array $assocArgs
     */
    public function list(array $args, array $assocArgs): void
    {
        $blockMigrations = $this->registry->all();
        if (\count($blockMigrations) === 0) {
            $this->logger->info('No block migrations found');
            return;
        }

        $fields = array_map(trim(...), explode(',', Utils\get_flag_value($assocArgs, 'fields', 'name,description,class,has_fixtures')));

        $items = array_map(static function (BlockMigrationInterface $migration): array {
            return [
                'name' => $migration->getName(),
                'description' => $migration->getDescription(),
                'class' => $migration::class,
                'has_fixtures' => $migration instanceof TestableBlockMigrationInterface ? 'Yes' : 'No',
            ];
        }, $blockMigrations);

        $formatter = new Formatter($assocArgs, $fields);
        $formatter->display_items($items);
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
     * [--diff-output=<path>]
     * : Save diff files to the specified folder (creates if needed)
     *
     * @param array $args
     * @param array $assocArgs
     */
    public function run(array $args, array $assocArgs): void
    {
        if (\in_array(self::ALL_MIGRATION, $args, true)) {
            if (\count($args) !== 1) {
                $this->logger->error('The "all" option cannot be used with other migration names');
                exit;
            }
            $migrationsToRun = $this->registry->all();
        } else {
            $migrationsToRun = [];
            $notFound = [];
            foreach ($args as $id) {
                $migration = $this->registry->get($id);
                if ($migration === null) {
                    $notFound[] = $id;
                    continue;
                }
                $migrationsToRun[$id] = $migration;
            }

            if (\count($notFound) > 0) {
                $this->logger->error(\sprintf('The following migration names are not supported: %s', implode(', ', $notFound)));
                exit;
            }
        }

        $migrationIds = array_keys($migrationsToRun);

        // Flags
        $dryRun = (bool) Utils\get_flag_value($assocArgs, 'dry-run');
        $fixtures = (bool) Utils\get_flag_value($assocArgs, 'fixtures');
        $diffOutputPath = Utils\get_flag_value($assocArgs, 'diff-output');

        if ($dryRun && $fixtures) {
            $this->logger->error('The "dry-run" and "fixtures" options cannot be used together');
            exit;
        }

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

        if ($fixtures) {
            $query = [
                'fixtures' => static fn (): iterable => $migrationsRunner->getFixtures(),
            ];
        }

        // Create diff output folder if specified
        if ($diffOutputPath) {
            $diffOutputPath = rtrim($diffOutputPath, '/\\');
            if (!is_dir($diffOutputPath) && !mkdir($diffOutputPath, 0755, true)) {
                $this->logger->error(\sprintf('Failed to create diff output folder: %s', $diffOutputPath));
                exit;
            }
        }

        $this->pause_side_effects();

        $bulkTask = new BulkTask(
            (string) $migrationsRunner,
            new PHP_CLI_Progress_Bar(\sprintf('Running %s migrations', implode(', ', $migrationIds)))
        );

        $totalProcessed = 0;

        $bulkTask->run(
            $query,
            function (WP_Post $post, mixed $expected = null) use ($migrationsRunner, $dryRun, $fixtures, $diffOutputPath, &$totalProcessed): void {

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

                // Generate diff if verbose output or diff-output is specified
                if ($this->isVeryVerbose() || $diffOutputPath) {
                    $slug = sanitize_title($post->post_title) ?: 'untitled';
                    $postIdentifier = \sprintf('%d-%s-%s', $post->ID, $slug, $post->post_type);
                    $diff = $this->getDiff($prevPost->post_content, $post->post_content, $postIdentifier);

                    if ($this->isVeryVerbose()) {
                        $this->logger->info($diff);
                        // There is an expected fixture, check if it matches
                        if (is_string($expected)) {
                            $pass = $post->post_content === $expected;
                            $this->logger->{$pass ? 'info' : 'error'}($pass ? '✔ Output matches expected fixture' : '✘ Output does not match expected fixture');
                        }
                    }

                    if ($diffOutputPath && $prevPost->post_content !== $post->post_content) {
                        $filename = \sprintf('%s/%s.diff', $diffOutputPath, $postIdentifier);
                        file_put_contents($filename, $diff);
                        $this->logger->info(\sprintf('Diff saved to %s', $filename));
                    }
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

        if ($diffOutputPath) {
            $this->logger->info(\sprintf('Diff files saved to: %s', $diffOutputPath));
        }

        $bulkTask->cursor->reset();

        $this->resume_side_effects();
    }

    private function getDiff(string $contentBefore, string $contentAfter, string $filename = 'content'): string
    {
        $differ = new Differ(new StrictUnifiedDiffOutputBuilder([
            'fromFile' => 'a/' . $filename,
            'toFile' => 'b/' . $filename,
        ]));
        return $differ->diff($contentBefore, $contentAfter);
    }
}
