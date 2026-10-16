<?php

return [
    'uri' => env('MONGO_URI', sprintf(
        'mongodb://%s:%s',
        env('MONGO_HOST', '127.0.0.1'),
        env('MONGO_PORT', 27017),
    )),
    'database' => env('MONGO_DATABASE', 'laravel'),
    'collection' => env('MONGO_IMAGE_COLLECTION', 'profile_images'),
    'username' => env('MONGO_USERNAME'),
    'password' => env('MONGO_PASSWORD'),
    'auth_source' => env('MONGO_AUTH_SOURCE', env('MONGO_DATABASE', 'laravel')),
];
