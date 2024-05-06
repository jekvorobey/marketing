<?php

namespace App\Services\Cache;

class CacheHelper
{
    public static function getCacheKey(string $prefix, array $params): string
    {
        ksort($params);
        $paramsString = http_build_query($params);

        return $prefix . ':' . hash('sha256', $paramsString);
    }
}
