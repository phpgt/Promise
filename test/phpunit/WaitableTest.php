<?php
namespace Gt\Promise\Test;

use Gt\Promise\Promise;
use Gt\Promise\PromiseWaitTaskNotSetException;
use PHPUnit\Framework\TestCase;

class WaitableTest extends TestCase {
	public function testWait() {
		$callCount = 0;
		$resolveCallback = null;
		$executor = function(callable $resolve, callable $reject) use(&$resolveCallback):void  {
			$resolveCallback = $resolve;
		};
		$resolvedValue = "Done!";
		$sut = new Promise($executor);

		$waitTask = function() use(&$callCount, $resolveCallback, $resolvedValue) {
			if($callCount >= 10) {
				call_user_func($resolveCallback, $resolvedValue);
			}
			else {
				$callCount++;
			}
		};

		$sut->setWaitTask($waitTask);
		self::assertEquals($resolvedValue, $sut->wait());
		self::assertEquals(10, $callCount);
	}

	public function testWaitNotUnwrapped() {
		$callCount = 0;
		$resolveCallback = null;
		$executor = function(callable $resolve, callable $reject) use(&$resolveCallback):void  {
			$resolveCallback = $resolve;
		};
		$resolvedValue = "Done!";
		$sut = new Promise($executor);

		$waitTask = function() use(&$callCount, $resolveCallback, $resolvedValue) {
			if($callCount >= 10) {
				call_user_func($resolveCallback, $resolvedValue);
			}
			else {
				$callCount++;
			}
		};

		$sut->setWaitTask($waitTask);
		self::assertNull($sut->wait(false));
		self::assertEquals(10, $callCount);
	}

	public function testWaitWithNoWaitTask() {
		$executor = function(callable $resolve, callable $reject) use(&$resolveCallback):void  {
			$resolveCallback = $resolve;
		};
		$sut = new Promise($executor);;
		self::expectException(PromiseWaitTaskNotSetException::class);
		$sut->wait();
	}
}