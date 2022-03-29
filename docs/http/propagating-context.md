# Propagating context in HTTP requests

```php
<?php

namespace App\Controller;

use Instrumentation\Http\PropagationHeadersProvider;
use Instrumentation\Http\PropagatingHttpClientFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpClient\HttpClient;

class TestHttpClient
{
    public function __invoke(Request $request): JsonResponse
    {        
        $client = HttpClient::create();
        $info = $client->request(
            'GET', 
            'http://worldtimeapi.org/api/timezone/Europe/Paris',
            ['headers' => PropagationHeadersProvider::getPropagationHeaders()]
        );
        
        // OR
        
        $client = PropagatingHttpClientFactory::getClient();
        $info = $client->request('GET', 'http://worldtimeapi.org/api/timezone/Europe/Paris');
        
        
        return new JsonResponse($result);
    }
}
```
