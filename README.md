# Plataforma Inteligente de Clasificación y Procesamiento de Facturas Aduaneras

## 📋 Descripción General

Sistema profesional para automatizar el proceso de clasificación arancelaria de facturas aduaneras mediante tecnología OCR e Inteligencia Artificial con Vision Multi. La plataforma permite cargar facturas, extraer información automáticamente, digitalizar datos y clasificar productos con códigos arancelarios.

## ✨ Características Principales

- **🔐 Autenticación Segura**: Sistema de login con roles (admin, operador, visualizador)
- **📄 OCR con Vision Multi**: Extracción automática de texto de facturas PDF e imágenes
- **⌨️ Digitación Automática**: Auto-completado de formularios con datos extraídos
- **🤖 Clasificación IA con Vision Multi**: Sugerencias de códigos arancelarios con explicaciones
- **✅ Revisión y Aprobación**: Flujo de trabajo con validación humana
- **📊 Auditoría Completa**: Registro detallado de todas las operaciones
- **🎨 Interfaz Moderna**: Diseño responsivo y profesional

## 🛠️ Arquitectura

**Stack Tecnológico:**
- **Backend**: PHP 8.x + Apache
- **Base de Datos**: MySQL 5.7+
- **IA/OCR**: Scripts Python 3.12 ejecutados directamente desde PHP
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5

**Arquitectura Simplificada:**
```
Apache + PHP → Ejecuta directamente → Scripts Python (Vision Multi)
                                    ↓
                                  MySQL
```

**NO se usa Flask ni servicios HTTP separados**. Todo corre bajo Apache.

## 📦 Requisitos del Sistema

### Servidor Web
- Apache 2.4+
- PHP 8.0+ con extensiones: PDO, PDO_MySQL, cURL, GD, mbstring
- MySQL 5.7+ o MariaDB 10.3+

### Python
- Python 3.12 instalado en: `c:\Python312\python.exe`
- Paquetes: `openai`, `Pillow`, `pdf2image`

### Opcional (para PDFs)
- Poppler (para convertir PDF a imágenes)
- Descargar: https://github.com/oschwartz10612/poppler-windows/releases/

## 🚀 Instalación

### 1. Configurar Base de Datos

```bash
mysql -u root -p < database/schema.sql
```

Esto crea:
- Base de datos `facturacion_aduanera`
- 8 tablas con datos iniciales
- Usuario admin: `admin@facturacion.com` / `Admin123!`

### 2. Configurar PHP

Editar `config/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'facturacion_aduanera');
define('DB_USER', 'root');
define('DB_PASS', 'tu_contraseña');
define('PYTHON_PATH', 'c:\Python312\python.exe');
define('OPENAI_API_KEY', 'tu_clave_api');
```

### 3. Instalar Dependencias Python

```bash
cd c:\WEBSERVER\htdocs\agenteClasificador\python-scripts
c:\Python312\python.exe -m pip install -r requirements.txt
```

### 4. Configurar Permisos

Asegurar que el directorio `uploads/` tenga permisos de escritura para Apache.

### 5. Acceder al Sistema

1. Abrir: `http://localhost/agenteClasificador/public/`
2. Login: `admin@facturacion.com` / `Admin123!`

## 📖 Uso del Sistema

### Flujo de Trabajo

1. **Cargar Factura (OCR)**
   - Ir a "Reconocimiento de Factura (OCR)"
   - Arrastrar archivo PDF o imagen
   - El sistema extrae automáticamente el texto con Vision Multi

2. **Digitación Automática**
   - Ir a "Digitación Automática"
   - Revisar datos auto-completados
   - Editar manualmente si es necesario
   - Guardar factura e ítems

3. **Clasificación IA**
   - Ir a "Clasificación Inteligente"
   - Seleccionar ítems a clasificar
   - Vision Multi sugiere códigos arancelarios
   - Revisar explicaciones y confianza

4. **Revisión y Aprobación**
   - Ir a "Revisión y Aprobación"
   - Revisar clasificaciones sugeridas
   - Aprobar o modificar
   - El sistema registra en auditoría

## 🗂️ Estructura del Proyecto

