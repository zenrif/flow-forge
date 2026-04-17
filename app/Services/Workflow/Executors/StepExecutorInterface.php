<?php

namespace App\Services\Workflow\Executors;

use App\Services\Workflow\DAG\DagNode;

interface StepExecutorInterface
{
    /**
     * Jalankan step dan kembalikan output-nya.
     * Throw StepExecutionException jika gagal.
     *
     * @param  DagNode $node         Definisi step yang akan dieksekusi
     * @param  array   $context      Output dari step-step sebelumnya, key = step key
     * @return array                 Output step ini (akan disimpan ke step_runs.output)
     */
    public function execute(DagNode $node, array $context): array;
}
