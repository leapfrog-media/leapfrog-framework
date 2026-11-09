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
| `src/Bricks/ElementSettings.php` | Element settings: better defaults, extra CSS controls, overflow list, dropdown search (switches in option `lf_element_settings`) |
| `src/Bricks/ThemeStyle.php` | The site-wide Bricks theme style Leapfrog writes fonts and defaults to |
| `src/Fonts/FontInstaller.php` | Installs Google fonts as self-hosted Bricks custom fonts (variable where possible) and sets them in the site-wide theme style |
| `src/Bricks/BuilderHelpers.php` | Grid guides (`assets/builder/grid-guides.js`) and the BEM class generator (`assets/builder/bem.js`) |
| `src/Bricks/GridExtras.php` | Leapfrog row in Bricks' Visual grid builder: layouts, gaps, bento, stack on mobile (+ `assets/builder/grid-extras.js`) |
| `src/Scale/Shape.php` | Layout and shape variables: widths, sizes, grids, corners, borders, focus, shadows, motion, layers |
| `src/Bricks/VariableSet.php` | Keeps one Bricks variable category equal to a list of variables |
| `src/Build.php` | Rebuilds palette, roles palette and theme CSS together |
| `src/Scale/Components.php` | Component tokens (buttons, fields, cards and media, header, navigation and footer, tabs/modals/tooltips/alerts, tables and lists) |
| `src/Settings/Starter.php` | Brand starter: brand choices → full settings |
| `src/Settings/History.php` | Last 20 versions of the settings |
| `src/Settings/Transfer.php` | Framework files (export/import) and presets |
| `src/Rest/SiteController.php` | Starter, history, rebuild, export/import and preset routes |
| `src/Bricks/Buttons.php` | Bricks Button controls → `lf-btn` classes (+ `assets/frontend/buttons.css`, `assets/builder/buttons.js`) |
| `src/Bricks/LogicalSizing.php` | Inline/Block size controls (+ `assets/builder/logical-sizing.*`) |
| `src/Bricks/VariablePicker.php` | Contextual variable picker data (+ `assets/builder/variable-rules.js`, `variable-picker.*`) |
| `src/Rest/RestController.php` | REST routes under `leapfrog/v1` (admins only) |
| `src/Admin/FrameworkPage.php` | Leapfrog > Framework admin page |
| `src/Admin/LeapfrogMenu.php` | The shared Leapfrog menu (same logic as Leapfrog Admin's `includes/menu.php`) |
| `src/Admin/NavigationPage.php` | Leapfrog > Navigation Builder admin page |
| `src/Navigation/Starter.php` | The starter header: a Bricks header template with a BricksExtras Header Row, the logo and a Leapfrog navigation |
| `src/Navigation/Design.php` | Navigation Builder: the style settings (with the parts each one reaches), the mobile settings, cleaning and CSS (base + styles) |
| `src/Navigation/Store.php` | The site's navigation style (`lf_navigation`), draft (`lf_navigation_draft`), history (`lf_navigation_history`); headers that hold a Leapfrog navigation |
| `src/Navigation/Frontend.php` | Prints the navigation CSS and loads the mobile menu script where the header has a Leapfrog navigation; preview mode for the builder |
| `src/Navigation/Controller.php` | Navigation Builder REST routes |
| `assets/frontend/navigation.js` | The mobile menu on the site: drill-down sub-menus, Back, scroll lock, Escape, focus |
| `assets/frontend/navigation-preview.js` | Takes styles and states from the builder in its preview |
| `assets/editor/` | Editor app source (React, WordPress components) |
| `assets/navigation/` | Navigation Builder app source |
| `build/` | Built editor and Navigation Builder apps (committed, so the plugin works without a build) |

## Navigation Builder

Leapfrog > Navigation Builder styles the site's top navigation and its mobile menu, with a live
preview. Needs BricksExtras with its Header Row element on: the header's rows, sticky and scrolling
are Header Row and Bricks template settings, set in Bricks.

- **New header…** creates a Bricks header template (named when created): a BricksExtras Header Row
  with the logo and a Leapfrog navigation, a Bricks Nav (Nestable) with the class `lfn` holding two
  links, a dropdown, a mega menu (rows with a title, text and arrow, and a call to action) and a link
  styled as a button (`lfn-btn`), plus the mobile menu's top bar (Back, logo, close) and menu button.
  Its content is edited in Bricks from then on. It shows on the whole site unless another header
  already has display conditions.
- **One style for the site**, for every Leapfrog navigation: menu bar links in every state, the
  button link, dropdowns, mega menus (panel, rows, call to action) and the mobile menu. Empty =
  Bricks' own styling; a value set here wins. Values can be typed or picked from the Leapfrog
  colours and variables.
- **Mobile menu**: from a chosen Bricks breakpoint down, the panel slides in from the right, left or
  top, or fills the screen; sub-menus drill down with a Back button (or open underneath); the button
  link can sit at the bottom. Bricks' own mobile menu is off (`mobileMenu: never`); Leapfrog's CSS and
  `assets/frontend/navigation.js` do it.
- **Preview**: a real page (the long `/header-display/` page when there is one) at each Bricks
  breakpoint, with the unsaved style; views for scrolling, dropdown and mega menu open, mobile menu
  and sub-menu open. Style changes show at once; mobile settings reload the preview.

## Updates

Updates come from GitHub through WordPress's own updater (Dashboard > Updates,
the Plugins screen, automatic updates). The Version in the plugin file on the
main branch of github.com/leapfrog-media/leapfrog-framework is the newest version, so a
release is a pushed commit that raises it (no tags). Only push finished
versions to main. Public repositories need nothing more. Private ones need
a GitHub token that can read them: LEAPFROG_GITHUB_TOKEN in wp-config.php, or
the Updates tab of Leapfrog Admin or Leapfrog Performance (one token covers all
Leapfrog plugins). A copy that is a Git working folder is never updated.

Before raising the version, run `npm run build` and commit `build/`: the editor sources aren't in GitHub's zip.

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
- `POST /wp-json/leapfrog/v1/fonts/status` with `{ families }`, `POST …/fonts/install` with `{ heading, body }`
- `GET /wp-json/leapfrog/v1/element-settings`, `POST …/element-settings/save` with `{ settings }`
- `GET /wp-json/leapfrog/v1/navigation`, `POST …/navigation/starter` with `{ name }`, `POST …/navigation/draft` with `{ design }`, `POST …/navigation/save` with `{ design }`, `POST …/navigation/discard`

## Fonts

Leapfrog sets the font variables (`--font-heading`, `--font-body`); the font files themselves are added in Bricks > Custom Fonts (v1 does not download Google Fonts).
