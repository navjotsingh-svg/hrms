<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Validation\ValidationException;

class AttendanceNetworkService
{
    public function resolveClientIpFromRequest(\Illuminate\Http\Request $request): ?string
    {
        return $this->resolveClientIp(
            $request->header('X-Forwarded-For'),
            $request->ip() ?: $request->server('REMOTE_ADDR'),
            $request->header('X-Real-IP'),
            $request->header('CF-Connecting-IP'),
        );
    }

    public function resolveClientIp(
        ?string $forwardedFor,
        ?string $remoteAddress,
        ?string $realIp = null,
        ?string $cfConnectingIp = null,
    ): ?string {
        $candidates = [];

        foreach ([$cfConnectingIp, $realIp] as $headerIp) {
            if (is_string($headerIp) && trim($headerIp) !== '') {
                $candidates[] = trim($headerIp);
            }
        }

        if ($forwardedFor) {
            foreach (array_map('trim', explode(',', $forwardedFor)) as $part) {
                if ($part !== '') {
                    $candidates[] = $part;
                }
            }
        }

        if ($remoteAddress) {
            $candidates[] = trim($remoteAddress);
        }

        foreach ($candidates as $candidate) {
            $ipv4 = $this->normalizeToIpv4($candidate);

            if ($ipv4 !== null) {
                return $ipv4;
            }
        }

        return null;
    }

    /** @param  array<int, string|null>  $headerValues */
    public function resolveClientMac(array $headerValues, ?string $clientProvided = null): ?string
    {
        foreach ($headerValues as $value) {
            $normalized = $this->normalizeMacAddress($value);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $this->normalizeMacAddress($clientProvided);
    }

    public function normalizeMacAddress(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtoupper(trim($value));

        if ($value === '') {
            return null;
        }

        $value = str_replace('-', ':', $value);
        $value = preg_replace('/[^0-9A-F:]/', '', $value) ?? '';

        if (preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $value) === 1) {
            return $value;
        }

        $hex = preg_replace('/[^0-9A-F]/', '', $value) ?? '';

        if (strlen($hex) === 12) {
            return implode(':', str_split($hex, 2));
        }

        return null;
    }

    /** @return array<int, string> */
    public function allowedIpsForCompany(int $companyId): array
    {
        $raw = Company::query()->whereKey($companyId)->value('attendance_allowed_ips');

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded), fn (string $ip) => $ip !== ''));
        }

        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw) ?: [])));
    }

    public function assertIpAllowed(int $companyId, ?string $ipAddress): void
    {
        $allowedIps = $this->allowedIpsForCompany($companyId);

        if ($allowedIps === []) {
            return;
        }

        if (! $ipAddress) {
            throw ValidationException::withMessages([
                'punch' => ['Unable to verify your network address. Attendance must be marked from an approved office network.'],
            ]);
        }

        foreach ($allowedIps as $allowedIp) {
            if ($this->ipMatches($ipAddress, $allowedIp)) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'punch' => ["Attendance cannot be marked from this network ({$ipAddress}). Use an approved office IP address."],
        ]);
    }

    /** @param  array<int, string>  $ips */
    public function normalizeAllowedIps(array $ips): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($ip) => trim((string) $ip),
            $ips,
        ), fn (string $ip) => $ip !== '' && $this->isValidIp($ip))));
    }

    /** @param  array<int, string>  $ips */
    public function encodeAllowedIps(array $ips): ?string
    {
        $normalized = $this->normalizeAllowedIps($ips);

        return $normalized === [] ? null : json_encode($normalized);
    }

    private function ipMatches(string $clientIp, string $allowedIp): bool
    {
        if (strcasecmp($clientIp, $allowedIp) === 0) {
            return true;
        }

        if (str_contains($allowedIp, '/')) {
            return $this->ipInCidr($clientIp, $allowedIp);
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $maskBits] = array_pad(explode('/', $cidr, 2), 2, null);

        if (! $subnet || $maskBits === null || ! is_numeric($maskBits)) {
            return false;
        }

        $maskBits = (int) $maskBits;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $mask = -1 << (32 - $maskBits);

            return ($ipLong & $mask) === ($subnetLong & $mask);
        }

        return false;
    }

    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    private function normalizeToIpv4(string $ip): ?string
    {
        $ip = trim($ip);

        if ($ip === '') {
            return null;
        }

        if (str_starts_with($ip, '[') && str_contains($ip, ']')) {
            $ip = substr($ip, 1, strpos($ip, ']') - 1);
        }

        if (str_contains($ip, ':') && substr_count($ip, ':') === 1 && ! str_contains($ip, '::')) {
            [$possibleIp] = explode(':', $ip, 2);
            $ip = $possibleIp;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $ip;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return null;
        }

        $normalized = strtolower($ip);

        if ($normalized === '::1') {
            return '127.0.0.1';
        }

        if (str_starts_with($normalized, '::ffff:')) {
            $mappedIpv4 = substr($ip, 7);

            if (filter_var($mappedIpv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $mappedIpv4;
            }
        }

        return null;
    }
}
