$env:JAVA_HOME = "C:\Users\Moonshine\java_temp\jdk-17.0.13+11"
$env:PATH = "$env:JAVA_HOME\bin;$env:PATH"

Write-Host "JAVA_HOME: $env:JAVA_HOME"
Write-Host "Java version:"
& "$env:JAVA_HOME\bin\java.exe" -version

Write-Host "`nBuilding Android app..."
cd C:\laragon\www\payment\android
.\gradlew.bat assembleDebug
