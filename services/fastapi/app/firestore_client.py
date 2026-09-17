from __future__ import annotations

import base64
import os
import threading
from pathlib import Path
from typing import Any

from dotenv import load_dotenv
from google.cloud import firestore

ROOT = Path(__file__).resolve().parents[3]
load_dotenv(ROOT / ".env", override=False)

FIRESTORE_STREAM_TIMEOUT_SECONDS = 3


def _credential_candidates() -> list[Path]:
    candidates: list[Path] = []

    env_path = os.getenv("FIREBASE_CREDENTIALS")
    if env_path:
        candidate = Path(env_path)
        candidates.append(candidate if candidate.is_absolute() else ROOT / candidate)

    env_base64 = os.getenv("FIREBASE_CREDENTIALS_BASE64")
    if env_base64:
        decoded = base64.b64decode(env_base64)
        temp_path = ROOT / ".tmp" / "firebase-service-account.json"
        temp_path.parent.mkdir(parents=True, exist_ok=True)
        temp_path.write_bytes(decoded)
        candidates.append(temp_path)

    known_file = ROOT / "credentials" / "dormitory-monitoring-sys-d3cc7-firebase-adminsdk-fbsvc-93ae0c0e5c.json"
    candidates.extend([
        known_file,
        ROOT / "credentials" / "firebase-service-account.json",
    ])

    deduped: list[Path] = []
    seen: set[str] = set()
    for candidate in candidates:
        resolved = str(candidate.resolve()) if candidate.exists() else str(candidate)
        if resolved not in seen:
            deduped.append(candidate)
            seen.add(resolved)
    return deduped


def get_firestore_client() -> firestore.Client:
    project_id = os.getenv("FIREBASE_PROJECT_ID") or "dormitory-monitoring-sys-d3cc7"

    for candidate in _credential_candidates():
        if candidate.exists():
            return firestore.Client.from_service_account_json(str(candidate), project=project_id)

    raise RuntimeError(
        "Firebase credentials are not configured. Set FIREBASE_CREDENTIALS or FIREBASE_CREDENTIALS_BASE64 in the project .env file."
    )


def _as_dict(doc: Any) -> dict[str, Any]:
    if doc is None:
        return {}
    if hasattr(doc, "to_dict"):
        return doc.to_dict() or {}
    return dict(doc)


def _stream_collection(db: firestore.Client, collection_name: str) -> list[Any]:
    result: list[Any] = []
    error: Exception | None = None

    def _read_collection() -> None:
        nonlocal result, error
        try:
            result = list(db.collection(collection_name).stream())
        except Exception as exc:  # pragma: no cover - exercised via runtime fallback
            error = exc

    thread = threading.Thread(target=_read_collection, daemon=True)
    thread.start()
    thread.join(FIRESTORE_STREAM_TIMEOUT_SECONDS)

    if thread.is_alive():
        raise RuntimeError(
            f"Timed out reading Firestore collection '{collection_name}' after {FIRESTORE_STREAM_TIMEOUT_SECONDS} seconds."
        )

    if error is not None:
        raise error

    return result


def count_collection(collection_name: str) -> int:
    db = get_firestore_client()
    return len(_stream_collection(db, collection_name))


def room_occupancy_stats() -> dict[str, Any]:
    try:
        db = get_firestore_client()
        rooms = _stream_collection(db, "rooms")
        total_capacity = 0
        occupied_beds = 0
        room_breakdown: list[dict[str, Any]] = []

        for doc in rooms:
            data = _as_dict(doc)
            capacity = int(data.get("capacity") or data.get("roomCapacity") or 0)
            occupied = int(
                data.get("occupied")
                or data.get("occupancy")
                or data.get("currentOccupancy")
                or data.get("studentsCount")
                or 0
            )
            total_capacity += capacity
            occupied_beds += occupied
            room_breakdown.append(
                {
                    "id": str(data.get("roomNumber") or data.get("name") or doc.id),
                    "capacity": capacity,
                    "occupied": occupied,
                    "available": max(capacity - occupied, 0),
                }
            )

        utilization_rate = 0.0
        if total_capacity:
            utilization_rate = round((occupied_beds / total_capacity) * 100, 2)

        return {
            "totalRooms": len(room_breakdown),
            "totalCapacity": total_capacity,
            "occupiedBeds": occupied_beds,
            "availableBeds": max(total_capacity - occupied_beds, 0),
            "utilizationRate": utilization_rate,
            "rooms": room_breakdown,
        }
    except Exception:
        return {
            "totalRooms": 0,
            "totalCapacity": 0,
            "occupiedBeds": 0,
            "availableBeds": 0,
            "utilizationRate": 0.0,
            "rooms": [],
            "status": "fallback",
            "source": "local",
        }


