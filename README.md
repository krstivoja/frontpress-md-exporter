# FrontPress MD Exporter

**Transform your WordPress content into production-ready Markdown files.**

Perfect for migrating to static site generators (Hugo, Jekyll, Next.js), building documentation systems, or creating content backups. Export posts, pages, custom post types, taxonomies, ACF fields, and media — all organized and ready to use.

---

## Why FrontPress MD Exporter?

✅ **Clean Markdown Output** - WordPress content converted to properly formatted Markdown with YAML front matter
✅ **Preserves Everything** - Posts, pages, custom post types, taxonomies, custom fields, and media
✅ **ACF Ready** - Full Advanced Custom Fields support with automatic field type detection
✅ **Multisite Support** - Export entire WordPress networks with per-site organization
✅ **Smart Media Handling** - Automatic media collection, URL rewriting, and proper folder structure
✅ **Flexible Configuration** - Map fields, taxonomies, and post types exactly how you need them
✅ **One-Click Download** - Get everything in a single, organized ZIP file

---

## Use Cases

### 🚀 Migrate to Static Site Generators
Export your WordPress content to Markdown and move to Hugo, Jekyll, Gatsby, or Next.js. Front matter is automatically formatted for static site generators.

### 📚 Create Documentation Systems
Export documentation, knowledge bases, or help centers to Markdown for use with Docusaurus, MkDocs, or VuePress.

### 💾 Content Backups
Create human-readable, version-controllable backups of your WordPress content. No more database exports — just clean Markdown files.

### 🔄 Content Migrations
Move content between WordPress sites, platforms, or systems. Export once, import anywhere.

---

## Features

### 📝 Markdown Conversion
- Clean, readable Markdown from WordPress HTML
- Proper heading hierarchy
- Code blocks, lists, blockquotes preserved
- Links and images converted correctly

### 🏷️ Taxonomies & Metadata
- Categories, tags, and custom taxonomies exported to front matter
- Choose slug or name format
- Configure custom front matter keys

### 🎨 Advanced Custom Fields
- Full ACF support (text, number, boolean, dates)
- Image, gallery, and file fields (media collected automatically)
- Relationships, post objects, repeaters, groups, flexible content
- Automatic type detection and conversion

### 📸 Media Management
- All images, videos, and attachments collected
- URLs rewritten to local paths
- Organized per-post media folders
- Featured images preserved

### 🌐 Multisite Networks
- Export entire networks at once
- Per-site configuration respected
- Organized folder structure
- Subsite selection control

### ⚙️ Custom Field Mapping
- Map any post meta to front matter
- Configure field names and types
- Automatic type coercion

---

## Installation

### From GitHub Release (Recommended)

1. Download the latest release ZIP from [Releases](https://github.com/krstivoja/frontpress-md-exporter/releases)
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Upload the ZIP file and activate

### Requirements

- WordPress 5.8+
- PHP 8.0+
- Multisite support (optional, for network exports)

---

## Quick Start

### Single Site Export

1. Go to **WordPress Admin → MD Export**
2. **Post Types tab**: Choose which post types to export (posts, pages, products, etc.)
3. **Taxonomies tab**: Select which taxonomies to include (categories, tags, etc.)
4. **ACF tab**: Configure Advanced Custom Fields (if using ACF)
5. **Meta / Fields tab**: Map custom meta fields to front matter
6. Click **Run Export** and download your ZIP

### Multisite Network Export

1. Go to **Network Admin → MD Export**
2. Select which subsites to export
3. Click **Start Export**
4. Download the complete network ZIP

Each subsite uses its own configuration from the individual site's admin panel.

---

## Export Output

### Single Site Structure

```
site/
├── config.json              # Export metadata
└── content/
    ├── blog/                # Posts (folder name is configurable)
    │   ├── my-post.md
    │   └── my-post/         # Media files for this post
    │       └── image.jpg
    ├── pages/               # Pages
    │   └── about.md
    └── products/            # Custom post type
        └── product-1.md
```

### Multisite Network Structure

```
site/
├── config.json
└── content/
    ├── site-1/              # First subsite
    │   ├── blog/
    │   ├── pages/
    │   └── products/
    ├── marketing/           # Second subsite
    │   ├── blog/
    │   └── pages/
    └── support/             # Third subsite
        └── docs/
```

### Markdown File Format

Each post becomes a Markdown file with YAML front matter:

```markdown
---
title: "Getting Started with WordPress"
slug: getting-started-with-wordpress
date: 2024-01-15T10:30:00+00:00
author: admin
status: publish
categories:
  - tutorials
  - wordpress
tags:
  - beginner
  - guide
featured_image: /uploads/blog/getting-started/featured.jpg
excerpt: "Learn the basics of WordPress in this beginner-friendly guide."
---

# Getting Started with WordPress

WordPress is a powerful content management system...

![Dashboard Screenshot](/uploads/blog/getting-started/dashboard.jpg)
```

---

## Configuration Guide

### Post Types

Control which content types to export:

- **Include** - Enable/disable export for this post type
- **Folder** - Output directory name (e.g., "blog" for posts, "docs" for custom type)

### Taxonomies

Map WordPress taxonomies to front matter fields:

- **Include** - Export this taxonomy
- **Target** - Front matter key name (e.g., "categories", "topics")
- **Value Format** - Use slugs or display names

### ACF Fields

Configure how ACF fields appear in front matter:

- Automatic type detection (text, number, boolean, dates)
- Media fields trigger automatic file collection
- Relationships and post objects preserved
- Nested fields (repeaters, groups) supported

### Custom Meta Fields

Map any post meta to front matter:

- Specify source meta key and target front matter key
- Automatic type detection and coercion

---

## Support & Documentation

- **Issues**: [GitHub Issues](https://github.com/krstivoja/frontpress-md-exporter/issues)
- **Development**: See [DEV.md](DEV.md) for technical documentation
- **Website**: [DPlugins](https://dplugins.com)

---

## Credits

Built by **[Marko Krstić](https://markokrstic.com)** · Part of the **[DPlugins](https://dplugins.com)** family

---

## License

GPL-2.0-or-later
