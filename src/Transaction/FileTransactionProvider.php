<?php

declare(strict_types=1);

namespace Baraja\Lock\Transaction;


use Tracy\Debugger;
use Tracy\ILogger;

final class FileTransactionProvider implements TransactionProvider
{
	private string $baseDir;

	private int $mode;

	/** Probability that the clean() routine is started */
	private float $gcProbability = 0.001;


	public function __construct(?string $baseDir = null, int $mode = 0777)
	{
		$this->mode = $mode;
		if ($baseDir === null) {
			$baseDir = sys_get_temp_dir() . '/lock/' . md5(__FILE__);
		}
		if (!is_dir($baseDir)) {
			$this->createDir($baseDir);
		}
		$this->baseDir = (string) $baseDir;
		if (mt_rand() / mt_getrandmax() < $this->gcProbability) {
			try {
				$this->gc();
			} catch (\Throwable $e) {
				if (class_exists(Debugger::class)) {
					Debugger::log($e, ILogger::EXCEPTION);
				}
			}
		}
	}


	public function startTransaction(?string $transactionName = null, int $maxExecutionTimeMs = 3000): void
	{
		$file = $this->getFilePath($transactionName);
		$time = (string) (microtime(true) + ($maxExecutionTimeMs / 1000));
		$content = $time . '|' . ($transactionName ?? 'common');
		if (@file_put_contents($file, $content) === false) { // @ is escalated to exception
			throw new \RuntimeException('Unable to write file "' . $file . '". ' . $this->getLastError());
		}
		if (!@chmod($file, $this->mode)) { // @ is escalated to exception
			throw new \RuntimeException(
				'Unable to chmod file "' . $file . '" to mode ' . decoct($this->mode)
				. '. ' . $this->getLastError(),
			);
		}
	}


	public function stopTransaction(?string $transactionName = null): void
	{
		$file = $this->getFilePath($transactionName);
		if (!is_file($file)) {
			return;
		}
		if (!@unlink($file)) { // @ is escalated to exception
			throw new \RuntimeException('Unable to delete "' . $file . '". ' . $this->getLastError());
		}
	}


	public function isTransactionRunning(?string $transactionName = null): bool
	{
		$file = $this->getFilePath($transactionName);
		if (!is_file($file)) {
			return false;
		}

		$content = @file_get_contents($file); // @ is escalated to exception
		if ($content === false) {
			throw new \RuntimeException('Unable to read file "' . $file . '". ' . $this->getLastError());
		}
		if (((float) (explode('|', $content)[0] ?? '0')) - microtime(true) > 0) {
			return true;
		}
		$this->stopTransaction($transactionName);

		return false;
	}


	public function setGcProbability(float $gcProbability): void
	{
		if ($gcProbability < 0) {
			$gcProbability = 0.0;
		}
		$this->gcProbability = $gcProbability;
	}


	private function getFilePath(?string $transactionName = null): string
	{
		if ($transactionName === null) {
			$transactionName = 'common';
		} elseif (!preg_match('/^[a-zA-Z0-9]+/', $transactionName)) {
			$transactionName = md5($transactionName);
		}

		return $this->baseDir . '/' . $transactionName . '.tmp';
	}


	/**
	 * Creates a directory if it doesn't exist.
	 */
	private function createDir(string $dir): void
	{
		if (!is_dir($dir) && !@mkdir($dir, $this->mode, true) && !is_dir($dir)) { // @ - dir may already exist
			throw new \RuntimeException(
				'Unable to create directory "' . $dir . '" with mode ' . decoct($this->mode)
				. '. ' . $this->getLastError(),
			);
		}
	}


	/**
	 * Returns the last occurred PHP error or an empty string if no error occurred. Unlike error_get_last(),
	 * it is nit affected by the PHP directive html_errors and always returns text, not HTML.
	 */
	private function getLastError(): string
	{
		$message = error_get_last()['message'] ?? '';
		$message = ini_get('html_errors')
			? html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8')
			: $message;
		$message = preg_replace('#^\w+\(.*?\): #', '', $message);

		return $message;
	}


	private function gc(): void
	{
		foreach (new \FilesystemIterator($this->baseDir) as $path) {
			$file = (string) $path;
			$content = @file_get_contents($file); // @ is escalated to exception
			if ($content === false) {
				continue;
			}
			if (((float) (explode('|', $content)[0] ?? '0')) - microtime(true) > 0) {
				continue;
			}
			if (!@unlink($file)) { // @ is escalated to exception
				throw new \RuntimeException('Unable to delete "' . $file . '". ' . $this->getLastError());
			}
		}
	}
}
