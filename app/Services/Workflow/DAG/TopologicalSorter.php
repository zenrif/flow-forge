<?php

namespace App\Services\Workflow\DAG;

/**
 * TopologicalSorter — mengambil DagNode[] (key-indexed) dan
 * menghasilkan level-by-level execution plan.
 *
 * Output adalah array of arrays (execution waves):
 * [
 *   wave 0 → ['fetch_data', 'load_config'],   // bisa dijalankan paralel
 *   wave 1 → ['transform_data'],               // menunggu wave 0 selesai
 *   wave 2 → ['send_email', 'write_db'],       // menunggu wave 1 selesai
 * ]
 *
 * Algoritma: Kahn's algorithm (BFS-based)
 * — menghitung in-degree tiap node
 * — node dengan in-degree 0 masuk antrian wave berikutnya
 * — setelah diproses, kurangi in-degree tetangga
 */
class TopologicalSorter
{
    /**
     * @param  DagNode[] $nodes  key-indexed array dari DagParser
     * @return string[][]        level-by-level execution waves
     */
    public function sort(array $nodes): array
    {
        // Hitung in-degree: berapa dependency yang dimiliki tiap node
        $inDegree = [];
        foreach ($nodes as $key => $node) {
            $inDegree[$key] = count($node->depends_on);
        }

        // Bangun adjacency list terbalik:
        // "jika A selesai, siapa yang bisa di-unlock?"
        // depends_on: B depends on A  →  adjacency[A] = [B]
        $adjacency = array_fill_keys(array_keys($nodes), []);
        foreach ($nodes as $key => $node) {
            foreach ($node->depends_on as $dep) {
                $adjacency[$dep][] = $key;
            }
        }

        $waves   = [];
        $visited = 0;

        // Mulai dari semua node yang tidak punya dependency
        $currentWave = array_keys(array_filter($inDegree, fn($d) => $d === 0));

        while (!empty($currentWave)) {
            sort($currentWave); // deterministik untuk testing
            $waves[] = $currentWave;
            $visited += count($currentWave);

            $nextWave = [];
            foreach ($currentWave as $key) {
                // Kurangi in-degree semua node yang bergantung pada key ini
                foreach ($adjacency[$key] as $neighbor) {
                    $inDegree[$neighbor]--;
                    if ($inDegree[$neighbor] === 0) {
                        $nextWave[] = $neighbor;
                    }
                }
            }

            $currentWave = $nextWave;
        }

        // Sanity check — seharusnya tidak terjadi karena DagParser sudah
        // mendeteksi cycle, tapi defense-in-depth tetap penting
        if ($visited !== count($nodes)) {
            $unvisited = array_diff(array_keys($nodes), array_merge(...$waves));
            throw new \LogicException(
                'TopologicalSorter: tidak semua node dapat diurutkan. '
                    . 'Node yang tersisa: ' . implode(', ', $unvisited)
            );
        }

        return $waves;
    }

    /**
     * Flatten waves menjadi urutan eksekusi satu dimensi.
     * Berguna untuk sequential-only execution atau debugging.
     *
     * @return string[]
     */
    public function sortFlat(array $nodes): array
    {
        return array_merge(...$this->sort($nodes));
    }
}
