<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\AI\Sampling;

use Instrumentation\Tracing\AI\Sampling\OperationNameVoter;
use PHPUnit\Framework\TestCase;

class OperationNameVoterTest extends TestCase
{
    public function testItTracesEverythingWhenBlacklistIsEmpty(): void
    {
        $voter = new OperationNameVoter([]);

        $this->assertTrue($voter->shouldTrace('gpt-4o'));
        $this->assertTrue($voter->shouldTrace('anything'));
    }

    public function testItDoesNotTraceNamesMatchingABlacklistPattern(): void
    {
        $voter = new OperationNameVoter(['-mini$']);

        $this->assertFalse($voter->shouldTrace('gpt-4o-mini'));
        $this->assertTrue($voter->shouldTrace('gpt-4o'));
    }

    public function testItMatchesAgainstAnyPatternInTheBlacklist(): void
    {
        $voter = new OperationNameVoter(['^internal_', '^debug_']);

        $this->assertFalse($voter->shouldTrace('internal_agent'));
        $this->assertFalse($voter->shouldTrace('debug_dump'));
        $this->assertTrue($voter->shouldTrace('get_weather'));
    }
}
