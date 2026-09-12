import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from fastapi.testclient import TestClient

from main import app

client = TestClient(app)


def test_root_status():
    response = client.get("/")
    assert response.status_code == 200
    assert response.json()["status"] == "running"


def test_analytics_summary_reads_live_firestore_data():
    response = client.get("/analytics/summary")
    assert response.status_code == 200
    payload = response.json()
    assert payload["status"] != "placeholder"
    assert isinstance(payload["totalStudents"], int)
    assert isinstance(payload["activeExeatRequests"], int)
    assert isinstance(payload["medicalAlerts"], int)
    assert isinstance(payload["securityIncidents"], int)
