# deploy_to_hf.ps1
# Deploys the ai-service folder to the Hugging Face Space: ibeh12/fitrova-ai
# Usage: .\deploy_to_hf.ps1 -Token "hf_YOUR_WRITE_TOKEN"

param(
    [Parameter(Mandatory=$true)]
    [string]$Token
)

$HF_USERNAME = "ibeh12"
$HF_SPACE    = "fitrova-ai"
$HF_URL      = "https://${HF_USERNAME}:${Token}@huggingface.co/spaces/${HF_USERNAME}/${HF_SPACE}"
$TEMP_DIR    = "$env:TEMP\hf-space-deploy-$(Get-Random)"
$SOURCE_DIR  = "c:\xampp\htdocs\Fitrova\ai-service"

Write-Host "🚀 Deploying to HF Space: $HF_USERNAME/$HF_SPACE" -ForegroundColor Cyan

# Clone the existing Space (shallow)
Write-Host "📥 Cloning existing Space..." -ForegroundColor Yellow
git clone --depth=1 $HF_URL $TEMP_DIR
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Clone failed. Check token permissions and Space name." -ForegroundColor Red
    exit 1
}

# Copy updated files over the clone
Write-Host "📋 Copying updated files..." -ForegroundColor Yellow
$filesToCopy = @(
    "api\video_analyzer.py",
    "requirements.txt"
)

foreach ($f in $filesToCopy) {
    $src  = Join-Path $SOURCE_DIR $f
    $dest = Join-Path $TEMP_DIR   $f
    $destDir = Split-Path $dest -Parent
    if (!(Test-Path $destDir)) { New-Item -ItemType Directory -Path $destDir -Force | Out-Null }
    Copy-Item -Path $src -Destination $dest -Force
    Write-Host "  ✅ Copied: $f" -ForegroundColor Green
}

# Stage, commit, push
Set-Location $TEMP_DIR
git add api/video_analyzer.py requirements.txt
git commit -m "feat: add Gemma 2 /api/gemma-coach endpoint + transformers deps"
git push

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "✅ Deployed! HF Space is rebuilding..." -ForegroundColor Green
    Write-Host "   Watch progress at: https://huggingface.co/spaces/$HF_USERNAME/$HF_SPACE" -ForegroundColor Cyan
} else {
    Write-Host "❌ Push failed. Check token write permissions." -ForegroundColor Red
}

# Cleanup
Set-Location "c:\xampp\htdocs\Fitrova"
Remove-Item -Recurse -Force $TEMP_DIR -ErrorAction SilentlyContinue
