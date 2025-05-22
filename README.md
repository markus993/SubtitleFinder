# SubtitleFinder - Development Documentation

## General Description
SubtitleFinder is a web application designed to automate the search and download of subtitles for videos. The application scans directories for video files, detects their language, and searches for corresponding Spanish subtitles.

## Technology Stack

### Backend
- **Framework**: Laravel 10.x
- **PHP**: 8.1+
- **Database**: MySQL 8.0+
- **External Services**:
  - OpenSubtitles API (for subtitle search and download)
  - FFmpeg (for language detection in video files)

### Frontend
- **CSS Framework**: Bootstrap 5.3
- **JavaScript**: Vanilla JS
- **Libraries**:
  - Bootstrap Bundle (includes Popper.js for tooltips)
  - Fetch API for AJAX requests

## Architecture

### Project Structure
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

### Main Components

#### 1. VideoController
- Handles main application routes
- Manages directory scanning
- Controls video processing
- Handles pagination and result sorting

#### 2. SubtitleService
- Implements core business logic
- Manages OpenSubtitles API communication
- Performs language detection using FFmpeg
- Handles subtitle search and download

#### 3. Video Model
- Represents the main application entity
- Stores video file information
- Manages processing status
- Maintains relationship with subtitles

## Workflow

1. **Directory Scanning**
   - User provides a directory path
   - Application recursively scans for video files
   - Language is detected for each video using FFmpeg
   - Videos with Spanish audio are ignored
   - Records are created in the database

2. **Video Processing**
   - Verifies existing subtitles
   - Authenticates with OpenSubtitles API
   - Searches for Spanish subtitles
   - Downloads and saves found subtitles

## Environment Setup

### Prerequisites
- PHP 8.1 or higher
- Composer
- MySQL 8.0 or higher
- FFmpeg installed and accessible in PATH
- Node.js and NPM (for assets)

### Environment Variables
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

### Installation
1. Clone the repository
2. Install dependencies: `composer install`
3. Copy `.env.example` to `.env`
4. Configure environment variables
5. Run migrations: `php artisan migrate`
6. Start the server: `php artisan serve`

## Technical Features

### Language Detection
- Uses FFmpeg to extract audio metadata
- Analyzes language tags in audio tracks
- Ignores tracks marked as "undefined"
- Prioritizes detection of non-Spanish languages

### Subtitle Management
- Search based on hash and file size
- Support for multiple formats (.srt, .sub, .ssa, .ass, .vtt)
- Existing subtitle verification
- Error handling and retries

### User Interface
- Responsive design with Bootstrap 5
- Server-side pagination
- Dynamic column sorting
- Informative tooltips
- Visual status indicators

## Implemented Best Practices

### Security
- Directory path validation
- Input sanitization
- Secure API token handling
- CSRF protection

### Performance
- API token caching
- Efficient pagination
- Database query optimization
- Asynchronous handling of long operations

### Maintainability
- Modular and well-documented code
- Clear separation of concerns
- Detailed logging for debugging
- Consistent error handling

## Logging and Debugging

### Log Levels
- INFO: Normal operations
- WARNING: Unexpected but manageable situations
- ERROR: Issues requiring attention
- DEBUG: Detailed development information

### Log Files
- `storage/logs/laravel.log`: Main application log
- Specific logs for FFmpeg and API operations

## Contributing
1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License
This project is under the MIT License. See the `LICENSE` file for details.
