<?php

declare(strict_types=1);

namespace Baraja\Lock;


use Baraja\Lock\Transaction\FileTransactionProvider;
use Baraja\Lock\Transaction\TransactionProvider;

/**
 * Simple and efficient lock management in your PHP application.
 * To use it, just start a lock (you can use many different locks at the same time),
 * wait for it to unlock, process any competing tasks, and then let the lock expire or unlock manually.
 * If you let the lock expire naturally, it will be automatically cleared via the Garbage collector component.
 */
final class Lock
{
	private static ?TransactionProvider $transactionProvider = null;


	public static function wait(?string $transactionName = null, int $maxExecutionTimeMs = 30000, int $ttl = 500): void
	{
		$startTime = microtime(true);
		while (true) {
			if (self::isTransactionRunning($transactionName) === false) {
				break;
			}
			usleep(10_000);
			$ttl--;
			if ($ttl <= 0 && microtime(true) - $startTime >= ($maxExecutionTimeMs / 1000)) {
				break;
			}
		}
	}


	public static function startTransaction(?string $transactionName = null, int $maxExecutionTimeMs = 3000): void
	{
		self::getTransactionProvider()
			->startTransaction($transactionName, $maxExecutionTimeMs);
	}


	public static function stopTransaction(?string $transactionName = null): void
	{
		if (self::isTransactionRunning($transactionName) === false) {
			return;
		}
		self::getTransactionProvider()
			->stopTransaction($transactionName);
	}


	public static function isTransactionRunning(?string $transactionName = null): bool
	{
		return self::getTransactionProvider()
			->isTransactionRunning($transactionName);
	}


	public static function getTransactionProvider(): TransactionProvider
	{
		if (self::$transactionProvider === null) {
			self::$transactionProvider = new FileTransactionProvider;
		}

		return self::$transactionProvider;
	}


	public static function setTransactionProvider(TransactionProvider $transactionProvider): void
	{
		self::$transactionProvider = $transactionProvider;
	}
}
