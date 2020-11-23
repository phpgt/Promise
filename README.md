A pleasant way to work with asynchronous PHP.
=============================================

In computer science, a `Promise` is a mechanism that provides a simple and direct relationship between procedural code and asynchronous callbacks. Functions within procedural languages, like plain old PHP, have two ways they can affect your program's flow: either by returning values or throwing exceptions.

When working with functions that execute asynchronously, we can't return values because they might not be ready yet, and we can't throw exceptions because that's a procedural concept (where should we catch them?). That's where promises come in: instead of returning a value or throwing an exception, your functions can return a `Promise`, which is an object that can be _fulfilled_ with a value, or _rejected_ with an exception, but not necessarily at the point that they are returned.

With this concept, the actual work that calculates or loads the value required by your code can be _deferred_ to a task that executes asynchronously. Behind the scenes of PHP.Gt/Promise is a `Deferred` class that is used for exactly this.

There are many implementations of the concept of a `Promise`. This library aims to be compatible with the [Web API's Promise][mdn-promise] implementation, providing a `then`, `catch` and `finally` mechanism that behave the same as when working with promises in the web browser.

Example usage
-------------

The following is an example of the syntax provided by this library.

```php
// A simple operation with just a single "then":
$exampleSlowFileReader->read()
->then(function(string $contents) {
        echo "Contents of file: $contents", PHP_EOL;
});

// A more complex example, showing how promises can be chained together:
$exampleRemoteApi->getCustomerById(105)
->then(function(Customer $customer) {
        return $customer->loadLatestOrders(new DateTime("-5 weeks"));
})
->then(function(CustomerOrderList $orders) {
        echo "Customer {$orders->getCustomer()->getName()} ",
        "has made {count($orders)} in the last 5 weeks!", PHP_EOL;
})
->catch(function(Throwable $reason) {
        echo "There was an error loading the customer's details: $reason", PHP_EOL;
})
->finally(function() use($exampleRemoteApi) {
        $exampleRemoteApi->disconnect();
});
```

`Deferred` and `Promise` objects
--------------------------------

This repository splits the responsibility of the asynchronous task's processing and the result of the task completion into the `Deferred` and `Promise` classes respectively.

A `Deferred` object is assigned one or more "process" callbacks, which will be called in order to execute the deferred task.

A `Promise` is created by the `Deferred` upon construction, which is used to represent the result of the deferred task's completion.

To make a class work with promises, it needs at least two functions: one public function that constructs the Deferred object and returns the Promise, and one function that is assigned as the Deferred's process function.

See the example code layout below:

```php
class Example {
// The class must keep a reference to its own Deferred object, as this will be
// referenced when the work completes in order to resolve its Promise.
        private Deferred $deferred;

// This public function will return a Promise representing the task's outcome.
        public function doTheTask():PromiseInterface {
// The Deferred is constructed with the process function as its only parameter.
                $this->deferred = new Deferred(fn() => $this->processFunction());
// The function returns the Deferred's promise.
                return $this->deferred->getPromise();
        }

// The process function will do one small piece of work each time it's called
// until the task is complete, at which point it will resolve the Deferred with
// the final value (from wherever it's calculated).
        private function processFunction():void {
                if($this->isThereMoreWorkToDo()) {
                        $this->doMoreWork();
                }
                else {
                        $this->deferred->resolve($this->getFinalValue());
                }
        }

        private function isThereMoreWorkToDo():bool { /* ... */ }
        private function doMoreWork():void { /* ... */ }
        private function getFinalValue() { /* ... */ }
}
```

The simplest representation of how the above Example class would be integrated is as follows:

```php
$example = new Example();
$example->doTheTask()
->then(function($finalValue) {
        echo "Final value: ", $finalValue;
});
```

The end result is the ability to call the public function, without needing to know anything about Promise/Deferred implementation.

Important: PHP is still a fully procedural language, so without an external loop being used to call the deferred process, the promise will never fulfil or reject.

Event loop
----------

In order for this Promise library to be useful, some code has got to act as an event loop to call the deferred processes. This could be a simple while loop, but for real world tasks a more comprehensive loop system should be used.

The implementation of a Promise-based architecture is complex enough on its own, so the responsibility of an event loop library is maintained separately in [PHP.Gt/Async][gt-async].

[mdn-promise]: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise
[gt-async]: https://php.gt/async 