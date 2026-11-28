# 0003 — The default name of a group follows its layout

Status: accepted (0.28).

## Context

A group is a Group, a Row, a Stack or a Grid by its `arrange`, and an editor that shows it in a list of layers names it by that. The package did not write the name an element has by default, by
type (`Group`), so a Row the editor named "Row" was written as `metadata.name: "Row"` and looked renamed when the file was read again, and a Row the owner named "Group" was dropped as the default and
came back as a plain group's name.

## Decision

- `defaultName()` (`names.ts`) and `Names::default_name()` look a name up by `type:slot`, then, **for a group, by `group:<arrange>`**, then by `type`. The package says `group:row` is Row, `group:stack` is Stack and
  `group:grid` is Grid; a host may name them otherwise with the `names` option, as for any type.
- Only a group has a name by layout: a `verification` also has an `arrange` and is still a Verification.
- The name that is the default of the element's own layout is not written, and a file with no name is read as the default of its layout; a name that is another layout's default is a name.

## Consequences

- A group the editor switches from Group to Row keeps no name of its own: the host shows the default of the layout it has.
- A file written before 0.28 with `metadata.name: "Row"` on a Row is read as a Row named "Row" and written without the name the next time.
