<?php

declare(strict_types=1);

namespace Baraja\Lock\Transaction;


interface TransactionProvider
{
	public function startTransaction(?string $transactionName = null, int $maxExecutionTimeMs = 3000): void;

	public function stopTransaction(?string $transactionName = null): void;

	public function isTransactionRunning(?string $transactionName = null): bool;
}
