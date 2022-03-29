# Adding a metric

```php
namespace App\EventSubscriber;

use Instrumentation\Metrics\MetricProviderInterface;
use Instrumentation\Metrics\RegistryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestEventSubscriber implements EventSubscriberInterface, MetricProviderInterface
{
    public static function getProvidedMetrics(): array
    {
        return [
            'requests_handled_total' => [
                'type' => 'counter',
                'help' => 'Total requests handled by this instance',
            ]
        ];
    }

    public function __construct(private RegistryInterface $registry)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onRequest',
        ];
    }

    public function onRequest(Event\RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->registry->getCounter('requests_handled_total')->inc();
    }
}
```
