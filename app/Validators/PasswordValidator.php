<?php

declare(strict_types=1);

namespace App\Validators;

class PasswordValidator
{
    /**
     * Validate a password against all rules.
     *
     * @return array<string> Array of error messages (empty if valid)
     */
    public static function validate(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one digit.';
        }

        if (!preg_match('/[!@#$%^&*()\-_=+\[\]{}|;:\',.<>?\/`~]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }

        return $errors;
    }

    /**
     * Check if a password meets all validation rules.
     */
    public static function isValid(string $password): bool
    {
        return empty(self::validate($password));
    }

    /**
     * Calculate a password strength score from 0 to 5.
     */
    public static function getStrengthScore(string $password): int
    {
        $score = 0;

        if (strlen($password) >= 8) {
            $score++;
        }

        if (preg_match('/[A-Z]/', $password)) {
            $score++;
        }

        if (preg_match('/[a-z]/', $password)) {
            $score++;
        }

        if (preg_match('/[0-9]/', $password)) {
            $score++;
        }

        if (preg_match('/[!@#$%^&*()\-_=+\[\]{}|;:\',.<>?\/`~]/', $password)) {
            $score++;
        }

        return $score;
    }

    /**
     * Get a human-readable label for a strength score.
     */
    public static function getStrengthLabel(int $score): string
    {
        return match (true) {
            $score <= 1 => 'Very Weak',
            $score === 2 => 'Weak',
            $score === 3 => 'Fair',
            $score === 4 => 'Strong',
            default => 'Very Strong',
        };
    }
}
