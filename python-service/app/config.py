from pydantic_settings import BaseSettings
from typing import List


class Settings(BaseSettings):
    """Configuración de la aplicación"""
    
    # CORS
    ALLOWED_ORIGINS: str = "*"
    
    # Límites de archivo
    MAX_FILE_SIZE: int = 10485760  # 10MB por defecto
    
    # Configuración de la aplicación
    APP_TITLE: str = "OCR Microservice"
    APP_DESCRIPTION: str = "Servicio de OCR para facturas aduaneras usando OpenAI Vision"
    APP_VERSION: str = "1.0.0"
    
    class Config:
        env_file = ".env"
        case_sensitive = True
    
    @property
    def allowed_origins_list(self) -> List[str]:
        """Convierte la cadena de orígenes permitidos en una lista"""
        if self.ALLOWED_ORIGINS == "*":
            return ["*"]
        return [origin.strip() for origin in self.ALLOWED_ORIGINS.split(",")]


# Instancia global de configuración
settings = Settings()
