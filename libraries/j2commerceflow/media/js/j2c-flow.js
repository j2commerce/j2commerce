/**
 * J2CFlow — generic flow-diagram renderer built on AntV X6 (vendored, MIT license:
 * media/lib_j2commerceflow/js/vendor/x6/x6.min.js + x6-LICENSE.txt). Vanilla ES6+ glue code,
 * zero domain knowledge (no products/offers/promotions). Reusable by any J2Commerce admin
 * flow-shaped screen. Exposes window.J2CFlow.
 *
 * X6 itself (and its official 2.x plugin set, vendored under js/vendor/x6/plugins/, and dagre,
 * vendored under js/vendor/dagre/) is third-party code and is exempt from the project's
 * no-innerHTML rule (same precedent as swiper-bundle.min.js / grapesjs). This file's OWN code
 * below never uses innerHTML/insertAdjacentHTML — every node's DOM is built with
 * document.createElement + append().
 *
 * Data shape (unchanged from the original app_aftersalespecial extraction — this contract is
 * FROZEN, see the library PRD's Phase 1 scope):
 * {
 *   nodes: [{ id: string, type: string, icon?: string, title: string, body: string[],
 *             badges?: [{text: string, variant: 'secondary'|'warning'|'danger'}],
 *             x?: number, y?: number }],
 *   edges: [{ from: string, to: string, label?: string, style?: 'solid'|'dashed',
 *             variant?: 'yes'|'no' }]
 * }
 *
 * edge.variant (additive, optional) picks the label pill color (yes = primary blue,
 * no = grey, unset = neutral) so branch styling never keys on localized display text.
 *
 * node.locked (additive, optional) disables dragging for that one node via the Graph's
 * interacting() option (see createGraph()) — generic, the library has no idea what a
 * "locked" node represents (e.g. a host's ghost/placeholder node type).
 *
 * x/y are optional persisted canvas positions (see the host page's own position-persistence
 * round trip). When a node has no x/y, it is auto-laid-out in a single vertical column in
 * array order — trigger-shaped screens keep their original stacked look on first load before
 * the user drags anything or calls tidy().
 *
 * Usage: J2CFlow.render(containerEl, data, { onNodeClick(nodeId, nodeType), onNodePositionChange(nodeId, x, y), onHistoryChange()?, minimapContainer? })
 * - The FIRST call for a given containerEl clears its existing content and mounts a new X6
 *   Graph there. Every call (first or not) then clears and rebuilds the graph's cells from the
 *   given data — the same containerEl/Graph instance is reused across calls so listeners (and
 *   any wired official X6 plugins) are set up only once.
 * - onNodeClick fires on mouse click (X6's node:click) and on Enter/Space when a node has focus
 *   (keyboard access — X6 has no built-in keyboard activation for custom shapes).
 * - onNodePositionChange fires once a drag ends (X6's node:moved) and once per node after a
 *   J2CFlow.tidy() call, with the node's final {x, y} read from the cell itself.
 * - minimapContainer (optional) is only honoured on the FIRST render() call for a given
 *   containerEl (mirrors Graph mount timing) and only if the minimap plugin
 *   (window.X6PluginMinimap) is loaded — see FlowAssets::register(['minimap']).
 *
 * Sizing: the graph's width is recomputed from the container's actual clientWidth on every
 * render() call (not a fixed constant) and each node's height is measured from its real
 * rendered content (not a fixed per-type constant) — see computeGraphWidth() and
 * measureNodeHeight().
 *
 * Optional capability upgrades (Phase 2) — each wires ONLY when its official X6 2.x plugin
 * global is present (verified against the actual vendored UMD headers, not guessed):
 * - History (window.X6PluginHistory) — undo/redo. J2CFlow.undo()/redo()/canUndo()/canRedo(),
 *   plus Ctrl+Z / Ctrl+Shift+Z bound through the Keyboard plugin when both are loaded. Every
 *   history-stack change re-fires options.onHistoryChange so hosts can sync their own
 *   undo/redo button disabled states.
 * - Snapline (window.X6PluginSnapline) — alignment guides while dragging.
 * - Selection (window.X6PluginSelection) — shift+click / rubberband multi-select (visual only;
 *   J2CFlow's own single-node onNodeClick panel-opening behaviour is untouched).
 * - Keyboard (window.X6PluginKeyboard) — required for the Ctrl+Z/Ctrl+Shift+Z bindings above.
 *   Deliberately does NOT bind Delete/Backspace: J2CFlow's cells are ephemeral view state (the
 *   host page fully rebuilds the canvas from server-confirmed data after every mutation), so a
 *   client-only cell delete with no save round trip would look like data loss without being one.
 * - Scroller (window.X6PluginScroller) — pan support once a diagram grows past the viewport.
 *   CAUTION: with the Scroller mounted, the graph area used by translating.restrict no longer
 *   tracks render()'s graph.resize(), so node drags get clamped to the tiny initial canvas
 *   (verified live 2026-07-05: a downward drag snapped the node to y=0). Consumers whose
 *   screens scroll via their own CSS overflow container (e.g. aftersalespecial's
 *   .j2c-flow-scroll) should NOT opt into 'scroller' until this restrict interplay is solved.
 * - MiniMap (window.X6PluginMinimap) — only mounted when the caller supplies
 *   options.minimapContainer.
 * - dagre (window.dagre) — J2CFlow.tidy(containerEl): top-down ranked auto-layout for branching
 *   graphs, firing onNodePositionChange per repositioned node so hosts can persist the result
 *   through their existing save mechanism.
 *
 * Extensibility contract (Phase 2) — J2CFlow.registerStepType({ type, icon, renderCard(bodyEl,
 * node) }) lets a consumer customize a node type's BODY rendering (header/icon/title/badges
 * stay generic, since that chrome is identical across types). Unregistered types keep the
 * original default per-line body renderer.
 */