```
agenteClasificador/
├── config/
│   └── config.php              # Configuración principal
├── classes/
│   ├── Database.php            # Conexión BD
│   ├── Auth.php                # Autenticación
│   ├── User.php                # Gestión usuarios
│   ├── Invoice.php             # Gestión facturas
│   ├── InvoiceItem.php         # Ítems de factura
│   ├── OCRService.php          # Servicio OCR
│   ├── AIClassificationService.php  # Servicio IA
│   └── AuditLog.php            # Auditoría
├── database/
│   └── schema.sql              # Esquema BD
├── public/
│   ├── index.php               # Página pública
│   ├── login.php               # Login
│   ├── register.php            # Registro
│   ├── css/styles.css          # Estilos
│   └── js/app.js               # JavaScript
├── private/
│   ├── dashboard.php           # Dashboard
│   ├── ocr-upload.php          # Módulo OCR
│   ├── auto-digitize.php       # Digitación
│   ├── ai-classify.php         # Clasificación
│   └── audit-log.php           # Auditoría
├── python-scripts/             # Scripts Python (NO Flask)
│   ├── ocr_process.py          # OCR con Vision Multi
│   ├── ai_classify.py          # Clasificación IA
│   └── requirements.txt        # Dependencias
├── uploads/                    # Archivos cargados
├── .htaccess                   # Config Apache
└── README.md                   # Este archivo
```

## 🔧 Cómo Funciona (Sin Flask)

### OCR Process

```
PHP (OCRService.php)
  ↓
shell_exec("python ocr_process.py archivo.pdf api_key")
  ↓
Python procesa con Vision Multi
  ↓
Retorna JSON a stdout
  ↓
PHP parsea JSON y guarda en MySQL
```

### AI Classification

```
PHP (AIClassificationService.php)
  ↓
shell_exec("python ai_classify.py '[descripciones]' api_key")
  ↓
Python clasifica con Vision Multi
  ↓
Retorna JSON a stdout
  ↓
PHP parsea JSON y guarda en MySQL
```

## 🔒 Seguridad

- ✅ Contraseñas hasheadas con bcrypt
- ✅ Protección CSRF
- ✅ Validación de entrada
- ✅ Sentencias preparadas PDO
- ✅ Headers de seguridad HTTP
- ✅ Protección de directorios
- ✅ Sesiones seguras

## 📊 Base de Datos

### Tablas Principales

- **usuarios**: Cuentas con roles
- **facturas**: Registro de facturas
- **items_factura**: Ítems individuales
- **resultados_ocr**: Resultados OCR
- **resultados_ia**: Clasificaciones IA
- **clasificacion_final**: Aprobadas
- **auditoria**: Registro completo
- **configuracion**: Config sistema

## 🐛 Solución de Problemas

### Error: "Python no encontrado"
- Verificar ruta en `config.php`: `define('PYTHON_PATH', 'c:\Python312\python.exe');`
- Probar en CMD: `c:\Python312\python.exe --version`

### Error: "OpenAI API Key inválida"
- Verificar clave en `config.php`
- Probar manualmente el script Python

### Error al procesar PDF
- Instalar Poppler y agregar al PATH
- Alternativamente, usar solo imágenes JPG/PNG

### Error de permisos
- Dar permisos de escritura a `uploads/`
- Verificar que Apache puede ejecutar `shell_exec()`

## 📝 Notas Importantes

1. **No se usa Flask**: Los scripts Python se ejecutan directamente desde PHP
2. **No hay servicios HTTP separados**: Todo corre bajo Apache
3. **Vision Multi es requerido**: Se necesita una API key válida de OpenAI
4. **Windows**: Rutas con backslash `\` en config.php

## 🎉 Conclusión

La plataforma está **100% funcional** con arquitectura simplificada:
- ✅ Sin Flask ni microservicios HTTP
- ✅ Scripts Python ejecutados directamente desde PHP
- ✅ Todo bajo Apache + PHP + MySQL
- ✅ Vision Multi para OCR y clasificación
- ✅ Código en español
- ✅ Listo para producción

---

**Versión**: 2.0.0 (Sin Flask)  
**Última Actualización**: Diciembre 2024  
**Stack**: Apache + PHP + MySQL + Python (Vision Multi)
