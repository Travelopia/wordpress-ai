<?php

namespace Travelopia\WordPress_AI\Dependencies;

// Don't redefine the functions if included multiple times.
if (!\function_exists('Travelopia\WordPress_AI\Dependencies\GuzzleHttp\describe_type')) {
    require __DIR__ . '/functions.php';
}
