<?php

namespace Tests\Unit;

use App\Jobs\SyncFirefliesData;
use App\Jobs\SyncFreeAgentData;
use Tests\TestCase;

class QueuedJobCompatibilityTest extends TestCase
{
    public function test_jobs_queued_before_full_option_existed_still_unserialise(): void
    {
        foreach ([SyncFirefliesData::class, SyncFreeAgentData::class] as $class) {
            // Serialised form of the job from before $full was added
            $old = sprintf('O:%d:"%s":0:{}', strlen($class), $class);

            $this->assertFalse(unserialize($old)->full, $class);
        }
    }
}
