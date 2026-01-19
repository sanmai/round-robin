<?php

/**
 * Copyright 2026 Alexey Kopytko <alexey@kopytko.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace Tests\RoundRobin;

use Fiber;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use RoundRobin\Scheduler;
use SplQueue;

#[CoversMethod(Scheduler::class, 'run')]
#[CoversMethod(Scheduler::class, 'add')]
final class SchedulerTest extends TestCase
{
    public function testEmptyScheduler(): void
    {
        $scheduler = new Scheduler();

        $scheduler->run();

        $this->assertTrue(true);
    }

    public function testSingleFiber(): void
    {
        $scheduler = new Scheduler();
        $output = [];

        $scheduler->add(new Fiber(function () use (&$output): void {
            $output[] = 'A1';
            Fiber::suspend();
            $output[] = 'A2';
        }));

        $scheduler->run();

        $this->assertSame(['A1', 'A2'], $output);
    }

    public function testRoundRobinOrder(): void
    {
        $scheduler = new Scheduler();
        $output = [];

        $scheduler->add(new Fiber(function () use (&$output): void {
            $output[] = 'A1';
            Fiber::suspend();
            $output[] = 'A2';
        }));

        $scheduler->add(new Fiber(function () use (&$output): void {
            $output[] = 'B1';
            Fiber::suspend();
            $output[] = 'B2';
        }));

        $scheduler->run();

        $this->assertSame(['A1', 'B1', 'A2', 'B2'], $output);
    }

    public function testThreeFibers(): void
    {
        $scheduler = new Scheduler();
        $output = [];

        $scheduler->add(new Fiber(function () use (&$output): void {
            $output[] = 'A1';
            Fiber::suspend();
            $output[] = 'A2';
        }));

        $scheduler->add(new Fiber(function () use (&$output): void {
            $output[] = 'B1';
            Fiber::suspend();
            $output[] = 'B2';
        }));

        $scheduler->add(new Fiber(function () use (&$output): void {
            $output[] = 'C1';
            Fiber::suspend();
            $output[] = 'C2';
        }));

        $scheduler->run();

        $this->assertSame(['A1', 'B1', 'C1', 'A2', 'B2', 'C2'], $output);
    }

    public function testFibersWithDifferentSuspendCounts(): void
    {
        $scheduler = new Scheduler();
        $output = [];

        $scheduler->add(new Fiber(function () use (&$output): void {
            $output[] = 'A1';
            Fiber::suspend();
            $output[] = 'A2';
            Fiber::suspend();
            $output[] = 'A3';
        }));

        $scheduler->add(new Fiber(function () use (&$output): void {
            $output[] = 'B1';
        }));

        $scheduler->run();

        $this->assertSame(['A1', 'B1', 'A2', 'A3'], $output);
    }

    public function testProducerConsumer(): void
    {
        $scheduler = new Scheduler();
        $queue = new SplQueue();
        $done = false;
        $consumed = [];

        $producer = new Fiber(function () use ($queue, &$done): void {
            foreach ([1, 2, 3] as $value) {
                $queue->enqueue($value);
                Fiber::suspend();
            }
            $done = true;
        });

        $consumer = new Fiber(function () use ($queue, &$done, &$consumed): void {
            while (!$done || !$queue->isEmpty()) {
                if ($queue->isEmpty()) {
                    Fiber::suspend();
                    continue;
                }

                $consumed[] = $queue->dequeue();
                Fiber::suspend();
            }
        });

        $scheduler->add($producer);
        $scheduler->add($consumer);
        $scheduler->run();

        $this->assertSame([1, 2, 3], $consumed);
    }

    public function testFiberWithNoSuspend(): void
    {
        $scheduler = new Scheduler();
        $executed = false;

        $scheduler->add(new Fiber(function () use (&$executed): void {
            $executed = true;
        }));

        $scheduler->run();

        $this->assertTrue($executed);
    }

    public function testFiberReturn(): void
    {
        $scheduler = new Scheduler();
        $fiber = new Fiber(fn(): string => 'result');

        $scheduler->add($fiber);
        $scheduler->run();

        $this->assertSame('result', $fiber->getReturn());
    }

    public function testTerminatedFiberBeforeActiveOne(): void
    {
        $scheduler = new Scheduler();
        $output = [];

        // First fiber terminates immediately
        $scheduler->add(new Fiber(function () use (&$output): void {
            $output[] = 'A';
        }));

        // Second fiber needs multiple ticks
        $scheduler->add(new Fiber(function () use (&$output): void {
            $output[] = 'B1';
            Fiber::suspend();
            $output[] = 'B2';
        }));

        $scheduler->run();

        $this->assertSame(['A', 'B1', 'B2'], $output);
    }
}
