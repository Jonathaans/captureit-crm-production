param(
    [string]$ProjectPath = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path,
    [string]$PhpPath = 'php.exe'
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path (Join-Path $ProjectPath 'artisan'))) {
    throw "artisan tidak ditemukan di $ProjectPath"
}

$schedulerAction = New-ScheduledTaskAction -Execute $PhpPath -Argument 'artisan schedule:run' -WorkingDirectory $ProjectPath
$schedulerTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date.AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -ExecutionTimeLimit (New-TimeSpan -Minutes 10) -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)
Register-ScheduledTask -TaskName 'CRM Laravel Scheduler' -Action $schedulerAction -Trigger $schedulerTrigger -Settings $settings -RunLevel Highest -Force | Out-Null

$queueScript = Join-Path $ProjectPath 'tools\run_crm_queue_worker_v2.cmd'
$queueAction = New-ScheduledTaskAction -Execute 'cmd.exe' -Argument ('/c "{0}"' -f $queueScript) -WorkingDirectory $ProjectPath
$queueTrigger = New-ScheduledTaskTrigger -AtStartup
$queueSettings = New-ScheduledTaskSettingsSet -ExecutionTimeLimit ([TimeSpan]::Zero) -RestartCount 99 -RestartInterval (New-TimeSpan -Minutes 1) -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName 'CRM Laravel Queue Worker' -Action $queueAction -Trigger $queueTrigger -Settings $queueSettings -RunLevel Highest -Force | Out-Null
Start-ScheduledTask -TaskName 'CRM Laravel Queue Worker'

Write-Host '[OK] CRM Laravel Scheduler terpasang.' -ForegroundColor Green
Write-Host '[OK] CRM Laravel Queue Worker terpasang.' -ForegroundColor Green
Write-Host 'Pastikan task menggunakan user Windows yang memiliki akses ke project dan database.'
