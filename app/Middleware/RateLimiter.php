<?php

declare(strict_types=1);

namespace App\Middleware;

class RateLimiter
{
    private string $cacheDir;

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? dirname(__DIR__, 2) . '/storage/cache';

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Check if the rate limit has been exceeded.
     *
     * @return array{allowed: bool, attempts: int, remaining: int, retryAfter: int|null}
     */
    public function checkRateLimit(string $key, int $maxAttempts = 5, int $decayMinutes = 15): array
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
     * Record a new attempt.
     */
    public function incrementAttempts(string $key): void
    {
        $data = $this->getAttemptData($key);
        $data['attempts'][] = time();
        $this->saveAttemptData($key, $data);
    }

    /**
     * Clear all attempts for a key.
     */
    public function clearAttempts(string $key): void
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
