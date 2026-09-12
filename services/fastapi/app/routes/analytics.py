from fastapi import APIRouter, HTTPException

from app.firestore_client import (
    analytics_summary,
    attendance_stats,
    incident_breakdown,
    room_occupancy_stats,
    student_stats,
)

router = APIRouter(tags=["analytics"])


@router.get("/summary")
def analytics_summary_route() -> dict[str, object]:
    try:
        return analytics_summary()
    except Exception as exc:  # pragma: no cover - runtime-firestore fallback
        raise HTTPException(
            status_code=503,
            detail=f"Unable to read Firestore analytics data: {exc}",
        ) from exc


@router.get("/students")
def students_summary() -> dict[str, object]:
    try:
        return student_stats()
    except Exception as exc:  # pragma: no cover - runtime-firestore fallback
        raise HTTPException(
            status_code=503,
            detail=f"Unable to read Firestore student statistics: {exc}",
        ) from exc


@router.get("/attendance")
def attendance_summary() -> dict[str, object]:
    try:
        return attendance_stats()
    except Exception as exc:  # pragma: no cover - runtime-firestore fallback
        raise HTTPException(
            status_code=503,
            detail=f"Unable to read Firestore attendance statistics: {exc}",
        ) from exc


@router.get("/rooms")
def room_occupancy_summary() -> dict[str, object]:
    try:
        return room_occupancy_stats()
    except Exception as exc:  # pragma: no cover - runtime-firestore fallback
        raise HTTPException(
            status_code=503,
            detail=f"Unable to read Firestore room occupancy data: {exc}",
        ) from exc


@router.get("/incidents")
def incident_breakdown_summary() -> dict[str, object]:
    try:
        return incident_breakdown()
    except Exception as exc:  # pragma: no cover - runtime-firestore fallback
        raise HTTPException(
            status_code=503,
            detail=f"Unable to read Firestore incident breakdown: {exc}",
        ) from exc
