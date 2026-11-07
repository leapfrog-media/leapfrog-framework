# Changelog

## 1.1.0-alpha.7 — 2026-11-07

Milestone 1.1c: builder helpers.

- Grid guides: a toolbar button beside Undo, or Ctrl+Shift+G, shows 12 column guides on the builder canvas, inside `--width-content` with `--gutter` at the edges and `--gap` between columns. When the selected element is a grid, its own tracks are outlined. Builder only; the choice is remembered.
- BEM class generator: right-click an element that has children (in the Structure panel or on the canvas) and choose "Add BEM classes…". The block name comes from the element's label, and each element inside gets `block__part` (title, text, image, button… for elements that still have their default name). Names can be edited, elements left out, and a modifier added. Leapfrog creates the global classes (reusing any with the same name) and adds them; optionally it renames the elements to match and moves styles set on them onto the new classes (new classes only). Elements that already have classes, and component instances, start unticked.
- Variable autocomplete is not added: Bricks 2.4 already opens its variable list, filtered, when you type `var(--` in a field.

## 1.1.0-alpha.3 — 2026-11-07

Milestone 1.1b: grid extras. Bricks 2.4 has its own Visual grid builder (the button beside Display on a grid), which already covers tracks, placing elements, breakpoints, classes and query loops, so Leapfrog adds a row to it instead of a second builder.

- Layout: Leapfrog grid layouts (equal columns, uneven splits, auto-fit and auto-fill card grids) set the grid's columns as `var(--grid-…)`.
- Gap: Leapfrog gap values (`--gap`, stack, cluster and inline gaps, space scale steps).
- Bento: 3, 4, 5 or 6 tile layouts, placing the children. Layout children (container, block, div) get Bricks' Grid column and Grid row settings; other elements, and any grid with a class selected, get Bricks' own grid builder custom CSS, in the same marked section and format, so Bricks can keep editing them.
- Stack on mobile: one column at the smallest breakpoint, every child back in normal order (settings and grid builder CSS alike).
- Everything is written through Bricks' own setting function on the breakpoint shown in the grid builder ("Applies to" shows which), lands in Bricks' undo history, and works on the selected class when one is active.

## 1.1.0-alpha.1 — 2026-11-07

Milestone 1.1a: values. 145 new Bricks variables and colour roles (229 variables and 35 roles in all), the design values CoreBridge had that 1.0 left out.

- Grid layouts (Layout and shape > Grids): `--grid-1` … `--grid-12`, `--grid-1-2`, `--grid-2-1`, `--grid-1-3`, `--grid-3-1`, and wrapping card grids `--grid-auto-fit` / `--grid-auto-fill` sized by `--grid-min` (300px), which never overflow a phone. Offered by the variable picker in Bricks' Grid template columns field; the editor shows a small preview of each.
- 13 colour roles: subtle and disabled text, icons, raised and selected surfaces, text selection, dividers, and text on success, warning, error and info. Page styles now colour text selection.
- Typography: display font and line height, named font weights (regular, medium, semibold, bold), heading and label weights, body and heading letter spacing, caption, label, quote and code sizes.
- Spacing: stack, cluster, list and inline gaps, panel padding.
- Layout and shape: sidebar and form widths, scroll offset, icon, avatar and control sizes, focus ring, minimum tap target (44px), disabled and placeholder opacity, divider width, skip-link offset, reduced-motion duration, an instant duration, and backdrop, drawer and tooltip layers.
- Components: more button, form field, card and media tokens, plus new groups for navigation and footer, tabs/accordions/modals/tooltips/alerts, and tables and lists. Most point at existing values, so the brand starter still sets everything.
- Buttons: new Contrast (filled with the text colour) and Ghost (no fill until hover) styles. Neutral is now a soft grey button; its old look (text-colour fill) is Contrast. Buttons use the new icon size, letter spacing, focus ring, transition and disabled opacity tokens.
- Variable picker rules for every new value, including new element types (footer, tabs, accordion, offcanvas, alert, image, icon, list, pagination).

## 1.0.2 — 2026-11-07

- The Leapfrog Media mark (`assets/brand/leapfrog-mark.svg`) is now the admin menu icon, as a solid arch that WordPress recolours to match the admin menu, and appears in brand purple in the Framework editor header in place of "LF".

## 1.0.1 — 2026-11-07

- Fix: Bricks' Style Manager showed type sizes shifted by one step after a step was removed and added back. Bricks works out each step's size from its position in the variable list, and added the step at the end. Leapfrog now keeps every scale's variables in step order (smallest first), reordering them through Bricks' safe methods when needed; values and IDs are unchanged.

