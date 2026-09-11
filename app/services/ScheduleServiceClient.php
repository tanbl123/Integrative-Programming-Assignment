<?php

/**
 * Client for consuming Collection Scheduling web services.
 *
 * Author : Ong Kar Heng (2408830), Phang Jun Hong (2406646)
 * Module : Bin & Location Management, User & Access Management
 */
class ScheduleServiceClient {

    private const TIMEOUT = 4;

    private ?string $lastError = null;
    private ?string $lastRequestId = null;

    /**
     * Web service consumer for Bin module.
     * Calls Scheduling REST service to get one bin's schedule status.
     *
     * @return array|null decoded Scheduling response data, or null on failure
     */
    public function getBinScheduleStatus(int $binId): ?array {
        $this->lastError = null;
        $this->lastRequestId = $this->newRequestId('BIN-SCH');

        $url = $this->baseUrl() . '/schedule-api/bin-status/' . $binId . '?' . http_build_query([
                    'requestID' => $this->lastRequestId,
                    'timeStamp' => ifaTimestamp(),
        ]);

        $decoded = $this->requestJson($url);

        if ($decoded === null) {
            return null;
        }

        $ok = ($decoded['status'] ?? null) === 'S' || ($decoded['success'] ?? false) === true;

        if (!$ok) {
            $this->lastError = is_string($decoded['message'] ?? null) ? $decoded['message'] : 'The scheduling service rejected the request.';

            return null;
        }

        return is_array($decoded['data'] ?? null) ? $decoded['data'] : null;
    }

    /**
     * Web service consumer for User & Access Management module.
     * Checks whether a Cleaner still has open collection assignments.
     */
    public function hasOpenAssignments(int $cleanerId): bool {
        $this->lastError = null;
        $this->lastRequestId = $this->newRequestId('REQ-SCH');

        $url = $this->baseUrl() . '/schedule-api/cleaner-open-assignments?' . http_build_query([
                    'requestID' => $this->lastRequestId,
                    'cleanerId' => $cleanerId,
                    'timeStamp' => ifaTimestamp(),
        ]);

        $decoded = $this->requestJson($url);

        if ($decoded === null) {
            return false;
        }

        $ok = ($decoded['status'] ?? null) === 'S' || ($decoded['success'] ?? false) === true;

        if (!$ok) {
            $this->lastError = is_string($decoded['message'] ?? null) ? $decoded['message'] : 'The scheduling service returned an error.';

            return false;
        }

        return ($decoded['hasOpenAssignments'] ?? false) === true;
    }

    public function getLastError(): ?string {
        return $this->lastError;
    }

    public function getLastRequestId(): ?string {
        return $this->lastRequestId;
    }

    /**
     * Sends the request and decodes JSON response.
     */
    private function requestJson(string $url): ?array {
        /*
         * Release PHP session lock before calling the same localhost app.
         * This prevents the internal web service request from hanging.
         */
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

        return $decoded;
    }

    /**
     * Sends GET request using cURL when available.
     */
    private function send(string $url): ?string {
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
                return null;
            }

            return (string) $body;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT,
                'ignore_errors' => true,
                'header' => implode("\r\n", array_filter([
                    'Accept: application/json',
                    $cookieHeader === null ? null : 'Cookie: ' . $cookieHeader,
                ])),
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            $this->lastError = 'Could not reach the scheduling service.';
            return null;
        }

        return $body;
    }

    /**
     * Forwards current login session to the internal web service.
     */
    private function sessionCookieHeader(): ?string {
        $name = session_name();
        $id = session_id();

        return is_string($name) && is_string($id) && $id !== '' ? $name . '=' . $id : null;
    }

    /**
     * Builds localhost project base URL.
     */
    private function baseUrl(): string {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host . BASE_URL;
    }

    /**
     * Generates Interface Agreement request ID.
     */
    private function newRequestId(string $prefix): string {
        return $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    }
}
