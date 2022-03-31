# Propagating context in HTTP requests

```php
namespace App\Controller;

use Instrumentation\Http\PropagationHeadersProvider;
use Instrumentation\Http\PropagatingHttpClientFactory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class TestHttpClient
{
    public function __invoke(Request $request): JsonResponse
    {        
        $client = HttpClient::create();
        $info = $client->request(
            'GET', 
            'http://worldtimeapi.org/api/timezone/Europe/Paris',
            ['headers' => PropagationHeadersProvider::getPropagationHeaders()]
        )->toArray();
        
        // OR
        
        $client = PropagatingHttpClientFactory::getClient();
        $info = $client->request('GET', 'http://worldtimeapi.org/api/timezone/Europe/Paris')->toArray();
        
        
        return new JsonResponse($info);
    }
}
```
