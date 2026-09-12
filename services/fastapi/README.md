# Student Dormitory FastAPI Service

This service runs alongside the existing PHP application and exposes lightweight API endpoints for health checks and analytics.

## Setup

1. Open a terminal in this folder.
2. Create a virtual environment:
   ```bash
   python -m venv .venv
   ```
3. Activate it:
   - Windows PowerShell:
     ```powershell
     .\.venv\Scripts\Activate.ps1
     ```
   - Windows CMD:
     ```cmd
     .\.venv\Scripts\activate.bat
     ```
4. Install dependencies:
   ```bash
   python -m pip install --upgrade pip
   python -m pip install -r requirements.txt
   ```

## Run

```bash
uvicorn main:app --host 0.0.0.0 --port 8001 --reload
```

## Endpoints

- `GET /` — service status
- `GET /health` — health check
- `GET /analytics/summary` — Firestore-backed dashboard summary
- `GET /analytics/students` — Firestore-backed student distribution stats
- `GET /analytics/attendance` — Firestore-backed attendance stats

## Notes

This service is intentionally isolated from the PHP app so the main project remains stable while the API can be extended later for reporting, dashboards, or external integrations.
