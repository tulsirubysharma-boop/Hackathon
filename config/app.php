<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');

$configuredBasePath = getenv('HEALTHSPHERE_BASE_PATH');
if ($configuredBasePath === false) {
    $configuredBasePath = '/Hackathon';
}

$configuredBasePath = trim($configuredBasePath);
if ($configuredBasePath !== '' && !preg_match('~^/[A-Za-z0-9/_-]*$~', $configuredBasePath)) {
    error_log('Invalid HEALTHSPHERE_BASE_PATH; using /Hackathon.');
    $configuredBasePath = '/Hackathon';
}

$healthSphereBasePath = rtrim($configuredBasePath, '/');

function hsUrl(string $path = ''): string
{
    global $healthSphereBasePath;
    return $healthSphereBasePath . '/' . ltrim($path, '/');
}
