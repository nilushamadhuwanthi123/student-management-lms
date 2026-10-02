<?php

declare(strict_types=1);

namespace App\Middleware;

use Database;
use PDO;

class RateLimiter
{
    private string $cacheDir;
    private ?PDO $pdo = null;
    private bool $useDatabase = false;

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? dirname(__DIR__, 2) . '/storage/cache';

        // Try to use database, fall back to file-based storage
        try {
            $this->pdo = Database::connect();
            $this->ensureTableExists();
            $this->useDatabase = true;
        } catch (\Throwable $e) {
            // Database unavailable, use file-based fallback
            $this->useDatabase = false;
        }

        if (!$this->useDatabase && !is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Ensure the login_attempts table exists.
     */
    private function ensureTableExists(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS login_attempts (
                `key` VARCHAR(100) NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                first_attempt_at DATETIME NOT NULL,
                last_attempt_at DATETIME NOT NULL,
                PRIMARY KEY (`key`)
            )'
        );
    }

    /**
     * Check if the rate limit has been exceeded.
     *
     * @return array{allowed: bool, attempts: int, remaining: int, retryAfter: int|null}
     */
    public function checkRateLimit(string $key, int $maxAttempts = 5, int $decayMinutes = 15): array
    {
        if ($this->useDatabase) {
            return $this->checkRateLimitDb($key, $maxAttempts, $decayMinutes);
        }

        return $this->checkRateLimitFile($key, $maxAttempts, $decayMinutes);
    }

    /**
     * Record a new attempt.
     */
    public function incrementAttempts(string $key): void
    {
        if ($this->useDatabase) {
            $this->incrementAttemptsDb($key);
            return;
        }

        $this->incrementAttemptsFile($key);
    }

    /**
     * Clear all attempts for a key.
     */
    public function clearAttempts(string $key): void
    {
        if ($this->useDatabase) {
            $this->clearAttemptsDb($key);
            return;
        }

        $this->clearAttemptsFile($key);
    }

    // ---------------------------------------------------------------
    // Database-based methods
    // ---------------------------------------------------------------

    /**
     * Check rate limit using database storage.
     *
     * @return array{allowed: bool, attempts: int, remaining: int, retryAfter: int|null}
     */
    private function checkRateLimitDb(string $key, int $maxAttempts, int $decayMinutes): array
    {
        $now = time();
        $windowStart = date('Y-m-d H:i:s', $now - ($decayMinutes * 60));

        $stmt = $this->pdo->prepare(
            'SELECT attempts, first_attempt_at FROM login_attempts WHERE `key` = :key'
        );
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [
                'allowed' => true,
                'attempts' => 0,
                'remaining' => $maxAttempts,
                'retryAfter' => null,
            ];
        }

        // If first attempt is older than the decay window, reset
        if ($row['first_attempt_at'] < $windowStart) {
            $deleteStmt = $this->pdo->prepare('DELETE FROM login_attempts WHERE `key` = :key');
            $deleteStmt->execute(['key' => $key]);

            return [
                'allowed' => true,
                'attempts' => 0,
                'remaining' => $maxAttempts,
                'retryAfter' => null,
            ];
        }

        $currentAttempts = (int) $row['attempts'];
        $allowed = $currentAttempts < $maxAttempts;

        $retryAfter = null;
        if (!$allowed) {
            $firstAttemptTimestamp = strtotime($row['first_attempt_at']);
            $retryAfter = ($firstAttemptTimestamp + ($decayMinutes * 60)) - $now;
            $retryAfter = max(0, $retryAfter);
        }

        return [
            'allowed' => $allowed,
            'attempts' => $currentAttempts,
            'remaining' => max(0, $maxAttempts - $currentAttempts),
            'retryAfter' => $retryAfter,
        ];
    }

    /**
     * Increment attempts using database storage.
     */
    private function incrementAttemptsDb(string $key): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (`key`, attempts, first_attempt_at, last_attempt_at)
             VALUES (:key, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt_at = NOW()'
        );
        $stmt->execute(['key' => $key]);
    }

    /**
     * Clear attempts using database storage.
     */
    private function clearAttemptsDb(string $key): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM login_attempts WHERE `key` = :key');
        $stmt->execute(['key' => $key]);
    }

    // ---------------------------------------------------------------
    // File-based methods (fallback)
    // ---------------------------------------------------------------

    /**
     * Check rate limit using file-based storage.
     *
     * @return array{allowed: bool, attempts: int, remaining: int, retryAfter: int|null}
     */
    private function checkRateLimitFile(string $key, int $maxAttempts, int $decayMinutes): array
    {
        $data = $this->getAttemptData($key);
        $now = time();

        // Clean up expired attempts
        $windowStart = $now - ($decayMinutes * 60);
        $data['attempts'] = array_values(
            array_filter($data['attempts'], fn(int $timestamp) => $timestamp > $windowStart)
        );

        $currentAttempts = count($data['attempts']);
        $allowed = $currentAttempts < $maxAttempts;

        $retryAfter = null;
        if (!$allowed && !empty($data['attempts'])) {
            $oldestAttempt = min($data['attempts']);
            $retryAfter = ($oldestAttempt + ($decayMinutes * 60)) - $now;
            $retryAfter = max(0, $retryAfter);
        }

        // Save cleaned data back
        $this->saveAttemptData($key, $data);

        return [
            'allowed' => $allowed,
            'attempts' => $currentAttempts,
            'remaining' => max(0, $maxAttempts - $currentAttempts),
            'retryAfter' => $retryAfter,
        ];
    }

    /**
     * Increment attempts using file-based storage.
     */
    private function incrementAttemptsFile(string $key): void
    {
        $data = $this->getAttemptData($key);
        $data['attempts'][] = time();
        $this->saveAttemptData($key, $data);
    }

    /**
     * Clear attempts using file-based storage.
     */
    private function clearAttemptsFile(string $key): void
    {
        $filePath = $this->getFilePath($key);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Get the file path for a rate limit key.
     */
    private function getFilePath(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        return $this->cacheDir . '/ratelimit_' . $safeKey . '.json';
    }

    /**
     * Read attempt data from file.
     *
     * @return array{attempts: array<int>}
     */
    private function getAttemptData(string $key): array
    {
        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return ['attempts' => []];
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return ['attempts' => []];
        }

        $data = json_decode($contents, true);
        if (!is_array($data) || !isset($data['attempts'])) {
            return ['attempts' => []];
        }

        return $data;
    }

    /**
     * Save attempt data to file.
     *
     * @param array{attempts: array<int>} $data
     */
    private function saveAttemptData(string $key, array $data): void
    {
        $filePath = $this->getFilePath($key);
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        file_put_contents($filePath, $json, LOCK_EX);
    }
}
