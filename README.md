# draw

A drawing as blocks: pages made of rectangles, ellipses, lines, paths, SVG groups, images and text, saved as block markup
(`<!-- wp:draw/rect {…} /-->`) instead of a custom JSON. A monorepo:

- `packages/php` — `camaleaun/draw` (Composer): reads and writes the markup, no WordPress needed.
- `packages/js` — `@camaleaun/draw` (npm): the same, in TypeScript.
- `plugins/draw` — the WordPress plugin that registers the `draw/*` blocks.
- `tests/golden` — designs used by the PHP and the JS tests, so both read the same.

Used by [Events Certificates](https://github.com/camaleaun/events-certificates), whose certificate is the `events-certificates/certificate` block.

## Install

```
composer require camaleaun/draw
npm install github:camaleaun/draw   # until it is published to npm
```

(The Composer package is the repository root: its classes are in `packages/php/src`.)
