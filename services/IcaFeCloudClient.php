<?php
declare(strict_types=1);

final class IcaFeCloudApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $codeName = 'API ERROR',
        public readonly int $httpStatus = 0
    ) {
        parent::__construct($message, $httpStatus);
    }
}

final class IcaFeCloudClient
{
    private string $baseUrl;
    private string $cafeId;
    private string $apiKey;
    private int $connectTimeout;
    private int $timeout;

    public function __construct(?array $config = null)
    {
        $config ??= require __DIR__ . '/../config/icafecloud.php';
        $this->baseUrl = rtrim((string)($config['base_url'] ?? 'https://api.icafecloud.com'), '/');
        $this->cafeId = trim((string)($config['cafe_id'] ?? ''));
        $this->apiKey = trim((string)($config['api_key'] ?? ''));
        $this->connectTimeout = max(1, (int)($config['connect_timeout'] ?? 5));
        $this->timeout = max($this->connectTimeout, (int)($config['timeout'] ?? 15));
    }

    public function cafeId(): string
    {
        return $this->cafeId;
    }

    public function pcList(): array
    {
        return $this->request('/api/v2/cafe/' . rawurlencode($this->cafeId) . '/pcList');
    }

    public function onlinePcList(): array
    {
        return $this->request('/api/v2/cafe/' . rawurlencode($this->cafeId) . '/onlinePcList');
    }

    public function pricePcGroups(): array
    {
        return $this->request('/api/v2/cafe/' . rawurlencode($this->cafeId) . '/pricePcGroups');
    }

    private function request(string $path): array
    {
        if ($this->apiKey === '') {
            throw new IcaFeCloudApiException('API key is not configured.', 'UNAUTHORIZED', 401);
        }
        if ($this->cafeId === '') {
            throw new IcaFeCloudApiException('Cafe ID is not configured.', 'API ERROR', 422);
        }
        if (!function_exists('curl_init')) {
            throw new IcaFeCloudApiException('PHP cURL extension is not enabled.', 'API ERROR', 0);
        }

        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            $message = $errno === CURLE_OPERATION_TIMEDOUT ? 'iCafeCloud request timed out.' : 'iCafeCloud network request failed.';
            throw new IcaFeCloudApiException($message, 'NETWORK ERROR', 0);
        }

        if ($status < 200 || $status >= 300) {
            throw new IcaFeCloudApiException($this->messageForStatus($status), $this->statusCodeName($status), $status);
        }

        $data = json_decode((string)$body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new IcaFeCloudApiException('iCafeCloud returned invalid JSON.', 'API ERROR', $status);
        }

        return $data;
    }

    private function statusCodeName(int $status): string
    {
        return match (true) {
            $status === 401 => 'UNAUTHORIZED',
            $status === 403 => 'API ACCESS DENIED',
            $status === 404 => 'API ERROR',
            $status === 429 => 'RATE LIMITED',
            $status >= 500 => 'API ERROR',
            default => 'API ERROR',
        };
    }

    private function messageForStatus(int $status): string
    {
        return match (true) {
            $status === 401 => 'Authentication failed.',
            $status === 403 => 'API access denied.',
            $status === 404 => 'Cafe or endpoint not found.',
            $status === 429 => 'iCafeCloud rate limit reached.',
            $status >= 500 => 'iCafeCloud API unavailable.',
            default => 'iCafeCloud request failed.',
        };
    }
}
