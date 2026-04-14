<?php

return [
    'backend' => [
        'luisasofie/rainbow/user-color' => [
            'target' => \LuisaSofie\Rainbow\Middleware\UserColorMiddleware::class,
            'after' => [
                'typo3/cms-backend/authentication',
            ],
            'before' => [
                'typo3/cms-backend/backend-module-validator',
            ],
        ],
    ],
];
