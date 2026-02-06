// js/modules/dashboard.js

const App = window.App || {};

class Dashboard {
    constructor() {
        console.log('Dashboard class instantiated');
        this.state = {
            layout: {
                selectedCharts: [],
                dateRange: 'last_7_days',
                custom: { start: null, end: null }
            },
            availableCharts: [],
            chartInstances: {},
        };

        this.apiUrl = window.AppData.config.apiUrl;
        this.csrfToken = window.AppData.csrf_token;

        // Elements are now inside #appContent, so we find them after HTML injection
        this.initDOMElements(); 
        this.initEventListeners();
        this.init();
    }

    initDOMElements() {
        console.log('initDOMElements called');
        this.grid = document.getElementById('dashboard-grid');
        this.loadingIndicator = document.getElementById('dashboard-loading');
        this.chartSelectorPanel = document.getElementById('chart-selector-panel');
        this.chartSelectorList = document.getElementById('chart-selector-list');
        this.chartSelectorOverlay = document.getElementById('chart-selector-overlay');
        this.dateRangeButtons = document.querySelectorAll('.date-range-btn');
        console.log('DOM Elements:', {
            grid: this.grid,
            loadingIndicator: this.loadingIndicator,
            chartSelectorPanel: this.chartSelectorPanel,
            chartSelectorList: this.chartSelectorList,
            chartSelectorOverlay: this.chartSelectorOverlay,
            dateRangeButtons: this.dateRangeButtons
        });
    }

    initEventListeners() {
        document.getElementById('toggle-chart-selector').addEventListener('click', () => this.toggleChartSelector(true));
        document.getElementById('close-chart-selector').addEventListener('click', () => this.toggleChartSelector(false));
        document.getElementById('save-dashboard-btn-panel').addEventListener('click', () => this.toggleChartSelector(false));
        this.chartSelectorOverlay.addEventListener('click', () => this.toggleChartSelector(false));

        this.dateRangeButtons.forEach(btn => {
            btn.addEventListener('click', (e) => this.handleDateRangeClick(e.currentTarget.id));
        });
    }

    async init() {
        await this.loadLayout();
        await this.loadAvailableCharts();
        
        this.renderChartSelector();
        this.updateDateRangeUI();
        
        if (this.state.layout.selectedCharts && this.state.layout.selectedCharts.length > 0) {
            this.grid.style.display = 'grid';
            this.loadingIndicator.style.display = 'none';
            await this.renderDashboard();
        } else {
            this.loadingIndicator.innerHTML = '<p class="text-gray-500">Dashboard is empty. Click "Add / Remove Charts" to get started.</p>';
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
        }
    }

