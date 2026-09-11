# User REST integration regression for local XAMPP.
param(
    [string]$BaseUrl = 'http://localhost/EcoCampus',
    [string]$AdminEmail = 'admin@ecocampus.my',
    [string]$AdminPassword = 'password123',
    [string]$MysqlPath = 'C:/xampp/mysql/bin/mysql.exe',
    [string]$Database = 'ecocampus'
)
$ErrorActionPreference = 'Stop'
Import-Module Microsoft.PowerShell.Utility
$BaseUrl = $BaseUrl.TrimEnd('/')
$created = [System.Collections.Generic.List[object]]::new()
$checks = 0
function Check($condition, [string]$label) {
    if (!$condition) { throw "FAIL: $label" }
    $script:checks++
    Write-Output "PASS: $label"
}
function Request([string]$path, [string]$method = 'GET', $session, $body, [string]$token = '') {
    $args = @{ Uri="$BaseUrl/$path"; Method=$method; SkipHttpErrorCheck=$true }
    if ($session) { $session.Headers.Remove('X-CSRF-Token'); $args.WebSession = $session }
    if ($null -ne $body) { $args.Body = ConvertTo-Json $body -Depth 8 -Compress; $args.ContentType='application/json' }
    if ($token) { $args.Headers=@{'X-CSRF-Token'=$token} }
    Invoke-WebRequest @args
}
function SignIn([string]$email, [string]$password) {
    $session = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
    $page = Request 'auth' 'GET' $session
    $token = [regex]::Match($page.Content, 'name="_token" value="([^"]+)"').Groups[1].Value
    if (!$token) { throw 'Missing sign-in CSRF token' }
    $result = Invoke-WebRequest "$BaseUrl/auth/login" -Method Post -WebSession $session -Body @{email=$email; password=$password; _token=$token} -SkipHttpErrorCheck
    return @{Session=$session; Token=$token; Response=$result}
}
function Json($response) { $response.Content | ConvertFrom-Json }
try {
    $admin = SignIn $AdminEmail $AdminPassword
    Check ($admin.Response.StatusCode -eq 200) 'Administrator login'
    $adminList = Json (Request 'user-api' 'GET' $admin.Session)
    $adminId = ($adminList.data | Where-Object email -EQ $AdminEmail).id
    Check ($null -ne $adminId) 'Resolve current admin without changing seed data'
    $prefix = 'codex_user_' + [guid]::NewGuid().ToString('N').Substring(0,12)
    $password = 'TestPass9432'
    foreach ($role in @('Reporter','Cleaner')) {
        $data = @{full_name="$prefix $role";email="$prefix.$($role.ToLower())@example.test";phone_no='0123456789';role=$role;account_status='Active';password=$password;password_confirmation=$password;ic_no='010101-01-1234';gender='Prefer not to say';birth_date='2001-01-01';address_line1='Synthetic test address';city='Test City';state='Selangor';postcode='43000';nationality='Malaysian'}
        $response = Request 'user-api' 'POST' $admin.Session $data $admin.Token
        Check ($response.StatusCode -eq 201) "$role REST create"
        $entity = (Json $response).data
        Check (-not [string]::IsNullOrWhiteSpace($entity.temporary_password)) "$role receives temporary password"
        $created.Add(@{Id=[int]$entity.id; Email=$data.email; Role=$role; Password=$entity.temporary_password})
    }
    $reporter = $created[0]; $cleaner = $created[1]
    $detail = (Json (Request "user-api/$($reporter.Id)" 'GET' $admin.Session)).data
    Check ($detail.ic_no -eq '010101011234' -and !$detail.PSObject.Properties['password_hash']) 'Authorized details normalize IC and omit password hash'
    $listing = (Json (Request "user-api?q=$prefix" 'GET' $admin.Session)).data
    Check ($listing.Count -eq 2 -and !$listing[0].PSObject.Properties['ic_no'] -and !$listing[0].PSObject.Properties['address_line1']) 'User list excludes private demographics'
    $response = Request "user-api/$($reporter.Id)" 'PATCH' $admin.Session @{city='Test City';birth_date='2000-02-29';ic_no='000229-01-1234';gender='Female'} $admin.Token
    Check ($response.StatusCode -eq 200) 'PATCH demographics'
    $detail = (Json (Request "user-api/$($reporter.Id)" 'GET' $admin.Session)).data
    Check ($detail.city -eq 'Test City' -and $detail.email -eq $reporter.Email -and $detail.birth_date -eq '2000-02-29') 'PATCH values persist and preserve omitted identity'
    $response = Request "user-api/$($cleaner.Id)" 'PUT' $admin.Session @{full_name="$prefix Updated cleaner";email=$cleaner.Email;phone_no='0123456789';role='Cleaner';account_status='Active';address_line1='Synthetic test address';city='Test City';state='Selangor';postcode='43000';nationality='Test nationality';ic_no='010101011234';gender='Prefer not to say';birth_date='2001-01-01'} $admin.Token
    Check ($response.StatusCode -eq 200 -and (Json $response).data.nationality -eq 'Test nationality') 'PUT complete account update'
    foreach ($invalid in @(@{birth_date='2025-02-29'}, @{birth_date='2999-01-01'}, @{gender='Invalid'}, @{ic_no='abc'}, @{city=@('bad')}, @{address_line1=('x'*201)})) {
        $response = Request "user-api/$($reporter.Id)" 'PATCH' $admin.Session $invalid $admin.Token
        Check ($response.StatusCode -eq 422) "Reject invalid $($invalid.Keys -join ',')"
    }
    Check ((Request "user-api/$adminId" 'DELETE' $admin.Session $null $admin.Token).StatusCode -eq 422) 'Reject admin self-delete'
    Check ((Request 'user-api').StatusCode -eq 401) 'Reject anonymous list access'
    Check ((Request "user-api/$($reporter.Id)" 'PATCH' $admin.Session @{city='Rejected'}).StatusCode -eq 403) 'Reject missing CSRF'
    $own = SignIn $reporter.Email $reporter.Password
    Check ($own.Response.StatusCode -eq 200) 'Synthetic reporter login'
    Check ((Request 'user-api' 'GET' $own.Session).StatusCode -eq 403) 'Reject non-admin management read'
    Check ((Request "user-api/$($cleaner.Id)" 'DELETE' $own.Session $null $own.Token).StatusCode -eq 403) 'Reject non-admin management delete'
    $profile = Invoke-WebRequest "$BaseUrl/user/update-profile" -Method Post -WebSession $own.Session -Body @{_token=$own.Token;full_name="$prefix Own profile";email=$reporter.Email;phone_no='0123456789';address_line1='Own profile address';city='Own City';state='Selangor';postcode='43000';nationality='Malaysian';ic_no='020202-01-1234';gender='Female';birth_date='2002-02-02';role='Administrator';account_status='Active'} -SkipHttpErrorCheck
    Check ($profile.StatusCode -eq 200 -and $profile.Content.Contains('Own City')) 'Own profile demographics saved and displayed'
    $detail = (Json (Request "user-api/$($reporter.Id)" 'GET' $admin.Session)).data
    Check ($detail.role -eq 'Reporter' -and $detail.city -eq 'Own City') 'Profile ignores attempted role escalation'
    Check ((Request 'user-api/cleaners' 'POST' $admin.Session @{} $admin.Token).StatusCode -eq 405) 'Cleaners service rejects writes'
    foreach ($entity in $created) {
        Check ((Request "user-api/$($entity.Id)" 'DELETE' $admin.Session $null $admin.Token).StatusCode -eq 200) "$($entity.Role) soft delete"
        Check ((Request "user-api/$($entity.Id)" 'GET' $admin.Session).StatusCode -eq 404) 'Deleted account detail hidden'
        Check ((Request "user-api/$($entity.Id)" 'PATCH' $admin.Session @{city='No resurrection'} $admin.Token).StatusCode -eq 404) 'Deleted account cannot be edited'
        $login = SignIn $entity.Email $entity.Password
        Check ($login.Response.StatusCode -eq 422) 'Deleted account cannot sign in'
        $row = & $MysqlPath -u root --batch --skip-column-names $Database -e "SELECT COUNT(*) FROM users WHERE user_id=$($entity.Id) AND email='$($entity.Email)' AND deleted_at IS NOT NULL AND account_status='Inactive';"
        if ($LASTEXITCODE -ne 0) { throw 'Database verification failed' }
        Check ($row -eq '1') 'Database retains inactive soft-deleted account'
    }
    $listing = (Json (Request "user-api?q=$prefix" 'GET' $admin.Session)).data
    Check ($listing.Count -eq 0) 'Deleted accounts excluded from search'
    $expiredProfile = Request 'user/profile' 'GET' $own.Session
    Check ($expiredProfile.Content.Contains('<h1>Sign in</h1>')) 'Existing deleted-user session loses profile access'
    Write-Output "RESULT: $checks checks passed"
} finally {
    # Only remove exact synthetic ID/email pairs created by this invocation.
    foreach ($entity in $created) {
        if ($entity.Email -notmatch '^codex_user_[a-f0-9]{12}\.(reporter|cleaner)@example\.test$' -or $entity.Id -lt 1) { throw 'Unsafe cleanup target' }
        & $MysqlPath -u root $Database -e "DELETE FROM users WHERE user_id=$($entity.Id) AND email='$($entity.Email)';"
        if ($LASTEXITCODE -ne 0) { throw "Cleanup failed for synthetic user $($entity.Id)" }
        $remaining = & $MysqlPath -u root --batch --skip-column-names $Database -e "SELECT COUNT(*) FROM users WHERE user_id=$($entity.Id) AND email='$($entity.Email)';"
        if ($LASTEXITCODE -ne 0 -or $remaining -ne '0') { throw 'Synthetic account cleanup verification failed' }
    }
    Write-Output "Cleanup: removed $($created.Count) exact synthetic accounts"
}


