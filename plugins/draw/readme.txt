=== Draw ===
Contributors: camaleaun
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later

A drawing as blocks: a frame with rectangles, ellipses, lines, paths, SVG groups, images and text inside, drawn on the page as one SVG.

== Description ==

Insert the **Drawing** block and put shapes in it. Every shape is a block with its place (x, y, w, h, in points) and its color; an SVG group has a block for each path. The page shows the whole drawing as one SVG, made on the server from what the blocks say, with every value checked: colors are colors, paths are paths, pictures are files of the Media Library.

The reading and cleaning of the drawing are the `camaleaun/draw` packages (Composer and npm), the same ones the Events Certificates plugin uses for its certificate.
