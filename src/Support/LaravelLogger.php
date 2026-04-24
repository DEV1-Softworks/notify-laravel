<?php

namespace Dev1\NotifyLaravel\Support;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface as PsrLogger;

class LaravelLogger extends AbstractLogger
{
    /** @var PsrLogger */
    private $logger;

    public function __construct($logger)
    {
        if ($logger instanceof PsrLogger) {
            $this->logger = $logger;
        } elseif (method_exists($logger, 'getLogger')) {
            $this->logger = $logger->getLogger();
        } else {
            $this->logger = new class($logger) extends AbstractLogger {
                private $inner;

                public function __construct($inner)
                {
                    $this->inner = $inner;
                }

                public function log($level, $message, array $context = []): void
                {
                    if (method_exists($this->inner, $level)) {
                        $this->inner->{$level}($message, $context);
                        return;
                    }
                    $this->inner->log($level, $message, $context);
                }
            };
        }
    }

    public function log($level, $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
