BernardServiceProvider
======================

Brings Bernard to Silex.

Getting Started
---------------

Registering and configuring `PredisServiceProvider` is required. It will default to use the
service `predis`. This can be overwritten if needed.

``` php
<?php

use Bernard\Silex\BernardServiceProvider;
use Predis\Silex\PredisServiceProvider;

// .. create $app
$app->register(new BernardServiceProvider);
$app->register(new PredisServiceProvider);

// if you want to use a custom predis client the best thing is to
// overwrite $app['bernard.predis'];
$app['predis.parameters']  = 'tcp://localhost';
$app['predis.options'] = array(
    'prefix' => 'bernard:',
);
```

Now you are ready to produce messages to a queue.

``` php
<?php

use Bernard\Message\DefaultMessage;

// .. create $app
$app['bernard.producer']->produce(new DefaultMessage('SendNewsletter', array(
    'id' => 12,
));
```

Or consume messages.

``` php
<?php

use Bernard\Command\ConsumeCommand;

// .. create $app
$app['bernard.service_resolver'] = $app->share($app->extend('bernard.service_resolver', function ($resolver, $app) {
    // The ServicePrivider uses a special lazy loading service resolver.
    // which will resolve the service based on the id.
    $resolver->register('SendNewsletter', 'my_service_id');

    return $resolver;
}));

$app['console']->add(new ConsumeCommand($app['bernard.service_resolver'], $app['bernard.queue_factory']));
$app['console']->run();
```

``` bash
$ ./bin/console bernard:consume 'send-newsletter'
```

A Note on Debug
---------------

When `$app['debug']` is true it will use the in memory queuing instead of redis.
This can be circumvented by doing the following after registering this provider.

``` php
<?php
// .. create $app and register BernardServiceProvider
$app['bernard.queue_factory'] = $app->raw('bernard.queue_factory.real'];
```
