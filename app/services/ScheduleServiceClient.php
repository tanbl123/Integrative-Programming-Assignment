<?php

/**
 * Client for consuming Collection Scheduling web services.
 * Author : Phang Jun Hong (2406646)
 * Module : User & Access Management
 */
class ScheduleServiceClient
{
    private ?string $lastError = null;
    private ?string $lastRequestId = null;

    public function hasOpenAssignments(int $cleanerId): bool
    {
        $this->lastError = null;
        $this->lastRequestId = 'REQ-SCH-' . time();

        $timeStamp = ifaTimestamp();

        $url = 'http://localhost' . BASE_URL . '/schedule-api/cleaner-open-assignments'
            . '?requestID=' . urlencode($this->lastRequestId)
            . '&cleanerId=' . urlencode((string) $cleanerId)
            . '&timeStamp=' . urlencode($timeStamp);

        $response = @file_get_contents($url);

        if ($response === false) {
            $this->lastError = 'Unable to connect to schedule web service.';
            return false;
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            $this->lastError = 'Invalid JSON response from schedule web service.';
            return false;
        }

        if (($data['status'] ?? '') !== 'S') {
            $this->lastError = (string) ($data['message'] ?? 'Schedule web service returned an error.');
            return false;
        }
        
        return ($data['hasOpenAssignments'] ?? false) === true;
    }
    
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getLastRequestId(): ?string
    {
        return $this->lastRequestId;
    }
}