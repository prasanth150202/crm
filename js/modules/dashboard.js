// js/modules/dashboard.js

const App = window.App || {};

class Dashboard {
    constructor() {
        console.log('Dashboard class instantiated');
        this.state = {
            layout: {
                selectedCharts: [],
            },
            availableCharts: [],
            chartInstances: {},
        };

        this.renderId = 0;

        this.apiUrl = window.AppData.config.apiUrl;
        this.csrfToken = window.AppData.csrf_token;

        // Elements are now inside #appContent, so we find them after HTML injection
        this.initDOMElements();
        // Listeners for global elements (selectors, overlays) should only be added once
        this.initEventListeners();
        this.init();
    }

    initDOMElements() {
        this.gridEl = document.getElementById('dashboard-grid');
        this.loadingIndicator = document.getElementById('dashboard-loading');
        this.chartSelectorPanel = document.getElementById('chart-selector-panel');
        this.chartSelectorList = document.getElementById('chart-selector-list');
        this.chartSelectorOverlay = document.getElementById('chart-selector-overlay');
        this.dateRangeButtons = document.querySelectorAll('.date-range-btn');
        this.gs = null; // GridStack instance, created after first render
    }

    initEventListeners() {
        if (Dashboard._listenersInitialized) return;

        const toggleBtn = document.getElementById('toggle-chart-selector');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                if (App.dashboardInstance && App.dashboardInstance.active) {
                    App.dashboardInstance.toggleChartSelector(true);
                }
            });
        }

        const closeBtn = document.getElementById('close-chart-selector');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                if (App.dashboardInstance && App.dashboardInstance.active) {
                    App.dashboardInstance.toggleChartSelector(false);
                }
            });
        }

        const saveBtn = document.getElementById('save-dashboard-btn-panel');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                if (App.dashboardInstance && App.dashboardInstance.active) {
                    App.dashboardInstance.toggleChartSelector(false);
                }
            });
        }

        if (this.chartSelectorOverlay) {
            this.chartSelectorOverlay.addEventListener('click', () => {
                if (App.dashboardInstance && App.dashboardInstance.active) {
                    App.dashboardInstance.toggleChartSelector(false);
                }
            });
        }

        Dashboard._listenersInitialized = true;
    }

    async init() {
        await this.loadLayout();
        await this.loadAvailableCharts();

        this.renderChartSelector();

        if (this.state.layout.selectedCharts && this.state.layout.selectedCharts.length > 0) {
            this.gridEl.style.display = 'block';
            this.loadingIndicator.style.display = 'none';
            await this.renderDashboard();
        } else {
            this.loadingIndicator.innerHTML = '<p class="text-slate-400 text-sm">Dashboard is empty. Click <strong>Add / Remove Charts</strong> to get started.</p>';
        }

    }

    // --- API Methods ---
    async fetchAPI(endpoint, options = {}) {
        try {
            const response = await fetch(`${this.apiUrl}/${endpoint}`, {
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken,
                },
                ...options,
            });

            if (!response.ok) {
                console.error(`Error fetching ${endpoint}:`, response.statusText);
                const text = await response.text();
                console.error("Server Response:", text);
                return null;
            }

            const text = await response.text();
            if (!text) {
                return {}; // Return empty object for empty but successful responses
            }
            return JSON.parse(text); // This is now inside the try block
        } catch (error) {
            console.error(`Failed to fetch or parse JSON for ${endpoint}:`, error);
            return null; // Return null on any failure
        }
    }

    async loadLayout() {
        const layout = await this.fetchAPI('dashboard/get_layout.php');
        if (layout && Object.keys(layout).length > 0) {
            this.state.layout = { ...this.state.layout, ...layout };
            // Sync dashboard date range to global state on initial load
            // REMOVED per user request: Always default to 'All' on refresh
            // if (layout.dateRange) {
            //     App.state.dateRange = layout.dateRange;
            // }
            // if (layout.custom) {
            //     App.state.custom = layout.custom;
            // }
        }
    }

    async saveLayout() {
        if (this.state.availableCharts.length === 0) return;
        const layoutToSave = {
            ...this.state.layout,
            dateRange: App.state.dateRange,
            custom: App.state.custom
        };
        await this.fetchAPI('dashboard/save_layout.php', {
            method: 'POST',
            body: JSON.stringify(layoutToSave),
        });
    }

    async loadAvailableCharts() {
        const charts = await this.fetchAPI('dashboard/list_charts.php');
        if (charts && Array.isArray(charts)) {
            this.state.availableCharts = charts;
        } else if (charts && charts.data && Array.isArray(charts.data)) {
            this.state.availableCharts = charts.data;
        } else {
            console.warn('Invalid charts response:', charts);
            this.state.availableCharts = [];
        }
    }

    async fetchChartData(chartId, dateRange) {
        let endpoint = `reports/chart_data.php?chart_id=${chartId}&source=dashboard`;
        endpoint += `&range=${dateRange.type}`;
        if (dateRange.type === 'custom' && dateRange.start && dateRange.end) {
            endpoint += `&start=${dateRange.start}&end=${dateRange.end}`;
        }
        return await this.fetchAPI(endpoint);
    }

    // --- Rendering Methods ---
    async renderDashboard() {
        if (!this.active) return;
        const currentRenderId = ++this.renderId;

        this.destroyAllCharts();

        // Destroy previous GridStack instance cleanly
        if (this.gs) {
            this.gs.destroy(false); // false = keep DOM
            this.gs = null;
        }
        this.gridEl.innerHTML = '';

        // Init GridStack — starts in static (locked) mode; edit mode enabled by pencil button
        this.gs = GridStack.init({
            column: 12,
            cellHeight: 80,
            margin: 10,
            animate: false,
            staticGrid: true,
            disableOneColumnMode: true,
            handle: '.gs-drag-handle',
            resizable: { handles: 'se,s,e' },
        }, this.gridEl);

        // Batch add all selected charts as widgets
        const positions = this.state.layout.gridPositions || {};
        const widgets = this.state.layout.selectedCharts.map((chartId, idx) => {
            const cfg = this.state.availableCharts.find(c => c.id == chartId);
            const ctype = cfg?.chart_type || cfg?.type || 'bar';
            const pos = positions[chartId] || this._defaultPos(idx, ctype);
            const minW = ctype === 'scorecard' ? 2 : (ctype === 'channel_stage_matrix' ? 4 : 2);
            const minH = 2;
            return { id: `chart-widget-${chartId}`, x: pos.x, y: pos.y, w: pos.w, h: pos.h, minW, minH, noMove: false, noResize: false, content: `<div id="chart-container-${chartId}" class="h-full"></div>` };
        }).filter(Boolean);

        this.gs.batchUpdate(true);
        widgets.forEach(w => this.gs.addWidget(w));
        this.gs.batchUpdate(false);

        // Now render chart content into each container
        const chartPromises = this.state.layout.selectedCharts.map(chartId => {
            const chartConfig = this.state.availableCharts.find(c => c.id == chartId);
            if (chartConfig && this.active) return this.renderChart(chartConfig);
        });
        await Promise.all(chartPromises);

        if (!this.active || this.renderId !== currentRenderId) return;

        // Save positions whenever user drags or resizes
        this._saveTimeout = null;
        this.gs.on('change', () => {
            if (!this.active) return;
            clearTimeout(this._saveTimeout);
            this._saveTimeout = setTimeout(() => this._captureAndSavePositions(), 600);
        });
    }

    _defaultPos(idx, chartType) {
        // Smart sizing by chart type
        let w = 6, h = 5;
        const type = (chartType || '').toLowerCase();
        if (type === 'scorecard') { w = 3; h = 3; }
        else if (type === 'channel_stage_matrix') { w = 12; h = 6; }
        else if (type === 'pipeline_funnel' || type === 'channel_table') { w = 6; h = 6; }
        else if (type === 'table') { w = 6; h = 6; }
        // Place scorecards 4-across, others 2-across
        const cols = w === 3 ? 4 : 2;
        const perRow = Math.floor(12 / w);
        return { x: (idx % perRow) * w, y: Math.floor(idx / perRow) * h, w, h };
    }

    _captureAndSavePositions() {
        if (!this.gs) return;
        const positions = {};
        // Read positions from DOM — gs-id, gs-x, gs-y, gs-w, gs-h attributes are always in sync
        this.gridEl.querySelectorAll('.grid-stack-item').forEach(el => {
            const gsId = el.getAttribute('gs-id');
            if (!gsId) return;
            const chartId = gsId.replace('chart-widget-', '');
            positions[chartId] = {
                x: parseInt(el.getAttribute('gs-x') || 0),
                y: parseInt(el.getAttribute('gs-y') || 0),
                w: parseInt(el.getAttribute('gs-w') || 6),
                h: parseInt(el.getAttribute('gs-h') || 5),
            };
        });
        this.state.layout.gridPositions = positions;
        this.saveLayout();
    }

    async renderChart(chartConfig) {
        if (!this.active) return;
        const currentRenderId = this.renderId;

        // GridStack already placed an empty #chart-container-{id} div in the widget
        const chartContainer = document.getElementById(`chart-container-${chartConfig.id}`);
        if (!chartContainer) return;

        chartContainer.className = 'bg-white rounded-2xl border h-full flex flex-col overflow-hidden';
        chartContainer.style.borderColor = 'var(--color-border, #E2E8F0)';

        const chartType = (chartConfig.type || chartConfig.chart_type || 'bar').toLowerCase();
        const chartId = chartConfig.id;

        // Clean SVG icon per chart type (no emojis)
        const typeIconSvg = {
            bar:              '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6m14 0v-4a2 2 0 00-2-2h-2a2 2 0 00-2 2v4m-6 0h16"/>',
            line:             '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4"/>',
            area:             '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M5 20h14"/>',
            pie:              '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>',
            doughnut:         '<circle cx="12" cy="12" r="8" stroke-width="2"/><circle cx="12" cy="12" r="4" stroke-width="2" fill="none"/>',
            funnel:           '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 8h12M9 12h6M11 16h2"/>',
            combo:            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-4H5v4m8 0v-8h-4v8m8 0V8h-4v11"/>',
            scorecard:        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>',
            table:            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M10 6v12M3 6h18v12H3z"/>',
            pipeline_funnel:  '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 8h12M9 12h6M11 16h2"/>',
            channel_table:    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M10 6v12M3 6h18v12H3z"/>',
            channel_stage_matrix: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>',
        };
        const iconPath = typeIconSvg[chartType] || typeIconSvg.bar;

        chartContainer.innerHTML = `
            <div class="gs-drag-handle chart-card-header flex-shrink-0 select-none" style="background:#FAFBFC;border-bottom:1px solid var(--color-border,#E2E8F0)">
                <!-- Row 1: title + drag hint (hidden when title is toggled off) -->
                <div class="chart-title-row flex items-center gap-2 px-4 pt-2.5 pb-1.5 min-w-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="chart-drag-icon h-3.5 w-3.5 flex-shrink-0" style="color:#D1D5DB" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    <h3 class="text-sm font-semibold leading-tight" style="color:#0F172A;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;min-width:0">${chartConfig.name}</h3>
                </div>
                <!-- Row 2: type badge + toolbar (always visible) -->
                <div class="flex items-center justify-between px-4 pb-2" onclick="event.stopPropagation()">
                    <div class="flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0" style="color:#94A3B8" fill="none" viewBox="0 0 24 24" stroke="currentColor">${iconPath}</svg>
                        <span class="text-xs font-medium" style="color:#94A3B8">${chartType.replace(/_/g,' ')}</span>
                    </div>
                    <div class="flex items-center gap-0.5">
                        <button class="chart-toolbar-btn" title="Refresh" onclick="App.dashboardInstance && App.dashboardInstance.refreshChart(${chartId})" style="cursor:default">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </button>
                        <button class="chart-toolbar-btn" title="Fullscreen" onclick="App.dashboardInstance && App.dashboardInstance.fullscreenChart(${chartId})" style="cursor:default">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                        </button>
                        <button class="chart-toolbar-btn" title="Download PNG" onclick="App.dashboardInstance && App.dashboardInstance.downloadChart(${chartId})" style="cursor:default">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </button>
                        <div class="relative chart-menu-wrapper">
                            <button class="chart-toolbar-btn" title="More options" onclick="App.dashboardInstance && App.dashboardInstance.toggleChartMenu(${chartId}, this)" style="cursor:default">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                            </button>
                            <div id="chart-menu-${chartId}" class="chart-dropdown-menu hidden">
                                <button onclick="App.dashboardInstance && App.dashboardInstance.toggleTitleVisibility(${chartId})">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                    <span id="chart-title-toggle-label-${chartId}">${(this.state.layout.hiddenTitles || []).includes(String(chartId)) ? 'Show title' : 'Hide title'}</span>
                                </button>
                                <button onclick="App.dashboardInstance && App.dashboardInstance.editChart(${chartId})">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    Edit chart
                                </button>
                                <button class="text-red-600 hover:bg-red-50" onclick="App.dashboardInstance && App.dashboardInstance.removeChart(${chartId})">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    Remove from dashboard
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="chart-inner-container flex-1 overflow-auto" style="position:relative; min-height:0;">
                <div class="chart-canvas-wrap" style="position:absolute;inset:0;padding:12px;display:none;">
                    <canvas id="chart-${chartConfig.id}"></canvas>
                </div>
                <p class="chart-loading" style="padding:12px;color:var(--color-muted,#64748B);font-size:0.875rem">Loading…</p>
            </div>
        `;

        // Apply saved title-hidden state (hide only the title row, keep toolbar)
        const hiddenTitles = this.state.layout.hiddenTitles || [];
        if (hiddenTitles.includes(String(chartId))) {
            const titleRow = chartContainer.querySelector('.chart-title-row');
            if (titleRow) titleRow.style.display = 'none';
        }

        // channel_stage_matrix: fetched via chart_data.php but rendered custom
        if (chartType === 'channel_stage_matrix') {
            const innerEl = chartContainer.querySelector('.chart-inner-container');
            innerEl.innerHTML = '<p class="text-sm" style="color:var(--color-muted,#64748B)">Loading matrix…</p>';
            const dateRange = { type: App.state.dateRange, start: App.state.custom?.start, end: App.state.custom?.end };
            const data = await this.fetchChartData(chartConfig.id, dateRange);
            if (!data || data.type !== 'channel_stage_matrix') {
                innerEl.innerHTML = '<p class="text-sm text-red-400">Failed to load matrix.</p>';
                return;
            }
            this.renderChannelStageMatrix(innerEl, data);
            return;
        }

        // Summary-based chart types bypass chart_data.php
        if (chartType === 'pipeline_funnel' || chartType === 'channel_table') {
            const innerEl = chartContainer.querySelector('.chart-inner-container');
            innerEl.innerHTML = '<p class="text-sm" style="color:var(--color-muted,#64748B)">Loading…</p>';
            const summary = await this.fetchSummary();
            if (!summary || summary.error) {
                innerEl.innerHTML = '<p class="text-sm" style="color:#ef4444">Failed to load data.</p>';
                return;
            }
            if (chartType === 'pipeline_funnel') {
                this.renderPipelineFunnel(innerEl, summary);
            } else {
                this.renderChannelTable(innerEl, summary);
            }
            return;
        }

        const dateRange = {
            type: App.state.dateRange,
            start: App.state.custom?.start,
            end: App.state.custom?.end,
        };

        const data = await this.fetchChartData(chartConfig.id, dateRange);

        if (this.renderId !== currentRenderId) {
            console.log(`Chart ${chartConfig.id} render aborted (outdated renderId)`);
            return;
        }

        const loadingEl = chartContainer.querySelector('.chart-loading');
        if (loadingEl) loadingEl.style.display = 'none';

        // Helper to show the canvas wrapper
        const showCanvasWrap = () => {
            const wrap = chartContainer.querySelector('.chart-canvas-wrap');
            if (wrap) wrap.style.display = 'block';
        };

        if (data && data.labels && data.datasets) {
            const colorScheme = this.getColorScheme(chartConfig.colorScheme || chartConfig.color_scheme || 'default');

            // Handle combo charts specially
            if (chartType === 'combo') {
                const counts = data.comboCounts || data.datasets[0].data;
                const totals = data.comboValues || data.datasets[0].data;

                showCanvasWrap();
                const canvasEl = document.getElementById(`chart-${chartConfig.id}`);
                if (!canvasEl) { console.error(`Canvas element chart-${chartConfig.id} not found`); return; }
                const ctx = canvasEl.getContext('2d');
                this.state.chartInstances[chartConfig.id] = new Chart(ctx, {
                    data: {
                        labels: data.labels,
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Leads',
                                data: counts,
                                backgroundColor: colorScheme[0],
                                borderColor: colorScheme[0],
                                yAxisID: 'y'
                            },
                            {
                                type: 'line',
                                label: 'Value',
                                data: totals,
                                borderColor: colorScheme[1] || colorScheme[0],
                                backgroundColor: (colorScheme[1] || colorScheme[0]) + '40',
                                tension: 0.3,
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        onClick: (evt, elements) => {
                            if (!elements.length) return;
                            const xField = chartConfig.x_axis || chartConfig.xAxis || 'stage_id';
                            const drillF = xField === 'source' ? 'source' : (xField === 'stage_id' ? 'stage_id' : null);
                            if (!drillF) return;
                            const label = data.labels[elements[0].index];
                            App.drillToLeads(drillF, label);
                        },
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: { callbacks: { footer: () => 'Click to view leads' } }
                        },
                        scales: {
                            y: { beginAtZero: true, title: { display: true, text: 'Leads' } },
                            y1: {
                                beginAtZero: true,
                                position: 'right',
                                grid: { drawOnChartArea: false },
                                ticks: { callback: (val) => App.formatCurrency(val) },
                                title: { display: true, text: 'Value' }
                            }
                        }
                    }
                });
                canvasEl.style.cursor = 'pointer';
                return;
            }

            // Handle scorecard charts specially
            if (chartType === 'scorecard') {
                const meta     = data.scorecard_meta || null;
                const dataField = chartConfig.data_field || chartConfig.dataField || 'leads';
                const agg       = chartConfig.aggregation || 'count';
                const xAxis     = chartConfig.x_axis || chartConfig.xAxis || 'stage_id';

                // Determine display value
                let total, isMonetary, drillField, drillValue, accentColor;
                if (meta) {
                    // New typed scorecards
                    isMonetary   = agg === 'sum';
                    total        = isMonetary ? meta.value : meta.count;
                    if (dataField === 'meetings') {
                        drillField  = null; drillValue = null;
                        accentColor = '#0891B2';
                    } else {
                        // stage_count
                        drillField  = 'stage_id';
                        drillValue  = xAxis;  // e.g. 'proposal' or 'won'
                        accentColor = isMonetary ? '#10B981' : '#8B5CF6';
                    }
                } else {
                    // Legacy scorecard (data_field='leads' or 'lead_value')
                    total       = data.datasets[0].data.reduce((s, v) => s + v, 0);
                    isMonetary  = dataField === 'lead_value' || agg === 'sum';
                    drillField  = null; drillValue = null;
                    accentColor = '#2563EB';
                }

                const displayValue = isMonetary
                    ? (App.formatCurrency ? App.formatCurrency(total) : '₹' + Number(total).toLocaleString())
                    : Number(total).toLocaleString();

                const innerEl = chartContainer.querySelector('.chart-inner-container');
                innerEl.style.cursor = 'pointer';
                innerEl.innerHTML = `
                    <div class="scorecard-body flex flex-col justify-center items-center h-full p-3 text-center" style="overflow:hidden">
                        <div class="scorecard-value font-extrabold leading-none mb-2" style="color:#0F172A;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%">${displayValue}</div>
                        <span class="scorecard-hint inline-flex items-center gap-1 font-medium rounded-full" style="background:${accentColor}15;color:${accentColor};white-space:nowrap">↗ View</span>
                    </div>`;
                innerEl.addEventListener('click', () => App.drillToLeads(drillField, drillValue));

                // Fluid font sizing via ResizeObserver
                const valEl  = innerEl.querySelector('.scorecard-value');
                const hintEl = innerEl.querySelector('.scorecard-hint');
                const scaleScorecard = () => {
                    const base = Math.min(innerEl.offsetWidth, innerEl.offsetHeight);
                    valEl.style.fontSize  = Math.max(14, Math.min(52, base * 0.3)) + 'px';
                    hintEl.style.fontSize = Math.max(9,  Math.min(12, base * 0.07)) + 'px';
                    hintEl.style.padding  = `${Math.max(2, base * 0.025)}px ${Math.max(8, base * 0.07)}px`;
                };
                scaleScorecard();
                if (window.ResizeObserver) {
                    const ro = new ResizeObserver(scaleScorecard);
                    ro.observe(innerEl);
                    innerEl._scorecardRO = ro;
                }
                return;
            }

            // Handle table charts specially
            if (chartType === 'table') {
                const xField = chartConfig.x_axis || chartConfig.xAxis || 'stage_id';
                const drillField = xField === 'source' ? 'source' : (xField === 'stage_id' ? 'stage_id' : null);
                const total = data.comboCounts ? data.comboCounts.reduce((a, b) => a + b, 0) : data.datasets[0].data.reduce((a, b) => a + b, 0);
                const innerEl = chartContainer.querySelector('.chart-inner-container');
                innerEl.style.padding = '0';

                let tableHtml = `
                    <div class="flex justify-between items-center px-4 py-2 border-b flex-shrink-0" style="border-color:var(--color-border,#E2E8F0)">
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full" style="background:#EFF6FF;color:#2563EB">Total: ${total}</span>
                    </div>
                    <div class="overflow-auto" style="max-height:calc(100% - 36px)">
                        <table class="min-w-full">
                            <thead style="background:#F8FAFC;position:sticky;top:0;z-index:1">
                                <tr class="border-b" style="border-color:var(--color-border,#E2E8F0)">
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider" style="color:var(--color-muted,#64748B)">Label</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider" style="color:var(--color-muted,#64748B)">Count</th>
                                    ${data.comboValues ? '<th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider" style="color:var(--color-muted,#64748B)">Value</th>' : ''}
                                </tr>
                            </thead>
                            <tbody>`;

                data.labels.forEach((label, index) => {
                    const count = data.comboCounts ? data.comboCounts[index] : data.datasets[0].data[index];
                    const value = data.comboValues ? data.comboValues[index] : null;
                    const drillAttr = drillField ? `onclick="App.drillToLeads('${drillField}','${label}')" style="cursor:pointer" title="Click to view ${label} leads"` : '';
                    tableHtml += `
                        <tr class="border-b hover:bg-blue-50 transition-colors" style="border-color:#F1F5F9" ${drillAttr}>
                            <td class="px-4 py-2.5 text-sm font-medium" style="color:var(--color-text,#0F172A)">${label}</td>
                            <td class="px-4 py-2.5 text-sm text-right" style="color:var(--color-muted,#64748B)">${count}</td>
                            ${value !== null ? `<td class="px-4 py-2.5 text-sm font-medium text-right" style="color:#2563EB">${App.formatCurrency ? App.formatCurrency(value) : '₹'+Number(value).toLocaleString()}</td>` : ''}
                        </tr>`;
                });

                tableHtml += `</tbody></table></div>`;
                innerEl.innerHTML = tableHtml;
                return;
            }

            // Handle funnel charts specially
            if (chartType === 'funnel') {
                const values = data.datasets[0].data.map(v => Number(v) || 0);
                const labels = data.labels;
                const width = 420;
                const segmentHeight = 76;
                const gap = 16;
                const baseWidth = 340;
                const minWidth = 70;
                const svgHeight = labels.length * (segmentHeight + gap) + 30;

                const maxValue = Math.max(...values);
                const widths = values.map((value, idx) => {
                    if (idx === 0) return baseWidth;
                    const ratio = maxValue > 0 ? value / maxValue : 0;
                    return Math.max(minWidth, Math.min(baseWidth, baseWidth * (0.3 + ratio * 0.7)));
                });

                const buildGradientDefs = () => {
                    let defs = '';
                    labels.forEach((_, idx) => {
                        if (values[idx] <= 0) return;
                        const color = colorScheme[idx % colorScheme.length];
                        const startOpacity = Math.max(0.5, 0.92 - idx * 0.08).toFixed(2);
                        const endOpacity = Math.max(0.38, startOpacity - 0.18).toFixed(2);
                        defs += `
                            <linearGradient id="funnelGradDash${chartConfig.id}_${idx}" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="${color}" stop-opacity="${startOpacity}"></stop>
                                <stop offset="100%" stop-color="${color}" stop-opacity="${endOpacity}"></stop>
                            </linearGradient>`;
                    });
                    return defs;
                };

                const getBottomWidth = (idx) => {
                    if (idx < widths.length - 1) return widths[idx + 1];
                    return Math.max(minWidth * 0.55, widths[idx] * 0.6);
                };

                let y = 20;
                let segments = '';
                labels.forEach((label, idx) => {
                    const value = values[idx];
                    const topWidth = widths[idx];
                    const bottomWidth = getBottomWidth(idx);
                    const topLeft = (width - topWidth) / 2;
                    const topRight = topLeft + topWidth;
                    const bottomLeft = (width - bottomWidth) / 2;
                    const bottomRight = bottomLeft + bottomWidth;
                    const isZero = value <= 0;
                    const color = colorScheme[idx % colorScheme.length];
                    const fill = isZero ? '#e5e7eb' : `url(#funnelGradDash${chartConfig.id}_${idx})`;
                    const stroke = isZero ? '#cbd5f5' : color;
                    const valueText = (value || 0).toLocaleString();
                    const prevValue = idx === 0 ? values[idx] : values[idx - 1];
                    const conversionRaw = idx === 0 ? 100 : (prevValue > 0 ? (value / prevValue) * 100 : 0);
                    const conversionText = idx === 0 ? '100% of start' : `${conversionRaw.toFixed(1)}% of previous`;
                    const conversionColor = isZero ? '#cbd5f5' : '#94a3b8';

                    const xField = chartConfig.x_axis || chartConfig.xAxis || 'stage_id';
                    const drillF = xField === 'source' ? 'source' : 'stage_id';
                    segments += `
                        <g style="cursor:pointer;" onclick="App.drillToLeads('${drillF}','${label}')" title="View ${label} leads">
                            <polygon points="${topLeft},${y} ${topRight},${y} ${bottomRight},${y + segmentHeight} ${bottomLeft},${y + segmentHeight}"
                                     fill="${fill}" stroke="${stroke}" stroke-width="1.2" opacity="${isZero ? 0.5 : 1}"></polygon>
                            <text x="${width / 2}" y="${y + 26}" text-anchor="middle" fill="${isZero ? '#94a3b8' : '#0f172a'}" font-size="13" font-weight="600" style="pointer-events:none;">${label}</text>
                            <text x="${width / 2}" y="${y + 49}" text-anchor="middle" fill="${isZero ? '#9ca3af' : '#0f172a'}" font-size="22" font-weight="700" style="pointer-events:none;">${valueText}</text>
                            <text x="${width / 2}" y="${y + segmentHeight + 14}" text-anchor="middle" fill="${conversionColor}" font-size="11" font-weight="500" style="pointer-events:none;">${conversionText}</text>
                        </g>`;
                    y += segmentHeight + gap;
                });

                const svg = `<div style="width:100%;padding:12px;overflow-y:auto;height:100%">
                    <svg width="100%" height="${svgHeight}" viewBox="0 0 ${width} ${svgHeight}" preserveAspectRatio="xMidYMid meet">
                        <defs>${buildGradientDefs()}</defs>
                        ${segments}
                    </svg>
                </div>`;

                const innerContainer = chartContainer.querySelector('.chart-inner-container');
                innerContainer.innerHTML = svg;
                return;
            }

            // Normalize area charts
            const finalChartType = chartType === 'area' ? 'line' : chartType;

            // Apply colors and fill for area charts
            if (data.datasets && data.datasets[0]) {
                data.datasets[0].backgroundColor = (chartType === 'area') ? colorScheme[0] + '40' : (chartType === 'pie' || chartType === 'doughnut' ? colorScheme : colorScheme[0]);
                data.datasets[0].borderColor = (chartType === 'pie' || chartType === 'doughnut' ? colorScheme : colorScheme[0]);
                data.datasets[0].fill = (chartType === 'area');
                data.datasets[0].tension = 0.4;
            }

            showCanvasWrap();
            const canvasEl = document.getElementById(`chart-${chartConfig.id}`);
            if (!canvasEl) { console.error(`Canvas element chart-${chartConfig.id} not found`); return; }
            const ctx = canvasEl.getContext('2d');
            const xAxisField = chartConfig.x_axis || chartConfig.xAxis || 'stage_id';
            const drillField = (xAxisField === 'source') ? 'source' : (xAxisField === 'stage_id' ? 'stage_id' : null);
            this.state.chartInstances[chartConfig.id] = new Chart(ctx, {
                type: finalChartType,
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onClick: drillField ? (evt, elements) => {
                        if (!elements.length) return;
                        const idx = elements[0].index;
                        const label = data.labels[idx];
                        App.drillToLeads(drillField, label);
                    } : undefined,
                    plugins: {
                        legend: {
                            display: finalChartType === 'pie' || finalChartType === 'doughnut',
                            position: 'right'
                        },
                        tooltip: drillField ? { callbacks: { footer: () => 'Click to view leads' } } : {}
                    },
                    scales: finalChartType === 'line' || finalChartType === 'bar' ? {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    } : undefined
                }
            });
            if (drillField) canvasEl.style.cursor = 'pointer';
        } else {
            if (loadingEl) {
                loadingEl.style.display = 'block';
                loadingEl.textContent = 'No data available for this range.';
            }
        }
    }

    renderChartSelector() {
        this.chartSelectorList.innerHTML = '';

        // Ensure availableCharts is an array
        if (!Array.isArray(this.state.availableCharts)) {
            console.warn('availableCharts is not an array:', this.state.availableCharts);
            this.state.availableCharts = [];
        }

        this.state.availableCharts.forEach(chart => {
            const isChecked = this.state.layout.selectedCharts.includes(chart.id.toString());
            const item = document.createElement('div');
            item.className = 'flex items-center';
            item.innerHTML = `
                <input id="chart-toggle-${chart.id}" type="checkbox" data-chart-id="${chart.id}" ${isChecked ? 'checked' : ''} class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <label for="chart-toggle-${chart.id}" class="ml-3 block text-sm font-medium text-gray-700">${chart.name}</label>
            `;
            this.chartSelectorList.appendChild(item);
        });

        this.chartSelectorList.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => this.handleChartSelectionChange(e.currentTarget));
        });
    }

    // --- Event Handlers ---
    async handleChartSelectionChange(checkbox) {
        const chartId = checkbox.dataset.chartId;
        const isSelected = checkbox.checked;

        if (isSelected) {
            this.state.layout.selectedCharts.push(chartId);
            this.loadingIndicator.style.display = 'none';
            this.gridEl.style.display = 'block';

            // Init GridStack if this is the first chart
            if (!this.gs) {
                this.gs = GridStack.init({ column: 12, cellHeight: 80, margin: 10, animate: false, staticGrid: true, disableOneColumnMode: true, handle: '.gs-drag-handle', resizable: { handles: 'se,s,e' } }, this.gridEl);
                this.gs.on('change', () => {
                    if (!this.active) return;
                    clearTimeout(this._saveTimeout);
                    this._saveTimeout = setTimeout(() => this._captureAndSavePositions(), 600);
                });
            }

            const idx = this.state.layout.selectedCharts.length - 1;
            const chartConfig = this.state.availableCharts.find(c => c.id == chartId);
            const ctype = chartConfig?.chart_type || chartConfig?.type || 'bar';
            const pos = this._defaultPos(idx, ctype);
            const minW = ctype === 'scorecard' ? 2 : (ctype === 'channel_stage_matrix' ? 4 : 2);
            this.gs.addWidget({ id: `chart-widget-${chartId}`, x: pos.x, y: pos.y, w: pos.w, h: pos.h, minW, minH: 2, content: `<div id="chart-container-${chartId}" class="h-full"></div>` });

            await this.renderChart(chartConfig);
        } else {
            this.state.layout.selectedCharts = this.state.layout.selectedCharts.filter(id => id !== chartId);
            if (this.state.chartInstances[chartId]) {
                this.state.chartInstances[chartId].destroy();
                delete this.state.chartInstances[chartId];
            }
            if (this.gs) {
                const widgetEl = this.gridEl.querySelector(`[gs-id="chart-widget-${chartId}"]`);
                if (widgetEl) this.gs.removeWidget(widgetEl);
            }
            if (this.state.layout.selectedCharts.length === 0) {
                this.loadingIndicator.innerHTML = '<p class="text-slate-400 text-sm">Dashboard is empty. Click <strong>Add / Remove Charts</strong> to get started.</p>';
                this.loadingIndicator.style.display = 'block';
                this.gridEl.style.display = 'none';
            }
        }
        this._captureAndSavePositions();
    }


    async refreshAllCharts() {
        this.loadingIndicator.style.display = 'none';
        this.gridEl.style.display = 'block';
        await this.renderDashboard();
    }

    toggleChartSelector(show) {
        if (show) {
            this.chartSelectorOverlay.classList.remove('hidden');
            this.chartSelectorPanel.classList.remove('hidden', 'translate-x-full');
        } else {
            this.chartSelectorOverlay.classList.add('hidden');
            this.chartSelectorPanel.classList.add('hidden', 'translate-x-full');
        }
    }

    destroyAllCharts() {
        for (const chartId in this.state.chartInstances) {
            this.state.chartInstances[chartId].destroy();
        }
        this.state.chartInstances = {};
        // Clean up any scorecard ResizeObservers
        document.querySelectorAll('.chart-inner-container').forEach(el => {
            if (el._scorecardRO) { el._scorecardRO.disconnect(); delete el._scorecardRO; }
        });
    }

    // ── Summary-based chart types (pipeline_funnel, channel_table) ──────────
    async fetchSummary() {
        const orgId = window.AppData?.user?.org_id;
        try {
            const res = await fetch(`${this.apiUrl}/reports/summary.php?org_id=${orgId}`, {
                headers: { 'X-CSRF-Token': this.csrfToken }
            });
            return await res.json();
        } catch(e) { return null; }
    }

    renderPipelineFunnel(innerEl, summary) {
        const stageMap = {};
        (summary.by_stage || []).forEach(s => { stageMap[s.stage_id] = s; });
        const wonValue = Number(stageMap['won']?.total_value || 0);
        const fmtNum = n => Number(n).toLocaleString();
        const currency = window.AppData?.user?.currency || 'INR';
        const currencySymbol = currency === 'INR' ? '₹' : '$';
        const fmtMoney = n => currencySymbol + Number(n).toLocaleString();

        const stageOrder = ['new','contacted','qualified','proposal','won'];
        const stageLabels = { new:'New', contacted:'Contacted', qualified:'Qualified', proposal:'Proposal', won:'Won' };
        const stageColors = { new:'#2563EB', contacted:'#10B981', qualified:'#06B6D4', proposal:'#8B5CF6', won:'#059669' };
        const funnelStages = stageOrder.map(s => ({
            label: stageLabels[s],
            count: Number(stageMap[s]?.count || 0),
            color: stageColors[s]
        }));
        const maxCount = Math.max(...funnelStages.map(s => s.count), 1);

        const rows = funnelStages.map((stage, idx) => {
            const pct = Math.round((stage.count / maxCount) * 100);
            const convPct = idx === 0 ? 100 : (funnelStages[idx-1].count > 0 ? Math.round((stage.count / funnelStages[idx-1].count) * 100) : 0);
            return `<div class="mb-4 rounded-lg px-2 py-1 hover:bg-slate-50 transition-colors" style="cursor:pointer" title="View ${stage.label} leads" onclick="App.drillToLeads('stage_id','${stage.label}')">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-sm font-semibold" style="color:var(--color-text,#0F172A)">${stage.label}</span>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-bold" style="color:var(--color-text,#0F172A)">${fmtNum(stage.count)}</span>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full" style="background:${stage.color}18;color:${stage.color}">${idx===0?'100%':convPct+'% of prev'}</span>
                    </div>
                </div>
                <div class="w-full rounded-full h-2" style="background:#F1F5F9">
                    <div class="h-2 rounded-full" style="width:${pct}%;background:${stage.color}"></div>
                </div>
            </div>`;
        }).join('');

        innerEl.innerHTML = rows + `
            <div class="mt-4 p-4 rounded-xl flex items-center justify-between" style="background:#F0FDF4;border:1px solid #BBF7D0">
                <span class="text-sm font-bold" style="color:#15803D">Revenue Closed</span>
                <span class="text-xl font-black" style="color:#15803D">${fmtMoney(wonValue)}</span>
            </div>`;
    }

    renderChannelTable(innerEl, summary) {
        const sources = (summary.by_source || []).slice(0, 8);
        const total = sources.reduce((s,r) => s + Number(r.count), 0) || 1;
        const currency = window.AppData?.user?.currency || 'INR';
        const currencySymbol = currency === 'INR' ? '₹' : '$';
        const fmtMoney = n => currencySymbol + Number(n).toLocaleString();
        const srcPalette = ['#2563EB','#7C3AED','#0891B2','#059669','#DC2626','#D97706','#DB2777','#0D9488'];
        const srcColor = (name) => srcPalette[name.charCodeAt(0) % srcPalette.length];
        const srcInitials = (name) => name.replace(/[^a-zA-Z0-9 ]/g,'').trim().split(/\s+/).map(w=>w[0]).join('').slice(0,2).toUpperCase() || '??';
        const rows = sources.length > 0 ? sources.map(row => {
            const src = row.source || 'Unknown';
            const color = srcColor(src);
            const initials = srcInitials(src);
            const count = Number(row.count);
            const val = Number(row.total_value || 0);
            const pct = Math.round((count / total) * 100);
            return `<tr class="border-b hover:bg-slate-50 transition-colors" style="border-color:#F1F5F9;cursor:pointer" title="View ${src} leads" onclick="App.drillToLeads('source','${src}')">
                <td class="py-3 pr-4"><div class="flex items-center gap-3">
                    <div style="width:32px;height:32px;border-radius:8px;background:${color}15;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <span style="font-size:10px;font-weight:700;color:${color}">${initials}</span>
                    </div>
                    <span class="text-sm font-semibold" style="color:var(--color-text,#0F172A)">${src}</span>
                </div></td>
                <td class="py-3 px-2 text-right text-sm font-bold" style="color:var(--color-text,#0F172A)">${count}</td>
                <td class="py-3 px-2 text-right text-sm" style="color:var(--color-muted,#64748B)">${fmtMoney(val)}</td>
                <td class="py-3 pl-4 text-right"><div class="flex items-center justify-end gap-2">
                    <div class="w-16 rounded-full h-1.5" style="background:#F1F5F9">
                        <div class="h-1.5 rounded-full" style="width:${pct}%;background:${color}"></div>
                    </div>
                    <span class="text-xs font-medium w-8 text-right" style="color:${color}">${pct}%</span>
                </div></td>
            </tr>`;
        }).join('') : `<tr><td colspan="4" class="py-8 text-center text-sm" style="color:var(--color-muted,#64748B)">No source data yet.</td></tr>`;

        innerEl.innerHTML = `<table class="w-full">
            <thead><tr class="border-b" style="border-color:#E2E8F0">
                <th class="pb-2 text-left text-xs font-semibold uppercase tracking-wider" style="color:var(--color-muted,#64748B)">Channel</th>
                <th class="pb-2 text-right text-xs font-semibold uppercase tracking-wider" style="color:var(--color-muted,#64748B)">Leads</th>
                <th class="pb-2 text-right text-xs font-semibold uppercase tracking-wider" style="color:var(--color-muted,#64748B)">Revenue</th>
                <th class="pb-2 text-right text-xs font-semibold uppercase tracking-wider" style="color:var(--color-muted,#64748B)">Share</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table>`;
    }

    renderChannelStageMatrix(innerEl, data) {
        const stages = data.stages || ['new','contacted','qualified','proposal','won','lost'];
        const stageLabels = { new:'New', contacted:'Contacted', qualified:'Qualified', proposal:'Proposal', won:'Won', lost:'Lost' };
        const stageColors = { new:'#2563EB', contacted:'#F59E0B', qualified:'#06B6D4', proposal:'#8B5CF6', won:'#10B981', lost:'#EF4444' };
        const order = data.order || Object.keys(data.matrix || {});
        const matrix = data.matrix || {};

        // Source initial badge — first 2 chars, deterministic color from name
        const srcPalette = ['#2563EB','#7C3AED','#0891B2','#059669','#DC2626','#D97706','#DB2777','#0D9488'];
        const srcColor = (name) => srcPalette[name.charCodeAt(0) % srcPalette.length];
        const srcInitials = (name) => name.replace(/[^a-zA-Z0-9 ]/g,'').trim().split(/\s+/).map(w=>w[0]).join('').slice(0,2).toUpperCase() || '??';

        // Column totals
        const colTotals = {};
        stages.forEach(s => { colTotals[s] = 0; });
        let grandTotal = 0, totalMeetings = 0;
        order.forEach(src => {
            stages.forEach(s => { colTotals[s] += matrix[src]?.[s] || 0; });
            totalMeetings += matrix[src]?.meetings || 0;
        });
        grandTotal = Object.values(colTotals).reduce((a,b) => a+b, 0);

        const headerCells = stages.map(s => {
            const dot = `<span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:${stageColors[s]};margin-right:5px;vertical-align:middle"></span>`;
            return `<th class="px-3 py-2.5 text-center text-xs font-semibold" style="color:#374151;min-width:76px;white-space:nowrap">${dot}${stageLabels[s]||s}</th>`;
        }).join('');

        const bodyRows = order.map(src => {
            const rowTotal = stages.reduce((a,s) => a + (matrix[src]?.[s]||0), 0);
            const meetings = matrix[src]?.meetings || 0;
            const color = srcColor(src);
            const initials = srcInitials(src);

            const cells = stages.map(s => {
                const cnt = matrix[src]?.[s] || 0;
                const pct = rowTotal > 0 ? Math.round((cnt/rowTotal)*100) : 0;
                return `<td class="px-3 py-2.5 text-center" onclick="App.drillToLeads('stage_id','${s}','source','${src}')" style="cursor:pointer">
                    <div class="text-sm font-bold" style="color:${cnt>0?stageColors[s]:'#D1D5DB'}">${cnt||'—'}</div>
                    ${cnt>0?`<div class="text-xs" style="color:#9CA3AF">${pct}%</div>`:''}
                </td>`;
            }).join('');

            const meetCell = `<td class="px-3 py-2.5 text-center" onclick="App.router('meetings')" style="cursor:${meetings>0?'pointer':'default'}">
                <div class="text-sm font-semibold" style="color:${meetings>0?'#0891B2':'#D1D5DB'}">${meetings||'—'}</div>
            </td>`;

            return `<tr class="border-b hover:bg-slate-50 transition-colors" style="border-color:#F1F5F9">
                <td class="px-3 py-2.5 sticky left-0 bg-white" style="min-width:150px">
                    <div class="flex items-center gap-2.5">
                        <div style="width:28px;height:28px;border-radius:6px;background:${color}18;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <span style="font-size:10px;font-weight:700;letter-spacing:0.02em;color:${color}">${initials}</span>
                        </div>
                        <div>
                            <div class="text-sm font-semibold" style="color:#0F172A">${src}</div>
                            <div class="text-xs" style="color:#94A3B8">${rowTotal} leads</div>
                        </div>
                    </div>
                </td>
                ${cells}
                ${meetCell}
                <td class="px-3 py-2.5 text-center" onclick="App.drillToLeads('source','${src}')" style="cursor:pointer">
                    <span class="text-sm font-bold" style="color:#0F172A">${rowTotal}</span>
                </td>
            </tr>`;
        }).join('');

        const footerCells = stages.map(s =>
            `<td class="px-3 py-2 text-center text-sm font-bold" style="color:${stageColors[s]};cursor:pointer" onclick="App.drillToLeads('stage_id','${s}')">${colTotals[s]||0}</td>`
        ).join('');

        innerEl.innerHTML = `
            <div class="overflow-auto" style="max-height:100%">
                <table class="w-full border-collapse text-left" style="min-width:580px">
                    <thead style="background:#F8FAFC;position:sticky;top:0;z-index:2;border-bottom:1px solid #E2E8F0">
                        <tr>
                            <th class="px-3 py-2.5 text-xs font-semibold text-left sticky left-0 bg-slate-50" style="color:#6B7280;min-width:150px">Channel</th>
                            ${headerCells}
                            <th class="px-3 py-2.5 text-center text-xs font-semibold" style="color:#0891B2;white-space:nowrap">
                                <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#0891B2;margin-right:5px;vertical-align:middle"></span>Meetings
                            </th>
                            <th class="px-3 py-2.5 text-center text-xs font-semibold" style="color:#6B7280">Total</th>
                        </tr>
                    </thead>
                    <tbody>${bodyRows}</tbody>
                    <tfoot>
                        <tr style="border-top:2px solid #E2E8F0;background:#F8FAFC">
                            <td class="px-3 py-2.5 text-xs font-bold sticky left-0 bg-slate-50" style="color:#374151">Totals</td>
                            ${footerCells}
                            <td class="px-3 py-2.5 text-center text-sm font-bold" style="color:#0891B2;cursor:pointer" onclick="App.router('meetings')">${totalMeetings||'—'}</td>
                            <td class="px-3 py-2.5 text-center text-sm font-bold" style="color:#0F172A">${grandTotal}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>`;
    }

    // ── Edit mode (drag + resize toggle) ─────────────────────────────────────
    toggleEditMode(force) {
        const entering = force !== undefined ? force : !this._editMode;
        this._editMode = entering;

        if (this.gs) {
            this.gs.setStatic(!entering);
        }

        // Update pencil button UI
        const btn = document.getElementById('dashboard-edit-mode-btn');
        if (btn) {
            if (entering) {
                btn.style.background = 'var(--color-primary)';
                btn.style.color = '#fff';
                btn.style.borderColor = 'var(--color-primary)';
                btn.title = 'Lock layout';
            } else {
                btn.style.background = '';
                btn.style.color = '';
                btn.style.borderColor = '';
                btn.title = 'Edit layout (drag & resize)';
            }
        }

        // Show/hide the "Apply to All Users" button (admins only, when in edit mode)
        const applyBtn = document.getElementById('dashboard-apply-all-btn');
        if (applyBtn) {
            applyBtn.style.display = entering ? '' : 'none';
        }

        // Visual grid hint
        this.gridEl.classList.toggle('gs-edit-mode', entering);
    }

    async applyLayoutToAllUsers() {
        this._captureAndSavePositions();
        try {
            const resp = await this.fetchAPI('dashboard/apply_layout_all.php', {
                method: 'POST',
                body: JSON.stringify(this.state.layout),
            });
            if (resp && !resp.error) {
                App.showToast && App.showToast('Layout applied to all users in your org.', 'success');
            } else {
                App.showToast && App.showToast(resp?.error || 'Failed to apply layout.', 'error');
            }
        } catch(e) {
            App.showToast && App.showToast('Error applying layout.', 'error');
        }
    }

    // ── Chart toolbar actions ──────────────────────────────────────────────────
    toggleTitleVisibility(chartId) {
        document.querySelectorAll('.chart-dropdown-menu').forEach(m => m.classList.add('hidden'));
        if (!this.state.layout.hiddenTitles) this.state.layout.hiddenTitles = [];
        const id = String(chartId);
        const idx = this.state.layout.hiddenTitles.indexOf(id);
        const hiding = idx === -1;
        if (hiding) {
            this.state.layout.hiddenTitles.push(id);
        } else {
            this.state.layout.hiddenTitles.splice(idx, 1);
        }
        // Toggle only the title row, keep the toolbar row always visible
        const container = document.getElementById(`chart-container-${chartId}`);
        if (container) {
            const titleRow = container.querySelector('.chart-title-row');
            if (titleRow) titleRow.style.display = hiding ? 'none' : '';
        }
        // Update menu label
        const label = document.getElementById(`chart-title-toggle-label-${chartId}`);
        if (label) label.textContent = hiding ? 'Show title' : 'Hide title';
        this.saveLayout();
    }

    async refreshChart(chartId) {
        const chartConfig = this.state.availableCharts.find(c => c.id == chartId);
        if (!chartConfig) return;
        if (this.state.chartInstances[chartId]) {
            this.state.chartInstances[chartId].destroy();
            delete this.state.chartInstances[chartId];
        }
        const container = document.getElementById(`chart-container-${chartId}`);
        if (container) {
            const inner = container.querySelector('.chart-inner-container');
            if (inner) {
                inner.style.padding = '';
                inner.innerHTML = '<div class="chart-canvas-wrap" style="position:absolute;inset:0;padding:12px;display:none;"><canvas id="chart-' + chartId + '"></canvas></div><p class="chart-loading" style="padding:12px;color:var(--color-muted,#64748B);font-size:0.875rem">Loading…</p>';
            }
        }
        await this.renderChart(chartConfig);
    }

    fullscreenChart(chartId) {
        const container = document.getElementById(`chart-container-${chartId}`);
        if (!container) return;
        if (!document.fullscreenElement) {
            container.requestFullscreen().then(() => {
                container.style.borderRadius = '0';
                container.style.maxHeight = '100vh';
            }).catch(() => {});
        } else {
            document.exitFullscreen().then(() => {
                container.style.borderRadius = '';
                container.style.maxHeight = '';
            }).catch(() => {});
        }
    }

    downloadChart(chartId) {
        const chartInstance = this.state.chartInstances[chartId];
        if (chartInstance) {
            const url = chartInstance.toBase64Image();
            const a = document.createElement('a');
            a.href = url;
            const config = this.state.availableCharts.find(c => c.id == chartId);
            a.download = (config?.name || 'chart') + '.png';
            a.click();
            return;
        }
        // Fallback: capture inner container as PNG via html2canvas if available
        const inner = document.querySelector(`#chart-container-${chartId} .chart-inner-container`);
        if (inner && window.html2canvas) {
            window.html2canvas(inner).then(canvas => {
                const a = document.createElement('a');
                a.href = canvas.toDataURL();
                const config = this.state.availableCharts.find(c => c.id == chartId);
                a.download = (config?.name || 'chart') + '.png';
                a.click();
            });
        } else {
            App.showToast && App.showToast('Download only available for canvas-based charts.', 'info');
        }
    }

    toggleChartMenu(chartId, btn) {
        // Close all other open menus first
        document.querySelectorAll('.chart-dropdown-menu:not(.hidden)').forEach(m => {
            if (m.id !== `chart-menu-${chartId}`) m.classList.add('hidden');
        });
        const menu = document.getElementById(`chart-menu-${chartId}`);
        if (!menu) return;
        menu.classList.toggle('hidden');
        if (!menu.classList.contains('hidden')) {
            // Close on outside click
            const close = (e) => {
                if (!menu.contains(e.target) && e.target !== btn) {
                    menu.classList.add('hidden');
                    document.removeEventListener('click', close, true);
                }
            };
            setTimeout(() => document.addEventListener('click', close, true), 0);
        }
    }

    editChart(chartId) {
        document.querySelectorAll('.chart-dropdown-menu').forEach(m => m.classList.add('hidden'));
        // Navigate to Reports and open chart builder for this chart
        App.router('reports');
        setTimeout(() => {
            if (typeof App.reportsInstance?.openEditChart === 'function') {
                App.reportsInstance.openEditChart(chartId);
            }
        }, 300);
    }

    removeChart(chartId) {
        document.querySelectorAll('.chart-dropdown-menu').forEach(m => m.classList.add('hidden'));
        // Uncheck in selector + remove widget
        const checkbox = document.querySelector(`#chart-toggle-${chartId}`);
        if (checkbox && checkbox.checked) {
            checkbox.checked = false;
            this.handleChartSelectionChange(checkbox);
        } else {
            // Direct removal if selector is not visible
            this.state.layout.selectedCharts = this.state.layout.selectedCharts.filter(id => id != chartId);
            if (this.state.chartInstances[chartId]) {
                this.state.chartInstances[chartId].destroy();
                delete this.state.chartInstances[chartId];
            }
            if (this.gs) {
                const widgetEl = this.gridEl.querySelector(`[gs-id="chart-widget-${chartId}"]`);
                if (widgetEl) this.gs.removeWidget(widgetEl);
            }
            this._captureAndSavePositions();
        }
    }

    getColorScheme(scheme) {
        const schemes = {
            default: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#14B8A6', '#F97316'],
            green: ['#10B981', '#34D399', '#6EE7B7', '#A7F3D0', '#D1FAE5'],
            purple: ['#8B5CF6', '#A78BFA', '#C4B5FD', '#DDD6FE', '#EDE9FE'],
            warm: ['#F59E0B', '#F97316', '#EF4444', '#EC4899', '#DC2626'],
            cool: ['#3B82F6', '#14B8A6', '#10B981', '#06B6D4', '#0EA5E9']
        };
        return schemes[scheme] || schemes.default;
    }
}

