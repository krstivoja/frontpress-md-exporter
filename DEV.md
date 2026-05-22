# FrontPress MD Exporter - Developer Documentation

## Project Structure

```
frontpress-md-exporter/
├── frontpress-md-exporter.php    # Main plugin file
├── app/                          # PHP source (PSR-4)
│   ├── Plugin.php                # Bootstrap
│   ├── Settings/                 # Settings pages & mapping
│   ├── Network/                  # Multisite export
│   │   ├── AjaxHandler.php       # AJAX endpoints
│   │   └── Exporter.php          # Network export logic
│   └── Exporters/                # Export engines
├── src/                          # React source
│   ├── single/                   # Single-site UI
│   └── network/                  # Network admin UI
├── dist/                         # Built JS/CSS
└── vendor/                       # Composer dependencies

```

## Architecture

### PHP (PSR-4 under `FrontPressMdExp` namespace)

**Plugin Bootstrap** (`Plugin.php`)
- Registers admin pages
- Loads settings
- Initializes exporters

**Settings System** (`Settings/`)
- `Mapping.php` - Centralized settings access
- Post type, taxonomy, ACF, and meta field configuration

**Single Site Export** (`Exporters/`)
- Export engine for individual sites
- Markdown conversion (via `league/html-to-markdown`)
- Media collection and URL rewriting
- ZIP archive creation

**Network Export** (`Network/`)
- `Exporter.php` - Multisite batch export with progress tracking
- `AjaxHandler.php` - AJAX endpoints (`fps_network_start`, `fps_network_tick`, `fps_network_finalize`)
- Batched processing to avoid timeouts
- Per-site configuration inheritance

### JavaScript (React + esbuild)

**Build System**
- esbuild for fast bundling
- Separate bundles for single/network admin
- Development mode with watch: `npm run dev`

**Single Site UI** (`src/single/`)
- Settings tabs (Post Types, Taxonomies, ACF, Meta Fields)
- Export button with progress tracking

**Network UI** (`src/network/`)
- Subsite selection
- Network-wide export with real-time progress
- Download management

## Development Setup

```bash
# Install dependencies
npm install
composer install

# Build for development (with watch)
npm run dev

# Build for production
npm run build
```

## AJAX Export Flow (Network)

The network export uses a custom AJAX handler instead of REST API to work around CloudPanel/ModSecurity blocking issues.

### Output Buffer Workaround

WordPress's admin-ajax.php can have active output buffers that capture and discard echoed content. The `AjaxHandler::sendJson()` method clears all buffers before sending responses:

```php
// Clear ALL output buffers that WordPress might have created
while (ob_get_level() > 0) {
    ob_end_clean();
}
```

### Export Flow

1. **Start** (`fps_network_start`)
   - Receives selected site IDs
   - Creates export run with unique ID
   - Returns run metadata

2. **Tick** (`fps_network_tick`)
   - Processes batch of posts (default 20)
   - Returns progress update
   - Client polls until `done: true`

3. **Finalize** (`fps_network_finalize`)
   - Creates ZIP archive
   - Returns download URL

## Configuration Files

### `.distignore`

Controls which files are excluded from GitHub release ZIPs:

- `/src` - Exclude plugin source (we ship built `/dist/`)
- `node_modules/` - No development dependencies
- `.git/`, `.github/` - No version control files

**Important**: Use `/src` (with leading slash) to only exclude the root src folder, NOT vendor library src folders like `vendor/league/html-to-markdown/src/`.

### `.gitignore`

Controls which files are committed to Git:

- `/node_modules/` - Never commit npm packages
- `/dist/` - Built assets (generated from src)
- `.DS_Store` - macOS metadata

**Note**: `/vendor/` is committed because GitHub releases need composer dependencies.

## Release Process

Releases are automated via GitHub Actions (`.github/workflows/release.yml`):

1. Update version in `frontpress-md-exporter.php`:
   ```php
   * Version: 0.2.7
   define('FPS_MDEXP_VERSION', '0.2.7');
   ```

2. Commit and tag:
   ```bash
   git add frontpress-md-exporter.php
   git commit -m "v0.2.7: Description"
   git tag -a 0.2.7 -m "v0.2.7: Description"
   git push origin main
   git push origin 0.2.7
   ```

3. GitHub Actions will:
   - Run `npm ci && composer install --no-dev --optimize-autoloader`
   - Build production assets
   - Create release ZIP using rsync with `.distignore` patterns
   - Publish GitHub release

## Dependencies

### PHP (Composer)

- `league/html-to-markdown` - HTML to Markdown conversion
- `symfony/yaml` - YAML front matter generation

### JavaScript (npm)

- `react`, `react-dom` - UI framework
- `esbuild` - Bundler
- `@wordpress/i18n` - Internationalization

## Testing

### Local Multisite Setup

Use LocalWP or similar to create a multisite installation:

```
http://docsdplugins.local/wp-admin/network/admin.php?page=fps-mdexp-network
```

### Debugging AJAX

Network export logs are visible in PHP error logs:

```bash
tail -f /path/to/error.log
```

## Common Issues

### "HTML-to-Markdown dependency is missing"

This happens when vendor source files are missing from the release ZIP. Check:

1. `.distignore` has `/src` (with leading slash), not `src`
2. `vendor/league/html-to-markdown/src/*.php` files exist in the ZIP
3. GitHub Actions ran successfully

### Empty AJAX Responses

WordPress may have active output buffers. The fix is in `AjaxHandler::sendJson()` which clears all buffers before sending.

### Export Timeouts

Network exports process in batches (default 20 posts). Large sites with many attachments may need smaller batches:

```javascript
// Adjust in network UI
const BATCH_SIZE = 10; // Lower for sites with heavy media
```

## Code Standards

- **PHP**: PSR-4 autoloading, strict types, type hints
- **JavaScript**: Modern ES6+, React hooks
- **Formatting**: Follow WordPress coding standards
- **i18n**: All user-facing strings wrapped with `__()` or `esc_html__()`

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test on both single and multisite WordPress installations
5. Submit a pull request

## License

GPL-2.0-or-later
