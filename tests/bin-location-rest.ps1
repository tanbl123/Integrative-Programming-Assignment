# Local XAMPP integration test; fixtures are removed by exact IDs.
param([string]$BaseUrl = 'http://localhost/EcoCampus', [string]$Mysql = 'C:\xampp\mysql\bin\mysql.exe')
$ErrorActionPreference = 'Stop'
$checks = 0
function Check($condition, $message) {
    if (-not $condition) { throw $message }
    $script:checks++
}
function Login($email) {
    $page = Invoke-WebRequest "$BaseUrl/auth" -SessionVariable session
    $token = [regex]::Match($page.Content, 'name="_token" value="([^"]+)"').Groups[1].Value
    $result = Invoke-WebRequest "$BaseUrl/auth/login" -WebSession $session -Method Post -Body @{email=$email;password='password123';_token=$token} -SkipHttpErrorCheck
    Check ($result.StatusCode -eq 200) "Login $email failed"
    return @{Session=$session;Token=$token}
}
function Api($account, $method, $path, $body, $expected=200, [bool]$csrf=$true) {
    $account.Session.Headers.Remove('X-CSRF-Token') | Out-Null
    $requestId = "TEST-$([guid]::NewGuid().ToString('N'))"
    $timeStamp = [uri]::EscapeDataString((Get-Date).ToString('yyyy-MM-dd HH:mm:ss'))
    $separator = if ($path.Contains('?')) { '&' } else { '?' }
    $trackedPath = "$path${separator}requestID=$requestId&timeStamp=$timeStamp"
    $arguments = @{Uri="$BaseUrl/$trackedPath";WebSession=$account.Session;Method=$method;SkipHttpErrorCheck=$true}
    if ($csrf) { $arguments.Headers=@{'X-CSRF-Token'=$account.Token} }
    if ($null -ne $body) { $arguments.ContentType='application/json';$arguments.Body=ConvertTo-Json -InputObject $body -Compress }
    $result=Invoke-WebRequest @arguments
    Check ($result.StatusCode -eq $expected) "$method $path expected $expected got $($result.StatusCode): $($result.Content)"
    Check ($result.Headers.'Content-Type' -match 'application/json') 'API did not return JSON'
    $json = $result.Content | ConvertFrom-Json
    $expectedStatus = if ($expected -ge 500) { 'E' } elseif ($expected -ge 400) { 'F' } else { 'S' }
    Check ($json.status -eq $expectedStatus) "$method $path returned IFA status '$($json.status)' instead of '$expectedStatus'"
    Check ($json.requestID -eq $requestId) "$method $path returned requestID '$($json.requestID)' instead of '$requestId'"
    Check (-not [string]::IsNullOrWhiteSpace($json.timeStamp)) "$method $path omitted timeStamp"
    return $json
}
$admin=Login 'admin@ecocampus.my'
$reporter=Login 'siti@student.ecocampus.my'
$suffix=[guid]::NewGuid().ToString('N').Substring(0,10).ToUpperInvariant()
$locationId=$null; $binId=$null
try {
    $missingTracking=Invoke-WebRequest "$BaseUrl/bin-api" -WebSession $admin.Session -SkipHttpErrorCheck
    Check ($missingTracking.StatusCode -eq 422) 'Bin API accepted a request without requestID or timeStamp'
    $missingTrackingJson=$missingTracking.Content | ConvertFrom-Json
    Check ($missingTrackingJson.status -eq 'F') 'Missing tracking fields did not return IFA failure status'
    Check (-not [string]::IsNullOrWhiteSpace($missingTrackingJson.timeStamp)) 'IFA failure omitted response timeStamp'
    $timestampOnly=[uri]::EscapeDataString((Get-Date).ToString('yyyy-MM-dd HH:mm:ss'))
    $timestampReply=Invoke-WebRequest "$BaseUrl/bin-api?timeStamp=$timestampOnly" -WebSession $admin.Session -SkipHttpErrorCheck
    $timestampJson=$timestampReply.Content | ConvertFrom-Json
    Check ($timestampReply.StatusCode -eq 200 -and $timestampJson.status -eq 'S') 'Bin API rejected a timestamp-only tracked request'
    Check (-not [string]::IsNullOrWhiteSpace($timestampJson.requestID)) 'Timestamp-only request did not receive a generated requestID'
    $null=Api $admin POST 'location-api' @{location_name="BAD-PAIR-$suffix";building_name='Block C';floor_no='Ground';latitude=3.215118} 422
    $null=Api $admin POST 'location-api' @{location_name="BAD-RANGE-$suffix";building_name='Block C';floor_no='Ground';latitude=91;longitude=101.728345} 422
    $location=Api $admin POST 'location-api' @{location_name="REST-$suffix";building_name='Block C';floor_no='Ground';description='Disposable integration fixture';latitude=3.215118;longitude=101.728345} 201
    $locationId=[int]$location.data.location_id
    $read=Api $admin GET "location-api/$locationId" $null
    Check ($read.data.name -eq "REST-$suffix") 'Location read mismatch'
    Check ([Math]::Abs([double]$read.data.latitude - 3.215118) -lt 0.0000001) 'Location latitude was not returned'
    Check ([Math]::Abs([double]$read.data.longitude - 101.728345) -lt 0.0000001) 'Location longitude was not returned'
    $patched=Api $admin PATCH "location-api/$locationId" @{description='Updated through REST'}
    Check ($patched.data.description -eq 'Updated through REST') 'Location PATCH failed'
    Check ([Math]::Abs([double]$patched.data.latitude - 3.215118) -lt 0.0000001) 'Location PATCH cleared its coordinates'
    $null=Api $admin PUT "location-api/$locationId" @{location_name="REST-$suffix";building_name='Block D';floor_no='First';description='Replaced';latitude=3.215218;longitude=101.728445}
    $locationPage=Invoke-WebRequest "$BaseUrl/location?q=REST-$suffix" -WebSession $admin.Session
    Check ($locationPage.Content -match 'id="campusLocationMap"') 'Location directory map is missing'
    Check ($locationPage.Content -match 'OpenStreetMap') 'Location directory does not identify the map'
    Check ($locationPage.Content -match ('data-location-id="{0}"' -f $locationId)) 'Mapped location card is missing'
    Check ($locationPage.Content -match 'Show on map') 'Location card map action is missing'
    $locationForm=Invoke-WebRequest "$BaseUrl/location/edit/$locationId" -WebSession $admin.Session
    Check ($locationForm.Content -match 'id="locationPickerMap"') 'Location coordinate picker is missing'
    Check ($locationForm.Content -match 'name="latitude"') 'Location latitude field is missing'
    $bin=Api $admin POST 'bin-api' @{bin_code="TEST-$suffix";location_id=$locationId;category_id=1;capacity_litre=100;is_active=1} 201
    $binId=[int]$bin.data.id
    $null=Api $admin GET "bin-api/$binId" $null
    $scheduleStatus=Api $admin GET "schedule-api/bin-status/$binId" $null
    Check ($scheduleStatus.data.binId -eq $binId) 'Scheduling service returned the wrong bin'
    Check ($null -ne $scheduleStatus.data.hasOpenAssignment) 'Scheduling service omitted assignment status'
    $binPage=Invoke-WebRequest "$BaseUrl/bin/show/$binId" -WebSession $admin.Session
    Check ($binPage.Content -match 'Loaded from the Scheduling REST service') 'Bin page did not consume the Scheduling REST service'
    $patched=Api $admin PATCH "bin-api/$binId" @{capacity_litre=200}
    Check ($patched.data.capacity_litre -eq 200) 'Bin PATCH did not persist'
    $null=Api $admin PUT "bin-api/$binId" @{bin_code="TEST-$suffix";location_id=$locationId;category_id=1;capacity_litre=300;is_active=1}
    $null=Api $admin DELETE "location-api/$locationId" $null 422
    $null=Api $reporter DELETE "bin-api/$binId" $null 403
    $null=Api $admin PATCH "bin-api/$binId" @{capacity_litre=300} 403 $false
    $null=Api $admin PATCH "bin-api/$binId" @{capacity_litre=-1} 422
    $null=Api $admin PATCH "bin-api/$binId" @{bin_code=@('bad')} 422
    $null=Api $admin DELETE "bin-api/$binId" $null
    $page=Invoke-WebRequest "$BaseUrl/bin/show/$binId" -WebSession $admin.Session
    Check ($page.Content -match '>Reactivate</button>') 'Reactivate button missing'
    $null=Api $reporter GET "bin-api/$binId" $null 404
    $page=Invoke-WebRequest "$BaseUrl/bin/reactivate/$binId" -WebSession $admin.Session -Method Post -Body @{_token=$admin.Token}
    Check ($page.Content -match 'Bin was reactivated') 'Web reactivation failed'
    $read=Api $reporter GET "bin-api/$binId" $null
    Check $read.data.active 'Reactivation not persisted'
    # Move this fixture bin before deleting its location; other records stay unchanged.
    $null=Api $admin PATCH "bin-api/$binId" @{location_id=1}
    $null=Api $admin DELETE "location-api/$locationId" $null
    $null=Api $admin GET "location-api/$locationId" $null 404
    $rows=Api $admin GET "location-api?q=REST-$suffix" $null
    Check ($rows.data.Count -eq 0) 'Deleted location still listed'
    $state=& $Mysql -u root -N -e "SELECT deleted_at IS NOT NULL FROM ecocampus.locations WHERE location_id=$locationId"
    Check ($state -eq '1') 'Location not soft deleted'
    foreach ($path in 'bin-api/serialize-bin/1','bin-api/resource','bin/show/nope','bin/show','location/delete') {
        $response=Invoke-WebRequest "$BaseUrl/$path" -WebSession $admin.Session -SkipHttpErrorCheck
        Check ($response.StatusCode -eq 404) "Invalid route exposed: $path"
    }
    $response=Invoke-WebRequest "$BaseUrl/.git/config" -SkipHttpErrorCheck
    Check ($response.StatusCode -eq 403) 'Git metadata exposed'
    Write-Output "PASS: $checks bin/location REST and security checks"
} finally {
    if ($binId) { & $Mysql -u root -e "DELETE FROM ecocampus.bins WHERE bin_id=$binId AND bin_code='TEST-$suffix'" }
    if ($locationId) { & $Mysql -u root -e "DELETE FROM ecocampus.locations WHERE location_id=$locationId AND location_name='REST-$suffix'" }
}