## 1.0.0 — 2026-11-07

Milestone 6: brand starter and saving. First complete version.

- Brand starter: primary, secondary, accent and neutral colours, heading and body fonts, body text size, heading contrast (calm, balanced, bold), spacing (compact, comfortable, spacious), corners, pill or rounded buttons, and neutral or brand-coloured dark backgrounds generate the whole design system and write it to Bricks (about a second). Live light and dark preview; Google Fonts load in the preview only.
- History: every save keeps the version it replaces (last 20, with a note and who saved it); any version can be restored.
- Rebuild everything: writes all colours, roles, scales, layout and component variables to Bricks again (only differences are written).
- Export a framework file (.json) and import it on another site, with a preview first.
- Presets: save the current design system under a name, apply it later; "Leapfrog defaults" is built in.
- Kinetic Money's brand (from kineticmoney.co.uk) set up on kineticmoney.test with the starter in under 5 minutes.

## 0.5.1 — 2026-11-07

- The Theme control (Inherit, Light, Dark, Inverted) is now on Div as well as Section, Container and Block.

## 0.5.0 — 2026-11-07

Milestone 5: builder tools (taken over from CoreBridge) and components.

- Contextual variable picker: right-click a Bricks style field (or click Bricks' variable button) for the variables that fit it, with tokens made for the selected element first, then roles, then scales and palette colours. Hover previews; Enter or click applies; colour fields get an opacity slider that writes a live `color-mix()`. Rules rewritten for Leapfrog names; palette colours and roles included with swatches. Scale steps sort by size.
- Button controls: Bricks Button Style (Primary, Secondary, Accent, Neutral), Size, Outline and Circle map to `lf-btn` classes, styled by `assets/frontend/buttons.css` from the `--btn-*` tokens and colour roles; works in every theme; live on the builder canvas.
- Logical sizing: Inline size and Block size (with min/max) beside Width and Height on every element, plus a "Logical sizing only" switch. Same setting keys as CoreBridge, so existing values carry over.
- Component tokens (category "Components"): buttons, form fields, cards, header; Components screen.
- CoreBridge's Icon List element is not carried over.

## 0.4.0 — 2026-11-07

Milestone 4: roles, themes, layout and shape.

- 19 colour roles (`--color-text`, `--color-surface`, `--color-border`, `--color-on-primary`…) plus 3 live transparent roles, saved as the Bricks palette "Leapfrog roles": each holds `var(--palette-colour)` for light and for dark, so page dark mode switches them natively. "Automatic" text-on-colour picks white or black by contrast.
- Section themes: a Theme control (Inherit, Light, Dark, Inverted) on Section, Container and Block renders `data-lf-theme`; generated CSS (about 10KB, printed in the head) re-points brand, role and transparency values inside themed sections. Inverted flips the page, or the light/dark section around it. Ported from CoreBridge, which no longer needs to be installed; its old setting is still read.
- Optional page colouring: body text and background, headings, links and focus rings use the roles (styles set in Bricks win).
- Layout and shape variables (category "Layout and shape"): content widths, corners with Sharp/Soft/Round presets, borders, shadows, motion and layers.
- Roles and themes screen (light/dark previews, per-role choices, contrast table) and Layout and shape screen.
- Saving colours now also rebuilds the roles palette and the theme CSS.

## 0.3.0 — 2026-11-07

Milestone 3: typography, spacing and settings.

- Type scale (`--text-2xs` … `--text-4xl`) and space scale (`--space-3xs` … `--space-3xl`) saved as Bricks scale categories ("Type scale", "Space scale"). Bricks generates every clamp() value, so editing in Leapfrog or in Bricks' Style Manager gives the same result; edits made in Bricks are read back.
- Scale settings changes run in four guarded steps (park variables, save categories, generate, write back with the same IDs), because Bricks refuses scale changes on categories that variables belong to.
- Role variables: `--h1`…`--h6`, `--body-size`, `--lead-size`, `--small-size`, `--font-body/heading/mono`, `--line-height-body/heading` (category "Typography"); `--gutter`, `--gap`, `--flow`, `--section-space(-compact/-spacious)` (category "Spacing").
- Typography screen with specimens at both screen widths; Spacing screen with bars; Settings screen for Bricks' Style Manager screen widths and default colour mode (changing widths regenerates the scales).
- Bricks' CSS files are rebuilt after saves when CSS loading is "External files".
- Editor labels keep their case (variable names are case-sensitive).

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
