<?php
declare(strict_types=1);

return [
    'base_url' => (string)icafecloud_setting('base_url', 'https://api.icafecloud.com'),
    'cafe_id' => (string)icafecloud_setting('cafe_id', '50761'),
    'api_key' => (string)icafecloud_setting('api_key', ''),
    'connect_timeout' => 5,
    'timeout' => 15,
];