'use strict';

window.J2CFlow = (function () {
    const ICON_CLASSES = {
        bolt: 'fa-bolt',
        tag: 'fa-tag',
        'circle-check': 'fa-circle-check',
        plus: 'fa-plus',
    };

    const SHAPE_NAME = 'j2c-flow-node';
    const NODE_WIDTH = 352;

    // Floors only — actual node height is measured from real content (see
    // measureNodeHeight()). A node is never laid out shorter than its type's floor,
    // even if measured content comes back smaller than expected.
    const MIN_NODE_HEIGHTS = {
        trigger: 170,
        offer: 150,
        terminal: 60,
        add: 56,
        default: 130,
    };

    const TOP_MARGIN = 24;
    const ROW_GAP = 56;

    // Floor + horizontal breathing room for the responsive graph width (see
    // computeGraphWidth()) — replaces the old fixed 440px constant.
    const MIN_GRAPH_WIDTH = 440;
    const CANVAS_HPADDING = 24;

    // Fixed pixel offset (each side of the straight vertical line) for a same-pair
    // Yes/No fork — see addEdges(). Small and symmetric so both labels stay fully
    // inside the canvas with wide margin regardless of graph width or node position.
    const FORK_OFFSET = 48;

    // dagre tidy() layout spacing (see tidy()).
    const DAGRE_NODESEP = 48;
    const DAGRE_RANKSEP = 56;

    let shapesRegistered = false;

    // containerEl -> X6.Graph instance (one Graph per mount point, reused across renders).
    const graphRegistry = new WeakMap();
    // containerEl -> { onNodeClick, onNodePositionChange, minimapContainer } (updated on every
    // render() call so the persistent graph.on(...) listeners always call the latest callbacks).
    const callbackRegistry = new WeakMap();
    // containerEl -> true once a minimap has been mounted for it (mount-once, like the Graph itself).
    const minimapRegistry = new WeakMap();

    // node type -> { type, icon?, renderCard(bodyEl, node) } — see registerStepType().
    const stepTypeRegistry = new Map();

    function registerShapes() {
        if (shapesRegistered || !window.X6) {
            return;
        }

        window.X6.Shape.HTML.register({
            shape: SHAPE_NAME,
            effect: ['data'],
            html(cell) {
                return buildNodeEl(cell.getData() || {});
            },
        });

        shapesRegistered = true;
    }

    /**
     * Registers a node type's body renderer. Unregistered types keep the original default
     * per-line body renderer (renderDefaultBody). Header (icon + title + badges) always stays
     * generic — it is identical chrome across every type.
     *
     * @param {{type: string, icon?: string, renderCard?: (bodyEl: HTMLElement, node: object) => void}} config
     */
    function registerStepType(config) {
        if (!config || typeof config.type !== 'string' || config.type === '') {
            return;
        }

        stepTypeRegistry.set(config.type, config);
    }

    function createBadge(badge) {
        const span = document.createElement('span');
        span.className = 'badge j2c-flow-badge j2c-flow-badge--' + (badge.variant || 'secondary') + ' ms-auto';
        span.textContent = badge.text;

        return span;
    }

    /** Original default body renderer — one <p> per body line, unchanged from Phase 1. */
    function renderDefaultBody(bodyEl, node) {
        (node.body || []).forEach((line) => {
            if (!line) {
                return;
            }

            const p = document.createElement('p');
            p.className = 'j2c-flow-node-line';
            p.textContent = line;
            bodyEl.append(p);
        });
    }

    function buildNodeEl(node) {
        const el = document.createElement('div');
        el.className = 'j2c-flow-node j2c-flow-node--' + node.type;
        el.dataset.j2cFlowNode = '';
        el.dataset.nodeId = node.id;
        el.dataset.nodeType = node.type;
        el.tabIndex = 0;
        el.setAttribute('role', 'button');

        const stepType = stepTypeRegistry.get(node.type);

        const header = document.createElement('div');
        header.className = 'j2c-flow-node-header';

        const iconName = node.icon || (stepType && stepType.icon) || 'tag';
        const icon = document.createElement('span');
        icon.className = 'fa-solid ' + (ICON_CLASSES[iconName] || ICON_CLASSES.tag);
        icon.setAttribute('aria-hidden', 'true');

        const title = document.createElement('span');
        title.className = 'j2c-flow-node-title';
        title.textContent = node.title || '';

        header.append(icon, title);
        (node.badges || []).forEach((badge) => header.append(createBadge(badge)));

        const body = document.createElement('div');
        body.className = 'j2c-flow-node-body';

        if (stepType && typeof stepType.renderCard === 'function') {
            stepType.renderCard(body, node);
        } else {
            renderDefaultBody(body, node);
        }

        el.append(header, body);

        return el;
    }

    /**
     * Mounts a real (off-screen, layout-participating) copy of the node to measure its
     * natural content height, replacing the old fixed NODE_HEIGHTS lookup. Falls back to
     * the type's floor (MIN_NODE_HEIGHTS) if measured content comes back smaller.
     */
    function measureNodeHeight(node) {
        const floor = MIN_NODE_HEIGHTS[node.type] || MIN_NODE_HEIGHTS.default;
        const el = buildNodeEl(node);

        el.style.position = 'absolute';
        el.style.visibility = 'hidden';
        el.style.left = '-9999px';
        el.style.width = NODE_WIDTH + 'px';
        el.style.height = 'auto';

        document.body.append(el);
        const measured = el.scrollHeight || el.getBoundingClientRect().height;
        el.remove();

        return Math.max(floor, Math.ceil(measured));
    }

    function selectNodeEl(containerEl, el) {
        if (!el) {
            return;
        }

        containerEl.querySelectorAll('.j2c-flow-node--selected').forEach((n) => n.classList.remove('j2c-flow-node--selected'));
        el.classList.add('j2c-flow-node--selected');
    }

    function findNodeEl(containerEl, nodeId) {
        return containerEl.querySelector('[data-cell-id="' + nodeId + '"] [data-j2c-flow-node]');
    }

    /** Graph width from the actual mount point's clientWidth, floored at MIN_GRAPH_WIDTH. */
    function computeGraphWidth(containerEl) {
        const measured = containerEl.clientWidth || MIN_GRAPH_WIDTH;

        return Math.max(MIN_GRAPH_WIDTH, measured - CANVAS_HPADDING);
    }

    /**
     * Wires every official X6 2.x plugin whose UMD global is actually present. Each plugin is
     * fully optional and independent — a host page only gets the ones its FlowAssets::register()
     * call opted into loading (see the library's FlowAssets.php allowlist).
     */
    function wirePlugins(graph, containerEl) {
        if (window.X6PluginHistory) {
            graph.use(new window.X6PluginHistory.History({ enabled: true }));
        }

        if (window.X6PluginSnapline) {
            graph.use(new window.X6PluginSnapline.Snapline({ enabled: true, sharp: true }));
        }

        if (window.X6PluginSelection) {
            graph.use(new window.X6PluginSelection.Selection({
                enabled: true,
                multiple: true,
                rubberband: true,
                movable: false,
                showNodeSelectionBox: true,
                modifiers: ['shift'],
            }));
        }

        if (window.X6PluginKeyboard) {
            graph.use(new window.X6PluginKeyboard.Keyboard({ enabled: true }));

            graph.bindKey(['ctrl+z', 'meta+z'], () => {
                if (typeof graph.canUndo === 'function' && graph.canUndo()) {
                    graph.undo();
                }

                return false;
            });

            graph.bindKey(['ctrl+shift+z', 'meta+shift+z', 'ctrl+y', 'meta+y'], () => {
                if (typeof graph.canRedo === 'function' && graph.canRedo()) {
                    graph.redo();
                }

                return false;
            });
        }

        if (window.X6PluginScroller) {
            // See the Scroller CAUTION in the header — breaks translating.restrict clamping.
            graph.use(new window.X6PluginScroller.Scroller({ enabled: true, pannable: true, autoResize: false }));
        }

        if (window.X6PluginMinimap) {
            const callbacks = callbackRegistry.get(containerEl);
            const minimapContainer = callbacks && callbacks.minimapContainer;

            if (minimapContainer && !minimapRegistry.get(containerEl)) {
                graph.use(new window.X6PluginMinimap.MiniMap({
                    container: minimapContainer,
                    width: 200,
                    height: 160,
                    padding: 10,
                }));
                minimapRegistry.set(containerEl, true);
            }
        }
    }

    function createGraph(containerEl) {
        containerEl.replaceChildren();
        registerShapes();

        const graph = new window.X6.Graph({
            container: containerEl,
            width: computeGraphWidth(containerEl),
            height: TOP_MARGIN * 2,
            panning: false,
            mousewheel: false,
            // Keeps dragged nodes within the graph's own content area (0,0 to the
            // current width/height). Verified against the vendored bundle's
            // getRestrictArea(): translating.restrict === true resolves to
            // graph.transform.getGraphArea(), which always tracks whatever size
            // graph.resize() last set (see render()) — not a one-time snapshot.
            translating: { restrict: true },
            // node.data.locked (additive, optional — same "frozen contract + additive
            // extension" precedent as edge.variant) disables dragging for that one node.
            // Generic: the library has no idea what a "locked" node represents.
            interacting(cellView) {
                const cell = cellView.cell;
                const data = cell && typeof cell.isNode === 'function' && cell.isNode() ? cell.getData() : null;

                return { nodeMovable: !(data && data.locked) };
            },
        });

        graph.on('node:click', ({ node }) => {
            const data = node.getData() || {};
            const callbacks = callbackRegistry.get(containerEl);

            selectNodeEl(containerEl, findNodeEl(containerEl, node.id));

            if (callbacks) {
                callbacks.onNodeClick(String(node.id), data.type);
            }
        });

        graph.on('node:moved', ({ node }) => {
            const callbacks = callbackRegistry.get(containerEl);

            if (!callbacks) {
                return;
            }

            const pos = node.getPosition();
            callbacks.onNodePositionChange(String(node.id), pos.x, pos.y);
        });

        // Only ever emitted once the History plugin is wired (see wirePlugins()), so hosts can
        // rely on it to keep their own undo/redo controls in sync with every stack change —
        // drags, undo/redo themselves (button OR Ctrl+Z), and tidy().
        graph.on('history:change', () => {
            const callbacks = callbackRegistry.get(containerEl);

            if (callbacks) {
                callbacks.onHistoryChange();
            }
        });

        // An undo/redo moves nodes WITHOUT ending in a drag (no node:moved), so re-emit
        // onNodePositionChange for every position command it replayed — otherwise the host's
        // persisted positions keep the pre-undo layout and a page reload silently resurrects it.
        // Payload shape ({cmds: [{event, data: {id}}]}) verified against the vendored
        // x6-plugin-history 2.2.4 UMD's notify(). The position read is deferred one tick:
        // verified live that history:redo can fire BEFORE the replayed position lands on the
        // model, so a synchronous getPosition() persists the stale pre-redo coordinates.
        const emitReplayedPositions = ({ cmds }) => {
            setTimeout(() => {
                const callbacks = callbackRegistry.get(containerEl);

                if (!callbacks) {
                    return;
                }

                (cmds || []).forEach((cmd) => {
                    if (!cmd || cmd.event !== 'cell:change:position' || !cmd.data) {
                        return;
                    }

                    const cell = graph.getCellById(cmd.data.id);

                    if (cell && cell.isNode()) {
                        const pos = cell.getPosition();
                        callbacks.onNodePositionChange(String(cell.id), pos.x, pos.y);
                    }
                });
            }, 0);
        };
        graph.on('history:undo', emitReplayedPositions);
        graph.on('history:redo', emitReplayedPositions);

        containerEl.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            const el = event.target.closest('[data-j2c-flow-node]');

            if (!el) {
                return;
            }

            event.preventDefault();
            selectNodeEl(containerEl, el);

            const callbacks = callbackRegistry.get(containerEl);

            if (callbacks) {
                callbacks.onNodeClick(el.dataset.nodeId, el.dataset.nodeType);
            }
        });

        wirePlugins(graph, containerEl);

        graphRegistry.set(containerEl, graph);

        return graph;
    }

    /** Computes a layout position for every node (saved x/y wins; otherwise stacked column). */
    function layoutNodes(nodes, columnX) {
        let y = TOP_MARGIN;
        const layout = {};

        nodes.forEach((node) => {
            const height = measureNodeHeight(node);
            const hasSavedPosition = typeof node.x === 'number' && typeof node.y === 'number';
            const x = hasSavedPosition ? node.x : columnX;
            const nodeY = hasSavedPosition ? node.y : y;

            layout[node.id] = { x, y: nodeY, height };

            if (!hasSavedPosition) {
                y = nodeY + height + ROW_GAP;
            }
        });

        return { layout, totalHeight: y };
    }

    function addEdges(graph, edges, layout) {
        // Pre-count edges per from->to pair so a Yes/No pair (same source AND target —
        // a decline advances to the same next step as an accept) can be told apart from a
        // single, unlabelled edge that needs no fork.
        const pairCounts = {};
        edges.forEach((edge) => {
            const pairKey = edge.from + '->' + edge.to;
            pairCounts[pairKey] = (pairCounts[pairKey] || 0) + 1;
        });

        const pairSeen = {};

        edges.forEach((edge) => {
            const pairKey = edge.from + '->' + edge.to;
            const isPaired = pairCounts[pairKey] > 1;
            pairSeen[pairKey] = (pairSeen[pairKey] || 0) + 1;

            // Klaviyo-style connectors: uniform light-grey orthogonal lines with rounded
            // corners, no arrowheads — branch meaning is carried by the pill labels below.
            // Colors are concrete hex values on purpose: X6 writes attrs.line as SVG
            // presentation attributes, where CSS var() does not resolve — the browser drops
            // the invalid value and the path falls back to SVG's default stroke:none, i.e.
            // invisible lines (verified live 2026-07-06).
            const lineAttrs = {
                stroke: '#b9c2cc',
                strokeWidth: 1.5,
                targetMarker: null,
            };

            if (edge.style === 'dashed') {
                lineAttrs.strokeDasharray = 4;
            }

            // Anchors: leave from the source's bottom edge, arrive at the target's top edge
            // (anchor presets top/bottom + the 'anchor' connection point are all registered
            // in the vendored bundle). The orth router + rounded connector turn the fork
            // vertex below into a clean Klaviyo-style down->across->down elbow.
            const edgeConfig = {
                source: { cell: edge.from, anchor: { name: 'bottom' }, connectionPoint: { name: 'anchor' } },
                target: { cell: edge.to, anchor: { name: 'top' }, connectionPoint: { name: 'anchor' } },
                router: { name: 'orth' },
                connector: { name: 'rounded', args: { radius: 8 } },
                attrs: { line: lineAttrs },
            };

            if (edge.label) {
                // Pill-style label riding the connector. The default edge label's attrs are
                // keyed by tag name ("rect"/"text", markup selectors body/label — verified in
                // the vendored bundle's Edge defaultLabel), the rect refs the text bbox, and
                // refWidth2/refHeight2 add pixel padding on top of the 100% ref size.
                // edge.variant ('yes' | 'no', optional, additive to the frozen contract)
                // picks the pill color so the library never keys styling on display text.
                const pillFill = edge.variant === 'yes'
                    ? '#0d6efd'
                    : (edge.variant === 'no' ? '#5c636a' : '#e9ecef');
                const pillText = edge.variant ? '#ffffff' : '#212529';

                edgeConfig.label = {
                    attrs: {
                        text: {
                            text: edge.label,
                            fill: pillText,
                            fontSize: 12,
                            fontWeight: 600,
                        },
                        rect: {
                            fill: pillFill,
                            rx: 11,
                            ry: 11,
                            refWidth: 1,
                            refHeight: 1,
                            refWidth2: 20,
                            refHeight2: 8,
                            refX: -10,
                            refY: -4,
                        },
                    },
                    position: { distance: 0.5 },
                };
            }

            const fromPos = layout[edge.from];
            const toPos = layout[edge.to];

            if (isPaired && fromPos && toPos) {
                const centerX = fromPos.x + NODE_WIDTH / 2;
                const sign = pairSeen[pairKey] === 1 ? -1 : 1;

                edgeConfig.vertices = [{
                    x: centerX + sign * FORK_OFFSET,
                    y: (fromPos.y + fromPos.height + toPos.y) / 2,
                }];
            }

            graph.addEdge(edgeConfig);
        });
    }

    function render(containerEl, data, options) {
        const callbacks = {
            onNodeClick: (options && options.onNodeClick) || function () {},
            onNodePositionChange: (options && options.onNodePositionChange) || function () {},
            onHistoryChange: (options && options.onHistoryChange) || function () {},
            minimapContainer: options && options.minimapContainer,
        };
        callbackRegistry.set(containerEl, callbacks);

        const graph = graphRegistry.get(containerEl) || createGraph(containerEl);

        // A full render() rebuild (initial mount AND every post-mutation refresh) is a
        // programmatic data-driven replace, not a user gesture — it must NOT show up on the
        // undo stack (a user pressing Ctrl+Z right after an Add/Remove-offer refresh should
        // undo their last DRAG, not the entire rebuild). Only wire history around it when the
        // History plugin is actually present (disableHistory/enableHistory are added to
        // Graph.prototype by X6PluginHistory — see wirePlugins()).
        const hadHistoryPlugin = typeof graph.disableHistory === 'function';
        const wasHistoryEnabled = hadHistoryPlugin && typeof graph.isHistoryEnabled === 'function'
            ? graph.isHistoryEnabled()
            : false;

        if (hadHistoryPlugin) {
            graph.disableHistory();
        }

        graph.clearCells();

        const nodes = (data && data.nodes) || [];
        const edges = (data && data.edges) || [];
        const graphWidth = computeGraphWidth(containerEl);
        const columnX = (graphWidth - NODE_WIDTH) / 2;
        const { layout, totalHeight } = layoutNodes(nodes, columnX);

        // Saved positions (drags, a persisted tidy()) can lie outside the stacked-column
        // footprint: layoutNodes() only advances its running y for nodes WITHOUT a saved
        // position, so totalHeight alone under-sizes the graph once positions persist —
        // and translating.restrict (see createGraph()) then snaps those nodes back into
        // the too-small area on their next drag. Grow the graph to cover every laid-out
        // node's actual extent instead (never shrink below the persisted content).
        let contentBottom = totalHeight;
        let contentRight = graphWidth;

        Object.keys(layout).forEach((id) => {
            const pos = layout[id];
            contentBottom = Math.max(contentBottom, pos.y + pos.height + ROW_GAP);
            contentRight = Math.max(contentRight, pos.x + NODE_WIDTH);
        });

        graph.resize(contentRight, contentBottom + TOP_MARGIN);

        nodes.forEach((node) => {
            const pos = layout[node.id];

            graph.addNode({
                id: node.id,
                shape: SHAPE_NAME,
                x: pos.x,
                y: pos.y,
                width: NODE_WIDTH,
                height: pos.height,
                data: node,
            });
        });

        addEdges(graph, edges, layout);

        if (hadHistoryPlugin && wasHistoryEnabled) {
            graph.enableHistory();
        }
    }

    /** True once a Graph has been mounted for containerEl AND the history plugin is wired. */
    function hasHistory(containerEl) {
        const graph = graphRegistry.get(containerEl);

        return !!(graph && typeof graph.canUndo === 'function');
    }

    function undo(containerEl) {
        const graph = graphRegistry.get(containerEl);

        if (graph && typeof graph.canUndo === 'function' && graph.canUndo()) {
            graph.undo();
        }
    }

    function redo(containerEl) {
        const graph = graphRegistry.get(containerEl);

        if (graph && typeof graph.canRedo === 'function' && graph.canRedo()) {
            graph.redo();
        }
    }

    function canUndo(containerEl) {
        const graph = graphRegistry.get(containerEl);

        return !!(graph && typeof graph.canUndo === 'function' && graph.canUndo());
    }

    function canRedo(containerEl) {
        const graph = graphRegistry.get(containerEl);

        return !!(graph && typeof graph.canRedo === 'function' && graph.canRedo());
    }

    /**
     * Top-down ranked auto-layout via dagre (window.dagre — see FlowAssets::register(['dagre'])).
     * Repositions every node, then fires onNodePositionChange once per node so the host can
     * persist the new layout through its existing save mechanism. No-op if dagre isn't loaded
     * or no Graph has been mounted for containerEl yet.
     */
    function tidy(containerEl) {
        const graph = graphRegistry.get(containerEl);

        if (!graph || !window.dagre) {
            return;
        }

        const callbacks = callbackRegistry.get(containerEl) || {};
        const g = new window.dagre.graphlib.Graph();
        g.setGraph({ rankdir: 'TB', nodesep: DAGRE_NODESEP, ranksep: DAGRE_RANKSEP });
        g.setDefaultEdgeLabel(() => ({}));

        const nodes = graph.getNodes();

        nodes.forEach((node) => {
            const size = node.getSize();
            g.setNode(String(node.id), { width: size.width, height: size.height });
        });

        graph.getEdges().forEach((edge) => {
            const source = edge.getSourceCellId();
            const target = edge.getTargetCellId();

            if (source && target) {
                g.setEdge(source, target);
            }
        });

        window.dagre.layout(g);

        let maxBottom = TOP_MARGIN;
        let maxRight = computeGraphWidth(containerEl);

        // batchUpdate (core X6 API) groups every node.setPosition() below into ONE history
        // entry — without it, a wired History plugin would record one undo step per node and
        // a single Ctrl+Z would only partially undo the tidy.
        const applyPositions = () => {
            nodes.forEach((node) => {
                const dagreNode = g.node(String(node.id));

                if (!dagreNode) {
                    return;
                }

                // dagre positions a node by its CENTER; X6's setPosition takes the top-left corner.
                const size = node.getSize();
                const x = dagreNode.x - size.width / 2;
                const y = dagreNode.y - size.height / 2;

                node.setPosition(x, y);
                maxBottom = Math.max(maxBottom, y + size.height);
                maxRight = Math.max(maxRight, x + size.width);

                if (typeof callbacks.onNodePositionChange === 'function') {
                    callbacks.onNodePositionChange(String(node.id), x, y);
                }
            });
        };

        if (typeof graph.batchUpdate === 'function') {
            graph.batchUpdate('tidy', applyPositions);
        } else {
            applyPositions();
        }

        // Same never-shrink rule as render(): a wide dagre spread (branching graphs) must
        // stay inside the translating.restrict area or the next drag snaps nodes back.
        graph.resize(maxRight, maxBottom + TOP_MARGIN);
    }

    return {
        render,
        registerStepType,
        undo,
        redo,
        canUndo,
        canRedo,
        hasHistory,
        tidy,
    };
})();
