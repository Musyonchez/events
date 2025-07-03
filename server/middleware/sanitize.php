<?php

function sanitizeInput(array $data): array
{
    $sanitizedData = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $sanitizedData[$key] = sanitizeInput($value); // Recursively sanitize arrays
        } elseif (is_string($value)) {
            $sanitizedData[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        } else {
            $sanitizedData[$key] = $value;
        }
    }
    return $sanitizedData;
}
