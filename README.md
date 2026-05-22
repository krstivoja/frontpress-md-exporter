# FrontPress MD Exporter

Export WordPress sites to Markdown files with front matter, taxonomies, ACF fields, and media — perfect for static site generators, documentation systems, or content migrations.

## Features

- 📝 **Markdown Export** - Convert WordPress posts, pages, and custom post types to clean Markdown with YAML front matter
- 🏷️ **Taxonomies** - Export categories, tags, and custom taxonomies
- 🎨 **ACF Support** - Full Advanced Custom Fields integration with field mapping
- 📸 **Media Handling** - Automatic media collection and URL rewriting
- 🔧 **Custom Fields** - Map WordPress meta fields to front matter
- 🌐 **Multisite Ready** - Export entire WordPress networks with per-site organization
- ⚙️ **Flexible Mapping** - Configure which post types and fields to export
- 📦 **ZIP Download** - Get everything in a single, organized archive

## Requirements

- WordPress 5.8 or higher
- PHP 8.0 or higher
- Node.js 18+ (for building from source)

## Installation

### From Release

1. Download the latest release ZIP from [Releases](../../releases)
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the ZIP file and activate

### From Source

```bash
git clone https://github.com/yourusername/frontpress-md-exporter.git
cd frontpress-md-exporter
npm install
composer install
npm run build
```

## Usage

### Single Site Export

1. Go to **WordPress Admin → MD Export**
2. Configure which post types to export in the **Post Types** tab
3. Map taxonomies in the **Taxonomies** tab
4. Configure ACF fields (if using ACF) in the **ACF** tab
5. Add custom meta field mappings in the **Meta / Fields** tab
6. Click **Run Export** and download your ZIP

### Multisite Network Export

1. Go to **Network Admin → MD Export**
2. Select which subsites to include
3. Click **Start Export**
4. Download the network-wide ZIP

Each subsite uses its own configured settings from the individual site's admin panel.

## Export Structure

### Single Site

```
site/
├── config.json
└── content/
    ├── blog/                  # Posts (configurable folder name)
    │   ├── my-post.md
    │   └── my-post/          # Media for this post
    │       └── image.jpg
    ├── pages/                # Pages
    │   └── about.md
    └── products/             # Custom post type
        └── product-1.md
```

### Multisite Network

```
site/
├── config.json
└── content/
    ├── site-1/               # First subsite
    │   ├── blog/
    │   ├── pages/
    │   └── products/
    ├── marketing/            # Second subsite
    │   ├── blog/
    │   └── pages/
    └── support/              # Third subsite
        └── docs/
```

## Markdown Format

Each exported post becomes a Markdown file with YAML front matter:

```markdown
---
title: "My Blog Post"
slug: my-blog-post
date: 2024-01-15T10:30:00+00:00
author: admin
status: publish
categories:
  - tech
  - wordpress
tags:
  - markdown
  - export
featured_image: /uploads/blog/my-blog-post/featured.jpg
custom_field: "Custom value from ACF or meta"
---

# My Blog Post

Post content converted to clean Markdown...

![Image](/uploads/blog/my-blog-post/image.jpg)
```

## Configuration

### Post Types

Configure which post types to export and their output folder names:

- **Include** - Enable/disable export for this post type
- **Folder** - Output directory name (e.g., "blog" for posts, "pages" for pages)

### Taxonomies

Map WordPress taxonomies to front matter fields:

- **Include** - Export this taxonomy
- **Target** - Front matter key name
- **Value Format** - Slugs or names

### ACF Fields

Map ACF fields to front matter with automatic type detection:

- Text, number, boolean fields
- Image/file/gallery (media collection)
- Relationships, post objects
- Repeaters, groups, flexible content

### Meta Fields

Map custom post meta to front matter:

- Configure field name and target key
- Automatic type coercion

## Development

### Build

```bash
npm run build          # Production build
npm run dev            # Development build with watch
```

### Dependencies

- **PHP**: PSR-4 autoloading via Composer
- **JavaScript**: React, esbuild
- **Markdown**: league/html-to-markdown
- **YAML**: symfony/yaml

## Release Process

Releases are automated via GitHub Actions:

1. Update version in `frontpress-md-exporter.php`
2. Commit changes
3. Create and push a tag:
   ```bash
   git tag 0.2.0
   git push origin 0.2.0
   ```
4. GitHub Actions will automatically:
   - Build assets
   - Create a release ZIP
   - Publish the release

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

GPL-2.0-or-later

## Credits

Built by [Marko Krstić](https://markokrstic.com) · [DPlugins](https://dplugins.com)

Part of the FanCoolo plugin family.
