# 0001 — A drawing is made of blocks

Status: accepted (0.x).

## Context

A drawing used to be a JSON of its own: a page and a list of elements (rectangles, ellipses, lines, paths, SVG, pictures, text), read by an
editor, a renderer and the code that cleans what is saved or imported. The Events Certificates plugin keeps its certificate templates that
way, and wanted them to be blocks, with inner blocks, like the block editor's; and a plugin that has only the drawing block.

## Decision

- A drawing is **block markup** (`<!-- wp:draw/rect {…} /-->`), the format the block editor saves. A frame holds shapes as inner blocks; an
  SVG group holds one `draw/svg-path` block for each path. **Every key of an element is an attribute of its block**, so nothing is lost in
  the trip from the older array and back (the golden designs in `tests/golden` are checked in PHP and TypeScript).
- **One repository, three kinds of package**: `packages/php` is the Composer library (the repository root is its package, so Composer and
  Packagist read it), `packages/js` is the TypeScript package (`@camaleaun/draw`), `packages/editor` is the canvas, and `plugins/draw` is the
  WordPress plugin.
- **The library needs no WordPress** (`Markup` reads and writes the block comments itself). What WordPress decides is given to it: which
  addresses are pictures of the site (`Clean`'s `upload_url`), which font names are safe, which numbers are attachments that are pictures.
- **The output is SVG only.** `Svg` draws a frame and its shapes as one SVG, every value escaped, with no markup an attribute could bring in.
  Paper (a PDF) is the business of whoever uses the library (Events Certificates draws its certificates with dompdf); nothing of it is here.
- **Strict entry.** `Clean` keeps only the blocks, attributes and values a drawing may have (numbers in ranges, colors that are a color or a
  variable, texts with no markup, drawings as paths of commands and numbers), at most 300 blocks and 2 MB; `Paths::is_data()` reads a path
  command by command.
- **Two plugins can carry the library**: a plugin that takes it with Composer moves it under its own namespace (Events Certificates does,
  with `evcert deps`), so two plugins with different versions of it do not clash.

## Consequences

- The canvas works on the block editor's store: a drag is one step of the history, and the List View and the selection are the editor's.
- Publishing the packages (Packagist, npm) is a decision of the owner, not of the code; until then they are taken from this repository
  (Composer from GitHub; the TypeScript sources travel in the Composer package, and a plugin builds them).
