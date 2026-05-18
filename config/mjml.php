<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | MJML binary
    |--------------------------------------------------------------------------
    |
    | When auto_detect_path is true, the package looks for node_modules/.bin/mjml
    | under the application base path. Set auto_detect_path to false and configure
    | path_to_binary when MJML is installed globally or in a custom location.
    |
    */
    'auto_detect_path' => env('MJML_AUTO_DETECT_PATH', true),

    'path_to_binary' => env('MJML_PATH_TO_BINARY', ''),

    'node_path' => env('MJML_NODE_PATH', 'node'),

    /*
    |--------------------------------------------------------------------------
    | Blade in MJML templates
    |--------------------------------------------------------------------------
    |
    | process_includes_with_blade (recommended, default true): inline <mj-include>
    | partials, then run a single Blade pass on the assembled MJML before the CLI.
    | Replaces app-level RerenderMjml traits from the original package.
    |
    | rerender_blade_after_compile (default false): legacy second Blade pass on HTML
    | after MJML. Keep false when process_includes_with_blade is true. Do not enable both.
    |
    */
    'process_includes_with_blade' => env('MJML_PROCESS_INCLUDES_WITH_BLADE', true),

    'rerender_blade_after_compile' => env('MJML_RERENDER_BLADE_AFTER_COMPILE', false),
];
