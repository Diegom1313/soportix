#Requires -Version 5.1
param(
    [string]$Url = $env:SOPORTIX_URL,
    [string]$Key = $env:SOPORTIX_KEY
)

$ErrorActionPreference = 'Stop'
if (-not $Url -or -not $Key) { throw 'Define SOPORTIX_URL y SOPORTIX_KEY' }
if ($Url -notmatch '^https://' -and $env:SOPORTIX_ALLOW_HTTP -ne '1') { throw 'SOPORTIX_URL debe usar HTTPS' }
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$os = Get-CimInstance Win32_OperatingSystem
$cpu = (Get-CimInstance Win32_Processor | Select-Object -First 1).Name
$disk = Get-CimInstance Win32_LogicalDisk -Filter "DeviceID='$($env:SystemDrive)'"
$usedPct = [int][math]::Round((($disk.Size - $disk.FreeSpace) / $disk.Size) * 100)
$ip = (Get-CimInstance Win32_NetworkAdapterConfiguration -Filter 'IPEnabled=true' |
    ForEach-Object { $_.IPAddress } | Where-Object { $_ -match '^\d+\.' } | Select-Object -First 1)

$paths = 'HKLM:\Software\Microsoft\Windows\CurrentVersion\Uninstall\*',
         'HKLM:\Software\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*'
$software = @(Get-ItemProperty $paths -ErrorAction SilentlyContinue |
    Where-Object { $_.DisplayName } |
    ForEach-Object { "$($_.DisplayName) $($_.DisplayVersion)".Trim() } |
    Sort-Object -Unique | Select-Object -First 2000 |
    ForEach-Object { if ($_.Length -gt 200) { $_.Substring(0, 200) } else { $_ } })

$body = @{
    os            = $os.Caption
    cpu           = $cpu.Trim()
    ip            = $ip
    ram_mb        = [int]($os.TotalVisibleMemorySize / 1024)
    disk_total_gb = [int]($disk.Size / 1GB)
    disk_used_pct = $usedPct
    software      = $software
} | ConvertTo-Json -Depth 3 -Compress

Invoke-RestMethod -Uri ($Url.TrimEnd('/') + '/api/agent/report') -Method Post `
    -Headers @{ 'X-Agent-Key' = $Key } -ContentType 'application/json; charset=utf-8' `
    -Body ([Text.Encoding]::UTF8.GetBytes($body))
