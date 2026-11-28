# 0002 — An element says its look with the attributes of a core block

Status: accepted (0.26 reads, 0.27 writes).

## Context

The blocks of a drawing said their look with short names of their own: `w`, `h`, `size`, `weight`, `font`, `color`, `fill`, `locked`, numbers for every length. A core block says the same
things with other names (`style.typography.fontSize`, `fontFamily`, `style.color`, `style.border`, `lock: { move, remove }`, `metadata`, lengths with units), so a copy of an element pasted in a
text editor, or read by a person, looked like nothing else in the block editor.

## Decision

- The attributes of an element are the ones a core block says, wherever core has an equivalent; the table is in `docs/block-markup.md`. What core has no name for (`layout` with its anchors, `rotation`,
  `opacity`, `slot`, `grow`, `corner`, `clip`) keeps its own. Every length is written with its unit (`"13pt"`); a number of points is what files wrote before.
- **The model does not change.** `Core::to_core()` / `toCore()` write and `Core::from_core()` / `fromCore()` read, at the file's edge, on the attributes `Names` has already renamed; the model that
  `Clean`, the renderer and the editor use keeps `w`, `h`, `size`, `weight`, `font`.
- **Both forms are read** with the same code (0.26), and a key of the new form wins over the old one. 0.27 writes the new form. A file is rewritten in it the next time it is saved; there is no migration.
- A font is written as its slug when the host says it has the font (`fonts` option: `cert-sans` is `CertSans`), and as the name as written otherwise; the slug is read as the name of the host's font it matches.
- The text alignment is only `style.typography.textAlign` (a top-level `align` of core means the alignment of the block, and a group's `align` is the other axis of its `arrange`).
- A group says its layout in `arrange` (the object core's layout has: `type`, `orientation`, `justifyContent`, `verticalAlignment`, `columnCount`), not in `layout`, which holds the anchors.
- The tests read `tests/golden/markup/*.old.html` (what 0.25 wrote) and `*.new.html` (made by `tests/tools/make-new-fixture.py` from the table, not by the package) in both twins.

## Consequences

- A file written by 0.27 loses its places and styles when it is opened by 0.25 or before; the version that only reads (0.26) comes first for that reason.
- `1.4lh` is written `"1.4"` (a line height with no unit is the multiplier already) and `13pt` is read as `13`: the same size, in the form the model keeps.
