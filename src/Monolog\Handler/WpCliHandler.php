<?php

declare(strict_types=1);

namespace n5s\BlockMigrations\Monolog\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use WP_CLI;

class WpCliHandler extends AbstractProcessingHandler
{
    /**
     * Write a log to the wp-cli.
     *
     * @param array $record Log Record.
     */
    protected function write(array $record): void
    {
        if (!\defined('WP_CLI') || !\WP_CLI) {
            return;
        }

        if (empty($record['level']) || empty($record['message']) || empty($record['level_name'])) {
            return;
        }

        $message = "[{$record['level_name']}]: {$record['message']}";

        if ($record['level'] === Logger::ERROR) {
            $message = WP_CLI::colorize('%R' . $message . '%n');
        } elseif ($record['level'] === Logger::WARNING) {
            $message = WP_CLI::colorize('%y' . $message . '%n');
        } elseif ($record['level'] === Logger::INFO) {
            $message = WP_CLI::colorize('%b' . $message . '%n');
        } elseif ($record['level'] === Logger::DEBUG) {
            $message = WP_CLI::colorize('%w' . $message . '%n');
        }

        WP_CLI::log($message);
    }
}
