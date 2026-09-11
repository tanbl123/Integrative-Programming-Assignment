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
    $arguments = @{Uri="$BaseUrl/$path";WebSession=$account.Session;Method=$method;SkipHttpErrorCheck=$true}
    if ($csrf) { $arguments.Headers=@{'X-CSRF-Token'=$account.Token} }
    if ($null -ne $body) { $arguments.ContentType='application/json';$arguments.Body=ConvertTo-Json -InputObject $body -Compress }
    $result=Invoke-WebRequest @arguments
    Check ($result.StatusCode -eq $expected) "$method $path expected $expected got $($result.StatusCode): $($result.Content)"
    Check ($result.Headers.'Content-Type' -match 'application/json') 'API did not return JSON'
    return ($result.Content | ConvertFrom-Json)
}
$admin=Login 'admin@ecocampus.my'
$reporter=Login 'siti@student.ecocampus.my'
$suffix=[guid]::NewGuid().ToString('N').Substring(0,10).ToUpperInvariant()
$locationId=$null; $binId=$null
try {
    $location=Api $admin POST 'location-api' @{location_name="REST-$suffix";building_name='Test building';floor_no='Ground';description='Disposable integration fixture'} 201
    $locationId=[int]$location.data.location_id
    $read=Api $admin GET "location-api/$locationId" $null
    Check ($read.data.name -eq "REST-$suffix") 'Location read mismatch'
    $patched=Api $admin PATCH "location-api/$locationId" @{description='Updated through REST'}
    Check ($patched.data.description -eq 'Updated through REST') 'Location PATCH failed'
    $null=Api $admin PUT "location-api/$locationId" @{location_name="REST-$suffix";building_name='Updated building';floor_no='First';description='Replaced'}
    $bin=Api $admin POST 'bin-api' @{bin_code="TEST-$suffix";location_id=$locationId;category_id=1;capacity_litre=100;is_active=1} 201
    $binId=[int]$bin.data.id
    $null=Api $admin GET "bin-api/$binId" $null
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
