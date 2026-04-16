@echo off
echo [POSCAM] Starting Hybrid Logic - Native Worker...
echo [POSCAM] Checking environment...

if not exist venv (
    echo Creating virtual environment...
    python -m venv venv
)

call venv\Scripts\activate

echo Installing/Updating requirements...
pip install -r requirements.txt

echo Starting FastAPI Worker on port 8001...
uvicorn main:app --host 0.0.0.0 --port 8001 --reload

pause
