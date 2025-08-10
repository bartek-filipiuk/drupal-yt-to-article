# YT to Article Module

A Drupal 11 module that converts YouTube videos to articles using the PocketFlow API with real-time WebSocket updates.

## Features

- Convert YouTube videos to comprehensive articles
- Real-time progress updates via WebSocket
- AJAX-powered form submission
- Customizable article generation options (length, style)
- Block placement support
- Secure API token management
- PHP 8.4 optimized code

## Requirements

- Drupal 11.x
- PHP 8.4 or higher
- Composer
- PocketFlow API access (API token required)

## Installation

1. **Download the module**
   ```bash
   cd /path/to/drupal/modules/custom
   git clone [repository-url] yt_to_article
   ```

2. **Install dependencies**
   ```bash
   cd yt_to_article
   composer install
   ```

3. **Configure API token in settings.php**
   ```php
   // Add to your sites/default/settings.php
   $settings['yt_to_article'] = [
     'api_token' => 'yt_your_token_here',
     'api_url' => 'http://localhost:8000/api/v1',
     'websocket_url' => 'ws://localhost:8000/api/v1/ws',
   ];
   ```

4. **Enable the module**
   ```bash
   drush en yt_to_article -y
   ```

5. **Set permissions**
   - Go to `/admin/people/permissions`
   - Grant "Access YouTube to Article form" to desired roles

## Usage

### Method 1: Direct Form Access
Navigate to `/yt-to-article` to access the conversion form.

### Method 2: Block Placement
1. Go to `/admin/structure/block`
2. Click "Place block"
3. Search for "YT to Article form"
4. Configure block visibility and save

### Using the Form
1. Enter a YouTube URL (supported formats):
   - `https://www.youtube.com/watch?v=VIDEO_ID`
   - `https://youtu.be/VIDEO_ID`
   - `https://www.youtube.com/embed/VIDEO_ID`

2. (Optional) Configure advanced options:
   - Maximum article length (500-10000 words)
   - Writing style (informative, casual, technical, academic)

3. Click "Generate Article"

4. Monitor real-time progress through:
   - Connection status
   - Processing stages (Connected → Transcription → Processing → Composing → Complete)
   - Progress percentage
   - Detailed status messages

5. Once complete, download the generated article as Markdown

## Configuration

### Settings.php Configuration
```php
$settings['yt_to_article'] = [
  // Required: API authentication token
  'api_token' => 'yt_your_token_here',
  
  // Optional: Override default API URL
  'api_url' => 'http://localhost:8000/api/v1',
  
  // Optional: Override default WebSocket URL
  'websocket_url' => 'ws://localhost:8000/api/v1/ws',
];
```

### Module Configuration
Default settings can be modified at `/admin/config/services/yt-to-article` (requires "Administer YT to Article" permission).

## API Integration

### Endpoints Used
- `POST /api/v1/article/` - Submit YouTube URL for processing
- `GET /api/v1/article/{request_id}` - Check generation status
- `GET /api/v1/article/{request_id}/markdown` - Download generated article
- `WS /api/v1/ws/article/{request_id}` - WebSocket for real-time updates

### Rate Limiting
The API enforces a rate limit of 1 request per minute. The module handles rate limit responses gracefully and displays retry information to users.

## WebSocket Messages

The module processes the following WebSocket message stages:
- `connected` - WebSocket connection established
- `transcription` - Video transcription in progress
- `chunks` - Processing transcript chunks
- `composition` - Composing final article
- `finished` - Generation complete
- `error`/`failed` - Error occurred

## Troubleshooting

### WebSocket Connection Issues
1. Verify WebSocket URL in settings.php
2. Check browser console for connection errors
3. Ensure firewall allows WebSocket connections
4. Try using `wss://` for secure connections

### API Authentication Errors
1. Verify API token in settings.php
2. Ensure token format is correct (should start with `yt_`)
3. Check token hasn't expired

### Form Not Appearing
1. Clear Drupal cache: `drush cr`
2. Verify module is enabled: `drush pml | grep yt_to_article`
3. Check user permissions

## Development

### Module Structure
```
yt_to_article/
├── src/
│   ├── Form/          # Form classes
│   ├── Service/       # API and WebSocket clients
│   ├── Controller/    # Route controllers
│   ├── Plugin/Block/  # Block plugins
│   ├── Exception/     # Custom exceptions
│   └── ValueObject/   # Data transfer objects
├── js/                # JavaScript files
├── css/               # Stylesheets
├── templates/         # Twig templates
├── config/            # Configuration schema
└── tests/             # PHPUnit tests
```

### PHP 8.4 Features Used
- Constructor property promotion
- Readonly properties
- Named arguments
- Match expressions
- Improved type system
- Null-safe operator

### Running Tests
```bash
cd /path/to/drupal
./vendor/bin/phpunit -c core modules/custom/yt_to_article/tests
```

## Security Considerations

- API tokens are stored in settings.php (not in database)
- All user input is validated and sanitized
- CSRF protection on forms
- XSS prevention in output
- Rate limiting awareness

## Support

For issues or feature requests, please use the issue tracker.

## License

This module is licensed under the same license as Drupal (GPL-2.0+).