// ── Drill-through: click a chart segment → navigate to Leads with filter ──
App.drillToLeads = function(field, value, field2, value2) {
    // Block drill-through while dashboard is in edit/layout mode
    if (App.dashboardInstance && App.dashboardInstance._editMode) return;

    // Clear all previous filter state so there are no conflicts
    App.facetFilters  = {};
    App.inlineFilters = {};
    const stageEl  = document.getElementById('statusFilter');
    const sourceEl = document.getElementById('sourceFilter');
    if (stageEl)  stageEl.value  = '';
    if (sourceEl) sourceEl.value = '';

    // Set facet filters BEFORE routing so the first loadLeads call sees them.
    // Using facetFilters (not DOM selects) means sources like Meta/Facebook/Instagram
    // work correctly even before the select options are dynamically populated.
    const setFacet = (f, v) => {
        if (f === 'stage_id') App.facetFilters.stage_id = [v.toLowerCase()];
        else if (f === 'source') App.facetFilters.source = [v];
    };
    setFacet(field, value);
    if (field2 && value2) setFacet(field2, value2);

    // Navigate to leads — the router calls loadLeads() which reads App.facetFilters,
    // then loadFacets() → renderFacetButtons() shows the active filter chips.
    App.router('leads');
};

// Attach the loader function to the global App object
App.loadDashboard = function () {
    const appContent = document.getElementById('appContent');
    if (!appContent) {
        console.error('#appContent element not found!');
        return;
    }

    // Check if we are already on the dashboard and it's active
    const isDashboardVisible = document.getElementById('dashboard-content') !== null;
    if (isDashboardVisible && App.dashboardInstance && App.dashboardInstance.active) {
        console.log('Dashboard already active, skipping full re-init.');
        App.dashboardInstance.refreshAllCharts();
        return;
    }

    // Clean up previous instance if it exists to prevent duplicate event listeners
    if (App.dashboardInstance) {
        console.log('Cleaning up previous Dashboard instance');
        if (typeof App.dashboardInstance.destroyAllCharts === 'function') {
            App.dashboardInstance.destroyAllCharts();
        }
        App.dashboardInstance.active = false;
    }

    // Inject the dashboard HTML structure
    appContent.innerHTML = `
        <div id="dashboard-content" class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8 pb-10">
            <div id="dashboard-loading" class="text-center py-10">
                <p class="text-gray-500">Loading Dashboard...</p>
            </div>
            <div id="dashboard-grid" class="grid-stack" style="display:none;">
                <!-- GridStack widgets inserted here -->
            </div>
        </div>
    `;

    App.dashboardInstance = new Dashboard();
    App.dashboardInstance.active = true;
};

window.App = App;