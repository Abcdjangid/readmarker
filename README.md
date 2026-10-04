# ReadFlow

ReadFlow is a free, open-source WordPress plugin for reading time, reading progress, and reader preferences. All features are included, with no account, paid upgrade, analytics, or external service required.

**Requirements:** WordPress 6.0 or later and PHP 7.4 or later. Current version: **0.1.0**.

## Features

- Estimated reading time and word count, with adjustable reading speed and four reading-information styles.
- Twelve reading-progress displays, sharing the same article progress state.
- Optional Reader Controls for text size, reading width, and resetting preferences.
- Optional Reading Position Memory with an explicit Continue reading prompt.
- Six editable starting presets: Minimal, Progress, Focus, Detailed, Milestones, and Balanced.
- Automatic placement before or after content, shared Custom CSS Selector placement, and manual placement.
- Content-type rules and per-post controls for global settings, disabling automatic output, or overriding supported settings.
- Five shortcodes, a dynamic ReadFlow Gutenberg block, and an admin shortcode reference with copy buttons and output previews.

### Display modes

| Mode | Output |
| --- | --- |
| Top Bar | A bar at the top of the window |
| Circular | A progress ring and percentage |
| Percentage | Reading progress as a percentage |
| Top Bar + Percentage | Both indicators |
| Remaining Time | Estimated reading time remaining |
| Reading Time + Remaining | Total and remaining reading time |
| Percentage + Remaining | Progress and remaining time |
| Countdown | Scroll-based remaining time in clock format |
| Floating Widget | Percentage, remaining time, and a small bar |
| Estimated Finish Time | A finish-time estimate using the reader's local clock |
| Reading Milestones | Reversible progress milestones |
| Inline Reading Progress | A bar and percentage inside article content |

Remaining-time displays follow reading progress; Countdown is not a running timer.

## Installation

1. Place this project's source in `wp-content/plugins/readflow` in a WordPress installation. If downloading a GitHub source archive, rename the extracted plugin folder to `readflow`.
2. Activate **ReadFlow** from **Plugins**.
3. Open **ReadFlow → Settings**.

The admin menu contains **Overview**, **Settings**, and **Shortcodes**. The WordPress.org production ZIP is managed separately and is not committed here.

## Basic usage

By default, ReadFlow displays reading time before individual posts using 200 words per minute. Word count, progress, Reader Controls, and position memory are off. Enable pages or other supported public content types in Display Rules.

Apply a preset for a starting configuration, then customize the normal Settings fields. Presets do not lock settings. Active status is derived from the saved values each preset controls, and unrelated preferences are preserved.

Enable reading progress and select a display. Top Bar remains the default selection. Inline Reading Progress follows automatic placement, while fixed progress displays have their own enable setting.

For Custom Selector placement, provide a CSS selector for an existing page element. ReadFlow uses the first matching element. Per-post Custom Selector overrides use this same shared selector.

### Reader preferences and privacy

Reader Controls offer Text Size, Reading Width, and Reset Preferences. Reader preferences stay in browser local storage. Optional Reading Position Memory also uses browser local storage and does not create server-side reading history. Continuing from a saved position requires a reader action.

ReadFlow does not use tracking, telemetry, cookies, or remote reporting. Global settings and per-post overrides are retained when the plugin is uninstalled.

### Shortcodes and block

| Shortcode | Supported options |
| --- | --- |
| `[readflow]` | Uses effective reading-information settings |
| `[readflow_time]` | `format="short"` (default), `format="long"` |
| `[readflow_words]` | `label="true"` (default), `label="false"` |
| `[readflow_progress]` | `show_percentage="true"` (default), `show_percentage="false"` |
| `[readflow_remaining]` | `format="natural"` (default), `"short"`, `"clock"`, `"detailed"` |

Use **ReadFlow → Shortcodes** for copyable examples and previews. The **ReadFlow** block provides Reading Time, Word Count, Progress, Remaining Time, and Combined output. These use the current eligible article, rather than arbitrary post IDs.

For complete usage, placement requirements, protected contexts, and theme limitations, see [readme.txt](readme.txt).

## Development and testing

There is no build step or Node package installation. PHP, JavaScript, CSS, and block metadata are maintained directly.

Keep the project at `wp-content/plugins/readflow` inside a WordPress installation: the PHP test harness uses the surrounding WordPress core. Run commands from the plugin directory.

```sh
php tests/display-test.php
```

This runs the PHP regression suite, including calculator tests. It uses in-memory fixtures and does not load the site's database configuration or change saved settings.

Run all JavaScript suites with Node.js. In PowerShell:

```powershell
Get-ChildItem tests/*-test.js | ForEach-Object {
    node $_.FullName
    if ($LASTEXITCODE -ne 0) { throw "JavaScript test failed" }
}
```

On a POSIX shell:

```sh
for test in tests/*-test.js; do
    node "$test" || exit 1
done
```

JavaScript tests use DOM doubles. Browser checks are still needed for theme layout, keyboard interaction, clipboard permissions, and the block editor.

Development tests and tools remain in this repository. Generated packages and reports under `.release/` are ignored. `tools/package.ps1` builds an allowlisted production ZIP; run it only when intentionally preparing a new package. The existing submitted ZIP should not be rebuilt as part of routine development.

With the official Plugin Check plugin installed, `tools/plugin-check.php` can run its static checks through WP-CLI. See the development notes in [readme.txt](readme.txt). Static checks do not replace runtime or browser testing.

## Issues and contributions

Report reproducible bugs through [GitHub Issues](https://github.com/Abcdjangid/readflow/issues). Include WordPress and PHP versions, steps to reproduce, expected behavior, and the relevant theme or plugin context. Do not include credentials, private content, or database dumps.

Keep pull requests focused, preserve existing behavior, and include relevant regression coverage. Run the PHP and JavaScript suites before proposing changes. Discuss larger changes in an issue first.

## License

ReadFlow is licensed under the [GNU General Public License, version 2 or later](LICENSE) (`GPL-2.0-or-later`). Maintained by [Chitvan](https://profiles.wordpress.org/chitvan/).
