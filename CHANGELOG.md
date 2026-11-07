# Changelog

## 0.2.0 — 2026-11-07

Milestone 2: colours.

- Colour engine in OKLCH: even shade ramps 10–90, gamut mapping, automatic dark values, WCAG contrast.
- Default colours: primary, secondary, accent, neutral (automatic grey tinted from primary), success, warning, error, info, white, black. Custom colours can be added.
- Transparency steps a10–a90 are live `color-mix(in oklch, var(--name) N%, transparent)`, so they follow dark mode.
- Bricks palette "Leapfrog": created in one write on first save; later saves add, update and remove single colours by CSS variable name through Bricks' ownership-checked methods. Rebuilds `style-manager.min.css` afterwards.
- Colours screen with live preview, contrast badges, dark values and a save bar; warns before leaving with unsaved changes.
- Settings: `colors.items` replaces `brand.colors`.

## 0.1.0 — 2026-11-06

Milestone 1: skeleton.

- Plugin structure, autoloader and requirement checks (Bricks 2.4+).
- Bricks gateway ported from CoreBridge, with a direct-call fallback when Bricks' AI / Abilities API switch is off.
- Settings store (`lf_framework`) with a revision lock.
- REST routes for status, Bricks variables and settings.
- Full-screen editor under Leapfrog > Framework, with the Bricks data screen and placeholders for later screens.
