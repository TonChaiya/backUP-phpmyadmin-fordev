<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Execution;

use BackupApp\Restore\Integration\Database\RestoreConnection;
use BackupApp\Restore\Integration\Policy\TrustedTargetName;
use BackupApp\Restore\Integration\Sql\ExecutableRestoreStatement;
use BackupApp\Restore\Integration\Sql\ValidatedExecutableStatement;
use BackupApp\Restore\Integration\Sql\RestorePlan;
use DomainException;

final class TypedRestoreExecutor
{
    public function execute(RestorePlan $plan, TrustedTargetName $target, RestoreConnection $connection): ExecutionResult
    {
        $executed = 0;
        foreach ($plan->items() as $item) {
            if (!$item instanceof ExecutableRestoreStatement) { continue; }
            $validated = ValidatedExecutableStatement::freeze($item);
            if ($connection->currentDatabase() !== $target->value()) { throw new DomainException('restore_target_context_mismatch'); }
            $connection->execute($validated);
            $executed++;
        }
        return new ExecutionResult(true, $executed, 'typed_execution_completed');
    }
}
