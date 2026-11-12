<?php

declare(strict_types=1);

namespace App\Services;

use Setting;

/**
 * Sending a text message through a Sri Lankan SMS gateway.
 *
 * The portal's password reset works by sending a one-time code to the
 * phone number already on the account. Until this existed there was no
 * way to actually deliver it: the code was queued as a WhatsApp message
 * for the office to tap and send by hand, which is fine at ten students
 * and hopeless at a thousand. With a gateway configured the code goes
 * out by itself, and the office queue stays as the fallback for when it
 * is not configured or the gateway is down.
 *
 * Nothing here is required. An install with no gateway configured keeps
 * behaving exactly as it did -- isConfigured() answers false and the
 * caller falls back -- so this cannot break a site that does not use it.
 *
 * The office enters its own credentials under Settings, so no key is
 * ever committed to the repository.
 *
 * Deliberately careful about two things:
 *  - It never throws. A failed send must not turn into a 500 on the
 *    "I forgot my password" page, and must not tell the person asking
 *    whether the account existed.
 *  - It never writes a message body to a log. The body carries the
 *    one-time code, so only the provider, the outcome and the last few
 *    digits of the number are ever recorded.
 */
final class SmsService
{
    /** Gateways this knows how to talk to. */
    public const PROVIDER_NOTIFY_LK = 'notify_lk';
    public const PROVIDER_TEXT_LK   = 'text_lk';
    public const PROVIDER_CUSTOM    = 'custom';

    public const PROVIDERS = [
        self::PROVIDER_NOTIFY_LK => 'Notify.lk',
        self::PROVIDER_TEXT_LK   => 'Text.lk',
        self::PROVIDER_CUSTOM    => 'Other gateway (URL template)',
    ];

    /** Setting keys, all stored in system_settings. */
    public const KEY_ENABLED   = 'sms_enabled';
    public const KEY_PROVIDER  = 'sms_provider';
    public const KEY_API_KEY   = 'sms_api_key';
    public const KEY_USER_ID   = 'sms_user_id';
    public const KEY_SENDER_ID = 'sms_sender_id';
    public const KEY_CUSTOM_URL = 'sms_custom_url';

    /** Long enough for a slow gateway, short enough not to hang a page. */
    private const TIMEOUT_SECONDS = 10;

    private Setting $settings;

    public function __construct(?Setting $settings = null)
    {
        $this->settings = $settings ?? new Setting();
    }

    /** @return array<string, string> the gateway settings, with '' for anything unset */
    public function config(): array
    {
        $keys = [
            self::KEY_ENABLED, self::KEY_PROVIDER, self::KEY_API_KEY,
            self::KEY_USER_ID, self::KEY_SENDER_ID, self::KEY_CUSTOM_URL,
        ];
        $out = [];
        foreach ($keys as $key) {
            try {
                $out[$key] = (string) $this->settings->get($key, '');
            } catch (\Throwable $e) {
                $out[$key] = '';
            }
        }
        return $out;
    }

    /**
     * Whether a send would actually be attempted: switched on, a known
     * provider, and the fields that provider needs filled in.
     */
    public function isConfigured(): bool
    {
        $c = $this->config();

        if ($c[self::KEY_ENABLED] !== '1') {
            return false;
        }
        if (!isset(self::PROVIDERS[$c[self::KEY_PROVIDER]])) {
            return false;
        }

        return match ($c[self::KEY_PROVIDER]) {
            self::PROVIDER_NOTIFY_LK => $c[self::KEY_API_KEY] !== '' && $c[self::KEY_USER_ID] !== '',
            self::PROVIDER_TEXT_LK   => $c[self::KEY_API_KEY] !== '',
            self::PROVIDER_CUSTOM    => str_contains($c[self::KEY_CUSTOM_URL], '{to}')
                                        && str_contains($c[self::KEY_CUSTOM_URL], '{message}'),
            default => false,
        };
    }

    /**
     * Send $body to $phone. Answers true only when the gateway accepted
     * it; false for every other outcome, including not being configured.
     * Never throws, and never records $body.
     */
    public function send(?string $phone, string $body): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $to = WhatsAppService::normalisePhone($phone);
        if ($to === null || trim($body) === '') {
            return false;
        }

        $c = $this->config();

        try {
            [$url, $post, $headers] = $this->request($c, $to, $body);
        } catch (\Throwable $e) {
            error_log('SMS request could not be built: ' . $e->getMessage());
            return false;
        }

        $ok = $this->http($url, $post, $headers);
        $this->note($c[self::KEY_PROVIDER], $to, $ok);

        return $ok;
    }

    /**
     * The URL, POST fields and headers for one message.
     *
     * @param array<string, string> $c
     * @return array{0: string, 1: array<string, string>|null, 2: array<int, string>}
     */
    private function request(array $c, string $to, string $body): array
    {
        return match ($c[self::KEY_PROVIDER]) {
            // https://app.notify.lk/api/v1/send -- plain form fields.
            self::PROVIDER_NOTIFY_LK => [
                'https://app.notify.lk/api/v1/send',
                array_filter([
                    'user_id'   => $c[self::KEY_USER_ID],
                    'api_key'   => $c[self::KEY_API_KEY],
                    'sender_id' => $c[self::KEY_SENDER_ID] !== '' ? $c[self::KEY_SENDER_ID] : 'NotifyDEMO',
                    'to'        => $to,
                    'message'   => $body,
                ], static fn ($v): bool => $v !== ''),
                [],
            ],

            // https://app.text.lk/api/v3/sms/send -- bearer token.
            self::PROVIDER_TEXT_LK => [
                'https://app.text.lk/api/v3/sms/send',
                array_filter([
                    'recipient' => $to,
                    'sender_id' => $c[self::KEY_SENDER_ID],
                    'type'      => 'plain',
                    'message'   => $body,
                ], static fn ($v): bool => $v !== ''),
                [
                    'Authorization: Bearer ' . $c[self::KEY_API_KEY],
                    'Accept: application/json',
                ],
            ],

            // Anything else: the office pastes a URL template using
            // {to}, {message} and optionally {api_key} and {sender_id}.
            self::PROVIDER_CUSTOM => [
                strtr($c[self::KEY_CUSTOM_URL], [
                    '{to}'        => rawurlencode($to),
                    '{message}'   => rawurlencode($body),
                    '{api_key}'   => rawurlencode($c[self::KEY_API_KEY]),
                    '{sender_id}' => rawurlencode($c[self::KEY_SENDER_ID]),
                ]),
                null,
                [],
            ],

            default => throw new \RuntimeException('Unknown SMS provider.'),
        };
    }

    /**
     * One HTTPS call. POST when there are fields, GET otherwise. A 2xx
     * is a success and everything else is not; the body is not parsed,
     * because each gateway words its own differently and the fallback
     * is the same either way.
     *
     * @param array<string, string>|null $post
     * @param array<int, string> $headers
     */
    private function http(string $url, ?array $post, array $headers): bool
    {
        if (!preg_match('#^https://#i', $url)) {
            error_log('SMS gateway URL must be https.');
            return false;
        }

        if (!function_exists('curl_init')) {
            error_log('SMS gateway needs the curl extension.');
            return false;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '') {
            error_log('SMS gateway unreachable: ' . $error);
            return false;
        }

        return $status >= 200 && $status < 300;
    }

    /** Record the outcome without recording the message or the whole number. */
    private function note(string $provider, string $to, bool $ok): void
    {
        error_log(sprintf(
            'SMS %s via %s to ...%s',
            $ok ? 'sent' : 'failed',
            $provider,
            substr($to, -4)
        ));
    }
}
