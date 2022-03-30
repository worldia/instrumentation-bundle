# Custom operation name for a message

```php
namespace App\Controller;

use Instrumentation\Tracing\Instrumentation\Messenger\OperationNameStamp;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class SendMessage
{
    public function __construct(private MessageBusInterface $messageBus)
    {
    }

    public function __invoke(): Response
    {
        $message = new MyMessage();
        
        $this->messageBus->dispatch($message, [new OperationNameStamp('my-message process')]);

        return new Response('Sent', 201);
    }
}
```
