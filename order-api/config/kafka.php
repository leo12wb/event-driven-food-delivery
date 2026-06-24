<?php

return [
    'brokers' => env('KAFKA_BROKERS', 'kafka:9092'),

    'topics' => [
        'order_created'   => 'order.created',
        'order_ready'     => 'order.ready',
        'order_delivered' => 'order.delivered',
    ],

    'consumer_groups' => [
        'kitchen'      => 'kitchen-consumer',
        'delivery'     => 'delivery-consumer',
        'notification' => 'notification-consumer',
    ],
];
