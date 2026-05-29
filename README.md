# MIME Guard

**Stop trusting file extensions. Validate what's actually inside.**

[![Statamic 6.x](https://img.shields.io/badge/Statamic-6.x-FF269E?style=flat-square)](https://statamic.com)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square)](https://php.net)
[![License MIT](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)
[![Tests](https://img.shields.io/github/actions/workflow/status/arz-cle/mime-guard/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/arz-cle/mime-guard/actions)

---

## The problem

A `.jpg` file can contain PHP code. A `.png` can be a ZIP archive. File extensions are trivial to fake, and Statamic doesn't inspect file contents by default. One malicious upload is all it takes.

MIME Guard reads the actual file bytes to determine what a file really is, then enforces your rules.

## Features

- **Real MIME detection** -- uses magic bytes (`finfo`), not file extensions
- **Hierarchical rules** -- Global > Container > Blueprint, most specific wins
- **Wildcards** -- `image/*`, `video/*`, `document/*`, `archive/*`
- **Native CP interface** -- tabs, toggle switches, built with Statamic 6 components
- **Blocked upload logging** -- MIME type, filename, user, and container
- **Secure defaults** -- SVG, PDF, archives, and binaries blocked out of the box

## Installation

```bash
composer require arz-cle/mime-guard
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=mime-guard-config
```

## Quick start

### Via the Control Panel

Navigate to **Tools > MIME Guard**. Four tabs let you configure everything visually:

| Tab | Purpose |
|-----|---------|
| **Global** | Block MIME types across all uploads |
| **Containers** | Override rules per asset container |
| **Blueprints** | Override rules per collection blueprint |
| **Help** | Rule hierarchy and wildcard reference |

### Via config file

```php
// config/mime-guard.php

return [
    'restricted_by_default' => [
        'application/octet-stream',
        'application/zip',
        'image/svg+xml',
        'application/pdf',
    ],

    'containers' => [
        'documents' => [
            'allow' => ['application/pdf'],
        ],
        'gallery' => [
            'allow' => ['image/*'],
            'deny'  => ['image/svg+xml'],
        ],
    ],

    'blueprints' => [
        'products::3d_viewer' => [
            'allow' => ['model/stl', 'model/gltf-binary'],
        ],
    ],

    'logging' => [
        'enabled' => true,
        'channel' => 'stack',
    ],
];
```

## How rules work

Rules cascade from general to specific. Each level can `allow`, `deny`, or `inherit: false` to start fresh.

```
Global          Blocks SVG, PDF, ZIP everywhere
  Container     Allows PDF in "documents" container
    Blueprint   Allows STL in "products::3d_viewer" blueprint
```

A type allowed at a more specific level overrides a global block. A type denied at a more specific level overrides a parent allow.

### Wildcards

| Pattern | Matches |
|---------|---------|
| `image/*` | JPEG, PNG, GIF, WebP, SVG, etc. |
| `video/*` | MP4, WebM, QuickTime, etc. |
| `document/*` | PDF, Word, Excel, PowerPoint, TXT, RTF |
| `archive/*` | ZIP, RAR, 7Z, TAR, GZIP |

## Screenshots

> Screenshots coming soon.

<!--
![Global restrictions](screenshots/global.png)
![Container rules](screenshots/containers.png)
![Blueprint rules](screenshots/blueprints.png)
-->

## Commands

```bash
composer test              # Run 100 tests (unit + feature + security)
composer lint              # Fix code style
composer lint-check        # Check code style
```

## Requirements

| Dependency | Version |
|------------|---------|
| Statamic | 6.x |
| PHP | 8.2+ |
| Laravel | 11.x / 12.x |

## License

MIT -- see [LICENSE](LICENSE) for details.

Built by [Clement Arzoumanian](https://arzoumanian.fr) for [Statamic](https://statamic.com).
