<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Functional\Stub;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * Minimal `ai.platform` service used by the boot test so the AI compiler passes
 * have a tagged service to decorate. Its methods are never invoked during the
 * boot test (we only assert the container compiles and the decoration applies).
 */
final class DummyPlatform implements PlatformInterface
{
    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        throw new \LogicException('DummyPlatform::invoke() is not meant to be called.');
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        throw new \LogicException('DummyPlatform::getModelCatalog() is not meant to be called.');
    }
}
