from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from starlette.responses import JSONResponse

from app.config import settings
from app.routes import analytics_router, health_router

app = FastAPI(
    title=settings.app_name,
    version="1.0.0",
    description="FastAPI service for dormitory analytics and operational APIs.",
    debug=settings.debug,
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.allowed_origin_list,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.middleware("http")
async def enforce_api_token(request: Request, call_next):
    if not settings.require_auth or not settings.api_token:
        return await call_next(request)

    auth_header = request.headers.get("authorization", "")
    expected = f"Bearer {settings.api_token}"
    if auth_header != expected:
        return JSONResponse(
            status_code=401,
            content={"detail": "Missing or invalid API token."},
        )

    return await call_next(request)

app.include_router(health_router)
app.include_router(analytics_router, prefix="/analytics")


@app.get("/")
def root() -> dict[str, str]:
    return {
        "app": settings.app_name,
        "environment": settings.environment,
        "status": "running",
    }


if __name__ == "__main__":
    import uvicorn

    uvicorn.run("main:app", host=settings.host, port=settings.port, reload=settings.debug)
