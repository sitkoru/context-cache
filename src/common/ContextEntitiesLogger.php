<?php

namespace sitkoru\contextcache\common;

use Psr\Log\LoggerInterface;

class ContextEntitiesLogger
{
    /**
     * @var null|LoggerInterface
     */
    private $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function info(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->info($message, $context);
        }
    }

    public function error(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error($message, $context);
        }
    }

    public function debug(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->debug($message, $context);
        }
    }

    public function warning(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->warning($message, $context);
        }
    }
}
