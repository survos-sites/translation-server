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
    'admin' => [
        'path' => './assets/admin.js',
        'entrypoint' => true,
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    'simple-datatables' => [
        'version' => '10.2.0',
    ],
    'simple-datatables/dist/style.min.css' => [
        'version' => '10.2.0',
        'type' => 'css',
    ],
    '@tabler/core' => [
        'version' => '1.4.0',
    ],
    '@tabler/core/dist/css/tabler.min.css' => [
        'version' => '1.4.0',
        'type' => 'css',
    ],
    'chart.js' => [
        'version' => '4.5.1',
    ],
    '@stimulus-components/timeago' => [
        'version' => '5.0.2',
    ],
    'date-fns' => [
        'version' => '4.1.0',
    ],
    '@kurkle/color' => [
        'version' => '0.4.0',
    ],
    'bootstrap' => [
        'version' => '5.3.8',
    ],
    '@popperjs/core' => [
        'version' => '2.11.8',
    ],
    'bootstrap/dist/css/bootstrap.min.css' => [
        'version' => '5.3.8',
        'type' => 'css',
    ],
    '@tacman1123/twig-browser' => [
        'version' => '0.4.18',
    ],
    '@tacman1123/twig-browser/src/compat/compileTwigBlocks.js' => [
        'version' => '0.4.18',
    ],
    '@tacman1123/twig-browser/adapters/symfony' => [
        'version' => '0.4.18',
    ],
    'perfect-scrollbar' => [
        'version' => '1.5.6',
    ],
    'perfect-scrollbar/css/perfect-scrollbar.min.css' => [
        'version' => '1.5.6',
        'type' => 'css',
    ],
    'datatables.net-plugins/i18n/en-GB.mjs' => [
        'version' => '2.3.6',
    ],
    'datatables.net-plugins/i18n/es-ES.mjs' => [
        'version' => '2.3.6',
    ],
    'datatables.net-plugins/i18n/de-DE.mjs' => [
        'version' => '2.3.6',
    ],
    'datatables.net-bs5' => [
        'version' => '2.3.7',
    ],
    'jquery' => [
        'version' => '4.0.0',
    ],
    'datatables.net' => [
        'version' => '2.3.7',
    ],
    'datatables.net-bs5/css/dataTables.bootstrap5.min.css' => [
        'version' => '2.3.7',
        'type' => 'css',
    ],
    'datatables.net-buttons-bs5' => [
        'version' => '3.2.6',
    ],
    'datatables.net-buttons' => [
        'version' => '3.2.6',
    ],
    'datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css' => [
        'version' => '3.2.6',
        'type' => 'css',
    ],
    'datatables.net-responsive-bs5' => [
        'version' => '3.0.8',
    ],
    'datatables.net-responsive' => [
        'version' => '3.0.8',
    ],
    'datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css' => [
        'version' => '3.0.8',
        'type' => 'css',
    ],
    'datatables.net-scroller-bs5' => [
        'version' => '2.4.3',
    ],
    'datatables.net-scroller' => [
        'version' => '2.4.3',
    ],
    'datatables.net-scroller-bs5/css/scroller.bootstrap5.min.css' => [
        'version' => '2.4.3',
        'type' => 'css',
    ],
    'datatables.net-searchpanes-bs5' => [
        'version' => '2.3.5',
    ],
    'datatables.net-searchpanes' => [
        'version' => '2.3.5',
    ],
    'datatables.net-searchpanes-bs5/css/searchPanes.bootstrap5.min.css' => [
        'version' => '2.3.5',
        'type' => 'css',
    ],
    'datatables.net-searchbuilder-bs5' => [
        'version' => '1.8.4',
    ],
    'datatables.net-searchbuilder' => [
        'version' => '1.8.4',
    ],
    'datatables.net-searchbuilder-bs5/css/searchBuilder.bootstrap5.min.css' => [
        'version' => '1.8.4',
        'type' => 'css',
    ],
    'datatables.net-select-bs5' => [
        'version' => '3.1.3',
    ],
    'datatables.net-select' => [
        'version' => '3.1.3',
    ],
    'datatables.net-select-bs5/css/select.bootstrap5.min.css' => [
        'version' => '3.1.3',
        'type' => 'css',
    ],
    'datatables.net-columncontrol' => [
        'version' => '1.2.1',
    ],
    'datatables.net-columncontrol-bs5' => [
        'version' => '1.2.1',
    ],
    'datatables.net-columncontrol-bs5/css/columnControl.bootstrap5.min.css' => [
        'version' => '1.2.1',
        'type' => 'css',
    ],
    'stimulus-attributes' => [
        'version' => '1.0.2',
    ],
    'escape-html' => [
        'version' => '1.0.3',
    ],
    'dexie' => [
        'version' => '4.4.2',
    ],
];
