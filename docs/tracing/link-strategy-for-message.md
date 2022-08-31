# Link strategy for a message

```php
use Instrumentation\Tracing\Propagation\Messenger\PropagationStrategyStamp;

$message = new MyMessage();

// Message processing will be recorded as a child span (default)
$messageBus->dispatch($message, [new PropagationStrategyStamp(PropagationStrategyStamp::STRATEGY_PARENT)]);    

// Message processing will be recorded as a new root span, and a link to the parent span will be set
$messageBus->dispatch($message, [new PropagationStrategyStamp(PropagationStrategyStamp::STRATEGY_LINK)]);    
```
