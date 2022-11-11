<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Tracing\Factory;

use Instrumentation\Tracing\Factory\SamplerFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOffSampler;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use PhpSpec\ObjectBehavior;

class SamplerFactorySpec extends ObjectBehavior
{
    public function it_is_initializable(): void
    {
        $this->beAnInstanceOf(SamplerFactory::class);
    }

    public function it_creates_samplers(): void
    {
        $this->create('always_on')->shouldReturnAnInstanceOf(AlwaysOnSampler::class);
        $this->create('always_off')->shouldReturnAnInstanceOf(AlwaysOffSampler::class);
        $this->create('traceidratio', .2)->shouldReturnAnInstanceOf(TraceIdRatioBasedSampler::class);
    }

    public function it_creates_parent_based_samplers(): void
    {
        $sampler = $this->create('parentbased_traceidratio', .2);
        $sampler->shouldReturnAnInstanceOf(ParentBased::class);
        $sampler->getDescription()->shouldReturn('ParentBased+TraceIdRatioBasedSampler{0.200000}');

        $sampler = $this->create('parentbased_always_on');
        $sampler->shouldReturnAnInstanceOf(ParentBased::class);
        $sampler->getDescription()->shouldReturn('ParentBased+AlwaysOnSampler');

        $sampler = $this->create('parentbased_always_off');
        $sampler->shouldReturnAnInstanceOf(ParentBased::class);
        $sampler->getDescription()->shouldReturn('ParentBased+AlwaysOffSampler');
    }

    public function it_creates_samplers_from_url(): void
    {
        $sampler = $this->createFromDsn('scheme://host');
        $sampler->shouldReturnAnInstanceOf(ParentBased::class);
        $sampler->getDescription()->shouldReturn('ParentBased+AlwaysOnSampler');

        $sampler = $this->createFromDsn('scheme://host?type=parentbased_always_off');
        $sampler->shouldReturnAnInstanceOf(ParentBased::class);
        $sampler->getDescription()->shouldReturn('ParentBased+AlwaysOffSampler');

        $sampler = $this->createFromDsn('scheme://host?type=parentbased_traceidratio&ratio=0.2');
        $sampler->shouldReturnAnInstanceOf(ParentBased::class);
        $sampler->getDescription()->shouldReturn('ParentBased+TraceIdRatioBasedSampler{0.200000}');
    }

    public function it_throws_an_exception_with_needed_ratio_is_not_provided(): void
    {
        $this->shouldThrow(\RuntimeException::class)->during('create', ['traceidratio']);
    }

    public function it_throws_an_exception_for_unknown_sampler(): void
    {
        $this->shouldThrow(\InvalidArgumentException::class)->during('create', ['some_sampler']);
    }
}
