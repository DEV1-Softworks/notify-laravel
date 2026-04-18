<?php

namespace Dev1\NotifyLaravel\Tests\Support;

use Dev1\NotifyLaravel\Support\LaravelLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LaravelLoggerTest extends TestCase
{
    public function test_delegates_every_psr_level_to_a_psr_logger()
    {
        $spy = new SpyPsrLogger();
        $logger = new LaravelLogger($spy);

        $logger->emergency('e', ['x' => 1]);
        $logger->alert('a');
        $logger->critical('c');
        $logger->error('er');
        $logger->warning('w');
        $logger->notice('n');
        $logger->info('i');
        $logger->debug('d');
        $logger->log('info', 'l', ['k' => 'v']);

        $this->assertSame(
            ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'],
            array_column($spy->calls, 'level')
        );
        $this->assertSame(['x' => 1], $spy->calls[0]['context']);
        $this->assertSame(['k' => 'v'], $spy->calls[8]['context']);
    }

    public function test_extracts_inner_logger_via_get_logger_when_available()
    {
        $inner = new SpyPsrLogger();
        $wrapper = new class($inner) {
            private $inner;
            public function __construct($inner) { $this->inner = $inner; }
            public function getLogger() { return $this->inner; }
        };

        $logger = new LaravelLogger($wrapper);
        $logger->info('via-getLogger');

        $this->assertCount(1, $inner->calls);
        $this->assertSame('info', $inner->calls[0]['level']);
    }

    public function test_wraps_non_psr_logger_with_closure_fallback()
    {
        $sink = new class {
            public $calls = [];
            public function emergency($m, array $c = []) { $this->calls[] = ['emergency', $m, $c]; }
            public function alert($m, array $c = [])     { $this->calls[] = ['alert', $m, $c]; }
            public function critical($m, array $c = [])  { $this->calls[] = ['critical', $m, $c]; }
            public function error($m, array $c = [])     { $this->calls[] = ['error', $m, $c]; }
            public function warning($m, array $c = [])   { $this->calls[] = ['warning', $m, $c]; }
            public function notice($m, array $c = [])    { $this->calls[] = ['notice', $m, $c]; }
            public function info($m, array $c = [])      { $this->calls[] = ['info', $m, $c]; }
            public function debug($m, array $c = [])     { $this->calls[] = ['debug', $m, $c]; }
            public function log($l, $m, array $c = [])   { $this->calls[] = ['log', $l, $m, $c]; }
        };

        $logger = new LaravelLogger($sink);

        $logger->emergency('e');
        $logger->alert('a');
        $logger->critical('c');
        $logger->error('er');
        $logger->warning('w');
        $logger->notice('n');
        $logger->info('i');
        $logger->debug('d');
        $logger->log('info', 'l', ['k' => 'v']);

        $this->assertCount(9, $sink->calls);
        $this->assertSame('emergency', $sink->calls[0][0]);
        $this->assertSame('log', $sink->calls[8][0]);
        $this->assertSame('info', $sink->calls[8][1]);
    }
}

class SpyPsrLogger implements LoggerInterface
{
    public array $calls = [];

    public function emergency($message, array $context = []): void { $this->calls[] = ['level' => 'emergency', 'message' => $message, 'context' => $context]; }
    public function alert($message, array $context = []): void     { $this->calls[] = ['level' => 'alert',     'message' => $message, 'context' => $context]; }
    public function critical($message, array $context = []): void  { $this->calls[] = ['level' => 'critical',  'message' => $message, 'context' => $context]; }
    public function error($message, array $context = []): void     { $this->calls[] = ['level' => 'error',     'message' => $message, 'context' => $context]; }
    public function warning($message, array $context = []): void   { $this->calls[] = ['level' => 'warning',   'message' => $message, 'context' => $context]; }
    public function notice($message, array $context = []): void    { $this->calls[] = ['level' => 'notice',    'message' => $message, 'context' => $context]; }
    public function info($message, array $context = []): void      { $this->calls[] = ['level' => 'info',      'message' => $message, 'context' => $context]; }
    public function debug($message, array $context = []): void     { $this->calls[] = ['level' => 'debug',     'message' => $message, 'context' => $context]; }
    public function log($level, $message, array $context = []): void { $this->calls[] = ['level' => 'log',     'message' => $message, 'context' => $context, 'actual_level' => $level]; }
}
