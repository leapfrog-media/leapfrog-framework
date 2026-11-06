# Leapfrog Framework

Design system editor for Bricks, by Leapfrog Media. You enter brand inputs (colours, fonts, base sizes); Leapfrog generates native Bricks data: colour palettes with light and dark values, fluid type and space scales, and semantic variables. Bricks stores it, outputs the CSS and shows it in its own pickers, so pages keep working if Leapfrog is switched off.

Spec: https://claude.ai/artifact/2JUJwA5pZw76f4LqcXFXzq

## Requirements

- WordPress 6.9+ (Abilities API), PHP 8.1+
- Bricks 2.4+ as the active theme

Bricks' AI / Abilities API switch does not need to be on. When it is, Leapfrog uses the registered abilities; when it is off, Leapfrog calls the same Bricks methods directly after their own permission checks (see `src/Bricks/BricksGateway.php`).

## Where things are

| Path | What it is |
| --- | --- |
| `leapfrog-framework.php` | Plugin header, constants, autoloader |
| `src/Plugin.php` | Wires the parts together |
| `src/Requirements.php` | Checks Bricks is active and new enough |
| `src/Bricks/BricksGateway.php` | The only code that talks to Bricks' design data |
| `src/Settings/SettingsStore.php` | Leapfrog's own settings (option `lf_framework`), with a revision lock |
| `src/Rest/RestController.php` | REST routes under `leapfrog/v1` (admins only) |
| `src/Admin/FrameworkPage.php` | Leapfrog > Framework admin page |
| `assets/editor/` | Editor app source (React, WordPress components) |
| `build/` | Built editor app (committed, so the plugin works without a build) |

## Building the editor

```
npm install
npm run build
```

`npm run start` rebuilds on every change while developing.

## REST routes

- `GET /wp-json/leapfrog/v1/status`
- `GET /wp-json/leapfrog/v1/bricks/variables`
- `GET /wp-json/leapfrog/v1/settings`
- `POST /wp-json/leapfrog/v1/settings` with `{ settings, revision }`
