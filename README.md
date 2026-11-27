# draw

A drawing as blocks: pages made of rectangles, ellipses, lines, paths, SVG groups, images and text, saved as block markup
(`<!-- wp:draw/rect {…} /-->`) instead of a custom JSON. A monorepo:

- `packages/php` — `camaleaun/draw` (Composer): reads and writes the markup, no WordPress needed.
- `packages/js` — `@camaleaun/draw` (npm): the same, in TypeScript.
- `packages/editor` — the canvas (`DrawCanvas`): select, move, resize, nudge, delete and insert shapes, on the block editor's store, so the editor's List View, Inspector and undo see the same drawing.
- `plugins/draw` — the WordPress plugin that registers the `draw/*` blocks (`npm run build:plugin` makes its `assets/editor.js`).
- `tests/golden` — designs used by the PHP and the JS tests, so both read the same.

Used by [Events Certificates](https://github.com/camaleaun/events-certificates), whose certificate is the `events-certificates/certificate` block.

## Install

```
composer require camaleaun/draw
npm install github:camaleaun/draw   # until it is published to npm
```

(The Composer package is the repository root: its classes are in `packages/php/src`.)

## Use

```php
use Camaleaun\Draw\Design;
use Camaleaun\Draw\Clean;
use Camaleaun\Draw\Svg;

$design = Design::from_markup( $markup );                       // blocks → the array a renderer works on
$clean  = ( new Clean( array( 'upload_url' => $is_ours ) ) )->design( $design ); // only what a drawing may have
$markup = Design::to_markup( $clean );                          // back to blocks
$svg    = Svg::render( $clean['elements'], 600, 400 );          // one SVG
```

```ts
import { fromMarkup, toMarkup, parseLen, resizeBox, snap } from '@camaleaun/draw';
```

See [the block markup](docs/block-markup.md) and the [decisions](docs/decisions/).

