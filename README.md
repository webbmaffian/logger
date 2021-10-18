# Logger
Composer logger package.

## Example usage

```php
<?php
$logger = new \Webbmaffian\Logger\File_Logger();

$logger->info(
  'Hello world',
  new Exception('Oh no'),
  $logger->index('order', 'ABC123'),
  $logger->meta('foo', 'bar'),
  $logger->meta(['moreFoo' => 'moreBar'])
);
```

Or if you're using the [Mafia Logger](https://github.com/webbmaffian/mafia-logger) WordPress plugin:

```php
<?php
$logger = Log::logger();

$logger->info(
  'Hello world',
  new Exception('Oh no'),
  $logger->index('order', 'ABC123'),
  $logger->meta('foo', 'bar'),
  $logger->meta(['moreFoo' => 'moreBar'])
);
```

## Methods

### $logger->{SEVERITY}(mixed ...$args): void
Takes any kind and quantity of arguments.

```php
<?php
$logger->emergency(); // Or: $logger->fatal();
$logger->alert();
$logger->critical(); // Or: $logger->crit();
$logger->error();
$logger->warning(); // Or: $logger->warn();
$logger->notice();
$logger->informational(); // Or: $logger->info();
$logger->debug();
```

| Severity      | Description                                                                                                                       |
| ------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| emergency     | System is unusable, e.g. total datacenter outages.                                                                                |
| alert         | Action must be taken immediately, e.g. hints that might lead to total datacenter outages.                                         |
| critical      | Critical conditions, e.g. service, database or connection disruptions.                                                            |
| error         | Error conditions, e.g. fatal application errors.                                                                                  |
| warning       | Warning conditions, e.g. non-fatal errors or possible security threats.                                                           |
| notice        | Normal but significant conditions, e.g. errors that was solved automatically but should be looked into, like undefined variables. |
| informational | Informational messages, e.g. events or audit logs.                                                                                |
| debug         | Debug-level messages, e.g. step-by-step actions of events.                                                                        |

### $logger->index(string|array $key, string $value): Index
Returns [indices](https://github.com/webbmaffian/log.mafia.tools#log-entry-indices) that can be added as argument to e.g. `$logger->info()` or `$logger->set_context()`.

```php
<?php
$logger->info(
  $logger->index('key', 'value'),
  $logger->index(['multiple' => 'keys', 'andMultiple' => 'values'])
);

### $logger->meta(string|array $key, mixed $value): Meta
Returns meta that can be added as argument to e.g. `$logger->info()` or `$logger->set_context()`. Can contain anything.
```php
<?php
$logger->info(
  $logger->index('key', 'value'),
  $logger->index([
    'multiple' => 'keys',
    'andMultiple' => 'values',
    'or' => [
      'whyNot' => 'nestedStuff'
    ]
  ])
);
```

### $logger->set_context(...$args): int
Can be used just like the logging methods, but will append its arguments to all following log entries until `$logger->reset_logger()` is called. Any call will append a new context layer while keeping the previous context layers. Returns a context number.

```php
<?php
$logger->set_context($logger->index('foo', 'bar'));
$logger->info('Hello'); // Will have the index foo:bar

$logger->set_context($logger->index('hello', 'world'));
$logger->info('Hello again'); // Will have the indices foo:bar and hello:world

$logger->reset_context();
$logger->info('Bye'); // Will have the index foo:bar

$logger->reset_context();
```

### $logger->reset_context(?int $context): void
Resets the context to the provided context number, or to the previous context if not provided.
