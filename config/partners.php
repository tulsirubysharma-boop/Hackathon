<?php
declare(strict_types=1);

function healthspherePartnerUrl(string $environmentVariable): ?string
{
    $value = getenv($environmentVariable);
    if (!$value) {
        return null;
    }

    $url = filter_var($value, FILTER_VALIDATE_URL);
    if (!$url || parse_url($url, PHP_URL_SCHEME) !== 'https') {
        error_log('HealthSphere partner URL must be a valid HTTPS URL: ' . $environmentVariable);
        return null;
    }

    return $url;
}

return [
    'cuelinks' => healthspherePartnerUrl('HEALTHSPHERE_CUELINKS_URL'),
    'earnkaro' => healthspherePartnerUrl('HEALTHSPHERE_EARNKARO_URL')
];
