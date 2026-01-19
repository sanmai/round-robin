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

namespace RoundRobin;

use Fiber;

/**
 * @final
 */
class Scheduler
{
    /** @var list<Fiber<mixed, mixed, mixed, mixed>> */
    private array $fibers = [];

    /** @param Fiber<mixed, mixed, mixed, mixed> $fiber */
    public function add(Fiber $fiber): void
    {
        $this->fibers[] = $fiber;
    }

    public function run(): void
    {
        while ($this->tick()) {
            // Continue until all fibers are terminated
        }
    }

    public function tick(): bool
    {
        $alive = false;

        foreach ($this->fibers as $fiber) {
            if ($fiber->isTerminated()) {
                continue;
            }

            $alive = true;

            if (!$fiber->isStarted()) {
                $fiber->start();
                continue;
            }

            $fiber->resume();
        }

        return $alive;
    }
}
