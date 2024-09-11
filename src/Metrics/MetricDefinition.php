<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics;

/**
 * @internal
 */
final class MetricDefinition
{
    /**
     * @param array<string>    $labels
     * @param array<int|float> $buckets
     */
    public function __construct(
        private string $namespace,
        private string $name,
        private string $type,
        private string $help,
        private array $labels = [],
        private array|null $buckets = null,
    ) {
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    public function getHelp(): string
    {
        return $this->help;
    }

    /**
     * @return array<int|float>
     */
    public function getBuckets(): array|null
    {
        return $this->buckets;
    }
}
