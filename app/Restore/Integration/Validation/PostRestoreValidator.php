<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Validation;

use BackupApp\Restore\Integration\Database\RestoreConnection;
use BackupApp\Restore\Integration\Sql\RestorePlan;
use BackupApp\Restore\Integration\Ownership\OwnershipMarker;

final class PostRestoreValidator
{
    public function __construct(
        private readonly RowCanonicalizer $rows = new RowCanonicalizer(),
        private readonly int $batchSize = 500,
        private readonly int $evidenceChunkHashes = 2048,
        private readonly ?string $temporaryDirectory = null
    ) {}

    public function validate(RestorePlan $expected, RestoreConnection $target, ?OwnershipMarker $ownershipMarker = null): ValidationReport
    {
        $actual = $target->schemaSnapshot();
        $inventory = $expected->objectInventory();
        $expectedSchema = $expected->schemaEvidence();
        $actualObjects = is_array($actual['objects'] ?? null) ? $actual['objects'] : ['tables' => [], 'views' => []];
        if ($ownershipMarker !== null && is_array($actualObjects['tables'] ?? null)) {
            if (array_is_list($actualObjects['tables'])) {
                $actualObjects['tables'] = array_values(array_filter(
                    $actualObjects['tables'],
                    static fn (mixed $name): bool => $name !== $ownershipMarker->table()
                ));
            } else { unset($actualObjects['tables'][$ownershipMarker->table()]); }
        }
        if ($expectedSchema === [] && array_is_list($inventory['tables'] ?? [])) {
            $actualObjects = [
                'tables' => $this->objectNames($actualObjects['tables'] ?? []),
                'views' => $this->objectNames($actualObjects['views'] ?? []),
            ];
            sort($inventory['tables'], SORT_STRING);
            sort($inventory['views'], SORT_STRING);
        }
        $schema = (new SchemaComparator())->compare($expectedSchema !== [] ? $expectedSchema : $inventory, $actualObjects);
        $data = $this->validateData($expected, $target, $actual);
        return new ValidationReport([
            'schema' => ['status' => $schema['equal'] ? 'pass' : 'fail', 'mandatory' => true, 'findings' => $schema['findings'], 'expected_digest' => $schema['expected_digest'], 'actual_digest' => $schema['actual_digest']],
            'data' => $data,
            'stored_objects' => ['status' => $inventory['views'] === [] ? 'pass' : 'not_verified', 'mandatory' => true, 'findings' => $inventory['views'] === [] ? [] : ['stored_object_normalization_unsupported']],
            'source_to_artifact_snapshot_consistency' => ['status' => 'not_verified', 'mandatory' => false, 'findings' => ['consistent_snapshot_not_proven']],
        ]);
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed> */
    private function validateData(RestorePlan $plan, RestoreConnection $target, array $snapshot): array
    {
        $evidence = $plan->dataEvidence();
        $allTables = $plan->objectInventory()['tables'] ?? [];
        foreach ($allTables as $table) {
            if (isset($evidence[$table])) { continue; }
            $columns = array_values(array_filter(array_map(
                static fn (mixed $column): mixed => is_array($column) ? ($column['name'] ?? null) : null,
                (array) ($plan->schemaEvidence()['tables'][$table]['columns'] ?? [])
            ), 'is_string'));
            if ($columns === []) { $columns = array_keys($this->columnTypes($snapshot, $table)); }
            if ($columns === []) {
                return ['status' => 'not_verified', 'mandatory' => true, 'findings' => ['empty_table_columns_unproven']];
            }
            $empty = new RowEvidenceAccumulator($table, $columns, $this->evidenceChunkHashes, $this->temporaryDirectory);
            $evidence[$table] = $empty->finish();
        }
        if ($evidence === []) {
            return ['status' => 'pass', 'mandatory' => true, 'findings' => [], 'tables' => [], 'algorithm' => 'sha256-sorted-row-sha256-v1'];
        }
        $summaries = [];
        try {
            foreach ($evidence as $table => $expected) {
                $types = $this->columnTypes($snapshot, $table);
                $accumulator = new RowEvidenceAccumulator($table, $expected->columns(), $this->evidenceChunkHashes, $this->temporaryDirectory);
                foreach ($target->rowBatches($table, $expected->columns(), $this->batchSize) as $batch) {
                    if (!is_array($batch) || count($batch) > $this->batchSize) { throw new \DomainException('invalid_target_row_batch'); }
                    foreach ($batch as $row) {
                        if (!is_array($row)) { throw new \DomainException('invalid_target_row'); }
                        $accumulator->addCanonicalRow($this->rows->canonicalizeColumns($row, $expected->columns(), $types));
                    }
                }
                $actual = $accumulator->finish();
                $equal = $actual->rowCount() === $expected->rowCount()
                    && hash_equals($expected->multisetSha256(), $actual->multisetSha256());
                $summaries[$table] = [
                    'equal' => $equal,
                    'expected_count' => $expected->rowCount(),
                    'actual_count' => $actual->rowCount(),
                    'expected_digest' => $expected->multisetSha256(),
                    'actual_digest' => $actual->multisetSha256(),
                ];
            }
        } catch (\Throwable) {
            return ['status' => 'not_verified', 'mandatory' => true, 'findings' => ['target_row_evidence_unavailable']];
        }
        $allEqual = !in_array(false, array_column($summaries, 'equal'), true);
        return [
            'status' => $allEqual ? 'pass' : 'fail',
            'mandatory' => true,
            'findings' => $allEqual ? [] : ['row_multiset_mismatch'],
            'tables' => $summaries,
            'algorithm' => 'sha256-sorted-row-sha256-v1',
        ];
    }

    /** @param array<string, mixed> $snapshot @return array<string, string> */
    private function columnTypes(array $snapshot, string $table): array
    {
        $direct = $snapshot['types'][$table] ?? null;
        if (is_array($direct)) { return array_filter($direct, 'is_string'); }
        $columns = $snapshot['objects']['tables'][$table]['columns'] ?? null;
        if (!is_array($columns)) { return []; }
        $types = [];
        foreach ($columns as $column) {
            if (is_array($column) && is_string($column['name'] ?? null) && is_string($column['type'] ?? null)) {
                $types[$column['name']] = $column['type'];
            }
        }
        return $types;
    }

    /** @param mixed $objects @return list<string> */
    private function objectNames(mixed $objects): array
    {
        if (!is_array($objects)) { return []; }
        $names = array_is_list($objects) ? array_values(array_filter($objects, 'is_string')) : array_keys($objects);
        sort($names, SORT_STRING);
        return $names;
    }
}
