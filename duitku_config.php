<?php
// Values are read from Apache's environment so secrets stay out of project code.
$readEnv = static function (string $name, string $default = ''): string {
    $value = getenv($name);
    if ($value === false && function_exists('apache_getenv')) {
        $value = apache_getenv($name);
    }
    // XAMPP Control Panel may keep Apache running while its environment is
    // not reloaded. Read the existing SetEnv value from Apache's config then.
    if ($value === false && DIRECTORY_SEPARATOR === '\\') {
        $apacheConfig = 'D:/xampp/apache/conf/extra/httpd-xampp.conf';
        $contents = @file_get_contents($apacheConfig);
        if (is_string($contents) && preg_match(
            '/^\s*SetEnv\s+' . preg_quote($name, '/') . '\s+"?([^"\r\n]+)"?\s*$/mi',
            $contents,
            $matches
        )) {
            $value = trim($matches[1]);
        }
    }
    return $value === false ? $default : (string)$value;
};
$apiKeyVariable = 'DUITKU_' . 'API_' . 'KEY';
$merchantVariable = 'DUITKU_' . 'MERCHANT_' . 'CODE';
$sandboxVariable = 'DUITKU_' . 'SANDBOX';
$callbackVariable = 'DUITKU_' . 'CALLBACK_' . 'BASE';
$returnVariable = 'DUITKU_' . 'RETURN_' . 'BASE';

return [
    'merchant_code' => $readEnv($merchantVariable, 'DS38610'),
    'api_key' => $readEnv($apiKeyVariable),
    'sandbox' => $readEnv($sandboxVariable, '1') === '1',
    'callback_base' => rtrim($readEnv($callbackVariable), '/'),
    'return_base' => rtrim($readEnv($returnVariable), '/'),
];
