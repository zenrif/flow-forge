<?php

namespace App\Services\Workflow\Executors;

use App\Services\Workflow\DAG\DagNode;
use App\Services\Workflow\DAG\Exceptions\StepExecutionException;
use Illuminate\Support\Facades\Http;

/**
 * Menjalankan step bertipe 'http'.
 *
 * Config yang dibutuhkan:
 * {
 *   "url":     "https://api.example.com/endpoint",
 *   "method":  "POST",                      // GET | POST | PUT | PATCH | DELETE
 *   "headers": { "Authorization": "..." },  // opsional
 *   "body":    { "key": "value" },          // opsional, untuk POST/PUT
 *   "timeout": 30                           // opsional, default dari node
 * }
 */
class HttpStepExecutor implements StepExecutorInterface
{
    public function execute(DagNode $node, array $context): array
    {
        $config  = $node->config;
        $url     = $this->interpolate($config['url'], $context);
        $method  = strtolower($config['method']);
        $headers = $config['headers'] ?? [];
        $body    = $config['body'] ?? [];
        $timeout = $config['timeout'] ?? $node->timeout_seconds;

        try {
            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->$method($url, $body);

            if ($response->failed()) {
                throw new StepExecutionException(
                    "HTTP {$method} ke {$url} gagal dengan status {$response->status()}.",
                    stepKey: $node->key,
                    retryable: $response->serverError(), // 5xx = retryable, 4xx = tidak
                );
            }

            return [
                'status_code' => $response->status(),
                'body'        => $response->json() ?? $response->body(),
                'headers'     => $response->headers(),
            ];
        } catch (StepExecutionException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new StepExecutionException(
                "HTTP request gagal: {$e->getMessage()}",
                stepKey: $node->key,
                retryable: true,
                previous: $e,
            );
        }
    }

    /**
     * Interpolasi template string dengan context dari step sebelumnya.
     * Contoh: "https://api.com/{{fetch_user.body.user_id}}"
     */
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
                return $value ?? $matches[0];
            },
            $template
        );
    }
}
