# The block markup

| Block | What it is | Inner blocks |
|---|---|---|
| `draw/frame` | The drawing: `width`, `height` (lengths) and `colors` (variable → color) | the shapes |
| `draw/rect` `draw/ellipse` `draw/line` `draw/path` `draw/image` `draw/text` | a shape, with `x y w h` in points and its own attributes (`fill`, `stroke`, `text`, `src`…) | — |
| `draw/svg` | a group drawn as a nested SVG (`vw`, `vh`, and `w`, `h` when it prefers a size) | `draw/svg-path` |
| `draw/group` | a box like `draw/rect` (`fill`, `stroke`, `strokeWidth`, `radius`, `corner`) that holds other shapes; they are placed from its top-left corner, and clipped to it only when `clip` is `true` | the shapes (any, but `-qr`, `-verification` and a signature; up to 3 groups deep, 300 elements in all) |
| `draw/svg-path` | one filled path: `d`, `fill`, `rule` | — |
| `events-certificates/certificate` | the root of a certificate (page, colors, palette, sample…) | the shapes, `-qr`, `-verification`, `-back` |

A shape can only be inside a frame, or a group in one (the editor enforces it, and the plugin draws nothing for a shape that is not in one).

**A group can be a row, a stack or a grid**, as the core's Group variations are (in the same order: Group, Row, Stack, Grid): `arrange` is `row`, `stack` or `grid` (the Group, the free one, has none), with `gap` and
`padding` in points, `justify` (`start`, `center`, `end`, `between`: along the row or the stack) and `align` (`start`, `center`, `end`, `stretch`: across it). A grid has `columns` (1 to 24)
columns of one width. What is in it is placed one after the other from the padding, whatever its `x` and `y`; an element with `grow` (a number) shares the room that is left with the others
that grow in a row or a stack, and what is hidden takes no room. The group keeps its own size. A group shows what goes out of its box unless it has `"clip":true`.
`Flow::boxes()` (PHP) and `flowBoxes()` (TypeScript) are the same.

**What is in a group** is placed from the group's top-left corner, and its anchors (`layout`, with `"ref":"parent"`) are to the size of the group:
`x`, `y`, a length in `%` and "fill" (stretch) are of the group's box, not of the page. A group's elements have ids that follow the group's
(`group-2-rect-1`), made again by position when the file is read, like any id.

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
and the text of a `draw/text` written as text (`runs`, a list of `{ text, b, i }` and `{ field, format, b, i }` in the model). `x` and `y` and the coordinates of a line (`x1 y1 x2 y2`) stay. An `id` that only counts the
elements (`rect-2`, or the editor's `svg-mwzbwe9c-1`) is not written: it is made again by position (`<type>-<position>`) when the markup is read; an id
that means something (`title`, `logo`) stays. The name an element has when it is made (`Text`, `Rectangle`, `SVG`…) is not written either: `Names::DEFAULT_NAMES`, and the host's own
types in the `names` option (`array( 'signature' => 'Signature', 'image:logo' => 'Community logo' )`). `Names::to_attrs()` / `Names::from_attrs()` (PHP) and `toAttrs()` / `fromAttrs()` (TypeScript) do it, inside
`Design`; the model in memory, the cleaning and the drawing keep the short names. The shapes of the Draw plugin (`draw/rect`…) have the same attributes
(`width`, `height`, `viewBox`).

### Lengths, radius and current color

A length an element is anchored by (`layout`: `start`, `end`, `size`) is written in the unit it was written in (`pt`, `px`, `mm`, `cm`, `in` or `%`): moving or resizing
an element on the canvas changes the number, not the unit (two decimals, three in inches). `px` is the point (`UNITS.px` is 1), the same as in the page's lengths.

A rectangle's corner `radius` is a number (points) or a percentage of the shorter side (`"25%"`, 0 to 50%; 50% makes a pill or a circle): `radiusPt()` (TypeScript)
and `Svg::radius()` (PHP). A color may be `currentColor`: the color of the text around it, which the host defines (the certificate's text color).

### The page and its size

The page of a design is kept with lengths with their unit (`width`, `height`, `safe`, `bleed`) and **no version**: the unit says what it is. `width` and
`height` are in the unit that makes both round: `Page::size()` (PHP) and `pageSize()` (TypeScript) give an A4 as `297mm` by `210mm` and a letter as
`11in` by `8.5in`, and points only when neither unit is round. A page written before (a point as `px`, `841.89px`) is still read.


## A block for a slot

The `blocks` option may name a block for a type **and slot** (`text:date-of-emission`): an element of that type and slot is written as that block, with no `slot` (the block says it), and read back with it.

## The text of a text

The `text` attribute of a `draw/text` (and each language of its `variants`) is written as text, the same in the JavaScript and the PHP package
(`parseRuns` / `Runs::parse`, `runsToText` / `Runs::to_text`; `packages/fixtures/runs.json` is what both read):

```
Dear [[name]], welcome to **[[event]]**, held in *[[location]]*.
```

- `[[key]]` and `[[key:format]]` are fields (a lone `[word]` is text); with the `fields` option only those keys are (the others stay text). `{key}`, what files wrote before, is read as a field too.
- `**…**` is bold and `*…*` italic, as in markdown (`***…***` is both).
- A switch with no partner (`5 * 3`) is text; a line break is a line break; a backslash before one of `\ * _ [ ] {` makes it text.
- A file of before, with `runs` (and `bold` and `italic`), is read the same.
