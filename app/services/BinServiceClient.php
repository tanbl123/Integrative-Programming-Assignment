<?php
/**
 * Web service CONSUMER: calls the Bin & Location module's REST service.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management - Web Service Technologies
 *
 * The Complaint module must not read another module's tables directly. When it
 * needs authoritative bin details it asks the Bin module's published service
 * over HTTP, exactly as an external system would:
 *
 *     GET /EcoCampus/bin-api/show/{binId}?requestId=...&timeStamp=...
 *
 * This is the CONSUMPTION half of the Interface Agreement. The matching
 * EXPOSURE half lives in ComplaintApiController.
 *
 * Two implementation details matter and are easy to get wrong:
 *
 * 1. SESSION DEADLOCK. PHP holds an exclusive lock on the session file for the
 *    whole request. Calling our own server over HTTP while holding that lock
 *    would make the second request block forever waiting for it. The session is
 *    therefore closed for writing before the call and reopened afterwards.
 *
 * 2. FAILING SOFT. The Bin service being unavailable must never stop a reporter
 *    from viewing their complaint. Every failure returns null and records a
 *    reason, and the page falls back to locally held data.
 */
class BinServiceClient
{
    /** Seconds before the call is abandoned. */
    private const TIMEOUT = 4;

    private ?string $lastError = null;
    private ?string $lastRequestId = null;

    /**
     * Fetches bin details from the Bin module's web service.
     *
     * @return array|null decoded 'data' payload, or null when unavailable
     */
    public function getBinInfo(int $binId): ?array
    {
        $this->lastError     = null;
        $this->lastRequestId = $this->newRequestId();

        $url = $this->baseUrl() . '/bin-api/show/' . $binId . '?' . http_build_query([
            // IFA mandatory request fields.
            'requestId' => $this->lastRequestId,
            'timeStamp' => ifaTimestamp(),
        ]);

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
            $this->lastError = 'The bin service returned a malformed response.';
            return null;
        }

        // Accept either the IFA status field or the legacy success flag.
        $ok = ($decoded['status'] ?? null) === 'S' || ($decoded['success'] ?? false) === true;
        if (!$ok) {
            $this->lastError = is_string($decoded['message'] ?? null)
                ? $decoded['message']
                : 'The bin service rejected the request.';
            return null;
        }

        return is_array($decoded['data'] ?? null) ? $decoded['data'] : null;
    }

    /** Reason the last call failed, for display or logging. */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /** The requestId sent on the last call, so responses can be traced. */
    public function getLastRequestId(): ?string
    {
        return $this->lastRequestId;
    }

    // ------------------------------------------------------------------

    /**
     * Performs the GET, forwarding this user's session cookie so the Bin
     * service can authorise the caller. Uses cURL when available and falls
     * back to a stream context otherwise.
     */
    private function send(string $url): ?string
    {
        $cookieHeader = $this->sessionCookieHeader();

        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER     => array_filter([
                    'Accept: application/json',
                    $cookieHeader === null ? null : 'Cookie: ' . $cookieHeader,
                ]),
            ]);

            $body   = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $error  = curl_error($handle);
            curl_close($handle);

            if ($body === false) {
                $this->lastError = 'Could not reach the bin service: ' . $error;
                return null;
            }
            if ($status >= 400) {
                $this->lastError = 'The bin service replied with HTTP ' . $status . '.';
                return (string) $body;
            }
            return (string) $body;
        }

        $context = stream_context_create(['http' => [
            'method'        => 'GET',
            'timeout'       => self::TIMEOUT,
            'ignore_errors' => true,
            'header'        => implode("\r\n", array_filter([
                'Accept: application/json',
                $cookieHeader === null ? null : 'Cookie: ' . $cookieHeader,
            ])),
        ]]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            $this->lastError = 'Could not reach the bin service.';
            return null;
        }
        return $body;
    }

    /** Rebuilds this request's session cookie so the callee sees the same user. */
    private function sessionCookieHeader(): ?string
    {
        $name = session_name();
        $id   = session_id();

        if (!is_string($name) || !is_string($id) || $id === '') {
            return null;
        }
        return $name . '=' . $id;
    }

    /** Scheme and host of this installation, e.g. http://localhost/EcoCampus */
    private function baseUrl(): string
    {
        $https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host . BASE_URL;
    }

    /** Unique id per request, as the Interface Agreement requires. */
    private function newRequestId(): string
    {
        return 'CMP-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    }
}
