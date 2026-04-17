<?php

namespace App\Services\Workflow\DAG;

use App\Services\Workflow\DAG\Exceptions\DagValidationException;

/**
 * DagParser — membaca raw JSON/array definition, memvalidasi struktur,
 * memastikan tidak ada circular dependency, dan menghasilkan list DagNode.
 *
 * Format input yang diharapkan:
 * {
 *   "steps": [
 *     {
 *       "key": "fetch_data",
 *       "type": "http",
 *       "config": { "url": "https://api.example.com/data", "method": "GET" },
 *       "depends_on": [],
 *       "max_retries": 3,
 *       "timeout_seconds": 30
 *     },
 *     {
 *       "key": "process_data",
 *       "type": "script",
 *       "config": { "command": "php artisan process:data" },
 *       "depends_on": ["fetch_data"]
 *     }
 *   ]
 * }
 */
class DagParser
{
    private const VALID_STEP_TYPES = ['http', 'script', 'delay', 'condition'];

    /** @return DagNode[] */
    public function parse(array $definition): array
    {
        $this->validateStructure($definition);

        $nodes = $this->buildNodes($definition['steps']);

        $this->validateDependencies($nodes);
        $this->detectCycles($nodes);

        return $nodes;
    }

    // ─────────────────────────────────────────────────────────────
    // Validasi struktur JSON (field wajib, tipe, dll)
    // ─────────────────────────────────────────────────────────────

    private function validateStructure(array $definition): void
    {
        if (empty($definition['steps']) || !is_array($definition['steps'])) {
            throw new DagValidationException('DAG harus memiliki minimal 1 step.');
        }

        $keys = [];
        foreach ($definition['steps'] as $index => $step) {
            $position = "step[{$index}]";

            if (empty($step['key']) || !is_string($step['key'])) {
                throw new DagValidationException("{$position}: field 'key' wajib diisi dan harus string.");
            }

            // key harus unik
            if (in_array($step['key'], $keys, true)) {
                throw new DagValidationException("Duplicate step key: '{$step['key']}'.");
            }
            $keys[] = $step['key'];

            if (empty($step['type']) || !in_array($step['type'], self::VALID_STEP_TYPES, true)) {
                $valid = implode(', ', self::VALID_STEP_TYPES);
                throw new DagValidationException(
                    "{$position} (key: '{$step['key']}'): 'type' harus salah satu dari [{$valid}]."
                );
            }

            // depends_on harus array jika ada
            if (isset($step['depends_on']) && !is_array($step['depends_on'])) {
                throw new DagValidationException(
                    "{$position} (key: '{$step['key']}'): 'depends_on' harus berupa array."
                );
            }

            // validasi config wajib per tipe step
            $this->validateStepConfig($step);
        }
    }

    private function validateStepConfig(array $step): void
    {
        $key    = $step['key'];
        $config = $step['config'] ?? [];

        match ($step['type']) {
            'http' => $this->require($config, ['url', 'method'], $key),
            'script' => $this->require($config, ['command'], $key),
            'delay' => $this->require($config, ['seconds'], $key),
            'condition' => $this->require($config, ['expression', 'on_true', 'on_false'], $key),
            default => null,
        };
    }

    private function require(array $config, array $fields, string $stepKey): void
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $config)) {
                throw new DagValidationException(
                    "Step '{$stepKey}': field config '{$field}' wajib diisi."
                );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Build DagNode objects
    // ─────────────────────────────────────────────────────────────

    /** @return DagNode[] key-indexed */
    private function buildNodes(array $steps): array
    {
        $nodes = [];
        foreach ($steps as $step) {
            $node          = DagNode::fromArray($step);
            $nodes[$node->key] = $node;
        }
        return $nodes;
    }

    // ─────────────────────────────────────────────────────────────
    // Pastikan semua depends_on merujuk key yang ada
    // ─────────────────────────────────────────────────────────────

    private function validateDependencies(array $nodes): void
    {
        foreach ($nodes as $node) {
            foreach ($node->depends_on as $dep) {
                if (!array_key_exists($dep, $nodes)) {
                    throw new DagValidationException(
                        "Step '{$node->key}' bergantung pada '{$dep}' yang tidak ditemukan."
                    );
                }
                if ($dep === $node->key) {
                    throw new DagValidationException(
                        "Step '{$node->key}' tidak boleh bergantung pada dirinya sendiri."
                    );
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Cycle detection — DFS dengan tiga warna state
    //   WHITE (0) = belum dikunjungi
    //   GRAY  (1) = sedang di stack (in progress)
    //   BLACK (2) = selesai
    // Jika kita menemukan edge ke node GRAY → ada siklus.
    // ─────────────────────────────────────────────────────────────

    private function detectCycles(array $nodes): void
    {
        $color = array_fill_keys(array_keys($nodes), 0); // semua WHITE
        $path  = [];

        foreach (array_keys($nodes) as $key) {
            if ($color[$key] === 0) {
                $this->dfs($key, $nodes, $color, $path);
            }
        }
    }

    private function dfs(string $key, array $nodes, array &$color, array &$path): void
    {
        $color[$key] = 1; // GRAY — sedang diproses
        $path[]      = $key;

        foreach ($nodes[$key]->depends_on as $dep) {
            if ($color[$dep] === 1) {
                // Ditemukan edge ke node yang sedang di-stack → siklus!
                $cycleStart = array_search($dep, $path, true);
                $cycle      = array_slice($path, $cycleStart);
                $cycle[]    = $dep;
                throw new DagValidationException(
                    'Circular dependency terdeteksi: ' . implode(' → ', $cycle)
                );
            }

            if ($color[$dep] === 0) {
                $this->dfs($dep, $nodes, $color, $path);
            }
        }

        array_pop($path);
        $color[$key] = 2; // BLACK — selesai
    }
}
