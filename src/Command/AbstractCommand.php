<?php

declare(strict_types=1);

namespace n5s\BlockMigrations\Command;

use Monolog\Logger;
use n5s\BlockMigrations\Monolog\Handler\WpCliHandler;
use Psr\Log\LoggerInterface;
use WP_CLI;
use WP_CLI_Command;

abstract class AbstractCommand extends WP_CLI_Command
{
    protected int $logLevel;
    protected readonly LoggerInterface $logger;

    public const VERBOSITY_VERBOSE = 'v';
    public const VERBOSITY_VERY_VERBOSE = 'vv';
    public const VERBOSITY_VERY_VERY_VERBOSE = 'vvv';
    public const VERBOSITY_DEBUG = self::VERBOSITY_VERY_VERY_VERBOSE;

    /**
     * @var array<string, int> Map of verbosity levels to Monolog levels
     */
    private array $verbosityLevelMap = [
        self::VERBOSITY_VERBOSE => Logger::NOTICE,
        self::VERBOSITY_VERY_VERBOSE => Logger::INFO,
        self::VERBOSITY_DEBUG => Logger::DEBUG,
    ];

    public function __construct()
    {
        $this->logger = new Logger('wp-cli');
        if (!$this->logger instanceof Logger) {
            return;
        }

        $this->logLevel = Logger::toMonologLevel(Logger::INFO);
        $assocArgs = WP_CLI::get_runner()->assoc_args ?? [];
        foreach ($this->verbosityLevelMap as $arg => $level) {
            if (isset($assocArgs[$arg]) && $assocArgs[$arg]) {
                $this->logLevel = $level;
                break;
            }
        }

        $this->logger->pushHandler(new WpCliHandler($this->logLevel));
    }

    protected function isVerbose(): bool
    {
        return $this->logLevel <= $this->verbosityLevelMap[self::VERBOSITY_VERBOSE];
    }

    protected function isVeryVerbose(): bool
    {
        return $this->logLevel <= $this->verbosityLevelMap[self::VERBOSITY_VERY_VERBOSE];
    }

    protected function isVeryVeryVerbose(): bool
    {
        return $this->logLevel <= $this->verbosityLevelMap[self::VERBOSITY_VERY_VERY_VERBOSE];
    }

    protected function isDebug(): bool
    {
        return $this->isVeryVeryVerbose();
    }
}