def incident_breakdown() -> dict[str, Any]:
    try:
        db = get_firestore_client()
        incidents = _stream_collection(db, "incidents")
        by_type: dict[str, int] = {}
        by_severity: dict[str, int] = {}

        for doc in incidents:
            data = _as_dict(doc)
            incident_type = str(data.get("type") or "other").lower()
            severity = str(data.get("severity") or "low").lower()
            by_type[incident_type] = by_type.get(incident_type, 0) + 1
            by_severity[severity] = by_severity.get(severity, 0) + 1

        return {
            "total": len(incidents),
            "byType": dict(sorted(by_type.items(), key=lambda item: item[1], reverse=True)),
            "bySeverity": dict(sorted(by_severity.items(), key=lambda item: item[1], reverse=True)),
        }
    except Exception:
        return {
            "total": 0,
            "byType": {},
            "bySeverity": {},
            "status": "fallback",
            "source": "local",
        }


def analytics_summary() -> dict[str, Any]:
    try:
        db = get_firestore_client()
        students = _stream_collection(db, "students")
        exeats = _stream_collection(db, "exeats")
        medical_records = _stream_collection(db, "medical_records")
        incidents = _stream_collection(db, "incidents")
        attendance = _stream_collection(db, "attendance")
        rooms = _stream_collection(db, "rooms")
        houses = _stream_collection(db, "houses")
        visitors = _stream_collection(db, "visitors")

        active_exeat_requests = 0
        for doc in exeats:
            data = _as_dict(doc)
            status = str(data.get("status", "")).lower()
            if status in {"pending", "approved", "departed"}:
                active_exeat_requests += 1

        medical_alerts = 0
        for doc in medical_records:
            data = _as_dict(doc)
            severity = str(data.get("severity", "")).lower()
            if severity in {"severe", "critical", "emergency"}:
                medical_alerts += 1

        attendance_statuses = {"present": 0, "absent": 0, "late": 0, "excused": 0}
        for doc in attendance:
            data = _as_dict(doc)
            status = str(data.get("status", "")).lower()
            if status in attendance_statuses:
                attendance_statuses[status] += 1

        total_attendance = sum(attendance_statuses.values())
        attendance_rate = 0.0
        if total_attendance:
            attendance_rate = round(
                ((attendance_statuses["present"] + attendance_statuses["excused"]) / total_attendance) * 100,
                2,
            )

        room_breakdown: list[dict[str, Any]] = []
        total_capacity = 0
        occupied_beds = 0
        for doc in rooms:
            data = _as_dict(doc)
            capacity = int(data.get("capacity") or data.get("roomCapacity") or 0)
            occupied = int(
                data.get("occupied")
                or data.get("occupancy")
                or data.get("currentOccupancy")
                or data.get("studentsCount")
                or 0
            )
            total_capacity += capacity
            occupied_beds += occupied
            room_breakdown.append(
                {
                    "id": str(data.get("roomNumber") or data.get("name") or doc.id),
                    "capacity": capacity,
                    "occupied": occupied,
                    "available": max(capacity - occupied, 0),
                }
            )

        utilization_rate = 0.0
        if total_capacity:
            utilization_rate = round((occupied_beds / total_capacity) * 100, 2)

        incident_types: dict[str, int] = {}
        incident_severities: dict[str, int] = {}
        for doc in incidents:
            data = _as_dict(doc)
            incident_type = str(data.get("type") or "other").lower()
            severity = str(data.get("severity") or "low").lower()
            incident_types[incident_type] = incident_types.get(incident_type, 0) + 1
            incident_severities[severity] = incident_severities.get(severity, 0) + 1

        summary = {
            "totalStudents": len(students),
            "activeExeatRequests": active_exeat_requests,
            "medicalAlerts": medical_alerts,
            "securityIncidents": len(incidents),
            "totalVisitors": len(visitors),
            "totalRooms": len(rooms),
            "totalHouses": len(houses),
            "attendanceRate": attendance_rate,
            "attendance": attendance_statuses,
            "roomOccupancy": {
                "totalRooms": len(room_breakdown),
                "totalCapacity": total_capacity,
                "occupiedBeds": occupied_beds,
                "availableBeds": max(total_capacity - occupied_beds, 0),
                "utilizationRate": utilization_rate,
                "rooms": room_breakdown,
            },
            "incidentBreakdown": {
                "total": len(incidents),
                "byType": dict(sorted(incident_types.items(), key=lambda item: item[1], reverse=True)),
                "bySeverity": dict(sorted(incident_severities.items(), key=lambda item: item[1], reverse=True)),
            },
            "status": "live",
            "source": "firestore",
        }
        return summary
    except Exception:
        return {
            "totalStudents": 0,
            "activeExeatRequests": 0,
            "medicalAlerts": 0,
            "securityIncidents": 0,
            "totalVisitors": 0,
            "totalRooms": 0,
            "totalHouses": 0,
            "attendanceRate": 0.0,
            "attendance": {"present": 0, "absent": 0, "late": 0, "excused": 0},
            "roomOccupancy": {
                "totalRooms": 0,
                "totalCapacity": 0,
                "occupiedBeds": 0,
                "availableBeds": 0,
                "utilizationRate": 0.0,
                "rooms": [],
                "status": "fallback",
                "source": "local",
            },
            "incidentBreakdown": {
                "total": 0,
                "byType": {},
                "bySeverity": {},
                "status": "fallback",
                "source": "local",
            },
            "status": "fallback",
            "source": "local",
        }


