<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    'simple-datatables' => [
        'version' => '9.2.1',
    ],
    'simple-datatables/dist/style.min.css' => [
        'version' => '9.2.1',
        'type' => 'css',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@tabler/core' => [
        'version' => '1.0.0-beta24',
    ],
    '@tabler/core/dist/css/tabler.min.css' => [
        'version' => '1.0.0-beta24',
        'type' => 'css',
    ],
    'chart.js' => [
        'version' => '3.9.1',
    ],
    '@stimulus-components/timeago' => [
        'version' => '5.0.2',
    ],
    'date-fns' => [
        'version' => '4.1.0',
    ],
];
