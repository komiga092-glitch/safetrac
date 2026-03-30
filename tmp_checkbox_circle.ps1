$fileCss = 'c:\wamp64\www\safetrac\assets\css\style.css'
$css = Get-Content -Path $fileCss -Raw
$oldCss = @'
.checklist-option {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 72px;
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  background: #fff;
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
  font-size: 14px;
  font-weight: 600;
  color: #334155;
}

.checklist-option:hover {
  border-color: #7aa2d1;
  background: #f8fafc;
}

.checklist-option input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.checklist-option .option-label {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  padding: 10px 12px;
  min-width: 72px;
  border-radius: 10px;
}

.checklist-option input:checked + .option-label {
  border-color: #0b1f3a;
  background: #0b1f3a;
  color: #fff;
}
'@
$newCss = @'
.checklist-option {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  transition: all 0.2s ease;
}

.checklist-option:hover .option-label {
  border-color: #7aa2d1;
}

.checklist-option input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.checklist-option .option-label {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 1px solid #cbd5e1;
  background: #fff;
  transition: all 0.2s ease;
}

.checklist-option input:checked + .option-label {
  border-color: #0b1f3a;
  background: #0b1f3a;
}
'@
if ($css.Contains($oldCss)) {
    $css = $css.Replace($oldCss, $newCss)
} else {
    Write-Host 'Old CSS block not found.'
}
$css = $css.Replace('<span class="option-label">&#10003;</span>', '<span class="option-label"></span>')
Set-Content -Path $fileCss -Value $css -Encoding UTF8

$filePhp = 'c:\wamp64\www\safetrac\safety_officer\new_inspection.php'
$php = Get-Content -Path $filePhp -Raw
$php = $php.Replace('<span class="option-label">&#10003;</span>', '<span class="option-label"></span>')
Set-Content -Path $filePhp -Value $php -Encoding UTF8
Write-Host 'circle checkbox style applied.'
