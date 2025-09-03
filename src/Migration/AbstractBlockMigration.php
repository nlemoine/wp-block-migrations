<?php

declare(strict_types=1);

namespace n5s\BlockMigrations\Migration;

use n5s\BlockVisitor\BlockTraverser;
use n5s\BlockVisitor\Visitor\TransformingBlockVisitorInterface;
use Psr\Log\LoggerAwareTrait;
use WP_Post;

abstract class AbstractBlockMigration implements BlockMigrationInterface
{
    use LoggerAwareTrait;

    private BlockTraverser $blockTraverser;

    /**
     * @param TransformingBlockVisitorInterface[] $visitors
     */
    public function __construct(
        private array $visitors = []
    ) {

        if ($this instanceof TransformingBlockVisitorInterface) {
            $this->visitors[] = $this;
        }

        if (count($this->visitors) === 0) {
            return;
        }

        $this->blockTraverser = new BlockTraverser(...$this->visitors);
    }

    public function getId(): string
    {
        return $this->getName();
    }

    public function shouldRun(WP_Post $post): bool
    {
        return true;
    }

    /**
     * The default query for all migrations. Override this method to customize the query.
     *
     * @return array
     */
    public function getQueryArgs(): array
    {
        // Get post types that support the Block Editor.
        $postTypes = array_intersect(
            get_post_types_by_support('editor'),
            get_post_types(['show_in_rest' => true])
        );

        // Add revision post type.
        $postTypes[] = 'revision';

        return [
            'post_type' => $postTypes,
            'post_status' => 'any',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];
    }

    public function runMigration(WP_Post $post): WP_Post
    {
        if (!isset($this->blockTraverser)) {
            $this->logger?->warning(sprintf('No visitor has been added to this migration %s', $this::class));
            return $post;
        }

        if (!$this->shouldRun($post)) {
            return $post;
        }

        $this->logger?->info(sprintf('Running %s migration on post %d', $this->getName(), $post->ID));
        $post->post_content = (string) $this->blockTraverser->traverse($post->post_content);

        return $post;
    }
}
