$ProjectRoot = "D:\Aplicaciones a Medida\WabotV2"
$PhpPath = "C:\xampp\php\php.exe"
$NgrokPath = "C:\ngrok\ngrok.exe"
$Port = 8081

Write-Host "=== WabotV2 - Entorno Local ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Acceso A) Usando XAMPP Apache (MySQL ya corriendo):" -ForegroundColor Yellow
Write-Host "   Panel: http://localhost/WabotV2/" -ForegroundColor White
Write-Host ""
Write-Host "Acceso B) Servidor PHP embebido + ngrok (para probar webhook):" -ForegroundColor Yellow

$phpJob = Start-Job -ScriptBlock {
    param($php, $dir, $port)
    & $php -S 0.0.0.0:$port -t $dir
} -ArgumentList $PhpPath, $ProjectRoot, $Port

Start-Sleep -Seconds 2

Write-Host "   Servidor: http://localhost:$Port" -ForegroundColor Green
Write-Host "   Panel:    http://localhost:$Port/" -ForegroundColor Gray
Write-Host "   Webhook:  http://localhost:$Port/webhook.php" -ForegroundColor Gray
Write-Host ""

Write-Host "Iniciando ngrok (opcional, Ctrl+C si no lo necesitás)..." -ForegroundColor Yellow
Start-Process -NoNewWindow -FilePath $NgrokPath -ArgumentList "http", $Port

Start-Sleep -Seconds 4

Write-Host ""
Write-Host "=== DATOS DE ACCESO ===" -ForegroundColor Cyan
Write-Host "DB: localhost / wabot_v2 / root / sin contraseña" -ForegroundColor White
Write-Host "Admin: email del .env - pass: admin" -ForegroundColor White
Write-Host ""
Write-Host "Presiona ENTER para detener todo..." -ForegroundColor Red
Read-Host

Write-Host "Deteniendo..." -ForegroundColor Yellow
Stop-Job $phpJob
Remove-Job $phpJob
Get-Process ngrok -ErrorAction SilentlyContinue | Stop-Process -Force
Write-Host "Detenido." -ForegroundColor Green
