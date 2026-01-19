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

#[CoversMethod(Scheduler::class, 'tick')]
#[CoversMethod(Scheduler::class, 'add')]
final class SchedulerTickTest extends TestCase
{
    public function testTickReturnsTrueWhileFibersRunning(): void
    {
        $scheduler = new Scheduler();

        $scheduler->add(new Fiber(function (): void {
            Fiber::suspend();
        }));

        $this->assertTrue($scheduler->tick());
        $this->assertTrue($scheduler->tick());
        $this->assertFalse($scheduler->tick());
    }

    public function testTickReturnsFalseOnEmptyScheduler(): void
    {
        $scheduler = new Scheduler();

        $this->assertFalse($scheduler->tick());
    }

    public function testTickStartsFiber(): void
    {
        $scheduler = new Scheduler();
        $output = [];

        $scheduler->add(new Fiber(function () use (&$output): void {
            $output[] = 'started';
            Fiber::suspend();
        }));

        $scheduler->tick();

        $this->assertSame(['started'], $output);
    }

    public function testTickResumesFiber(): void
    {
        $scheduler = new Scheduler();
        $output = [];

        $scheduler->add(new Fiber(function () use (&$output): void {
            $output[] = 'A';
            Fiber::suspend();
            $output[] = 'B';
        }));

        $scheduler->tick();
        $this->assertSame(['A'], $output);

        $scheduler->tick();
        $this->assertSame(['A', 'B'], $output);
    }

    public function testTickSkipsTerminatedFiber(): void
    {
        $scheduler = new Scheduler();
        $output = [];

        // First fiber terminates immediately
        $scheduler->add(new Fiber(function () use (&$output): void {
            $output[] = 'A';
        }));

        // Second fiber needs two ticks
        $scheduler->add(new Fiber(function () use (&$output): void {
            $output[] = 'B1';
            Fiber::suspend();
            $output[] = 'B2';
        }));

        $scheduler->tick();
        $this->assertSame(['A', 'B1'], $output);

        $scheduler->tick();
        $this->assertSame(['A', 'B1', 'B2'], $output);
    }
}
