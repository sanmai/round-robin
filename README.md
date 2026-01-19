# sanmai/round-robin

A minimal, dependency-free round-robin scheduler for PHP Fibers.

A thin layer for cooperative multitasking:
- no event loop
- no promises
- no futures
- no async/await syntax
- no I/O magic

You write synchronous-looking code, insert `Fiber::suspend()` where it makes sense,
and the scheduler resumes fibers in round-robin order.


## Installation

```bash
composer require sanmai/round-robin
```

## Design goals

* **Explicit control flow** - fibers yield only where you say so
* **Deterministic scheduling** - simple round-robin, no heuristics
* **Shared memory model** - no message passing abstraction
* **Zero dependencies**
* **Tiny surface area** - easy to read, audit, and modify

Non-goals:

* Non-blocking I/O
* Parallelism
* Promises / futures
* Structured concurrency
* Cancellation graphs

If you need those, use [Amp](https://amphp.org/) or [Revolt](https://revolt.run/) instead.


## Quality assurance

This library maintains 100% code coverage and 100% mutation score with [Infection](https://infection.github.io/). Tests cover every line and every mutation.


## Basic usage

### Creating a scheduler

```php
use RoundRobin\Scheduler;

$scheduler = new Scheduler();
```

### Adding fibers

```php
$scheduler->add(new Fiber(function (): void {
    echo "task A: step 1\n";
    Fiber::suspend();
    echo "task A: step 2\n";
}));

$scheduler->add(new Fiber(function (): void {
    echo "task B: step 1\n";
    Fiber::suspend();
    echo "task B: step 2\n";
}));
```

### Running

```php
$scheduler->run();
```

Output:

```
task A: step 1
task B: step 1
task A: step 2
task B: step 2
```


## Passing data between fibers

Fibers share memory.
Use ordinary PHP data structures.

```php
$queue = new SplQueue();
$done  = false;

$producer = new Fiber(function () use ($queue, &$done): void {
    foreach ([1, 2, 3] as $value) {
        $queue->enqueue($value);
        Fiber::suspend();
    }
    $done = true;
});

$consumer = new Fiber(function () use ($queue, &$done): void {
    while (!$done || !$queue->isEmpty()) {
        if ($queue->isEmpty()) {
            Fiber::suspend();
            continue;
        }

        echo "got {$queue->dequeue()}\n";
        Fiber::suspend();
    }
});

$scheduler->add($producer);
$scheduler->add($consumer);
$scheduler->run();
```


## API

### `RoundRobin\Scheduler`

#### `add(Fiber $fiber): void`

Adds a fiber to the scheduler.

* Fibers may be added before or after `run()`
* A fiber is scheduled until it terminates

#### `run(): void`

Runs all scheduled fibers until all of them terminate.

Behavior:

* starts fibers that have not yet started
* resumes suspended fibers
* skips terminated fibers
* stops when no runnable fibers remain

#### `tick(): bool`

Runs a single round of scheduling.

Returns:

* `true` if at least one fiber was executed
* `false` if all fibers are terminated

Useful for embedding the scheduler into an existing loop.

## How scheduling works

* Only **one fiber runs at a time**
* Fibers must call `Fiber::suspend()` to yield
* The scheduler resumes fibers in FIFO order
* There is **no preemption**
* Blocking I/O blocks the entire process

This is cooperative multitasking by design.

## About I/O

This library does **not** make I/O non-blocking.

If a fiber performs blocking I/O:

* the entire PHP process blocks
* no other fiber runs

If you need:

* non-blocking file or socket I/O
* readiness notifications
* timers

Use an event loop (e.g. Revolt) or integrate `stream_select()` yourself.

## When to use this

Use this when you need:

* incremental Fiber adoption
* structured long-running logic
* explicit yield points
* deterministic execution
* no framework lock-in

Skip if you need:

* async/await semantics
* transparent non-blocking I/O
* parallelism
* cancellation propagation

## License

Apache-2.0