# S2 — Simple and Fast CMS

S2 is a simple and fast website engine (or CMS — Content Management System) written in PHP.
It is distributed for free under the MIT license.
It is best suited for small to medium-sized content websites.
S2’s undeniable advantages include a convenient
[administrative interface](https://github.com/parpalak/s2/wiki/Control-Panel)
and high performance.
The engine provides a minimal set of essential features,
while additional functionality can be implemented via
[extensions](https://github.com/parpalak/s2/wiki/Extensions).

**Key advantages:**
- **User-friendly**: Intuitive [control panel](https://github.com/parpalak/s2/wiki/Control-Panel) for easy content management and comment moderation.
- **Reliable**: Auto-recovery after browser crashes or power outages.
- **Fast**: Optimized for high performance.
- **Free & open-source**: Licensed under the MIT license, allowing unrestricted use for any project.
- **Low system requirements**: PHP + MySQL/PostgreSQL/SQLite.
- **Team collaboration**: Role-based access (authors, moderators, editors, admins).
- **Extensible**: Plugins for added functionality (search, blog, etc.).
- **Minimalist**: Focuses on essential features (80/20 principle).

[Learn more in documentation](https://github.com/parpalak/s2/wiki/Features)

## Server Requirements

- **Web server**
- **PHP** 8.2 or higher.
- One of supported databases:
    - **MySQL** (tested on MariaDB 10.5 and higher, MySQL 8.0 and higher),
    - **PostgreSQL** (tested on 14),
    - **SQLite** (tested on 3.37).

## Installation and upgrade

```bash
git clone https://github.com/parpalak/s2.git
cd s2

composer install # for local development and running tests
# or
composer install --no-dev -o # for production
```

See [details in the documentation](https://github.com/parpalak/s2/wiki/Installation).

## Documentation

### For users
- [Installation and upgrade](https://github.com/parpalak/s2/wiki/Installation)
- [Configuration](https://github.com/parpalak/s2/wiki/Configuration)
- [Control panel](https://github.com/parpalak/s2/wiki/Control-Panel)

### For webmasters

- [Language packs](https://github.com/parpalak/s2/wiki/Language-Packs)
- [Styles](https://github.com/parpalak/s2/wiki/Styles)
- [Templates](https://github.com/parpalak/s2/wiki/Templates)

### For developers

- [Architecture Overview](_doc/architecture.md)
- [Comments](_doc/comments.md)
- [Extensions](_doc/extensions.md)