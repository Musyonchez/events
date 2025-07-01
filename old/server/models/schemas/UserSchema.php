<?php

// api/schemas/UserSchema.php

class UserSchema
{
    // Expected type definitions
    public static array $fields = [
        'id' => 'string',
        'full_name' => 'string',
        'email' => 'string',
        'student_id' => 'string',
        'password' => 'string',
        'avatar_url' => 'string',
    ];

    /**
     * Validate only the fields provided in $data.
     */
    public static function validate(array $data): void
    {
        foreach ($data as $key => $value) {
            if (! array_key_exists($key, self::$fields)) {
                throw new InvalidArgumentException("Unexpected field: $key");
            }

            $expectedType = self::$fields[$key];

            self::runExtraChecks($key, $value);

            if (! self::checkType($value, $expectedType)) {
                $actual = gettype($value);
                throw new InvalidArgumentException("Field '$key' must be of type $expectedType, $actual given");
            }

        }
    }

    /**
     * Validates that all defined fields are present and correct.
     */
    public static function validateStrict(array $data): void
    {
        foreach (self::$fields as $key => $expectedType) {
            if (! array_key_exists($key, $data)) {
                throw new InvalidArgumentException("Missing field: $key");
            }

            self::runExtraChecks($key, $data[$key]);

            if (! self::checkType($data[$key], $expectedType)) {
                $actual = gettype($data[$key]);
                throw new InvalidArgumentException("Field '$key' must be of type $expectedType, $actual given");
            }

        }
    }

    private static function checkType($value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'int' => is_int($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            'float' => is_float($value),
            default => false,
        };
    }

    /**
     * Custom rules for specific fields
     */
    private static function runExtraChecks(string $key, $value): void
    {
        switch ($key) {
            case 'email':
                if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException("Field 'email' must be a valid email address");
                }
                break;

            case 'id':
                if (! preg_match('/^[a-f0-9]{32}$/i', $value)) {
                    throw new InvalidArgumentException("Field 'id' must be a 32-character hex string");
                }
                break;

            case 'avatar_url':
                if (! is_string($value) || ! str_starts_with($value, 'https://')) {
                    throw new InvalidArgumentException("Field 'avatar_url' must start with 'https://'");
                }
                break;

            case 'student_id':
                if (! preg_match('/^\d{6}$/', strval($value))) {
                    throw new InvalidArgumentException("Field 'student_id' must be a 6-digit number");
                }
                break;

                // Add more custom rules here if needed
        }
    }
}
