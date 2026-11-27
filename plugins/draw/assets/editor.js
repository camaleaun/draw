"use strict";
(() => {
  var __create = Object.create;
  var __defProp = Object.defineProperty;
  var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
  var __getOwnPropNames = Object.getOwnPropertyNames;
  var __getProtoOf = Object.getPrototypeOf;
  var __hasOwnProp = Object.prototype.hasOwnProperty;
  var __commonJS = (cb, mod) => function __require() {
    return mod || (0, cb[__getOwnPropNames(cb)[0]])((mod = { exports: {} }).exports, mod), mod.exports;
  };
  var __copyProps = (to, from, except, desc) => {
    if (from && typeof from === "object" || typeof from === "function") {
      for (let key of __getOwnPropNames(from))
        if (!__hasOwnProp.call(to, key) && key !== except)
          __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
    }
    return to;
  };
  var __toESM = (mod, isNodeMode, target) => (target = mod != null ? __create(__getProtoOf(mod)) : {}, __copyProps(
    // If the importer is in node compatibility mode or this is not an ESM
    // file that has been converted to a CommonJS file using a Babel-
    // compatible transform (i.e. "__esModule" has not been set), then set
    // "default" to the CommonJS "module.exports" for node compatibility.
    isNodeMode || !mod || !mod.__esModule ? __defProp(target, "default", { value: mod, enumerable: true }) : target,
    mod
  ));

  // wp-global:@wordpress/element
  var require_element = __commonJS({
    "wp-global:@wordpress/element"(exports, module) {
      module.exports = window.wp.element;
    }
  });

  // wp-global:@wordpress/blocks
  var require_blocks = __commonJS({
    "wp-global:@wordpress/blocks"(exports, module) {
      module.exports = window.wp.blocks;
    }
  });

  // wp-global:@wordpress/block-editor
  var require_block_editor = __commonJS({
    "wp-global:@wordpress/block-editor"(exports, module) {
      module.exports = window.wp.blockEditor;
    }
  });

  // wp-global:@wordpress/components
  var require_components = __commonJS({
    "wp-global:@wordpress/components"(exports, module) {
      module.exports = window.wp.components;
    }
  });

  // wp-global:@wordpress/i18n
  var require_i18n = __commonJS({
    "wp-global:@wordpress/i18n"(exports, module) {
      module.exports = window.wp.i18n;
    }
  });

  // wp-global:@wordpress/data
  var require_data = __commonJS({
    "wp-global:@wordpress/data"(exports, module) {
      module.exports = window.wp.data;
    }
  });

  // plugins/draw/src/index.tsx
  var import_element3 = __toESM(require_element(), 1);
  var import_blocks2 = __toESM(require_blocks(), 1);
  var import_block_editor2 = __toESM(require_block_editor(), 1);
  var import_components2 = __toESM(require_components(), 1);
  var import_i18n2 = __toESM(require_i18n(), 1);

  // packages/editor/src/DrawCanvas.tsx
  var import_element2 = __toESM(require_element(), 1);
  var import_data = __toESM(require_data(), 1);
  var import_block_editor = __toESM(require_block_editor(), 1);
  var import_blocks = __toESM(require_blocks(), 1);
  var import_components = __toESM(require_components(), 1);
  var import_i18n = __toESM(require_i18n(), 1);

  // packages/js/src/geometry.ts
  var UNITS = { pt: 1, px: 1, mm: 2.83465, cm: 28.3465, in: 72 };
  function parseLen(value, total) {
    if (value === void 0 || value === "") {
      return 0;
    }
    if (typeof value === "number") {
      return value;
    }
    let expr = value.trim().toLowerCase();
    const calc = /^calc\((.*)\)$/.exec(expr);
    if (calc) {
      expr = calc[1];
    }
    let sum = 0;
    const re = /([+-]?)\s*(-?\d*\.?\d+)\s*(pt|px|mm|cm|in|%)?/g;
    let m;
    let matched = false;
    while (m = re.exec(expr)) {
      matched = true;
      const n = parseFloat(m[2]) * (m[1] === "-" ? -1 : 1);
      sum += m[3] === "%" ? n * total / 100 : n * (UNITS[m[3] || "pt"] || 1);
    }
    return matched ? sum : 0;
  }
  function radiusPt(value, w, h) {
    if (typeof value === "string") {
      const m = /^\s*(-?\d*\.?\d+)\s*%\s*$/.exec(value);
      return m ? Math.max(0, Math.min(Number(m[1]), 50)) / 100 * Math.min(w, h) : 0;
    }
    return typeof value === "number" && Number.isFinite(value) ? value : 0;
  }
  function rectPath(x, y, w, h, radius = [], shape = "round") {
    const r = [0, 1, 2, 3].map((i) => Math.max(0, Math.min(radiusPt(radius[i], w, h), w / 2, h / 2)));
    const f = (n) => Math.round(n * 100) / 100;
    const corners = [
      { c: [x, y], in: [0, 1], out: [1, 0] },
      // top-left: coming up the left edge, leaving right.
      { c: [x + w, y], in: [-1, 0], out: [0, 1] },
      { c: [x + w, y + h], in: [0, -1], out: [-1, 0] },
      { c: [x, y + h], in: [1, 0], out: [0, -1] }
    ];
    let d = "";
    corners.forEach(({ c, in: din, out }, i) => {
      const rr = r[i];
      const start = [c[0] + din[0] * rr, c[1] + din[1] * rr];
      const end = [c[0] + out[0] * rr, c[1] + out[1] * rr];
      d += (i === 0 ? "M" : "L") + `${f(start[0])} ${f(start[1])}`;
      if (!rr) {
        return;
      }
      switch (shape) {
        case "scoop":
          d += `A${f(rr)} ${f(rr)} 0 0 0 ${f(end[0])} ${f(end[1])}`;
          break;
        case "bevel":
          d += `L${f(end[0])} ${f(end[1])}`;
          break;
        case "notch": {
          const inner = [start[0] + end[0] - c[0], start[1] + end[1] - c[1]];
          d += `L${f(inner[0])} ${f(inner[1])}L${f(end[0])} ${f(end[1])}`;
          break;
        }
        case "squircle": {
          const k = 0.18;
          const c1 = [c[0] + (start[0] - c[0]) * k, c[1] + (start[1] - c[1]) * k];
          const c2 = [c[0] + (end[0] - c[0]) * k, c[1] + (end[1] - c[1]) * k];
          d += `C${f(c1[0])} ${f(c1[1])} ${f(c2[0])} ${f(c2[1])} ${f(end[0])} ${f(end[1])}`;
          break;
        }
        default:
          d += `A${f(rr)} ${f(rr)} 0 0 1 ${f(end[0])} ${f(end[1])}`;
      }
    });
    return d + "Z";
  }
  function isPathData(d) {
    return /^[MmLlHhVvCcSsQqTtAaZz0-9eE.,\s+-]*$/.test(d) && /^\s*[Mm]/.test(d);
  }
  function snap(moving, page, others, threshold) {
    const { w, h, safe } = page;
    const xs = [0, safe, w / 2, w - safe, w];
    const ys = [0, safe, h / 2, h - safe, h];
    for (const b of others) {
      xs.push(b.x, b.x + b.w / 2, b.x + b.w);
      ys.push(b.y, b.y + b.h / 2, b.y + b.h);
    }
    const best = (targets, edges) => {
      let found = null;
      for (const edge of edges) {
        for (const t of targets) {
          const delta = t - edge;
          if (Math.abs(delta) <= threshold && (!found || Math.abs(delta) < Math.abs(found.delta))) {
            found = { delta, at: t };
          }
        }
      }
      return found;
    };
    const spacing = (axis) => {
      const pos = axis === "x" ? "x" : "y";
      const size = axis === "x" ? "w" : "h";
      const cpos = axis === "x" ? "y" : "x";
      const csize = axis === "x" ? "h" : "w";
      const overlaps = (b) => b[cpos] < moving[cpos] + moving[csize] && b[cpos] + b[csize] > moving[cpos];
      const row = others.filter(overlaps);
      const before = row.filter((b) => b[pos] + b[size] <= moving[pos] + threshold).sort((a, b) => b[pos] + b[size] - (a[pos] + a[size]));
      const after = row.filter((b) => b[pos] >= moving[pos] + moving[size] - threshold).sort((a, b) => a[pos] - b[pos]);
      const cross = (b) => (Math.max(b[cpos], moving[cpos]) + Math.min(b[cpos] + b[csize], moving[cpos] + moving[csize])) / 2;
      const candidates = [];
      const L = before[0];
      const R = after[0];
      if (L && R) {
        const target = (L[pos] + L[size] + R[pos] - moving[size]) / 2;
        candidates.push({
          delta: target - moving[pos],
          gaps: [
            { axis, kind: "gap", from: L[pos] + L[size], to: target, cross: cross(L) },
            { axis, kind: "gap", from: target + moving[size], to: R[pos], cross: cross(R) }
          ]
        });
      }
      if (L && before[1]) {
        const gap = L[pos] - (before[1][pos] + before[1][size]);
        const target = L[pos] + L[size] + gap;
        candidates.push({
          delta: target - moving[pos],
          gaps: [
            { axis, kind: "gap", from: before[1][pos] + before[1][size], to: L[pos], cross: cross(before[1]) },
            { axis, kind: "gap", from: L[pos] + L[size], to: target, cross: cross(L) }
          ]
        });
      }
      if (R && after[1]) {
        const gap = after[1][pos] - (R[pos] + R[size]);
        const target = R[pos] - gap - moving[size];
        candidates.push({
          delta: target - moving[pos],
          gaps: [
            { axis, kind: "gap", from: target + moving[size], to: R[pos], cross: cross(R) },
            { axis, kind: "gap", from: R[pos] + R[size], to: after[1][pos], cross: cross(after[1]) }
          ]
        });
      }
      return candidates.filter((c) => Math.abs(c.delta) <= threshold && c.gaps.every((g) => "to" in g && g.to - g.from >= 2)).sort((a, b) => Math.abs(a.delta) - Math.abs(b.delta))[0] || null;
    };
    const fx = best(xs, [moving.x, moving.x + moving.w / 2, moving.x + moving.w]);
    const fy = best(ys, [moving.y, moving.y + moving.h / 2, moving.y + moving.h]);
    const sx = spacing("x");
    const sy = spacing("y");
    const guides = [];
    let dx = 0;
    let dy = 0;
    if (sx && (!fx || Math.abs(sx.delta) <= Math.abs(fx.delta))) {
      dx = sx.delta;
      guides.push(...sx.gaps);
    } else if (fx) {
      dx = fx.delta;
      guides.push({ axis: "x", at: fx.at });
    }
    if (sy && (!fy || Math.abs(sy.delta) <= Math.abs(fy.delta))) {
      dy = sy.delta;
      guides.push(...sy.gaps);
    } else if (fy) {
      dy = fy.delta;
      guides.push({ axis: "y", at: fy.at });
    }
    return { dx, dy, guides };
  }
  function resizeBox(origin, handle, dx, dy, keepRatio, fromCenter = false) {
    const k = fromCenter ? 2 : 1;
    let { x, y, w, h } = origin;
    if (handle.includes("e")) {
      w = Math.max(4, w + dx * k);
    }
    if (handle.includes("s")) {
      h = Math.max(4, h + dy * k);
    }
    if (handle.includes("w")) {
      const nw = Math.max(4, w - dx * k);
      x += w - nw;
      w = nw;
    }
    if (handle.includes("n")) {
      const nh = Math.max(4, h - dy * k);
      y += h - nh;
      h = nh;
    }
    if (keepRatio && origin.w && origin.h) {
      const ratio = origin.w / origin.h;
      if (handle === "n" || handle === "s") {
        w = h * ratio;
      } else {
        h = w / ratio;
      }
      if (handle.includes("n")) {
        y = origin.y + origin.h - h;
      }
      if (handle.includes("w")) {
        x = origin.x + origin.w - w;
      }
      if (handle === "n" || handle === "s") {
        x = origin.x + (origin.w - w) / 2;
      } else if (handle === "e" || handle === "w") {
        y = origin.y + (origin.h - h) / 2;
      }
    }
    if (fromCenter) {
      x = origin.x + (origin.w - w) / 2;
      y = origin.y + (origin.h - h) / 2;
    }
    return { x, y, w, h };
  }

  // packages/editor/src/shapes.tsx
  var import_element = __toESM(require_element(), 1);
  function paint(value, colors, other = "none") {
    const v = typeof value === "string" ? value.trim() : "";
    if (!v) {
      return other;
    }
    if (v[0] === "$") {
      return colors[v.slice(1)] ? paint(colors[v.slice(1)], {}, other) : other;
    }
    return /^(#[0-9a-f]{3,8}|none|transparent|(rgb|hsl)a?\([0-9.,%\s/deg-]+\))$/i.test(v) ? v : other;
  }
  function boxOf(a) {
    return { x: Number(a.x) || 0, y: Number(a.y) || 0, w: Number(a.width) || 0, h: Number(a.height) || 0 };
  }
  function Shape({ block, colors }) {
    const a = block.attributes;
    const { x, y, w, h } = boxOf(a);
    const stroke = paint(a.stroke, colors);
    const common = { fill: paint(a.fill, colors), stroke: stroke === "none" || !a.strokeWidth ? void 0 : stroke, strokeWidth: a.strokeWidth || void 0, opacity: a.opacity };
    let inside = null;
    switch (block.name) {
      case "draw/rect":
        inside = /* @__PURE__ */ (0, import_element.createElement)("path", { d: rectPath(x, y, w, h, Array.isArray(a.radius) ? a.radius : [], a.corner), ...common });
        break;
      case "draw/ellipse":
        inside = /* @__PURE__ */ (0, import_element.createElement)("ellipse", { cx: x + w / 2, cy: y + h / 2, rx: w / 2, ry: h / 2, ...common });
        break;
      case "draw/line":
        inside = /* @__PURE__ */ (0, import_element.createElement)("line", { x1: a.x1 || 0, y1: a.y1 || 0, x2: a.x2 || 0, y2: a.y2 || 0, stroke: stroke === "none" ? "currentColor" : stroke, strokeWidth: a.strokeWidth || 1 });
        break;
      case "draw/path":
        inside = isPathData(a.d || "") ? /* @__PURE__ */ (0, import_element.createElement)("svg", { x, y, width: w, height: h, viewBox: String(a.viewBox || "0 0 100 100"), overflow: "hidden" }, /* @__PURE__ */ (0, import_element.createElement)("path", { d: a.d, ...common })) : null;
        break;
      case "draw/svg":
        inside = /* @__PURE__ */ (0, import_element.createElement)("svg", { x, y, width: w, height: h, viewBox: String(a.viewBox || "0 0 24 24"), overflow: "hidden" }, (block.innerBlocks || []).filter((inner) => inner.name === "draw/svg-path" && isPathData(inner.attributes.d || "")).map((inner) => /* @__PURE__ */ (0, import_element.createElement)("path", { key: inner.clientId, d: inner.attributes.d, fill: paint(inner.attributes.fill, colors, paint(a.color, colors, "currentColor")), fillRule: inner.attributes.rule === "evenodd" ? "evenodd" : void 0 })));
        break;
      case "draw/image":
        inside = /^https?:\/\//i.test(a.src || "") ? /* @__PURE__ */ (0, import_element.createElement)("image", { x, y, width: w, height: h, href: a.src, preserveAspectRatio: a.fit === "cover" ? "xMidYMid slice" : "xMidYMid meet" }) : /* @__PURE__ */ (0, import_element.createElement)("rect", { x, y, width: w, height: h, fill: "none", stroke: "#999", strokeDasharray: "4 3" });
        break;
      case "draw/text": {
        const text = (Array.isArray(a.runs) ? a.runs.map((r) => r.text || "").join("") : "") || a.text || "";
        const size = Math.max(1, Number(a.size) || 14);
        const align = a.align === "center" ? "middle" : a.align === "right" ? "end" : "start";
        const tx = a.align === "center" ? x + w / 2 : a.align === "right" ? x + w : x;
        inside = /* @__PURE__ */ (0, import_element.createElement)("text", { x: tx, y: y + size, fontSize: size, textAnchor: align, fill: paint(a.color, colors, "currentColor"), fontWeight: a.weight || void 0 }, String(text));
        break;
      }
      default:
        return null;
    }
    return a.rotation ? /* @__PURE__ */ (0, import_element.createElement)("g", { transform: `rotate(${a.rotation} ${x + w / 2} ${y + h / 2})` }, inside) : inside;
  }

  // packages/editor/src/DrawCanvas.tsx
  var HANDLES = ["nw", "n", "ne", "e", "se", "s", "sw", "w"];
  var SNAP = 5;
  var SHAPES = [
    ["draw/rect", (0, import_i18n.__)("Rectangle", "draw"), { width: 120, height: 80, fill: "#dcdcde" }],
    ["draw/ellipse", (0, import_i18n.__)("Ellipse", "draw"), { width: 100, height: 100, fill: "#dcdcde" }],
    ["draw/text", (0, import_i18n.__)("Text", "draw"), { width: 160, height: 24, size: 18, runs: [{ text: (0, import_i18n.__)("Text", "draw") }] }],
    ["draw/svg", (0, import_i18n.__)("SVG", "draw"), { width: 64, height: 64, viewBox: "0 0 24 24" }]
  ];
  var INFO_ICON = "M5.5 12a6.5 6.5 0 1 1 13 0 6.5 6.5 0 0 1-13 0ZM12 4a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm.75 4v1.5h-1.5V8h1.5Zm0 8v-5h-1.5v5h1.5Z";
  function DrawCanvas({ clientId, width, height, colors = {} }) {
    const { blocks, selected } = (0, import_data.useSelect)(
      (select) => {
        const store = select(import_block_editor.store);
        return { blocks: store.getBlocks(clientId), selected: store.getSelectedBlockClientId() };
      },
      [clientId]
    );
    const { updateBlockAttributes, selectBlock, removeBlocks, insertBlocks } = (0, import_data.useDispatch)(import_block_editor.store);
    const svg = (0, import_element2.useRef)(null);
    const [drag, setDrag] = (0, import_element2.useState)(null);
    const [guides, setGuides] = (0, import_element2.useState)([]);
    const scale = () => svg.current ? svg.current.getBoundingClientRect().width / width : 1;
    const shapes = blocks.filter((b) => b.name !== "draw/svg-path");
    const current = shapes.find((b) => b.clientId === selected);
    const others = (id) => shapes.filter((b) => b.clientId !== id && b.attributes.width).map((b) => boxOf(b.attributes));
    const begin = (event, block, kind, handle) => {
      var _a, _b;
      event.stopPropagation();
      (_b = (_a = event.currentTarget).setPointerCapture) == null ? void 0 : _b.call(_a, event.pointerId);
      selectBlock(block.clientId);
      setDrag({ kind, id: block.clientId, handle, startX: event.clientX, startY: event.clientY, origin: boxOf(block.attributes) });
    };
    const move = (event) => {
      if (!drag) {
        return;
      }
      const k = scale() || 1;
      const dx = (event.clientX - drag.startX) / k;
      const dy = (event.clientY - drag.startY) / k;
      let next = drag.origin;
      if (drag.kind === "move") {
        next = { ...drag.origin, x: drag.origin.x + dx, y: drag.origin.y + dy };
        const found = snap(next, { w: width, h: height, safe: 0 }, others(drag.id), SNAP / k);
        next = { ...next, x: next.x + found.dx, y: next.y + found.dy };
        setGuides(found.guides);
      } else {
        next = resizeBox(drag.origin, drag.handle || "se", dx, dy, event.shiftKey, event.altKey);
      }
      updateBlockAttributes(drag.id, { x: round(next.x), y: round(next.y), width: round(next.w), height: round(next.h) });
    };
    const end = () => {
      setDrag(null);
      setGuides([]);
    };
    const keys = (event) => {
      if (!current) {
        return;
      }
      const step = event.shiftKey ? 10 : 1;
      const a = current.attributes;
      const patch = { ArrowLeft: { x: (a.x || 0) - step }, ArrowRight: { x: (a.x || 0) + step }, ArrowUp: { y: (a.y || 0) - step }, ArrowDown: { y: (a.y || 0) + step } }[event.key];
      if (patch) {
        event.preventDefault();
        updateBlockAttributes(current.clientId, patch);
      } else if (event.key === "Delete" || event.key === "Backspace") {
        event.preventDefault();
        removeBlocks(current.clientId);
      }
    };
    const add = (name, attrs) => {
      const w = Number(attrs.width) || 0;
      const h = Number(attrs.height) || 0;
      const place = { x: round((width - w) / 2), y: round((height - h) / 2) };
      const inner = name === "draw/svg" ? [(0, import_blocks.createBlock)("draw/svg-path", { d: INFO_ICON })] : [];
      insertBlocks((0, import_blocks.createBlock)(name, { ...attrs, ...place }, inner), void 0, clientId);
    };
    const cur = current ? boxOf(current.attributes) : null;
    const hs = 6 / (scale() || 1);
    return /* @__PURE__ */ (0, import_element2.createElement)("div", { className: "draw-canvas", onKeyDown: keys, tabIndex: 0 }, /* @__PURE__ */ (0, import_element2.createElement)(import_components.ButtonGroup, { className: "draw-canvas__tools" }, SHAPES.map(([name, label, attrs]) => /* @__PURE__ */ (0, import_element2.createElement)(import_components.Button, { key: name, variant: "secondary", size: "small", onClick: () => add(name, attrs) }, label))), /* @__PURE__ */ (0, import_element2.createElement)("svg", { ref: svg, className: "draw-canvas__page", viewBox: `0 0 ${width} ${height}`, onPointerMove: move, onPointerUp: end, onPointerCancel: end, onPointerDown: () => selectBlock(clientId), style: { width: "100%", background: "#fff", touchAction: "none" } }, shapes.map((block) => /* @__PURE__ */ (0, import_element2.createElement)("g", { key: block.clientId, onPointerDown: (event) => begin(event, block, "move"), style: { cursor: "move" } }, /* @__PURE__ */ (0, import_element2.createElement)(Shape, { block, colors }))), guides.map((g, i) => g.kind === "gap" ? null : g.axis === "x" ? /* @__PURE__ */ (0, import_element2.createElement)("line", { key: i, x1: g.at, x2: g.at, y1: 0, y2: height, stroke: "#d63638", strokeWidth: 1 / (scale() || 1) }) : /* @__PURE__ */ (0, import_element2.createElement)("line", { key: i, y1: g.at, y2: g.at, x1: 0, x2: width, stroke: "#d63638", strokeWidth: 1 / (scale() || 1) })), current && cur && cur.w > 0 && /* @__PURE__ */ (0, import_element2.createElement)("g", null, /* @__PURE__ */ (0, import_element2.createElement)("rect", { x: cur.x, y: cur.y, width: cur.w, height: cur.h, fill: "none", stroke: "#3858e9", strokeWidth: 1 / (scale() || 1), pointerEvents: "none" }), HANDLES.map((handle) => {
      const hx = handle.includes("w") ? cur.x : handle.includes("e") ? cur.x + cur.w : cur.x + cur.w / 2;
      const hy = handle.includes("n") ? cur.y : handle.includes("s") ? cur.y + cur.h : cur.y + cur.h / 2;
      return /* @__PURE__ */ (0, import_element2.createElement)("rect", { key: handle, x: hx - hs / 2, y: hy - hs / 2, width: hs, height: hs, fill: "#fff", stroke: "#3858e9", strokeWidth: 1 / (scale() || 1), style: { cursor: `${handle}-resize` }, onPointerDown: (event) => begin(event, current, "resize", handle) });
    }))));
  }
  var round = (n) => Math.round(n * 100) / 100;

  // plugins/draw/src/index.tsx
  var SHAPES2 = ["draw/rect", "draw/ellipse", "draw/line", "draw/path", "draw/image", "draw/text", "draw/svg"];
  var NUMBERS = ["x", "y", "width", "height"];
  function field(props, key, label) {
    return /* @__PURE__ */ (0, import_element3.createElement)(
      import_components2.TextControl,
      {
        key,
        label,
        value: props.attributes[key] === void 0 ? "" : String(props.attributes[key]),
        onChange: (value) => props.setAttributes({ [key]: value === "" ? void 0 : NUMBERS.includes(key) ? Number(value) : value })
      }
    );
  }
  (0, import_blocks2.registerBlockType)("draw/frame", {
    edit: (props) => {
      const width = Math.max(10, parseLen(props.attributes.width || "600px", 0));
      const height = Math.max(10, parseLen(props.attributes.height || "400px", 0));
      const layers = (0, import_block_editor2.useInnerBlocksProps)({ style: { display: "none" } }, { allowedBlocks: SHAPES2, renderAppender: false });
      return /* @__PURE__ */ (0, import_element3.createElement)("div", { ...(0, import_block_editor2.useBlockProps)({ className: "draw-frame draw-frame--editor" }) }, /* @__PURE__ */ (0, import_element3.createElement)(import_block_editor2.InspectorControls, null, /* @__PURE__ */ (0, import_element3.createElement)(import_components2.PanelBody, { title: (0, import_i18n2.__)("Frame", "draw") }, field(props, "width", (0, import_i18n2.__)("Width (e.g. 600px)", "draw")), field(props, "height", (0, import_i18n2.__)("Height (e.g. 400px)", "draw")))), /* @__PURE__ */ (0, import_element3.createElement)(DrawCanvas, { clientId: props.clientId, width, height, colors: props.attributes.colors || {} }), /* @__PURE__ */ (0, import_element3.createElement)("div", { ...layers }));
    },
    save: () => /* @__PURE__ */ (0, import_element3.createElement)(import_block_editor2.InnerBlocks.Content, null)
  });
  SHAPES2.forEach((name) => {
    (0, import_blocks2.registerBlockType)(name, {
      edit: (props) => /* @__PURE__ */ (0, import_element3.createElement)("div", { ...(0, import_block_editor2.useBlockProps)({ className: "draw-shape" }) }, /* @__PURE__ */ (0, import_element3.createElement)(import_block_editor2.InspectorControls, null, /* @__PURE__ */ (0, import_element3.createElement)(import_components2.PanelBody, { title: (0, import_i18n2.__)("Place and color", "draw") }, field(props, "x", "x"), field(props, "y", "y"), field(props, "width", (0, import_i18n2.__)("Width", "draw")), field(props, "height", (0, import_i18n2.__)("Height", "draw")), field(props, "fill", (0, import_i18n2.__)("Fill", "draw")))), /* @__PURE__ */ (0, import_element3.createElement)("strong", null, name.replace("draw/", "")), name === "draw/svg" ? /* @__PURE__ */ (0, import_element3.createElement)(import_block_editor2.InnerBlocks, { allowedBlocks: ["draw/svg-path"] }) : null),
      save: () => name === "draw/svg" ? /* @__PURE__ */ (0, import_element3.createElement)(import_block_editor2.InnerBlocks.Content, null) : null
    });
  });
  (0, import_blocks2.registerBlockType)("draw/svg-path", {
    edit: (props) => /* @__PURE__ */ (0, import_element3.createElement)("div", { ...(0, import_block_editor2.useBlockProps)({ className: "draw-path" }) }, /* @__PURE__ */ (0, import_element3.createElement)(import_components2.TextControl, { label: (0, import_i18n2.__)("Path data (d)", "draw"), value: props.attributes.d || "", onChange: (d) => props.setAttributes({ d }) })),
    save: () => null
  });
})();
