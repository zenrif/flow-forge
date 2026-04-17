<?php
// config/workflow.php
// Letakkan file ini di: config/workflow.php

return [
    /*
     |--------------------------------------------------------------------------
     | Global Workflow Timeout
     |--------------------------------------------------------------------------
     | Maksimum durasi satu workflow run dalam menit.
     | Jika melebihi ini, run akan otomatis di-timeout.
     */
    'global_timeout_minutes' => env('WORKFLOW_TIMEOUT_MINUTES', 60),

    /*
     |--------------------------------------------------------------------------
     | Queue Names
     |--------------------------------------------------------------------------
     | Pisahkan orchestration dan step execution ke queue berbeda
     | agar orchestrator tidak diblokir oleh step yang berjalan lama.
     */
    'queues' => [
        'orchestration' => 'workflow-orchestration',
        'steps'         => 'workflow-steps',
    ],

    /*
     |--------------------------------------------------------------------------
     | Script Step Whitelist
     |--------------------------------------------------------------------------
     | Hanya command dengan prefix ini yang boleh dijalankan oleh ScriptStepExecutor.
     | Tambahkan prefix sesuai kebutuhan project kamu.
     */
    'allowed_script_prefixes' => [
        'php artisan',
        'node ',
        'python ',
        'python3 ',
    ],

    /*
     |--------------------------------------------------------------------------
     | Retry Defaults
     |--------------------------------------------------------------------------
     | Default jika step tidak mendefinisikan sendiri.
     */
    'default_max_retries'     => env('WORKFLOW_DEFAULT_RETRIES', 3),
    'default_timeout_seconds' => env('WORKFLOW_DEFAULT_TIMEOUT', 30),
];
