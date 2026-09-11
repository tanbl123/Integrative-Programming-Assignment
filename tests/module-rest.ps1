# Complaint/schedule REST regression for local XAMPP.
param(
    [string]$BaseUrl = 'http://localhost/EcoCampus',
    [string]$Mysql = 'C:/xampp/mysql/bin/mysql.exe',
    [string]$Database = 'ecocampus'
)
# Local XAMPP integration regression. Creates synthetic records and removes only
# their exact returned IDs in finally; never changes seed bins or completes tasks.
$ErrorActionPreference = 'Stop'
Import-Module Microsoft.PowerShell.Utility
$complaintIds = [System.Collections.Generic.List[int]]::new()
$scheduleIds = [System.Collections.Generic.List[int]]::new()
$script:checks = 0
function Check($condition, [string]$message) {
    if (!$condition) { throw "FAIL: $message" }
    $script:checks++
    Write-Host "PASS: $message"
}
function Sql([string]$query) {
    $result = & $Mysql -u root --batch --skip-column-names $Database -e $query
    if ($LASTEXITCODE -ne 0) { throw 'Database verification or fixture cleanup failed.' }
    return $result
}
function Login([string]$email) {
    $session = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
    $page = Invoke-WebRequest "$BaseUrl/auth" -WebSession $session
    $token = [regex]::Match($page.Content, 'name="_token" value="([^"]+)"').Groups[1].Value
    Check ($token.Length -gt 0) "Login CSRF available for $email"
    $page = Invoke-WebRequest "$BaseUrl/auth/login" -Method Post -WebSession $session -Body @{email=$email;password='password123';_token=$token}
    Check ($page.Content -match 'Sign out') "Login accepted for $email"
    $token = [regex]::Match($page.Content, 'name="_token" value="([^"]+)"').Groups[1].Value
    return @{Session=$session;Token=$token}
}
function Api($identity, [string]$method, [string]$path, $body, [int]$expected = 200, [bool]$csrf = $true) {
    $identity.Session.Headers.Remove('X-CSRF-Token') | Out-Null
    $headers = @{}
    if ($csrf) { $headers['X-CSRF-Token'] = $identity.Token }
    $requestId = "TEST-$([guid]::NewGuid().ToString('N'))"
    $timeStamp = [uri]::EscapeDataString((Get-Date).ToString('yyyy-MM-dd HH:mm:ss'))
    $separator = if ($path.Contains('?')) { '&' } else { '?' }
    $trackedPath = "$path${separator}requestID=$requestId&requestId=$requestId&timeStamp=$timeStamp"
    $parameters = @{Uri="$BaseUrl/$trackedPath";Method=$method;WebSession=$identity.Session;Headers=$headers;SkipHttpErrorCheck=$true}
    if ($null -ne $body) { $parameters.Body = ConvertTo-Json $body -Depth 8 -Compress; $parameters.ContentType = 'application/json' }
    $reply = Invoke-WebRequest @parameters
    Check ([int]$reply.StatusCode -eq $expected) "$method $path returns $expected (actual $($reply.StatusCode))"
    $json = $reply.Content | ConvertFrom-Json
    Check ($json.success -eq ($expected -lt 400)) "$method $path envelope"
    return $json
}
try {
    $admin = Login 'admin@ecocampus.my'
    $reporter = Login 'siti@student.ecocampus.my'
    $cleaner = Login 'zaki@cleaner.ecocampus.my'
    $bins = Api $admin GET 'bin-api' $null
    $binId = [int]$bins.data[0].id
    Check ($binId -gt 0) 'Active bin fixture reference exists'
    $payload = @{bin_id=$binId;complaint_type='Other';description='Synthetic REST regression complaint; safe to remove.'}
    $null = Api $reporter POST 'complaint-api' $payload 403 $false
    $created = Api $reporter POST 'complaint-api' $payload 201
    $cid = [int]$created.data.complaint_id
    $complaintIds.Add($cid)
    Check ($cid -gt 0) 'Complaint API returns created ID'
    $null = Api $reporter GET "complaint-api/$cid" $null
    $updated = Api $reporter PATCH "complaint-api/$cid" @{description='Updated synthetic REST regression complaint description.'}
    Check ($updated.data.description -eq 'Updated synthetic REST regression complaint description.') 'Complaint details persist'
    $null = Api $cleaner GET "complaint-api/$cid" $null 403
    $null = Api $cleaner PATCH "complaint-api/$cid" @{description='Unauthorized mutation is forbidden.'} 403
    $null = Api $cleaner DELETE "complaint-api/$cid" $null 403
    $null = Api $reporter PATCH "complaint-api/$cid" @{complaint_status='Assigned'} 403
    $null = Api $admin PATCH "complaint-api/$cid" @{complaint_status='Assigned'}
    $null = Api $reporter DELETE "complaint-api/$cid" $null 422
    $null = Api $reporter PATCH "complaint-api/$cid" @{description='Assigned complaint should not change.'} 422
    $null = Api $admin PATCH "complaint-api/$cid" @{complaint_status='Resolved'}
    $null = Api $admin DELETE "complaint-api/$cid" $null
    $null = Api $reporter GET "complaint-api/$cid" $null 404
    $historyCount = [int](Sql "SELECT COUNT(*) FROM complaint_status_history WHERE complaint_id=$cid;")
    Check ($historyCount -eq 5) "Soft-deleted complaint retains five Observer history entries (actual $historyCount)"
    Check ([int](Sql "SELECT COUNT(*) FROM complaint_status_history WHERE complaint_id=$cid AND change_type='Withdrawn';") -eq 1) 'Complaint deletion records one Withdrawn Observer event'
    Check ([int](Sql "SELECT COUNT(*) FROM complaints WHERE complaint_id=$cid AND deleted_at IS NOT NULL;") -eq 1) 'Complaint row soft-deleted in MySQL'
    $cleaners = Api $admin GET 'user-api/cleaners' $null
    $cleanerId = [int]($cleaners.data | Where-Object { $_.email -eq 'zaki@cleaner.ecocampus.my' } | Select-Object -First 1).id
    if ($cleanerId -eq 0) { $cleanerId = [int](Sql "SELECT user_id FROM users WHERE email='zaki@cleaner.ecocampus.my';") }
    $scheduleData = @{schedule_date=(Get-Date).AddDays(2).ToString('yyyy-MM-dd');time_slot='09:00-12:00';strategy='Full Bins';cleaner_id=$cleanerId;notes='Synthetic REST regression schedule'}
    $null = Api $reporter POST 'schedule-api' $scheduleData 403
    $null = Api $reporter GET 'schedule-api' $null 403
    $null = Api $admin POST 'schedule-api' $scheduleData 403 $false
    $created = Api $admin POST 'schedule-api' $scheduleData 201
    $sid = [int]$created.data.schedule_id
    $scheduleIds.Add($sid)
    Check ($sid -gt 0) 'Schedule API returns created ID'
    $null = Api $admin GET "schedule-api/$sid" $null
    $updated = Api $admin PATCH "schedule-api/$sid" @{notes='Updated synthetic schedule';schedule_date=(Get-Date).AddDays(3).ToString('yyyy-MM-dd')}
    Check ($updated.data.notes -eq 'Updated synthetic schedule') 'Schedule PATCH persists notes'
    $null = Api $cleaner PATCH "schedule-api/$sid" @{notes='Forbidden'} 403
    $null = Api $cleaner DELETE "schedule-api/$sid" $null 403
    $null = Api $admin DELETE "schedule-api/$sid" $null 422
    $null = Api $admin PATCH "schedule-api/$sid" @{schedule_status='Cancelled'}
    $null = Api $admin DELETE "schedule-api/$sid" $null
    $null = Api $admin GET "schedule-api/$sid" $null 404
    Check ([int](Sql "SELECT COUNT(*) FROM collection_assignments WHERE schedule_id=$sid;") -gt 0) 'Deleted schedule retains assignments'
    Check ([int](Sql "SELECT COUNT(*) FROM collection_assignments WHERE schedule_id=$sid AND assignment_status<>'Skipped';") -eq 0) 'Cancellation skips every synthetic open assignment'
    Check ([int](Sql "SELECT COUNT(*) FROM collection_schedules WHERE schedule_id=$sid AND deleted_at IS NOT NULL;") -eq 1) 'Schedule row soft-deleted in MySQL'
    $null = Api $admin GET 'complaint-api/unresolved' $null
    $null = Api $cleaner GET 'schedule-api/mine' $null
    Write-Host "Completed $script:checks module REST checks."
} finally {
    foreach ($id in $scheduleIds) {
        Sql "START TRANSACTION; DELETE FROM collection_assignments WHERE schedule_id=$id; DELETE FROM collection_schedules WHERE schedule_id=$id; COMMIT;" | Out-Null
    }
    foreach ($id in $complaintIds) {
        Sql "START TRANSACTION; DELETE FROM complaint_status_history WHERE complaint_id=$id; DELETE FROM complaints WHERE complaint_id=$id; COMMIT;" | Out-Null
    }
    Write-Host 'Exact-ID synthetic fixtures cleaned up.'
}
