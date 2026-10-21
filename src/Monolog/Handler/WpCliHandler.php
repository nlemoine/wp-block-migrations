<?php

declare(strict_types=1);

namespace n5s\BlockMigrations\Monolog\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use WP_CLI;

class WpCliHandler extends AbstractProcessingHandler
{
    /**
     * Write a log to the wp-cli.
     *
     * Untyped so that it overrides both write(array $record) from Monolog 2 and write(LogRecord $record) from Monolog 3.
     *
     * @param array|LogRecord $record Log Record.
     */
    // phpcs:ignore Syde.Functions.ArgumentTypeDeclaration.NoArgumentType
    protected function write($record): void
    {
        if (!\defined('WP_CLI') || !\WP_CLI) {
            return;
        }

        if ($record instanceof LogRecord) {
            $record = $record->toArray();
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
