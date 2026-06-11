$path = "c:\xampp\htdocs\leads2\js\modules\leads.js"
$lines = Get-Content $path
# Keep lines 1-738 (indices 0-737) and 912-End (indices 911-End)
# Removing lines 739-911
$newLines = $lines[0..737] + $lines[911..($lines.Count-1)]
$newLines | Set-Content $path -Encoding UTF8
Write-Host "File cleaned. New line count: $($newLines.Count)"
