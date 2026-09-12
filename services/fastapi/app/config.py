from pathlib import Path

from pydantic import field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    app_name: str = "Student Dormitory API"
    environment: str = "development"
    debug: bool = True
    host: str = "0.0.0.0"
    port: int = 8001
    api_token: str = ""
    allowed_origins: str = "http://localhost:8000,http://127.0.0.1:8000,http://localhost:8080,http://127.0.0.1:8080"

    @field_validator("debug", mode="before")
    @classmethod
    def parse_debug(cls, value):
        if isinstance(value, bool):
            return value

        if isinstance(value, str):
            normalized = value.strip().lower()
            if normalized in {"1", "true", "yes", "on"}:
                return True
            if normalized in {"0", "false", "no", "off", "release", "prod", "production"}:
                return False

        return bool(value)

    @property
    def allowed_origin_list(self) -> list[str]:
        if not self.allowed_origins or self.allowed_origins.strip() == "*":
            return ["*"]
        return [origin.strip() for origin in self.allowed_origins.split(",") if origin.strip()]

    model_config = SettingsConfigDict(
        env_file=Path(__file__).resolve().parents[3] / ".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
        env_prefix="FASTAPI_",
        extra="ignore",
    )


settings = Settings()
