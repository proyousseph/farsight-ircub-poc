# Deploy IRCUB to Contabo VPS (behind ssiwebsite-proxy).
# Usage (from Project/):
#   .\scripts\deploy_contabo.ps1
#
# Requires: ssh key C:\Users\Yusuf\.ssh\hrsystem_vps_ed25519

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path $PSScriptRoot -Parent
$Key = Join-Path $env:USERPROFILE '.ssh\hrsystem_vps_ed25519'
$HostName = 'root@161.97.90.92'
$RemoteDir = '/opt/ircub'

if (-not (Test-Path $Key)) {
    throw "SSH key not found: $Key"
}

Set-Location $ProjectRoot

$Tar = Join-Path $env:TEMP 'ircub-docker.tar'
if (Test-Path $Tar) { Remove-Item $Tar -Force }

Write-Host "=== Packing project (no node_modules/vendor/.git) ===" -ForegroundColor Cyan
# Prefer tar.exe (Windows 10+)
& tar.exe -cf $Tar `
    --exclude=node_modules `
    --exclude=frontend/node_modules `
    --exclude=backend/vendor `
    --exclude=backend/node_modules `
    --exclude=.git `
    --exclude=frontend/dist `
    --exclude=backend/storage/logs `
    --exclude=backend/.env `
    --exclude=frontend/.env `
    --exclude=docker/.env.app `
    --exclude=.env `
    .

Write-Host "=== Uploading to $HostName`:$RemoteDir ===" -ForegroundColor Cyan
ssh -i $Key -o StrictHostKeyChecking=accept-new $HostName "mkdir -p $RemoteDir"
scp -i $Key $Tar "${HostName}:/opt/ircub-docker.tar"
ssh -i $Key $HostName @"
set -e
mkdir -p $RemoteDir
tar -xf /opt/ircub-docker.tar -C $RemoteDir
rm -f /opt/ircub-docker.tar
echo 'Upload extracted to $RemoteDir'
ls -la $RemoteDir | head
"@

Write-Host "=== Done packing/upload. Continue with remote compose steps. ===" -ForegroundColor Green
Write-Host "Tar left at: $Tar"
