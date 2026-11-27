# The block markup

| Block | What it is | Inner blocks |
|---|---|---|
| `draw/frame` | The drawing: `width`, `height` (lengths) and `colors` (variable → color) | the shapes |
| `draw/rect` `draw/ellipse` `draw/line` `draw/path` `draw/image` `draw/text` | a shape, with `x y w h` in points and its own attributes (`fill`, `stroke`, `runs`, `src`…) | — |
| `draw/svg` | a group drawn as a nested SVG (`vw`, `vh`, and `w`, `h` when it prefers a size) | `draw/svg-path` |
| `draw/svg-path` | one filled path: `d`, `fill`, `rule` | — |
| `events-certificates/certificate` | the root of a certificate (page, colors, palette, sample…) | the shapes, `-qr`, `-verification`, `-back` |

A shape can only be inside a frame (the editor enforces it, and the plugin draws nothing for a shape that is not in one).

A **design** is the array an editor and a renderer work on; `Design::to_markup()` writes it as blocks and `Design::from_markup()` reads it
back, in PHP and in TypeScript (`toMarkup()`, `fromMarkup()`), with the same result.

### Options: a design with the blocks of its own plugin

The default above is the native way of the blocks. A second argument to `Design::to_markup()` and `Design::from_markup()` (in TypeScript,
`toMarkup()` and `fromMarkup()`), the same for both directions, lets a plugin keep its design with blocks of its own:

| Option | What it does | Default |
|---|---|---|
| `blocks` | the block of a type, over the default ones: `{ svg: 'events-certificates/certificate-svg' }` | `draw/*` |
| `fallback` | the block that keeps the elements of a type this version does not know | `draw/element` |
| `paths` | `inner`: one `draw/svg-path` inner block for each path; `attribute`: the SVG is **one block** with its paths in the attribute, `{"paths":[{"d":"…"},{"d":"…"}]}`, and no inner blocks | `inner` |

Events Certificates uses `events-certificates/certificate-*` for every shape and `paths: attribute`; the Draw plugin keeps the defaults.
