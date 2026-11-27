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

### Element types of the host

The element types a design may have are the shapes above, `qr` and `verification`. A host that has more (Events Certificates has `signature`, the
place of the signer's signature) gives them to the cleaning, with the keys of their colors: `new Clean( array( 'types' => array( 'signature' =>
array( 'color' ) ) ) )`; what an element has beside those keys is what every element has (`id`, `name`, `x y w h`, `layout`, `views`, `rotation`,
`opacity`, `lock`, `slot`). And the block of the type is given in the `blocks` option of `Design` (see Options). Without `types` there are none.

### The names of an element

An element is written with the names a block says, never abbreviated: `width` and `height` (the model's `w` and `h`), `viewBox` (`vw` and `vh`),
`lineHeight` and `letterSpacing` (`lh` and `ls`, also in the hint and the code of a verification), `horizontal` and `vertical` in a `layout` (`h` and `v`),
and `bold` and `italic` in a run of a text (`b` and `i`). `x` and `y` and the coordinates of a line (`x1 y1 x2 y2`) stay. An `id` that only counts the
elements (`rect-2`, or the editor's `svg-mwzbwe9c-1`) is not written: it is made again by position (`<type>-<position>`) when the markup is read; an id
that means something (`title`, `logo`) stays. `Names::to_attrs()` / `Names::from_attrs()` (PHP) and `toAttrs()` / `fromAttrs()` (TypeScript) do it, inside
`Design`; the model in memory, the cleaning and the drawing keep the short names. The shapes of the Draw plugin (`draw/rect`…) have the same attributes
(`width`, `height`, `viewBox`).

### The page and its size

The page of a design is kept with lengths with their unit (`width`, `height`, `safe`, `bleed`) and **no version**: the unit says what it is. `width` and
`height` are in the unit that makes both round: `Page::size()` (PHP) and `pageSize()` (TypeScript) give an A4 as `297mm` by `210mm` and a letter as
`11in` by `8.5in`, and points only when neither unit is round. A page written before (a point as `px`, `841.89px`) is still read.
