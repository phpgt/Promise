<?php
namespace Gt\Promise;

use Throwable;

interface DeferredInterface {
	public function __construct(callable $process = null);

	/**
	 * Returns the instance of the Promise that will be resolved/rejected
	 * by this Deferred.
	 */
	public function getPromise():PromiseInterface;

	/**
	 * Resolve the Promise returned by getPromise() with its final value.
	 * All consuming callbacks registered with the Promise's then()
	 * function will be fulfilled with this value.
	 *
	 * @param ?mixed $value
	 */
	public function resolve($value = null):void;

	/**
	 * Reject the Promise returned by getPromise() with a Throwable,
	 * indicating that the computation failed. All consuming callbacks
	 * registered with the Promise's catch() function will be rejected
	 * with this reason.
	 */
	public function reject(Throwable $reason):void;

	/**
	 * Assigns a callback as a task to perform to complete the deferred
	 * work. A process can be assigned in the constructor, so this function
	 * is only required when a Deferred requires calling more than one
	 * process task to complete the work.
	 *
	 * The process should perform the smallest possible amount of work to
	 * progress the computation represented by the Deferred, aiming to block
	 * the execution for as little amount of time as possible.
	 */
	public function addProcess(callable $process):void;

	/**
	 * Return a list of all attached process callbacks. This list will be
	 * read by an event loop, so each process can be called until the
	 * Deferred completes (when then isActive() function returns false).
	 *
	 * @return callable[]
	 */
	public function getProcessList():array;

	/**
	 * Check whether there is still work to complete. This boolean will
	 * be read by an event loop. If this function returns false, the event
	 * loop will know that the deferred is complete, and can remove it from
	 * its stack.
	 */
	public function isActive():bool;
}