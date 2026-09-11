<?php
/**
 * Web service CONSUMER: calls Scheduling's REST service for one bin.
 *
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management - Web Service Technologies
 *
 * The Bin module uses this HTTP client instead of reading Scheduling's tables.
 * It sends the Interface Agreement tracking fields and forwards the current
 * session cookie so the provider can apply its own access-control checks.
 */
class ScheduleServiceClient
{
    private const TIMEOUT = 4;

    private ?string $lastError = null;
    private ?string $lastRequestId = null;

    /** @return array|null decoded Scheduling response data, or null on failure */
    public function getBinScheduleStatus(int $binId): ?array
    {
        $this->lastError = null;
        $this->lastRequestId = $this->newRequestId();

        $url = $this->baseUrl() . '/schedule-api/bin-status/' . $binId . '?' . http_build_query([
            'requestID' => $this->lastRequestId,
            'timeStamp' => ifaTimestamp(),
        ]);

        // Release PHP's session lock before this request calls the same app.
        $sessionWasOpen = session_status() === PHP_SESSION_ACTIVE;
        if ($sessionWasOpen) {
            session_write_close();
        }
        try {
            $raw = $this->send($url);
        } finally {
            if ($sessionWasOpen && !headers_sent()) {
                session_start();
            }
        }

        if ($raw === null) {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $this->lastError = 'The scheduling service returned a malformed response.';
            return null;
        }
        $ok = ($decoded['status'] ?? null) === 'S' || ($decoded['success'] ?? false) === true;
        if (!$ok) {
            $this->lastError = is_string($decoded['message'] ?? null)
                ? $decoded['message']
                : 'The scheduling service rejected the request.';
            return null;
        }
        return is_array($decoded['data'] ?? null) ? $decoded['data'] : null;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getLastRequestId(): ?string
    {
        return $this->lastRequestId;
    }

    private function send(string $url): ?string
    {
        $cookieHeader = $this->sessionCookieHeader();
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => array_filter([
                    'Accept: application/json',
                    $cookieHeader === null ? null : 'Cookie: ' . $cookieHeader,
                ]),
            ]);
            $body = curl_exec($handle);
            $httpStatus = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $error = curl_error($handle);
            curl_close($handle);

            if ($body === false) {
                $this->lastError = 'Could not reach the scheduling service: ' . $error;
                return null;
            }
            if ($httpStatus >= 400) {
                $this->lastError = 'The scheduling service replied with HTTP ' . $httpStatus . '.';
            }
            return (string) $body;
        }

        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => self::TIMEOUT,
            'ignore_errors' => true,
            'header' => implode("\r\n", array_filter([
                'Accept: application/json',
                $cookieHeader === null ? null : 'Cookie: ' . $cookieHeader,
            ])),
        ]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            $this->lastError = 'Could not reach the scheduling service.';
            return null;
        }
        return $body;
    }

    private function sessionCookieHeader(): ?string
    {
        $name = session_name();
        $id = session_id();
        return is_string($name) && is_string($id) && $id !== '' ? $name . '=' . $id : null;
    }

    private function baseUrl(): string
    {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . BASE_URL;
    }

    private function newRequestId(): string
    {
        return 'BIN-SCH-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    }
}
