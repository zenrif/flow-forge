<?php

namespace App\Services\Workflow\DAG;

/**
 * Merepresentasikan satu node/step di dalam DAG.
 * Immutable value object — dibuat sekali, tidak diubah.
 */
final class DagNode
{
    public function __construct(
        public readonly string $key,        // identifier unik, e.g. "send_email"
        public readonly string $type,       // http | script | delay | condition
        public readonly array  $config,     // konfigurasi spesifik per tipe step
        public readonly array  $depends_on, // key dari step yang harus selesai dulu
        public readonly int    $max_retries = 3,
        public readonly int    $timeout_seconds = 30,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            type: $data['type'],
            config: $data['config'] ?? [],
            depends_on: $data['depends_on'] ?? [],
            max_retries: $data['max_retries'] ?? 3,
            timeout_seconds: $data['timeout_seconds'] ?? 30,
        );
    }

    public function toArray(): array
    {
        return [
            'key'             => $this->key,
            'type'            => $this->type,
            'config'          => $this->config,
            'depends_on'      => $this->depends_on,
            'max_retries'     => $this->max_retries,
            'timeout_seconds' => $this->timeout_seconds,
        ];
    }
}
