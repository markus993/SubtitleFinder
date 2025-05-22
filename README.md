# SubtitleFinder - Documentación de Desarrollo

## Descripción General
SubtitleFinder es una aplicación web diseñada para automatizar la búsqueda y descarga de subtítulos para videos. La aplicación escanea directorios en busca de archivos de video, detecta su idioma y busca subtítulos en español correspondientes.

## Stack Tecnológico

### Backend
- **Framework**: Laravel 10.x
- **PHP**: 8.1+
- **Base de Datos**: MySQL 8.0+
- **Servicios Externos**:
  - OpenSubtitles API (para búsqueda y descarga de subtítulos)
  - FFmpeg (para detección de idioma en archivos de video)

### Frontend
- **Framework CSS**: Bootstrap 5.3
- **JavaScript**: Vanilla JS
- **Librerías**:
  - Bootstrap Bundle (incluye Popper.js para tooltips)
  - Fetch API para peticiones AJAX

## Arquitectura

### Estructura del Proyecto
```
app/
├── Http/
│   ├── Controllers/
│   │   └── VideoController.php
│   └── Requests/
├── Models/
│   └── Video.php
├── Services/
│   └── SubtitleService.php
└── Providers/
    └── AppServiceProvider.php
```

### Componentes Principales

#### 1. VideoController
- Maneja las rutas principales de la aplicación
- Gestiona el escaneo de directorios
- Controla el procesamiento de videos
- Maneja la paginación y ordenamiento de resultados

#### 2. SubtitleService
- Implementa la lógica de negocio principal
- Gestiona la comunicación con OpenSubtitles API
- Realiza la detección de idioma usando FFmpeg
- Maneja la búsqueda y descarga de subtítulos

#### 3. Modelo Video
- Representa la entidad principal de la aplicación
- Almacena información sobre archivos de video
- Gestiona el estado de procesamiento
- Mantiene la relación con los subtítulos

## Flujo de Trabajo

1. **Escaneo de Directorio**
   - El usuario proporciona una ruta de directorio
   - La aplicación escanea recursivamente en busca de archivos de video
   - Se detecta el idioma de cada video usando FFmpeg
   - Se ignoran videos con audio en español
   - Se crean registros en la base de datos

2. **Procesamiento de Videos**
   - Se verifica la existencia de subtítulos
   - Se autentica con OpenSubtitles API
   - Se buscan subtítulos en español
   - Se descargan y guardan los subtítulos encontrados

## Configuración del Entorno

### Requisitos Previos
- PHP 8.1 o superior
- Composer
- MySQL 8.0 o superior
- FFmpeg instalado y accesible en el PATH
- Node.js y NPM (para assets)

### Variables de Entorno
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=subtitle_finder
DB_USERNAME=root
DB_PASSWORD=

OPENSUBTITLES_API_KEY=your_api_key
OPENSUBTITLES_USERNAME=your_username
OPENSUBTITLES_PASSWORD=your_password
```

### Instalación
1. Clonar el repositorio
2. Instalar dependencias: `composer install`
3. Copiar `.env.example` a `.env`
4. Configurar variables de entorno
5. Ejecutar migraciones: `php artisan migrate`
6. Iniciar el servidor: `php artisan serve`

## Características Técnicas

### Detección de Idioma
- Utiliza FFmpeg para extraer metadatos de audio
- Analiza etiquetas de idioma en pistas de audio
- Ignora pistas marcadas como "undefined"
- Prioriza la detección de idiomas no españoles

### Gestión de Subtítulos
- Búsqueda basada en hash y tamaño del archivo
- Soporte para múltiples formatos (.srt, .sub, .ssa, .ass, .vtt)
- Verificación de subtítulos existentes
- Manejo de errores y reintentos

### Interfaz de Usuario
- Diseño responsive con Bootstrap 5
- Paginación del lado del servidor
- Ordenamiento dinámico de columnas
- Tooltips informativos
- Indicadores de estado visuales

## Buenas Prácticas Implementadas

### Seguridad
- Validación de rutas de directorio
- Sanitización de entradas
- Manejo seguro de tokens de API
- Protección CSRF

### Rendimiento
- Caché de tokens de API
- Paginación eficiente
- Optimización de consultas a base de datos
- Manejo asíncrono de operaciones largas

### Mantenibilidad
- Código modular y bien documentado
- Separación clara de responsabilidades
- Logging detallado para debugging
- Manejo consistente de errores

## Logging y Debugging

### Niveles de Log
- INFO: Operaciones normales
- WARNING: Situaciones inesperadas pero manejables
- ERROR: Errores que requieren atención
- DEBUG: Información detallada para desarrollo

### Archivos de Log
- `storage/logs/laravel.log`: Log principal de la aplicación
- Logs específicos para operaciones de FFmpeg y API

## Contribución
1. Fork el repositorio
2. Crear una rama para tu feature
3. Commit tus cambios
4. Push a la rama
5. Crear un Pull Request

## Licencia
Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.
