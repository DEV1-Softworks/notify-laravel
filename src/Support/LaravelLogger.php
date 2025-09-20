<?php

namespace Dev1\NotifyLaravel\Support;

use Psr\Log\LoggerInterface as PsrLogger;

class LaravelLogger implements PsrLogger
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
            $this->logger = new class($logger) implements PsrLogger {
                private $inner;

                public function __construct($inner)
                {
                    $this->inner = $inner;
                }

                public function emergency($message, array $context = [])
                {
                    $this->inner->emergency($message, $context);
                }
                public function alert($message, array $context = [])
                {
                    $this->inner->alert($message, $context);
                }
                public function critical($message, array $context = [])
                {
                    $this->inner->critical($message, $context);
                }
                public function error($message, array $context = [])
                {
                    $this->inner->error($message, $context);
                }
                public function warning($message, array $context = [])
                {
                    $this->inner->warning($message, $context);
                }
                public function notice($message, array $context = [])
                {
                    $this->inner->notice($message, $context);
                }
                public function info($message, array $context = [])
                {
                    $this->inner->info($message, $context);
                }
                public function debug($message, array $context = [])
                {
                    $this->inner->debug($message, $context);
                }
                public function log($level, $message, array $context = [])
                {
                    $this->inner->log($level, $message, $context);
                }
            };
        }
    }

    public function emergency($message, array $context = [])
    {
        $this->logger->emergency($message, $context);
    }

    public function alert($message, array $context = [])
    {
        $this->logger->alert($message, $context);
    }

    public function critical($message, array $context = [])
    {
        $this->logger->critical($message, $context);
    }

    public function error($message, array $context = [])
    {
        $this->logger->error($message, $context);
    }

    public function warning($message, array $context = [])
    {
        $this->logger->warning($message, $context);
    }

    public function notice($message, array $context = [])
    {
        $this->logger->notice($message, $context);
    }

    public function info($message, array $context = [])
    {
        $this->logger->info($message, $context);
    }

    public function debug($message, array $context = [])
    {
        $this->logger->debug($message, $context);
    }

    public function log($level, $message, array $context = [])
    {
        $this->logger->log($level, $message, $context);
    }
}