    async saveLayout() {
        if (this.state.availableCharts.length === 0) return;
        await this.fetchAPI('dashboard/save_layout.php', {
            method: 'POST',
            body: JSON.stringify(this.state.layout),
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
        if(dateRange.type === 'custom' && dateRange.start && dateRange.end) {
            endpoint += `&start=${dateRange.start}&end=${dateRange.end}`;
        }
        return await this.fetchAPI(endpoint);
    }
    
    // --- Rendering Methods ---
    async renderDashboard() {
        this.grid.innerHTML = '';
        this.destroyAllCharts();

        const chartPromises = this.state.layout.selectedCharts.map(chartId => {
            const chartConfig = this.state.availableCharts.find(c => c.id == chartId);
            if (chartConfig) {
                return this.renderChart(chartConfig);
            }
        });

        await Promise.all(chartPromises);
    }
    
    async renderChart(chartConfig) {
        const chartContainer = document.createElement('div');
        chartContainer.className = 'bg-white p-6 rounded-2xl shadow-sm border border-gray-100 relative group';
        chartContainer.id = `chart-container-${chartConfig.id}`;
        
        chartContainer.innerHTML = `
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-bold text-gray-900">${chartConfig.name}</h3>
            </div>
            <div class="chart-inner-container" style="position: relative; height:300px;">
                <p class="chart-loading text-gray-500">Loading chart data...</p>
                <canvas id="chart-${chartConfig.id}"></canvas>
            </div>
        `;
        this.grid.appendChild(chartContainer);

        const dateRange = {
            type: this.state.layout.dateRange,
            start: this.state.layout.custom?.start,
            end: this.state.layout.custom?.end,
        };
        
        const data = await this.fetchChartData(chartConfig.id, dateRange);

        const loadingEl = chartContainer.querySelector('.chart-loading');
        loadingEl.style.display = 'none';

        if (data && data.labels && data.datasets) {
            // Get color scheme from chart config
            const colorScheme = this.getColorScheme(chartConfig.colorScheme || chartConfig.color_scheme || 'default');
            
            let chartType = chartConfig.type || 'bar';
            
            // Handle combo charts specially
            if (chartType === 'combo') {
                const counts = data.comboCounts || data.datasets[0].data;
                const totals = data.comboValues || data.datasets[0].data;
                
                const ctx = document.getElementById(`chart-${chartConfig.id}`).getContext('2d');
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
                        plugins: { legend: { position: 'bottom' } },
                        scales: {
                            y: { beginAtZero: true, title: { display: true, text: 'Leads' } },
                            y1: {
                                beginAtZero: true,
                                position: 'right',
                                grid: { drawOnChartArea: false },
                                ticks: {
                                    callback: (val) => '$' + Number(val).toLocaleString()
                                },
                                title: { display: true, text: 'Value' }
                            }
                        }
                    }
                });
                return;
            }
            
            // Handle scorecard charts specially
            if (chartType === 'scorecard') {
                const total = data.datasets[0].data.reduce((sum, val) => sum + val, 0);
                const isMonetary = chartConfig.data_field === 'lead_value' || chartConfig.aggregation === 'sum';
                const displayValue = isMonetary 
                    ? '$' + total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : total.toLocaleString();
                
                chartContainer.innerHTML = `
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">${chartConfig.name}</h4>
                    </div>
                    <div class="text-4xl font-extrabold mb-1 text-gray-900">${displayValue}</div>
                    <div class="text-sm font-medium text-blue-600">Total ${chartConfig.data_field === 'lead_value' ? 'Value' : 'Count'}</div>
                `;
                return;
            }
            
            // Convert other unsupported chart types to supported ones
            if (chartType === 'funnel') {
                chartType = 'bar';
            }
            
            // Apply colors to datasets
            if (data.datasets && data.datasets[0]) {
                data.datasets[0].backgroundColor = chartType === 'pie' || chartType === 'doughnut' ? colorScheme : colorScheme[0];
                data.datasets[0].borderColor = chartType === 'pie' || chartType === 'doughnut' ? colorScheme : colorScheme[0];
            }
            
            const ctx = document.getElementById(`chart-${chartConfig.id}`).getContext('2d');
            this.state.chartInstances[chartConfig.id] = new Chart(ctx, {
                type: chartType,
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: chartType === 'pie' || chartType === 'doughnut',
                            position: 'right'
                        }
                    },
                    scales: chartType === 'line' || chartType === 'bar' ? {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    } : undefined
                }
            });
        } else {
             loadingEl.style.display = 'block';
             loadingEl.textContent = 'No data available for this range.';
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
    handleChartSelectionChange(checkbox) {
        const chartId = checkbox.dataset.chartId;
        const isSelected = checkbox.checked;

        if (isSelected) {
            this.state.layout.selectedCharts.push(chartId);
            this.loadingIndicator.style.display = 'none';
            this.grid.style.display = 'grid';
            const chartConfig = this.state.availableCharts.find(c => c.id == chartId);
            this.renderChart(chartConfig);
        } else {
            this.state.layout.selectedCharts = this.state.layout.selectedCharts.filter(id => id !== chartId);
            const chartContainer = document.getElementById(`chart-container-${chartId}`);
            if (chartContainer) chartContainer.remove();
            if (this.state.chartInstances[chartId]) {
                this.state.chartInstances[chartId].destroy();
                delete this.state.chartInstances[chartId];
            }
             if (this.state.layout.selectedCharts.length === 0) {
                this.loadingIndicator.innerHTML = '<p class="text-gray-500">Dashboard is empty. Click "Add / Remove Charts" to get started.</p>';
                this.loadingIndicator.style.display = 'block';
                this.grid.style.display = 'none';
            }
        }
        this.saveLayout();
    }
    
    handleDateRangeClick(id) {
        const range = id.replace('date-range-', '');
        if (range === 'custom') {
            this.openCustomDateModal();
            return;
        }
        this.state.layout.dateRange = range;
        this.updateDateRangeUI();
        this.refreshAllCharts();
        this.saveLayout();
    }

    openCustomDateModal() {
        const dummyElement = document.createElement('div');
        document.body.appendChild(dummyElement);
        
        const fp = flatpickr(dummyElement, {
            appendTo: document.body,
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: [this.state.layout.custom?.start, this.state.layout.custom?.end],
            onClose: (selectedDates) => {
                if (selectedDates.length === 2) {
                    const [start, end] = selectedDates;
                    this.state.layout.dateRange = 'custom';
                    this.state.layout.custom = {
                        start: start.toISOString().split('T')[0],
                        end: end.toISOString().split('T')[0]
                    };
                    this.updateDateRangeUI();
                    this.refreshAllCharts();
                    this.saveLayout();
                }
                setTimeout(() => fp.destroy(), 100);
            }
        });
        fp.open();
    }

    async refreshAllCharts() {
        this.loadingIndicator.style.display = 'none';
        this.grid.style.display = 'grid';
        await this.renderDashboard();
    }

    updateDateRangeUI() {
        this.dateRangeButtons.forEach(btn => {
            const range = btn.id.replace('date-range-', '');
            btn.classList.toggle('bg-blue-100', range === this.state.layout.dateRange);
            btn.classList.toggle('text-blue-700', range === this.state.layout.dateRange);
        });
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

// Attach the loader function to the global App object
App.loadDashboard = function() {
    const appContent = document.getElementById('appContent');
    if (!appContent) {
        console.error('#appContent element not found!');
        return;
    }

    // Inject the dashboard HTML structure
    appContent.innerHTML = `
        <div id="dashboard-content" class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
             <div id="dashboard-loading" class="text-center py-10">
                <p class="text-gray-500">Loading Dashboard...</p>
            </div>
            <div id="dashboard-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" style="display: none;">
                <!-- Charts will be dynamically inserted here -->
            </div>
        </div>
        
        <!-- These elements are now outside the main app content to avoid being overwritten -->
    `;

    // The side panel and date controls are part of the main page layout now, not injected.
    // The main index.php should be modified to contain the date controls and the chart selector panel.
    // We just need to ensure the event listeners are wired up by the Dashboard class.
    
    new Dashboard();
};

window.App = App;