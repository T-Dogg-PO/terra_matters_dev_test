<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Wide open on purpose: this is a throwaway local environment.
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
