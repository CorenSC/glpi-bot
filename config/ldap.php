<?php

declare(strict_types=1);

return [
    'enabled' => env('LDAP_ENABLED', false),
    'host' => env('LDAP_HOST', '127.0.0.1'),
    'port' => (int) env('LDAP_PORT', 389),
    'encryption' => env('LDAP_ENCRYPTION', 'none'),
    'base_dn' => env('LDAP_BASE_DN', 'DC=coren,DC=local'),
    'service_dn' => env('LDAP_SERVICE_DN'),
    'service_password' => env('LDAP_SERVICE_PASSWORD'),
    'user_filter' => env('LDAP_USER_FILTER', '(&(objectCategory=person)(objectClass=user)(sAMAccountName=%s))'),
    'required_description_contains' => env('LDAP_REQUIRED_DESCRIPTION_CONTAINS', 'DTI'),
];
