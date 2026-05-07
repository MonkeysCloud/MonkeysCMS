<?php

declare(strict_types=1);

namespace App\Cms\Auth;

use MonkeysLegion\DI\Attributes\Singleton;

/**
 * TwoFactorService — TOTP-based two-factor authentication.
 *
 * Implements RFC 6238 (TOTP) without external dependencies.
 * Compatible with Google Authenticator, Authy, and other TOTP apps.
 *
 * Secret encoding: Base32 (RFC 4648)
 * Hash: HMAC-SHA1
 * Period: 30 seconds
 * Digits: 6
 */
#[Singleton]
final class TwoFactorService
{
    private const int CODE_LENGTH = 6;
    private const int PERIOD = 30;
    private const int SECRET_LENGTH = 20; // 160 bits
    private const int WINDOW = 1; // Allow ±1 time step
    private const int RECOVERY_CODE_COUNT = 10;

    private const string BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // ── Secret Generation ──────────────────────────────────────────────

    /**
     * Generate a new TOTP secret (Base32-encoded, 160-bit).
     */
    public function generateSecret(): string
    {
        $bytes = random_bytes(self::SECRET_LENGTH);
        return $this->base32Encode($bytes);
    }

    /**
     * Generate recovery codes (10 single-use codes).
     *
     * @return list<string> Plain-text recovery codes (8 chars each)
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))); // 8-char hex
        }
        return $codes;
    }

    /**
     * Hash recovery codes for storage.
     *
     * @param list<string> $codes Plain-text codes
     * @return list<string> Hashed codes
     */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(
            static fn(string $code) => password_hash($code, PASSWORD_BCRYPT),
            $codes,
        );
    }

    // ── TOTP Verification ──────────────────────────────────────────────

    /**
     * Verify a TOTP code against a secret.
     *
     * Allows ±1 time window (90-second tolerance).
     */
    public function verify(string $secret, string $code): bool
    {
        $code = trim($code);
        if (strlen($code) !== self::CODE_LENGTH || !ctype_digit($code)) {
            return false;
        }

        $timeSlice = (int) floor(time() / self::PERIOD);
        $secretBytes = $this->base32Decode($secret);

        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            $calculatedCode = $this->generateCode($secretBytes, $timeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify a recovery code against stored hashes.
     *
     * @param list<string> $hashedCodes
     * @return int|false Index of the matched code (to remove it), or false
     */
    public function verifyRecoveryCode(string $inputCode, array $hashedCodes): int|false
    {
        $inputCode = strtoupper(trim($inputCode));

        foreach ($hashedCodes as $index => $hash) {
            if (password_verify($inputCode, $hash)) {
                return $index;
            }
        }

        return false;
    }

    // ── QR Code URL ────────────────────────────────────────────────────

    /**
     * Generate a Google Authenticator-compatible otpauth:// URI.
     */
    public function getOtpAuthUrl(string $secret, string $email, string $issuer = 'MonkeysCMS'): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($email);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            $label,
            $secret,
            rawurlencode($issuer),
            self::CODE_LENGTH,
            self::PERIOD,
        );
    }

    /**
     * Generate a QR code image URL using a public API (Google Charts).
     */
    public function getQrCodeUrl(string $otpAuthUrl, int $size = 200): string
    {
        return sprintf(
            'https://api.qrserver.com/v1/create-qr-code/?size=%dx%d&data=%s',
            $size,
            $size,
            urlencode($otpAuthUrl),
        );
    }

    // ── Current Code (for testing/display) ─────────────────────────────

    /**
     * Get the current TOTP code for a secret (useful for debugging).
     */
    public function getCurrentCode(string $secret): string
    {
        $timeSlice = (int) floor(time() / self::PERIOD);
        return $this->generateCode($this->base32Decode($secret), $timeSlice);
    }

    // ── Internal TOTP Implementation ───────────────────────────────────

    /**
     * Generate a TOTP code for a given time slice.
     */
    private function generateCode(string $secretBytes, int $timeSlice): string
    {
        // Pack time as 8-byte big-endian
        $time = pack('N*', 0, $timeSlice);

        // HMAC-SHA1
        $hmac = hash_hmac('sha1', $time, $secretBytes, true);

        // Dynamic truncation (RFC 4226 §5.4)
        $offset = ord($hmac[19]) & 0x0f;
        $binary = (
            ((ord($hmac[$offset]) & 0x7f) << 24) |
            ((ord($hmac[$offset + 1]) & 0xff) << 16) |
            ((ord($hmac[$offset + 2]) & 0xff) << 8) |
            (ord($hmac[$offset + 3]) & 0xff)
        );

        return str_pad(
            (string) ($binary % (10 ** self::CODE_LENGTH)),
            self::CODE_LENGTH,
            '0',
            STR_PAD_LEFT,
        );
    }

    // ── Base32 ─────────────────────────────────────────────────────────

    private function base32Encode(string $data): string
    {
        $encoded = '';
        $binary = '';

        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $chunks = str_split($binary, 5);

        foreach ($chunks as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= self::BASE32_CHARS[bindec($chunk)];
        }

        return $encoded;
    }

    private function base32Decode(string $data): string
    {
        $data = strtoupper(rtrim($data, '='));
        $binary = '';

        foreach (str_split($data) as $char) {
            $pos = strpos(self::BASE32_CHARS, $char);
            if ($pos === false) {
                continue;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        $chunks = str_split($binary, 8);

        foreach ($chunks as $chunk) {
            if (strlen($chunk) < 8) {
                break;
            }
            $decoded .= chr(bindec($chunk));
        }

        return $decoded;
    }
}
