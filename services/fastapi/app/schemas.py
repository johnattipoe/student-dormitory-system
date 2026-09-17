from pydantic import BaseModel, Field


class HealthResponse(BaseModel):
    status: str = Field(default="ok")
    service: str = Field(default="student-dormitory-api")


class AnalyticsSummaryResponse(BaseModel):
    totalStudents: int
    activeExeatRequests: int
    medicalAlerts: int
    securityIncidents: int
    totalVisitors: int
    totalRooms: int
    totalHouses: int
    attendanceRate: float
    attendance: dict[str, int]
    roomOccupancy: dict[str, object]
    incidentBreakdown: dict[str, object]
    status: str
    source: str