def student_stats() -> dict[str, Any]:
    try:
        db = get_firestore_client()
        students = _stream_collection(db, "students")
        by_gender: dict[str, int] = {"male": 0, "female": 0}
        by_house: dict[str, int] = {}

        for doc in students:
            data = _as_dict(doc)
            gender = str(data.get("gender", "")).strip().lower()
            if gender in {"male", "m"}:
                by_gender["male"] += 1
            elif gender in {"female", "f"}:
                by_gender["female"] += 1

            house_id = str(data.get("houseId") or data.get("house_id") or "unassigned")
            by_house[house_id] = by_house.get(house_id, 0) + 1

        return {"count": len(students), "byGender": by_gender, "byHouse": by_house}
    except Exception:
        return {
            "count": 0,
            "byGender": {"male": 0, "female": 0},
            "byHouse": {},
            "status": "fallback",
            "source": "local",
        }


def attendance_stats() -> dict[str, Any]:
    try:
        db = get_firestore_client()
        attendance = _stream_collection(db, "attendance")
        counts = {"present": 0, "absent": 0, "late": 0, "excused": 0}

        for doc in attendance:
            data = _as_dict(doc)
            status = str(data.get("status", "")).lower()
            if status in counts:
                counts[status] += 1

        total = sum(counts.values())
        rate = 0.0
        if total:
            rate = round(((counts["present"] + counts["excused"]) / total) * 100, 2)

        return {"totalRecords": total, "attendanceRate": rate, **counts}
    except Exception:
        return {
            "totalRecords": 0,
            "attendanceRate": 0.0,
            "present": 0,
            "absent": 0,
            "late": 0,
            "excused": 0,
            "status": "fallback",
            "source": "local",
        }
