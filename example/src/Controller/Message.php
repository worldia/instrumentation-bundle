<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace App\Controller;

use App\Messenger\AsyncMessage;
use App\Messenger\SyncMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class Message extends AbstractController
{
    #[Route('/message')]
    public function message(
        Request $request,
        #[Autowire(service: MessageBusInterface::class)]
        MessageBusInterface $messageBus,
    ): Response {
        $async = (bool) $request->get('async', 1);
        $class = $async ? AsyncMessage::class : SyncMessage::class;

        $messageBus->dispatch(new $class('Some content'));

        return new Response(\sprintf('%s message dispatched<br>%s', $async ? 'Async' : 'Sync', $this->getTraceLink()));
    }
}
