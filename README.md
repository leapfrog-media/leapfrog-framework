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
| `src/Color/Color.php` | OKLCH colour maths, gamut mapping, WCAG contrast |
| `src/Color/Palette.php` | Colour settings, shade/transparency/dark generation, rows for Bricks |
| `src/Bricks/PaletteSync.php` | Keeps the Bricks palette "Leapfrog" equal to the generated rows |
| `src/Scale/Scales.php` | Type and space scale settings, Bricks scale categories, role variables |
| `src/Bricks/ScaleSync.php` | Writes the scales (Bricks generates the values) and role variables |
| `src/Bricks/CssFiles.php` | Rebuilds Bricks' CSS files after a save |
| `src/Color/Roles.php` | Colour roles: definitions, light/dark choices, contrast, rows for the "Leapfrog roles" palette |
| `src/Color/ThemeCss.php` | Section theme CSS (`data-lf-theme`), stored on save and printed in the head |
| `src/Bricks/ThemeControl.php` | Theme control on Section/Container/Block (+ `assets/builder/theme-control.js` for the builder) |
| `src/Scale/Shape.php` | Layout and shape variables |
| `src/Bricks/VariableSet.php` | Keeps one Bricks variable category equal to a list of variables |
| `src/Build.php` | Rebuilds palette, roles palette and theme CSS together |
| `src/Scale/Components.php` | Component tokens (buttons, fields, cards, header) |
| `src/Settings/Starter.php` | Brand starter: brand choices → full settings |
| `src/Settings/History.php` | Last 20 versions of the settings |
| `src/Settings/Transfer.php` | Framework files (export/import) and presets |
| `src/Rest/SiteController.php` | Starter, history, rebuild, export/import and preset routes |
| `src/Bricks/Buttons.php` | Bricks Button controls → `lf-btn` classes (+ `assets/frontend/buttons.css`, `assets/builder/buttons.js`) |
| `src/Bricks/LogicalSizing.php` | Inline/Block size controls (+ `assets/builder/logical-sizing.*`) |
| `src/Bricks/VariablePicker.php` | Contextual variable picker data (+ `assets/builder/variable-rules.js`, `variable-picker.*`) |
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
- `POST /wp-json/leapfrog/v1/colours/preview` with `{ items }` (saves nothing)
- `POST /wp-json/leapfrog/v1/colours/save` with `{ items, revision }` (saves settings, then syncs the Bricks palette)
- `GET /wp-json/leapfrog/v1/scales` (scale settings, Bricks wins if edited there; previews; Style Manager basics)
- `POST /wp-json/leapfrog/v1/scales/preview` with `{ typography, spacing }` (Bricks generates, nothing saved)
- `POST /wp-json/leapfrog/v1/scales/save` with `{ typography, spacing, revision }`
- `POST /wp-json/leapfrog/v1/style-manager/save` with `{ minScreenWidth, maxScreenWidth, defaultMode }`
- `GET /wp-json/leapfrog/v1/roles`, `POST …/roles/preview` with `{ map }`, `POST …/roles/save` with `{ map, pageStyles, revision }`
- `GET /wp-json/leapfrog/v1/shape`, `POST …/shape/save` with `{ corners, values, revision }`
- `GET /wp-json/leapfrog/v1/components`, `POST …/components/save` with `{ values, revision }`
- `GET /wp-json/leapfrog/v1/starter`, `POST …/starter/preview` with `{ inputs }`, `POST …/starter/apply` with `{ inputs, revision }`
- `GET /wp-json/leapfrog/v1/history`, `POST …/history/restore` with `{ id, revision }`, `POST …/rebuild`
- `GET /wp-json/leapfrog/v1/export`, `POST …/import/preview` with `{ file }`, `POST …/import` with `{ file, revision }`
- `GET /wp-json/leapfrog/v1/presets`, `POST …/presets/save` with `{ name }`, `POST …/presets/apply` with `{ id, revision }`, `POST …/presets/delete` with `{ id }`

## Fonts

Leapfrog sets the font variables (`--font-heading`, `--font-body`); the font files themselves are added in Bricks > Custom Fonts (v1 does not download Google Fonts).
