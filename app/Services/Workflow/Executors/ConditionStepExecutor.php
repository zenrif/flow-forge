<?php

namespace App\Services\Workflow\Executors;

use App\Services\Workflow\DAG\DagNode;
use App\Services\Workflow\DAG\Exceptions\StepExecutionException;

/**
 * Menjalankan step bertipe 'condition'.
 * Mengevaluasi ekspresi dan mengembalikan branch mana yang harus dijalankan.
 *
 * Config yang dibutuhkan:
 * {
 *   "expression":  "{{fetch_data.body.status}} == 'active'",
 *   "on_true":     "send_welcome_email",   // step key yang dijalankan jika benar
 *   "on_false":    "send_rejection_email"  // step key yang dijalankan jika salah
 * }
 *
 * Operator yang didukung: ==, !=, >, <, >=, <=, contains, not_contains
 */
class ConditionStepExecutor implements StepExecutorInterface
{
    public function execute(DagNode $node, array $context): array
    {
        $raw        = $node->config['expression'];
        $expression = $this->interpolate($raw, $context);
        $result     = $this->evaluate($expression, $node->key);

        return [
            'expression'    => $expression,
            'result'        => $result,
            'branch'        => $result ? $node->config['on_true'] : $node->config['on_false'],
            // WorkflowOrchestrator akan membaca 'skip_steps' ini
            // untuk melewati branch yang tidak dipilih
            'skip_steps'    => [$result ? $node->config['on_false'] : $node->config['on_true']],
        ];
    }

    private function evaluate(string $expression, string $stepKey): bool
    {
        // Format: "<kiri> <operator> <kanan>"
        $operators = ['!=', '==', '>=', '<=', '>', '<', 'contains', 'not_contains'];

        foreach ($operators as $op) {
            if (!str_contains($expression, " {$op} ")) {
                continue;
            }

            [$left, $right] = array_map('trim', explode(" {$op} ", $expression, 2));
            // Hapus tanda kutip jika ada
            $left  = trim($left, "'\"");
            $right = trim($right, "'\"");

            return match ($op) {
                '=='           => $left == $right,
                '!='           => $left != $right,
                '>'            => (float) $left > (float) $right,
                '<'            => (float) $left < (float) $right,
                '>='           => (float) $left >= (float) $right,
                '<='           => (float) $left <= (float) $right,
                'contains'     => str_contains($left, $right),
                'not_contains' => !str_contains($left, $right),
            };
        }

        throw new StepExecutionException(
            "Ekspresi kondisi tidak valid: '{$expression}'.",
            stepKey: $stepKey,
            retryable: false,
        );
    }

    private function interpolate(string $template, array $context): string
    {
        return preg_replace_callback(
            '/\{\{([^}]+)\}\}/',
            function (array $matches) use ($context) {
                $parts = explode('.', trim($matches[1]));
                $value = $context;
                foreach ($parts as $part) {
                    $value = $value[$part] ?? null;
                }
                return (string) ($value ?? '');
            },
            $template
        );
    }
}
