<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The only request-derived data available to audit writers. User agents are
 * normalized to printable characters and capped at 512 bytes. The resulting
 * value is stored only as a SHA-256 fingerprint (71 bytes including prefix),
 * so arbitrary header content is never persisted. Header values resembling
 * credentials are discarded rather than fingerprinted.
 */
final readonly class AuditRequestContext
{
    private const USER_AGENT_MAX_LENGTH = 512;

    private function __construct(public ?string $ipAddress, public ?string $userAgent)
    {
    }

    public static function fromRequest(Request $request): self
    {
        $ip = $request->ip();
        $ip = is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
        $userAgent = $request->userAgent();

        if (! is_string($userAgent)) {
            return new self($ip, null);
        }

        $userAgent = preg_replace('/[^\x20-\x7E]/', '', $userAgent) ?? '';
        $userAgent = mb_substr(trim($userAgent), 0, self::USER_AGENT_MAX_LENGTH);
        if ($userAgent === '' || preg_match('/password|token|remember|api[_-]?key|credential|authorization|bearer|secret/i', $userAgent)) {
            $userAgent = null;
        }

        return new self($ip, $userAgent === null ? null : 'sha256:'.hash('sha256', $userAgent));
    }
}
