<?php

declare(strict_types=1);

namespace n5s\BlockMigrations\Migration;

use n5s\BlockVisitor\BlockTraverser;
use n5s\BlockVisitor\Visitor\BlockVisitorInterface;
use Psr\Log\LoggerAwareTrait;
use WP_Post;

abstract class AbstractBlockMigration implements BlockMigrationInterface
{
    use LoggerAwareTrait;

    private BlockTraverser $blockTraverser;

    /**
     * @param BlockVisitorInterface[] $visitors
     */
    public function __construct(
        private array $visitors
    ) {

        $this->blockTraverser = new BlockTraverser(...$this->visitors);
    }

    public function getId(): string
    {
        return sanitize_key($this->getName());
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
        $hasAnyBlock = array_any($this->getBlockNames(), static function (string $blockName) use ($post): bool {
            return has_block($blockName, $post->post_content);
        });

        if (!$hasAnyBlock) {
            return $post;
        }

        $this->logger?->info(sprintf('Running %s migration on post %d', $this->getName(), $post->ID));
        $post->post_content = (string) $this->blockTraverser->traverse($post->post_content);

        return $post;
    }
}
