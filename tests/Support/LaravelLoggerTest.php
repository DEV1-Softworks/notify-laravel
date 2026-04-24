<?php

namespace Dev1\NotifyLaravel\Tests\Support;

use Dev1\NotifyLaravel\Support\LaravelLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class LaravelLoggerTest extends TestCase
{
    public function test_routes_every_psr_level_through_the_wrapped_logger()
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
            ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'info'],
            array_column($spy->calls, 'level')
        );
        $this->assertSame(['x' => 1], $spy->calls[0]['context']);
        $this->assertSame('l', $spy->calls[8]['message']);
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
        $this->assertSame('via-getLogger', $inner->calls[0]['message']);
    }

    public function test_wraps_non_psr_logger_and_dispatches_to_named_methods()
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
        $logger->info('i', ['k' => 'v']);
        $logger->log('custom-level', 'x');

        $this->assertSame(['emergency', 'e', []],          $sink->calls[0]);
        $this->assertSame(['info', 'i', ['k' => 'v']],     $sink->calls[1]);
        $this->assertSame(['log', 'custom-level', 'x', []], $sink->calls[2]);
    }
}

class SpyPsrLogger extends AbstractLogger
{
    public array $calls = [];

    public function log($level, $message, array $context = []): void
    {
        $this->calls[] = [
            'level'   => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
