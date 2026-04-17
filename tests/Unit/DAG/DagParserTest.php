<?php

namespace Tests\Unit\DAG;

use App\Services\Workflow\DAG\DagParser;
use App\Services\Workflow\DAG\Exceptions\DagValidationException;
use PHPUnit\Framework\TestCase;

class DagParserTest extends TestCase
{
    private DagParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DagParser();
    }

    // ─────────────────────────────────────────────────────────────
    // Happy path
    // ─────────────────────────────────────────────────────────────

    public function test_parse_valid_linear_dag(): void
    {
        $definition = [
            'steps' => [
                ['key' => 'step_a', 'type' => 'http',   'config' => ['url' => 'https://a.com', 'method' => 'GET'], 'depends_on' => []],
                ['key' => 'step_b', 'type' => 'script', 'config' => ['command' => 'php artisan foo'], 'depends_on' => ['step_a']],
                ['key' => 'step_c', 'type' => 'delay',  'config' => ['seconds' => 5], 'depends_on' => ['step_b']],
            ],
        ];

        $nodes = $this->parser->parse($definition);

        $this->assertCount(3, $nodes);
        $this->assertArrayHasKey('step_a', $nodes);
        $this->assertArrayHasKey('step_b', $nodes);
        $this->assertArrayHasKey('step_c', $nodes);
        $this->assertEquals(['step_a'], $nodes['step_b']->depends_on);
    }

    public function test_parse_valid_parallel_dag(): void
    {
        $definition = [
            'steps' => [
                ['key' => 'fetch',     'type' => 'http',   'config' => ['url' => 'https://a.com', 'method' => 'GET'], 'depends_on' => []],
                ['key' => 'process_a', 'type' => 'script', 'config' => ['command' => 'php artisan a'], 'depends_on' => ['fetch']],
                ['key' => 'process_b', 'type' => 'script', 'config' => ['command' => 'php artisan b'], 'depends_on' => ['fetch']],
                ['key' => 'notify',    'type' => 'http',   'config' => ['url' => 'https://b.com', 'method' => 'POST'], 'depends_on' => ['process_a', 'process_b']],
            ],
        ];

        $nodes = $this->parser->parse($definition);
        $this->assertCount(4, $nodes);
    }

    public function test_parse_sets_default_retry_and_timeout(): void
    {
        $definition = [
            'steps' => [
                ['key' => 'step_a', 'type' => 'http', 'config' => ['url' => 'https://a.com', 'method' => 'GET']],
            ],
        ];

        $nodes = $this->parser->parse($definition);

        $this->assertEquals(3, $nodes['step_a']->max_retries);
        $this->assertEquals(30, $nodes['step_a']->timeout_seconds);
    }

    public function test_parse_respects_custom_retry_and_timeout(): void
    {
        $definition = [
            'steps' => [
                [
                    'key' => 'step_a',
                    'type' => 'http',
                    'config' => ['url' => 'https://a.com', 'method' => 'GET'],
                    'max_retries' => 5,
                    'timeout_seconds' => 60,
                ],
            ],
        ];

        $nodes = $this->parser->parse($definition);

        $this->assertEquals(5, $nodes['step_a']->max_retries);
        $this->assertEquals(60, $nodes['step_a']->timeout_seconds);
    }

    // ─────────────────────────────────────────────────────────────
    // Cycle detection
    // ─────────────────────────────────────────────────────────────

    public function test_throws_on_direct_cycle(): void
    {
        $this->expectException(DagValidationException::class);
        $this->expectExceptionMessageMatches('/[Cc]ircular/');

        $this->parser->parse([
            'steps' => [
                ['key' => 'a', 'type' => 'http', 'config' => ['url' => 'https://a.com', 'method' => 'GET'], 'depends_on' => ['b']],
                ['key' => 'b', 'type' => 'http', 'config' => ['url' => 'https://b.com', 'method' => 'GET'], 'depends_on' => ['a']],
            ],
        ]);
    }

    public function test_throws_on_indirect_cycle(): void
    {
        $this->expectException(DagValidationException::class);

        $this->parser->parse([
            'steps' => [
                ['key' => 'a', 'type' => 'http', 'config' => ['url' => 'https://a.com', 'method' => 'GET'], 'depends_on' => ['c']],
                ['key' => 'b', 'type' => 'http', 'config' => ['url' => 'https://b.com', 'method' => 'GET'], 'depends_on' => ['a']],
                ['key' => 'c', 'type' => 'http', 'config' => ['url' => 'https://c.com', 'method' => 'GET'], 'depends_on' => ['b']],
            ],
        ]);
    }

    public function test_throws_on_self_dependency(): void
    {
        $this->expectException(DagValidationException::class);

        $this->parser->parse([
            'steps' => [
                ['key' => 'a', 'type' => 'http', 'config' => ['url' => 'https://a.com', 'method' => 'GET'], 'depends_on' => ['a']],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Validasi struktur
    // ─────────────────────────────────────────────────────────────

    public function test_throws_on_empty_steps(): void
    {
        $this->expectException(DagValidationException::class);
        $this->parser->parse(['steps' => []]);
    }

    public function test_throws_on_duplicate_step_key(): void
    {
        $this->expectException(DagValidationException::class);
        $this->expectExceptionMessageMatches('/[Dd]uplicate/');

        $this->parser->parse([
            'steps' => [
                ['key' => 'same', 'type' => 'http', 'config' => ['url' => 'https://a.com', 'method' => 'GET']],
                ['key' => 'same', 'type' => 'script', 'config' => ['command' => 'php artisan foo']],
            ],
        ]);
    }

    public function test_throws_on_invalid_step_type(): void
    {
        $this->expectException(DagValidationException::class);

        $this->parser->parse([
            'steps' => [
                ['key' => 'step_a', 'type' => 'invalid_type', 'config' => []],
            ],
        ]);
    }

    public function test_throws_on_unknown_dependency(): void
    {
        $this->expectException(DagValidationException::class);

        $this->parser->parse([
            'steps' => [
                ['key' => 'step_a', 'type' => 'http', 'config' => ['url' => 'https://a.com', 'method' => 'GET'], 'depends_on' => ['nonexistent']],
            ],
        ]);
    }

    public function test_throws_on_missing_http_config_fields(): void
    {
        $this->expectException(DagValidationException::class);

        $this->parser->parse([
            'steps' => [
                ['key' => 'step_a', 'type' => 'http', 'config' => ['url' => 'https://a.com']], // 'method' missing
            ],
        ]);
    }
}
