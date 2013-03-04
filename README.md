RaekkeServiceProvider
=====================

Brings Raekke to Silex.

Getting Started
---------------

Registering and configuring `PredisServiceProvider` is required. It will default to use the
service `predis`. This can be overwritten if needed.

``` php
<?php

use Raekke\Silex\RaekkeServiceProvider;
use Predis\Silex\PredisServiceProvider;

// .. create $app
$app->register(new RaekkeServiceProvider);
$app->register(new PredisServiceProvider);

// if you want to use a custom predis client the best thing is to
// overwrite $app['raekke.predis'];
$app['predis.parameters']  = 'tcp://localhost';
$app['predis.options'] = array(
    'prefix' => 'raekke:',
);
```

Now you are ready to produce messages to a queue.

``` php
<?php

use Raekke\Message\DefaultMessage;

// .. create $app
$app['raekke.producer']->produce(new DefaultMessage('SendNewsletter', array(
    'id' => 12,
));
```

Or consume messages.

``` php
<?php

use Raekke\Command\ConsumeCommand;

// .. create $app
$app['raekke.service_resolver'] = $app->share($app->extend('raekke.service_resolver', function ($resolver, $app) {
    // The ServicePrivider uses a special lazy loading service resolver.
    // which will resolve the service based on the id.
    $resolver->register('SendNewsletter', 'my_service_id');

    return $resolver;
}));

$app['console']->add(new ConsumeCommand($app['raekke.service_resolver'], $app['raekke.queue_factory']));
$app['console']->run();
```

``` bash
$ ./bin/console raekke:consume 'send-newsletter'
```

A Note on Debug
---------------

When `$app['debug']` is true it will use the in memory queuing instead of redis.
This can be circumvented by doing the following after registering this provider.

``` php
<?php
// .. create $app and register RaekkeServiceProvider
$app['raekke.queue_factory'] = $app->raw('raekke.queue_factory.real'];
```
