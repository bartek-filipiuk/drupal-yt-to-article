# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Common Development Commands

### Initial Setup
```bash
# Install dependencies (required before any other commands work)
ddev composer install

# After composer install, drush will be available at vendor/bin/drush
# Configure database and install Drupal
ddev drush site:install

# Clear all caches
ddev drush cr
```

### Module Management
```bash
# Enable a module
ddev drush en MODULE_NAME -y

# Disable a module
ddev drush pm:uninstall MODULE_NAME

# List all modules
ddev drush pml
```

### Configuration Management
```bash
# Export configuration
ddev drush config:export

# Import configuration
ddev drush config:import

# Check configuration status
ddev drush config:status
```

### Testing
```bash
# Run PHPUnit tests for a module
./vendor/bin/phpunit -c core modules/custom/MODULE_NAME/tests

# Run coding standards check
./vendor/bin/phpcs --standard=Drupal modules/custom/MODULE_NAME
```

## Architecture Overview

This is a Drupal 11 project with custom modules for YouTube-to-article conversion functionality. The codebase follows Drupal's standard directory structure with web root at `/web`.

### Key Directories

- **`/web`**: Document root containing Drupal core and all modules/themes
  - **`/web/core`**: Drupal core (do not modify)
  - **`/web/modules/custom`**: Custom modules developed for this project
  - **`/web/modules/contrib`**: Community contributed modules installed via Composer
  - **`/web/themes/custom`**: Custom themes (oba_theme, pf_tech)
  - **`/web/themes/contrib`**: Contributed themes (bootstrap_barrio)
  - **`/web/sites/default`**: Site configuration and settings.php

### Custom Modules

**yt_to_article**: Main module for YouTube video to article conversion
- Integrates with PocketFlow API for AI-powered article generation
- WebSocket support for real-time progress updates
- AJAX form handling with progressive enhancement
- Service-oriented architecture with dependency injection
- API token stored securely in settings.php (not database)

**yt_to_article_admin**: Administrative features and cost metrics
- Tracks API usage costs and metrics
- Provides reporting dashboard for cost analysis
- Webhook endpoint for receiving cost data

**tailwind_style_editor**: Visual style editing capabilities
- Allows real-time CSS/Tailwind class editing
- Integrates with theme system for live preview

### Custom Themes

**pf_tech**: Modern, minimalist theme
- Custom CSS architecture with component-based styles
- YouTube article-specific templates and styling
- Dark mode support
- Toast notifications system

**oba_theme**: Bootstrap Barrio subtheme
- Bootstrap 5 integration
- Gulp-based build process
- SCSS compilation workflow

### Configuration Management

Configuration is stored in `/config/sync` and managed through Drupal's configuration management system. This includes:
- Content types (youtube_article, article_cost_metrics)
- Field definitions for tracking costs, tokens, and metadata
- Block configurations for multiple themes
- View configurations for content display

### API Integration Pattern

The yt_to_article module demonstrates best practices for external API integration:
1. Service classes encapsulate API logic (`YtToArticleApiClient`, `YtToArticleWebSocketClient`)
2. Custom exceptions for error handling (`ApiException`, `RateLimitException`, etc.)
3. Value objects for data transfer (`ArticleResponse`, `WebSocketMessage`)
4. Secure token management via settings.php configuration
5. WebSocket integration for real-time updates

### Security Considerations

- API tokens stored in settings.php (outside version control)
- All user input validated and sanitized
- CSRF protection on all forms
- XSS prevention in Twig templates
- Rate limiting awareness in API client

### Module Development Patterns

When developing modules in this codebase:
1. Use service-oriented architecture with dependency injection
2. Follow Drupal coding standards (enforced via PHPCS)
3. Implement proper permission checks
4. Use Drupal's Form API for user input
5. Leverage Drupal's AJAX framework for dynamic interfaces
6. Store sensitive configuration in settings.php, not config files
