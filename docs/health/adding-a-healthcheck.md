# Adding a healthcheck

```php
namespace App\Health;

use Instrumentation\Health\HealtcheckInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class DummyHealthcheck implements HealtcheckInterface
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public function getName(): string
    {
        return 'Dummy';
    }

    public function getDescription(): ?string
    {
        return 'Dummy healthcheck that retrieves the status from incoming "X-Dummy-Status" request header';
    }

    public function getStatus(): string
    {
        if ($header = $this->requestStack->getMainRequest()->headers->get('X-Dummy-Status', null)) {
            return $header;
        }

        return HealtcheckInterface::HEALTHY;
    }

    public function getStatusMessage(): ?string
    {
        return sprintf('I am in %s state because you told me so ;)', $this->getStatus());
    }

    public function isCritical(): bool
    {
        return true;
    }
}
```
