# Vendored third-party JS/CSS — lib_j2commerceflow

All files below are pristine, unmodified UMD/CSS distribution builds fetched directly from the
npm registry tarballs (`registry.npmjs.org/<pkg>/-/<pkg>-<version>.tgz`) on 2026-07-05. None of
these files have been hand-patched. SHA-256 computed with `certutil -hashfile <file> SHA256`.

## Core engine

| File | Package | Version | License | SHA-256 |
|------|---------|---------|---------|---------|
| `x6/x6.min.js` | `@antv/x6` | 2.19.2 | MIT | `62e3e9a422c4233ccb03a9d8dfbbfd4a1477339fe9ed7209939a645be9475a7a` |
| `x6/x6-LICENSE.txt` | `@antv/x6` | 2.19.2 | MIT | `39636bce8499f256423545aa6e4e9e3735a3ccb4e32dcc0fec865dc44a507226` |
| `../../css/vendor/x6.css` | `@antv/x6` | 2.19.2 | MIT | `cd1af73076567522c0e92edeed1c201aebe4317a65c29932f8cdf8063320f826` |

`x6.min.js` is byte-identical to the `dist/index.js` file inside the `@antv/x6@2.19.2` npm
tarball (confirmed via `diff` against the previously-vendored, unversioned
`plugins/j2commerce/app_aftersalespecial/media/js/vendor/x6.js` — see the extraction PRD's Phase
0 audit finding: **no local patches existed**, the prior copy was already an exact match for
2.19.2). Despite the `.min.js` filename (kept for naming-convention consistency with the plugin
dist files below), this is the package's `unpkg`/`jsdelivr` production build — `@antv/x6` ships
no separately-minified variant at this version.

## Official X6 2.x plugin set

All still declare `peerDependencies: { "@antv/x6": "^2.x" }` (only `@antv/x6-plugin-clipboard`
has moved to a v3-only line, and is not vendored here). UMD global names verified by inspecting
each package's actual `dist/index.js` header (not guessed).

| File | Package | Version | UMD global | Exported class | Has CSS | SHA-256 (js) |
|------|---------|---------|------------|-----------------|---------|--------------|
| `x6/plugins/x6-plugin-history.min.js` | `@antv/x6-plugin-history` | 2.2.4 | `X6PluginHistory` | `.History` | no | `d978e5c59b9cc3f10955dd35c8965cc1b2253304821e9a37269eb90022dbe3a5` |
| `x6/plugins/x6-plugin-snapline.min.js` (+`.css`) | `@antv/x6-plugin-snapline` | 2.1.7 | `X6PluginSnapline` | `.Snapline` | yes | `ebd2b6157c0334f1400b7334fd57d5451730d7383c1bf2a970a46d0970684608` |
| `x6/plugins/x6-plugin-selection.min.js` (+`.css`) | `@antv/x6-plugin-selection` | 2.2.2 | `X6PluginSelection` | `.Selection` | yes | `67c05842beeaf86c4c8ba78c951cdd60a7637d9683e976e183d20d438f5af826` |
| `x6/plugins/x6-plugin-keyboard.min.js` | `@antv/x6-plugin-keyboard` | 2.2.3 | `X6PluginKeyboard` | `.Keyboard` | no | `122030a47b3ce26d03afad0321bce420eaf296ee814e71a2e2ddd8d613978fb4` |
| `x6/plugins/x6-plugin-minimap.min.js` (+`.css`) | `@antv/x6-plugin-minimap` | 2.0.7 | `X6PluginMinimap` | `.MiniMap` | yes | `a0fee8aeab2d0e83ecd8deb6654800f77dc81ee4b082bd8dfd60d16ec1f9a64c` |
| `x6/plugins/x6-plugin-scroller.min.js` (+`.css`) | `@antv/x6-plugin-scroller` | 2.0.10 | `X6PluginScroller` | `.Scroller` / `.Background` | yes | `a03eb63c86cf5165ff729699086cabb08abbfd5e2d7343e9bc8affe66a577bec` |
| `x6/plugins/x6-plugin-stencil.min.js` (+`.css`) | `@antv/x6-plugin-stencil` | 2.1.5 | `X6PluginStencil` | `.Stencil` | yes | `699c543b042ea87207531011df6eea4035d153d6e3afb7eeb9bd56d780d46382` |
| `x6/plugins/x6-plugin-dnd.min.js` (+`.css`) | `@antv/x6-plugin-dnd` | 2.1.1 | `X6PluginDnd` | `.Dnd` | yes | `d427165e1dd1c62dea6f226482b16630f9bcda36808d8b4f1b1c43a927ec325f` |
| `x6/plugins/x6-plugin-export.min.js` | `@antv/x6-plugin-export` | 2.1.6 | `X6PluginExport` | `.Export` | no | `a969f758f2cc451fc28dd6654a039c7d4679fc5a1b554a4c46403691d6869b98` |
| `x6/plugins/x6-plugin-transform.min.js` (+`.css`) | `@antv/x6-plugin-transform` | 2.1.8 | `X6PluginTransform` | `.Transform` | yes | `bceb1a51871459f432482e1b0ff8936ff89a40d5de36e9503bdbbf1941d54620` |

All plugin `require("@antv/x6")` externals resolve to `window.X6` at runtime (UMD global mode) —
`x6.min.js` MUST be loaded before any plugin file (enforced by `FlowAssets::registerCore()`
running before `registerPlugin()`).

## Layout engine

| File | Package | Version | License | Global | SHA-256 |
|------|---------|---------|---------|--------|---------|
| `../dagre/dagre.min.js` | `dagre` | 0.8.5 | MIT | `window.dagre` (`dagre.graphlib.Graph`, `dagre.layout(g)`) | `62eb9787ccfdbdf4148d4d99d31dbf9ee4770eafee81e637d759b52aac22cd51` |
| `../dagre/dagre-LICENSE.txt` | `dagre` | 0.8.5 | MIT | — | `6a349742a6cb219d5a2fc8d0844f6d89a6efc62e20c664450d884fc7ff2d6015` |

## First-party glue (not vendored, tracked normally in git)

| File | SHA-256 (informational, changes with every edit) |
|------|-----|
| `../../j2c-flow.js` | `d01ca25088bc299fdad44c32b5c9c0acb66c7ef129ce5c4b330473a7a4dd9720` |
| `../../../css/j2c-flow.css` | `ba7f5a0e5123a7b034ff99e0a95b11119846d94cd99e0b31e5bf725a6e6cc9d3` |
