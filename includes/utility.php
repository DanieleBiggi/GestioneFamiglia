<?php

/**
 * Utility functions for remote data retrieval.
 */
function Encrypt(string $string, string $key): string
{
    // Placeholder encryption using base64 encoding.
    // Replace with proper encryption as needed.
    return base64_encode($string);
}

class Utility
{
    public function getDati(string $SQLinv): array
    {
        $token = Encrypt(microtime(true) . rand(1000, 9999), 'test');
        $SQLen = Encrypt(htmlspecialchars_decode($SQLinv), 'test');
        $url = 'https://new.cosulich.it/approvazione_fatture/user_get_inaz_json.php?action=execute_query'
            . '&token=' . $token . '&SQL=' . $SQLen;

        $response = @file_get_contents($url);

        return json_decode($response, true) ?? [];
    }
}
