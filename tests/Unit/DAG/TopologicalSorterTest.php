<?php

namespace Tests\Unit\DAG;

use App\Services\Workflow\DAG\DagParser;
use App\Services\Workflow\DAG\TopologicalSorter;
use PHPUnit\Framework\TestCase;

class TopologicalSorterTest extends TestCase
{
    private DagParser $parser;
    private TopologicalSorter $sorter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DagParser();
        $this->sorter = new TopologicalSorter();
    }

    public function test_linear_chain_sorts_correctly(): void
    {
        $nodes = $this->parser->parse([
            'steps' => [
                ['key' => 'a', 'type' => 'http', 'config' => ['url' => 'https://a.com', 'method' => 'GET'], 'depends_on' => []],
                ['key' => 'b', 'type' => 'script', 'config' => ['command' => 'php artisan b'], 'depends_on' => ['a']],
                ['key' => 'c', 'type' => 'delay',  'config' => ['seconds' => 1], 'depends_on' => ['b']],
            ],
        ]);

        $waves = $this->sorter->sort($nodes);

        $this->assertCount(3, $waves);
        $this->assertEquals(['a'], $waves[0]);
        $this->assertEquals(['b'], $waves[1]);
        $this->assertEquals(['c'], $waves[2]);
    }

    public function test_parallel_steps_in_same_wave(): void
    {
        $nodes = $this->parser->parse([
            'steps' => [
                ['key' => 'fetch',     'type' => 'http',   'config' => ['url' => 'https://a.com', 'method' => 'GET'], 'depends_on' => []],
                ['key' => 'process_a', 'type' => 'script', 'config' => ['command' => 'php artisan a'], 'depends_on' => ['fetch']],
                ['key' => 'process_b', 'type' => 'script', 'config' => ['command' => 'php artisan b'], 'depends_on' => ['fetch']],
                ['key' => 'notify',    'type' => 'http',   'config' => ['url' => 'https://b.com', 'method' => 'POST'], 'depends_on' => ['process_a', 'process_b']],
            ],
        ]);

        $waves = $this->sorter->sort($nodes);

        $this->assertCount(3, $waves);
        $this->assertEquals(['fetch'], $waves[0]);
        $this->assertEqualsCanonicalizing(['process_a', 'process_b'], $waves[1]); // urutan boleh beda
        $this->assertEquals(['notify'], $waves[2]);
    }

    public function test_no_dependency_steps_all_in_first_wave(): void
    {
        $nodes = $this->parser->parse([
            'steps' => [
                ['key' => 'a', 'type' => 'http',   'config' => ['url' => 'https://a.com', 'method' => 'GET'], 'depends_on' => []],
                ['key' => 'b', 'type' => 'script', 'config' => ['command' => 'php artisan b'], 'depends_on' => []],
                ['key' => 'c', 'type' => 'delay',  'config' => ['seconds' => 1], 'depends_on' => []],
            ],
        ]);

        $waves = $this->sorter->sort($nodes);

        $this->assertCount(1, $waves);
        $this->assertEqualsCanonicalizing(['a', 'b', 'c'], $waves[0]);
    }

    public function test_sort_flat_returns_valid_order(): void
    {
        $nodes = $this->parser->parse([
            'steps' => [
                ['key' => 'a', 'type' => 'http', 'config' => ['url' => 'https://a.com', 'method' => 'GET'], 'depends_on' => []],
                ['key' => 'b', 'type' => 'script', 'config' => ['command' => 'php artisan b'], 'depends_on' => ['a']],
                ['key' => 'c', 'type' => 'delay',  'config' => ['seconds' => 1], 'depends_on' => ['b']],
            ],
        ]);

        $flat = $this->sorter->sortFlat($nodes);

        $this->assertEquals(['a', 'b', 'c'], $flat);
        // Pastikan a sebelum b, b sebelum c
        $this->assertLessThan(array_search('b', $flat), array_search('a', $flat));
        $this->assertLessThan(array_search('c', $flat), array_search('b', $flat));
    }
}
