<?php
namespace Gt\Promise\Test;

use Exception;
use Gt\Promise\FulfilledPromise;
use Gt\Promise\FulfilledValueNotConcreteException;
use Gt\Promise\Promise;
use PHPUnit\Framework\TestCase;

class FulfilledPromiseTest extends TestCase {
	public function testCanNotResolveWithPromise() {
		$callback = fn()=>true;
		$promise = new Promise($callback);
		self::expectException(FulfilledValueNotConcreteException::class);
		new FulfilledPromise($promise);
	}

	public function testDoNothingWithNullComplete() {
		$message = "Test message";
		$sut = new FulfilledPromise($message);
		$exception = null;
		try {
			$sut->complete();
		}
		catch(Exception $exception) {}

		self::assertNull($exception);
	}
}