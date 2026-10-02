@echo off
cd /d "%~dp0battle-quiz-java"
echo ========================================
echo   BATTLE QUIZ - JAVA API :8081
echo ========================================
call mvnw.cmd spring-boot:run
pause
