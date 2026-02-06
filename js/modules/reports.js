/**
 * Reports, summary charts, and custom chart builder.
 */

const App = window.App || {};

Object.assign(App, {
    async loadReports() {
        document.getElementById('appContent').innerHTML = '<div class="text-center py-10"><p class="text-gray-500">Loading Reports...</p></div>';

        try {
            const data = await this.api('/reports/summary.php');
            if (data && !data.error) {
                this.saveReportsCache(data);
                await this.renderReports(data);
            } else {
                console.error('Reports summary error:', data && data.error);
                // Try to load from cache
                const cachedData = this.loadReportsCache();
                if (cachedData) {
                    this.showToast('Loaded reports from cache - data may be outdated', 'warning');
                    await this.renderReports(cachedData);
                } else {
                    document.getElementById('appContent').innerHTML = `<p class="p-4 text-red-500">Failed to load reports${data && data.error ? ': ' + data.error : ''}.</p>`;
                }
            }
        } catch (e) {
            console.error(e);
            // Try to load from cache
            const cachedData = this.loadReportsCache();
            if (cachedData) {
                this.showToast('Loaded reports from cache - data may be outdated', 'warning');
                await this.renderReports(cachedData);
            } else {
                document.getElementById('appContent').innerHTML = '<p class="p-4 text-red-500">Error loading reports.</p>';
            }
        }
    },

    saveReportsCache(data) {
        const user = this.getUser();
        if (user) {
            const cacheKey = 'crm_reports_cache_' + user.org_id;
            const cacheData = {
                data: data,
                timestamp: Date.now()
            };
            localStorage.setItem(cacheKey, JSON.stringify(cacheData));
        }
    },

    loadReportsCache() {
        const user = this.getUser();
        if (user) {
            const cacheKey = 'crm_reports_cache_' + user.org_id;
            const stored = localStorage.getItem(cacheKey);
            if (stored) {
                try {
                    const cacheData = JSON.parse(stored);
                    // Check if cache is not older than 24 hours
                    if (Date.now() - cacheData.timestamp < 24 * 60 * 60 * 1000) {
                        return cacheData.data;
                    } else {
                        localStorage.removeItem(cacheKey); // Remove expired cache
                    }
                } catch (e) {
                    console.error('Error parsing reports cache:', e);
                    localStorage.removeItem(cacheKey);
                }
            }
        }
        return null;
    },

    async renderReports(data) {
        const container = document.getElementById('appContent');

        const byStage = Array.isArray(data.by_stage) ? data.by_stage : [];
        const bySource = Array.isArray(data.by_source) ? data.by_source : [];

        const totalValue = byStage.reduce((sum, s) => sum + parseFloat(s.total_value || 0), 0);
        const formattedTotalValue = totalValue.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });
        const topSource = bySource.length > 0 ? (bySource[0].source || 'Unknown') + ` (${bySource[0].count || 0})` : 'N/A';

        container.innerHTML = `
            <div class="space-y-6">
                <div class="flex justify-between items-center bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Analytics Dashboard</h2>
                        <p class="text-sm text-gray-500">Real-time performance metrics</p>
                    </div>
                    <button onclick="App.openChartBuilder()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700 transition-all transform hover:scale-105 active:scale-95">
                        <i data-lucide="plus-circle" class="h-4 w-4 mr-2"></i>
                        Add New Chart
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="relative overflow-hidden bg-white p-6 rounded-2xl shadow-sm border border-gray-200 group hover:shadow-md transition-shadow">
                        <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
                            <i data-lucide="users" class="h-16 w-16 text-blue-600"></i>
                        </div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Total Leads</p>
                        <div class="flex items-end space-x-2 mt-2">
                            <p class="text-4xl font-extrabold text-gray-900">${data.total_leads.toLocaleString()}</p>
                            <span class="text-blue-600 text-sm font-medium mb-1">active</span>
                        </div>
                    </div>
                    <div class="relative overflow-hidden bg-white p-6 rounded-2xl shadow-sm border border-gray-200 group hover:shadow-md transition-shadow">
                        <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
                            <i data-lucide="dollar-sign" class="h-16 w-16 text-emerald-600"></i>
                        </div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Pipeline Value</p>
                        <div class="flex items-end space-x-2 mt-2">
                            <p class="text-4xl font-extrabold text-gray-900">${formattedTotalValue}</p>
                            <span class="text-emerald-600 text-sm font-medium mb-1">USD</span>
                        </div>
                    </div>
                    <div class="relative overflow-hidden bg-white p-6 rounded-2xl shadow-sm border border-gray-200 group hover:shadow-md transition-shadow">
                        <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
                            <i data-lucide="trending-up" class="h-16 w-16 text-amber-600"></i>
                        </div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Top Source</p>
                        <div class="mt-2">
                            <p class="text-3xl font-extrabold text-gray-900 truncate">${topSource}</p>
                            <p class="text-amber-600 text-sm font-medium">leading channel</p>
                        </div>
                    </div>
                </div>

                <div id="customChartsGrid" class="grid grid-cols-1 md:grid-cols-2 gap-6 min-h-[100px]"></div>
            </div>
        `;

        lucide.createIcons();

        this.reportData = data; // Save for re-rendering individual charts

        const savedCharts = await this.getSavedCharts();
        this.charts = savedCharts; // Store for later use
        if (savedCharts.length > 0) {
            await this.renderAllCustomCharts(savedCharts, data);

            // Initialize Sortable for drag-and-drop
            const grid = document.getElementById('customChartsGrid');
            if (grid && window.Sortable) {
                Sortable.create(grid, {
                    animation: 150,
                    handle: '.drag-handle',
                    ghostClass: 'bg-blue-50',
                    onEnd: async () => {
                        const order = Array.from(grid.children).map((el, index) => ({
                            id: el.getAttribute('data-id'),
                            order: index
                        }));
                        await this.api('/reports/update_order.php', 'POST', { order });
                        this.showToast('Chart order saved', 'success');
                    }
                });
            }
        } else {
            const grid = document.getElementById('customChartsGrid');
            grid.innerHTML = `
                <div class="col-span-2 text-center py-20 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-300">
                    <div class="bg-white h-20 w-20 rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm">
                        <i data-lucide="bar-chart-3" class="h-10 w-10 text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Build Your Visual Insights</h3>
                    <p class="text-gray-500 mb-8 max-w-sm mx-auto">Create custom charts and tables to monitor your leads pipeline in real-time.</p>
                    <button onclick="App.openChartBuilder()" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-semibold rounded-xl text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all">
                        <i data-lucide="plus" class="h-5 w-5 mr-2"></i>
                        Create Your First Chart
                    </button>
                </div>
            `;
            lucide.createIcons();
        }
    },

    getChartConfig() {
        const stored = localStorage.getItem('crm_chart_config_' + this.getUser().org_id);
        if (!stored) {
            return { stage: true, source: true, custom_fields: [] };
        }
        return JSON.parse(stored);
    },

    saveChartConfigToStorage(config) {
        localStorage.setItem('crm_chart_config_' + this.getUser().org_id, JSON.stringify(config));
    },

    async openChartConfig() {
        const config = this.getChartConfig();
        const customFields = await this.getCustomFields();

        document.getElementById('chart_stage').checked = config.stage;
        document.getElementById('chart_source').checked = config.source;

        const container = document.getElementById('customFieldChartsList');
        if (customFields.length === 0) {
            container.innerHTML = '<p class="text-xs text-gray-500">No custom fields available.</p>';
        } else {
            container.innerHTML = customFields.map(field => `
                <label class="flex items-center">
                    <input type="checkbox" id="chart_custom_${field.name}" 
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" 
                           ${config.custom_fields && config.custom_fields.includes(field.name) ? 'checked' : ''}>
                    <span class="ml-2 text-sm text-gray-700">${field.name}</span>
                </label>
            `).join('');
        }

        document.getElementById('chartConfigModal').classList.remove('hidden');
    },

    closeChartConfig() {
        document.getElementById('chartConfigModal').classList.add('hidden');
    },

    async saveChartConfig() {
        const config = {
            stage: document.getElementById('chart_stage').checked,
            source: document.getElementById('chart_source').checked,
            custom_fields: []
        };

        const customFields = await this.getCustomFields();
        customFields.forEach(field => {
            const checkbox = document.getElementById(`chart_custom_${field.name}`);
            if (checkbox && checkbox.checked) {
                config.custom_fields.push(field.name);
            }
        });

        this.saveChartConfigToStorage(config);
        this.closeChartConfig();
        this.loadReports();
    },

    async getSavedCharts() {
        try {
            const charts = await this.api('/reports/get_charts.php');
            if (charts && charts.length > 0) {
                // Save to localStorage as backup
                const user = this.getUser();
                if (user) {
                    localStorage.setItem('crm_custom_charts_' + user.org_id, JSON.stringify(charts));
                }
                return charts;
            }
        } catch (e) {
            console.error('Error loading charts from DB:', e);
        }

        // Fallback to localStorage
        const user = this.getUser();
        if (user) {
            const stored = localStorage.getItem('crm_custom_charts_' + user.org_id);
            const charts = stored ? JSON.parse(stored) : [];
            return charts;
        }
        return [];
    },

    async saveChartToDB(chart) {
        try {
            const result = await this.api('/reports/save_chart.php', 'POST', chart);
            return result && result.success;
        } catch (e) {
            console.error('Error saving chart:', e);
            return false;
        }
    },

    async openChartBuilder(chartId = null) {
        const modal = document.getElementById('chartBuilderModal');
        const form = document.getElementById('chartBuilderForm');
        const title = document.getElementById('chartBuilderTitle');
        const submitText = document.getElementById('chartBuilderSubmitText');

        const xAxisSelect = document.getElementById('chartXAxis');
        const yAxisMetricSelect = document.getElementById('chartYAxisMetric');
        const yAxisValueSelect = document.getElementById('chartYAxisValue');

        // Await customFields before using
        const customFieldsPromise = this.getCustomFields();
        // Ensure the dynamic filter section exists in the form UI
        let dynamicFilterSection = document.getElementById('dynamicFilterSection');
        if (!dynamicFilterSection) {
            const filterDiv = document.createElement('div');
            filterDiv.id = 'dynamicFilterSection';
            filterDiv.className = 'mb-4';
            filterDiv.innerHTML = `
                <label id="dynamicFilterLabel" class="block text-sm font-medium text-gray-700 mb-2">Filter</label>
                <div id="dynamicFilterList" class="flex flex-wrap gap-2 border rounded p-2 bg-gray-50"></div>
            `;
            // Insert after the chart title input if present, else at top
            const titleInput = form.querySelector('#chartTitle')?.parentElement || form.firstChild;
            if (titleInput && titleInput.nextSibling) {
                form.insertBefore(filterDiv, titleInput.nextSibling);
            } else {
                form.insertBefore(filterDiv, form.firstChild);
            }
        }
        customFieldsPromise.then(customFields => {
            // Reset core options (in case DOM was modified) and clear custom options
            xAxisSelect.innerHTML = `
                <option value="stage_id">Stage</option>
                <option value="source">Source</option>
                <option value="lead_name">Lead Name</option>
                <option value="lead_value">Lead Value</option>
                <option value="company">Company</option>
                <option value="email">Email</option>
                <option value="phone">Phone</option>
                <option value="title">Title</option>
                <option value="owner_email">Lead Owner</option>
            `;
            yAxisMetricSelect.innerHTML = `
                <option value="count">Count of</option>
                <option value="sum">Sum of</option>
                <option value="avg">Average of</option>
                <option value="show">Show the text</option>
            `;
            yAxisValueSelect.innerHTML = `
                <option value="leads">Leads</option>
                <option value="lead_value">Lead Value</option>
                <option value="notes">Notes</option>
                <option value="email">Email</option>
                <option value="phone">Phone</option>
                <option value="company">Company</option>
                <option value="title">Title</option>
                <option value="source">Source</option>
            `;

            // Add custom fields to X-axis
            customFields.forEach(field => {
                const option = document.createElement('option');
                option.value = 'custom_' + field.name;
                option.textContent = field.name + ' (Custom)';
                option.setAttribute('data-custom', 'true');
                xAxisSelect.appendChild(option);
            });

            // Add custom fields to Y-axis Value
            customFields.forEach(field => {
                const option = document.createElement('option');
                option.value = 'custom_' + field.name;
                option.textContent = field.name;
                option.setAttribute('data-custom', 'true');
                yAxisValueSelect.appendChild(option);
            });

            // --- Source Filter Section ---
            // --- Dynamic Filter Section for X/Y Axis ---
            let dynamicFilterSection = document.getElementById('dynamicFilterSection');
            if (!dynamicFilterSection) {
                // Insert the section if not present
                const filterDiv = document.createElement('div');
                filterDiv.id = 'dynamicFilterSection';
                filterDiv.className = 'mb-4';
                filterDiv.innerHTML = `
                    <label id="dynamicFilterLabel" class="block text-sm font-medium text-gray-700 mb-2">Filter</label>
                    <div id="dynamicFilterList" class="flex flex-wrap gap-2"></div>
                `;
                form.insertBefore(filterDiv, form.firstChild.nextSibling); // After title
                dynamicFilterSection = filterDiv;
            }

            // Helper to get unique values for a field from summary/detailed data
            const getUniqueValues = (field) => {
                let values = [];
                if (field.startsWith('custom_')) {
                    const fname = field.replace('custom_', '');
                    if (this.reportData && this.reportData.custom_fields && this.reportData.custom_fields[fname]) {
                        values = Object.keys(this.reportData.custom_fields[fname]);
                    }
                } else if (field === 'stage_id') {
                    values = (this.reportData && this.reportData.by_stage) ? this.reportData.by_stage.map(s => s.stage) : [];
                } else if (field === 'source') {
                    values = (this.reportData && this.reportData.by_source) ? this.reportData.by_source.map(s => s.source) : [];
                } else if (field === 'owner_email') {
                    values = (this.reportData && this.reportData.by_owner) ? this.reportData.by_owner.map(s => s.owner_email) : [];
                } else if (field === 'company') {
                    values = (this.reportData && this.reportData.by_company) ? this.reportData.by_company.map(s => s.company) : [];
                } else if (field === 'title') {
                    values = (this.reportData && this.reportData.by_title) ? this.reportData.by_title.map(s => s.title) : [];
                } else if (field === 'email') {
                    values = (this.reportData && this.reportData.by_email) ? this.reportData.by_email.map(s => s.email) : [];
                } else if (field === 'phone') {
                    values = (this.reportData && this.reportData.by_phone) ? this.reportData.by_phone.map(s => s.phone) : [];
                }
                // fallback: try detailed leads
                if ((!values || values.length === 0) && this.reportData && this.reportData.leads) {
                    values = Array.from(new Set(this.reportData.leads.map(l => l[field]).filter(Boolean)));
                }
                return values.filter(v => v !== undefined && v !== null && v !== '').map(String);
            };

            // Function to render filter checkboxes for a field
            const renderFilterCheckboxes = (field, chart) => {
                const filterList = document.getElementById('dynamicFilterList');
                const filterLabel = document.getElementById('dynamicFilterLabel');
                if (!field) {
                    filterList.innerHTML = '';
                    filterLabel.textContent = 'Filter';
                    return;
                }
                const values = getUniqueValues(field);
                filterLabel.textContent = `Filter by ${this.getAxisLabel(field)}`;
                filterList.innerHTML = values.map(val => `
                    <label class="inline-flex items-center">
                        <input type="checkbox" class="chart-filter-dynamic" value="${val}">
                        <span class="ml-1">${val}</span>
                    </label>
                `).join('');
                // Pre-check based on chart.filters
                if (chart && chart.filters && chart.filters[field] && chart.filters[field].include) {
                    const include = chart.filters[field].include;
                    document.querySelectorAll('.chart-filter-dynamic').forEach(cb => {
                        cb.checked = include.includes(cb.value);
                    });
                } else {
                    // Default: all checked
                    document.querySelectorAll('.chart-filter-dynamic').forEach(cb => cb.checked = true);
                }
            };

            // Initial render for X-axis field
            let initialField = document.getElementById('chartXAxis').value;
            let chart = chartId ? this.charts.find(c => c.id == chartId) : null;
            renderFilterCheckboxes(initialField, chart);

            // Update filter checkboxes when X-axis changes
            document.getElementById('chartXAxis').addEventListener('change', (e) => {
                renderFilterCheckboxes(e.target.value, chart);
            });

            // ...existing code...
            if (chartId) {
                const chart = this.charts.find(c => c.id == chartId);
                if (chart) {/* Lines 338-375 omitted */}
            } else {
                title.textContent = 'Create New Chart';
                submitText.textContent = 'Create Chart';
                form.reset();
                document.getElementById('chartEditId').value = '';
                document.getElementById('chartType').value = 'table';
                document.getElementById('chartXAxis').value = 'stage_id';
                document.getElementById('chartYAxisMetric').value = 'count';
                document.getElementById('chartYAxisValue').value = 'leads';
                document.getElementById('chartColorScheme').value = 'default';
                document.getElementById('chartSort').value = 'default';
                document.getElementById('chartShowTotal').checked = false;
                if (document.getElementById('chartFunnelSort')) {/* Lines 389-390 omitted */}
            }
            // Add event listener for chart type change
            document.getElementById('chartType').addEventListener('change', function() {
                const funnelContainer = document.getElementById('funnelSortContainer');
                if (funnelContainer) {/* Lines 397-398 omitted */}
            });
            // Initial check for funnel type
            const currentType = document.getElementById('chartType').value;
            const funnelContainer = document.getElementById('funnelSortContainer');
            if (funnelContainer) {
                funnelContainer.style.display = currentType === 'funnel' ? 'block' : 'none';
            }
            modal.classList.remove('hidden');
        });
    },

    closeChartBuilder() {
        document.getElementById('chartBuilderModal').classList.add('hidden');
        document.getElementById('chartBuilderForm').reset();
    },

    async saveChartFromBuilder(e) {
        e.preventDefault();

        const chartId = document.getElementById('chartEditId').value;
        const chartConfig = {
            title: document.getElementById('chartTitle') ? document.getElementById('chartTitle').value : 'Untitled',
            chart_type: document.getElementById('chartType') ? document.getElementById('chartType').value : 'table',
            data_field: document.getElementById('chartYAxisValue') ? document.getElementById('chartYAxisValue').value : 'leads',
            aggregation: document.getElementById('chartYAxisMetric') ? document.getElementById('chartYAxisMetric').value : 'count',
            x_axis: document.getElementById('chartXAxis') ? document.getElementById('chartXAxis').value : 'stage_id',
            color_scheme: document.getElementById('chartColorScheme') ? document.getElementById('chartColorScheme').value : 'default',
            chart_sort: document.getElementById('chartSort') ? document.getElementById('chartSort').value : 'default',
            show_total: document.getElementById('chartShowTotal') && document.getElementById('chartShowTotal').checked ? 1 : 0,
            funnel_sort: document.getElementById('chartFunnelSort') ? document.getElementById('chartFunnelSort').value : 'default'
        };


        // Collect selected values for dynamic filter (X-axis field)
        const dynamicField = document.getElementById('chartXAxis').value;
        const allDynamicCheckboxes = Array.from(document.querySelectorAll('.chart-filter-dynamic'));
        const selectedDynamic = allDynamicCheckboxes.filter(cb => cb.checked).map(cb => cb.value);
        chartConfig.filters = chartConfig.filters || {};
        if (selectedDynamic.length > 0 && selectedDynamic.length !== allDynamicCheckboxes.length) {
            chartConfig.filters[dynamicField] = { include: selectedDynamic };
        }
        // Still collect stage and custom filters as before
        const filterStages = Array.from(document.querySelectorAll('.chart-filter-stage')).filter(cb => !cb.checked).map(cb => cb.value);
        const filterCustom = Array.from(document.querySelectorAll('.chart-filter-custom')).filter(cb => !cb.checked).map(cb => cb.value);
        if (filterStages.length) chartConfig.filters.stages = { exclude: filterStages };
        if (filterCustom.length) chartConfig.filters.custom = { exclude: filterCustom };

        if (chartId) {
            chartConfig.id = chartId;
        }

        const result = await this.saveChartToDB(chartConfig);
        if (result) {
            this.closeChartBuilder();
            this.loadReports(); // Reload to show new chart
        }
    },

    async deleteChart(chartId) {
        if (!confirm('Are you sure you want to delete this chart?')) return;

        try {
            const result = await this.api('/reports/delete_chart.php', 'POST', { id: chartId });
            if (result && result.success) {
                this.loadReports(); // Reload
            } else {
                alert('Failed to delete chart');
            }
        } catch (e) {
            console.error('Error deleting chart:', e);
            alert('Error deleting chart');
        }
    },

    async renderAllCustomCharts(charts, summaryData) {
        for (const chartConfig of charts) {
            try {
                await this.renderCustomChart(chartConfig, summaryData);
            } catch (error) {
                console.error('Error rendering chart:', chartConfig.title, error);
                // Show error in the grid
                const grid = document.getElementById('customChartsGrid');
                grid.insertAdjacentHTML('beforeend', `
                    <div class="bg-red-50 p-6 rounded-2xl border border-red-200 relative group">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="text-lg font-bold text-red-900">${chartConfig.title || 'Error'}</h4>
                            <button onclick="App.deleteChart('${chartConfig.id}')" class="text-red-400 hover:text-red-700" title="Delete">
                                <i data-lucide="trash-2" class="h-5 w-5"></i>
                            </button>
                        </div>
                        <p class="text-sm text-red-700">Error: ${error.message}</p>
                        <p class="text-xs text-red-500 mt-2">This chart data seems corrupted. Please delete and recreate it.</p>
                    </div>
                `);
            }
        }
    },

    async renderCustomChart(config, summaryData) {
        // Normalize config fields
        config.type = config.type || config.chart_type;
        config.xAxis = config.xAxis || config.x_axis || config.dataField;
        config.yAxisMetric = config.yAxisMetric || config.aggregation || 'count';
        config.yAxisValue = config.yAxisValue || config.data_field || 'leads';
        config.colorScheme = config.colorScheme || config.color_scheme || 'default';
        config.chartSort = config.chartSort || config.chart_sort || 'default';

        const grid = document.getElementById('customChartsGrid');
        const chartIndex = 'custom_' + (config.id || Math.random().toString(36).substr(2, 9));

        // ... detailed data logic ...
        let detailedData = null;
        const needsDetailed = (
            config.xAxis === 'lead_name' ||
            config.xAxis === 'company' ||
            config.xAxis === 'email' ||
            config.xAxis === 'phone' ||
            config.xAxis === 'title' ||
            config.xAxis === 'owner_email' ||
            config.yAxisValue === 'notes'
        );
        if (needsDetailed) {
            detailedData = await this.api('/leads/list.php');
        }

        if (config.type === 'table') {
            const chartData = this.getChartData(config, summaryData, detailedData);

            // Check if this is a "show" metric for header alignment
            const isShowMetric = (config.yAxisMetric === 'show') ||
                (config.yAxis && config.yAxis.startsWith('show_'));
            const yHeaderAlign = isShowMetric ? 'text-left' : 'text-right';

            let tableHTML = `
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 relative group h-full flex flex-col" data-id="${config.id}">
                    <div class="flex justify-between items-start mb-4 flex-shrink-0">
                        <div class="flex items-center">
                            <i data-lucide="grip-vertical" class="h-5 w-5 text-gray-300 mr-2 cursor-move drag-handle opacity-0 group-hover:opacity-100 transition-opacity"></i>
                            <h4 class="text-lg font-bold text-gray-900">${config.title}</h4>
                        </div>
                        <div class="flex space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="App.openChartBuilder('${config.id}')" class="text-gray-400 hover:text-blue-600" title="Edit">
                                <i data-lucide="edit-2" class="h-4 w-4"></i>
                            </button>
                            <button onclick="App.deleteChart('${config.id}')" class="text-gray-400 hover:text-red-600" title="Delete">
                                <i data-lucide="trash-2" class="h-4 w-4"></i>
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto overflow-y-auto flex-grow custom-scrollbar" style="max-height: 400px;">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th onclick="App.toggleChartSort('${config.id}', 'label')" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100 group/head">
                                        <div class="flex items-center">
                                            ${this.getAxisLabel(config.xAxis || 'stage_id')}
                                            <i data-lucide="${config.chartSort === 'label_asc' ? 'chevron-up' : (config.chartSort === 'label_desc' ? 'chevron-down' : 'chevrons-up-down')}" class="ml-1 h-3 w-3 ${config.chartSort && config.chartSort.startsWith('label') ? 'text-blue-500' : 'text-gray-300'}"></i>
                                        </div>
                                    </th>
                                    <th onclick="App.toggleChartSort('${config.id}', 'value')" class="px-4 py-2 ${yHeaderAlign} text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100 group/head">
                                        <div class="flex items-center ${yHeaderAlign === 'text-right' ? 'justify-end' : ''}">
                                            ${this.getYAxisLabel(config)}
                                            <i data-lucide="${config.chartSort === 'value_asc' ? 'chevron-up' : (config.chartSort === 'value_desc' ? 'chevron-down' : 'chevrons-up-down')}" class="ml-1 h-3 w-3 ${config.chartSort && config.chartSort.startsWith('value') ? 'text-blue-500' : 'text-gray-300'}"></i>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">`;

            chartData.labels.forEach((label, idx) => {
                const value = chartData.values[idx];

                // Determine formatting
                let formattedValue;
                if (isShowMetric) {
                    // Display as text, not formatted as number
                    formattedValue = value;
                } else {
                    // Determine if this is a monetary field based on new format
                    const isMonetary = (config.yAxisValue === 'lead_value') ||
                        (config.yAxis && (config.yAxis.includes('value') || config.yAxis.includes('sum_') || config.yAxis.includes('avg_')));
                    formattedValue = isMonetary
                        ? '$' + value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                        : value.toLocaleString();
                }

                // Determine alignment based on type
                const alignment = isShowMetric ? 'text-left' : 'text-right';

                tableHTML += `
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-2 text-sm text-gray-900 font-medium">${label}</td>
                        <td class="px-4 py-2 text-sm text-gray-600 ${alignment}">${formattedValue}</td>
                    </tr>`;
            });

            // Add total row if enabled and metric is numeric
            if (config.showTotal && !isShowMetric && chartData.values.length > 0) {
                const total = chartData.values.reduce((sum, val) => {
                    const numVal = parseFloat(val) || 0;
                    return sum + numVal;
                }, 0);

                const isMonetary = (config.yAxisValue === 'lead_value') ||
                    (config.yAxis && (config.yAxis.includes('value') || config.yAxis.includes('sum_') || config.yAxis.includes('avg_')));
                const formattedTotal = isMonetary
                    ? '$' + total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : total.toLocaleString();

                tableHTML += `
                    <tr class="bg-gray-50 border-t-2 border-gray-200 font-bold">
                        <td class="px-4 py-2 text-sm text-gray-900">Total</td>
                        <td class="px-4 py-2 text-sm text-gray-900 text-right">${formattedTotal}</td>
                    </tr>`;
            }

            tableHTML += `
                            </tbody>
                        </table>
                    </div>
                </div>`;

            // Replace existing or append
            const existingEl = document.querySelector(`div[data-id="${config.id}"]`);
            if (existingEl) {
                existingEl.outerHTML = tableHTML;
            } else {
                grid.insertAdjacentHTML('beforeend', tableHTML);
            }
            lucide.createIcons();
            return;
        }

        if (config.type === 'scorecard') {
            const chartData = this.getChartData(config, summaryData, detailedData);

            // Handle show metric differently (can't sum text)
            const isShowMetric = (config.yAxisMetric === 'show') ||
                (config.yAxis && config.yAxis.startsWith('show_'));

            let displayValue;
            if (isShowMetric) {
                // For text display, show count of items or first value
                displayValue = chartData.values.length > 0 ? `${chartData.values.length} items` : '0 items';
            } else {
                const total = chartData.values.reduce((sum, val) => sum + val, 0);
                const isMonetary = (config.yAxisValue === 'lead_value') ||
                    (config.yAxis && (config.yAxis.includes('value') || config.yAxis.includes('sum_custom_') || config.yAxis.includes('avg_custom_')));
                displayValue = isMonetary
                    ? '$' + total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : total.toLocaleString();
            }

            const subtitle = this.getYAxisLabel(config);

            const chartHtml = `
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 relative group" data-id="${config.id}">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex items-center">
                            <i data-lucide="grip-vertical" class="h-4 w-4 text-gray-300 mr-2 cursor-move drag-handle opacity-0 group-hover:opacity-100 transition-opacity"></i>
                            <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">${config.title}</h4>
                        </div>
                        <div class="flex space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="App.openChartBuilder('${config.id}')" class="text-gray-400 hover:text-blue-600" title="Edit">
                                <i data-lucide="edit-2" class="h-4 w-4"></i>
                            </button>
                            <button onclick="App.deleteChart('${config.id}')" class="text-gray-400 hover:text-red-600" title="Delete">
                                <i data-lucide="trash-2" class="h-4 w-4"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Chart Controls for Scorecard removed -->
                    
                    <div class="text-4xl font-extrabold mb-1 text-gray-900">${displayValue}</div>
                    <div class="text-sm font-medium text-blue-600">${subtitle}</div>
                </div>
            `;
            
            const existingEl = document.querySelector(`div[data-id="${config.id}"]`);
            if (existingEl) {
                existingEl.outerHTML = chartHtml;
            } else {
                grid.insertAdjacentHTML('beforeend', chartHtml);
            }
            lucide.createIcons();
            return;
        }

        const chartHtml = `
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 relative group" data-id="${config.id}">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center">
                        <i data-lucide="grip-vertical" class="h-5 w-5 text-gray-300 mr-2 cursor-move drag-handle opacity-0 group-hover:opacity-100 transition-opacity"></i>
                        <h4 class="text-lg font-bold text-gray-900">${config.title}</h4>
                    </div>
                    <div class="flex space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button onclick="App.openChartBuilder('${config.id}')" class="text-gray-400 hover:text-blue-600" title="Edit">
                            <i data-lucide="edit-2" class="h-4 w-4"></i>
                        </button>
                        <button onclick="App.deleteChart('${config.id}')" class="text-gray-400 hover:text-red-600" title="Delete">
                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Chart Controls removed -->
                
                <div class="h-64"><canvas id="${chartIndex}"></canvas></div>
            </div>
        `;

        const existingEl = document.querySelector(`div[data-id="${config.id}"]`);
        if (existingEl) {
            existingEl.outerHTML = chartHtml;
        } else {
            grid.insertAdjacentHTML('beforeend', chartHtml);
        }
        lucide.createIcons();

        const chartData = this.getChartData(config, summaryData, detailedData);

        if (config.type === 'funnel') {
            const canvas = document.getElementById(chartIndex);
            const container = canvas.parentElement;
            const values = chartData.values.map(v => Number(v) || 0);
            const labels = chartData.labels;
            const colorScheme = this.getColorScheme(config.colorScheme || 'default');

            const totalStages = labels.length;
            const width = 420;
            const segmentHeight = 76;
            const gap = 16;
            const baseWidth = 340;
            const minWidth = 70;
            const svgHeight = totalStages * (segmentHeight + gap) + 30;

            // Calculate widths based on actual values for better proportions
            const maxValue = Math.max(...values);
            const widths = values.map((value, idx) => {
                if (idx === 0) return baseWidth; // First segment always full width
                const ratio = maxValue > 0 ? value / maxValue : 0;
                const calculatedWidth = baseWidth * (0.3 + ratio * 0.7); // 30% minimum + 70% proportional
                return Math.max(minWidth, Math.min(baseWidth, calculatedWidth));
            });

            const buildGradientDefs = () => {
                let defs = '';
                labels.forEach((_, idx) => {
                    if (values[idx] <= 0) return;
                    const color = colorScheme[idx % colorScheme.length];
                    const startOpacity = Math.max(0.5, 0.92 - idx * 0.08).toFixed(2);
                    const endOpacity = Math.max(0.38, startOpacity - 0.18).toFixed(2);
                    defs += `
                        <linearGradient id="funnelGrad${idx}" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="${color}" stop-opacity="${startOpacity}"></stop>
                            <stop offset="100%" stop-color="${color}" stop-opacity="${endOpacity}"></stop>
                        </linearGradient>
                    `;
                });
                return defs;
            };

            let defs = `
                <defs>
                    <filter id="funnelShadow" x="-40%" y="-40%" width="180%" height="220%">
                        <feGaussianBlur in="SourceAlpha" stdDeviation="3" />
                        <feOffset dx="0" dy="3" result="offsetblur" />
                        <feComponentTransfer><feFuncA type="linear" slope="0.18" /></feComponentTransfer>
                        <feMerge><feMergeNode /><feMergeNode in="SourceGraphic" /></feMerge>
                    </filter>
                    <filter id="funnelShadowHover" x="-40%" y="-40%" width="180%" height="220%">
                        <feGaussianBlur in="SourceAlpha" stdDeviation="4.5" />
                        <feOffset dx="0" dy="4" result="offsetblur" />
                        <feComponentTransfer><feFuncA type="linear" slope="0.32" /></feComponentTransfer>
                        <feMerge><feMergeNode /><feMergeNode in="SourceGraphic" /></feMerge>
                    </filter>
                    ${buildGradientDefs()}
                </defs>
            `;

            const getBottomWidth = (idx) => {
                if (idx < widths.length - 1) {
                    return widths[idx + 1];
                }
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
                const fill = isZero ? '#e5e7eb' : `url(#funnelGrad${idx})`;
                const stroke = isZero ? '#cbd5f5' : color;
                const valueText = (value || 0).toLocaleString();
                const prevValue = idx === 0 ? values[idx] : values[idx - 1];
                const conversionRaw = idx === 0 ? 100 : (prevValue > 0 ? (value / prevValue) * 100 : 0);
                const conversionText = idx === 0 ? '100% of start' : `${conversionRaw.toFixed(1)}% of previous`;
                const conversionColor = isZero ? '#cbd5f5' : '#94a3b8';

                segments += `
                    <g filter="url(#funnelShadow)" style="cursor: pointer; transition: filter 0.25s ease;"
                        onmouseover="this.setAttribute('filter','url(#funnelShadowHover)')"
                        onmouseout="this.setAttribute('filter','url(#funnelShadow)')">
                        <polygon
                            points="${topLeft},${y} ${topRight},${y} ${bottomRight},${y + segmentHeight} ${bottomLeft},${y + segmentHeight}"
                            fill="${fill}"
                            stroke="${stroke}"
                            stroke-width="1.2"
                            opacity="${isZero ? 0.5 : 1}"
                        ></polygon>
                        <text
                            x="${width / 2}"
                            y="${y + 26}"
                            text-anchor="middle"
                            fill="${isZero ? '#94a3b8' : '#0f172a'}"
                            font-size="13"
                            font-weight="600"
                            letter-spacing="0.3"
                            style="pointer-events: none;">
                            ${label}
                        </text>
                        <text
                            x="${width / 2}"
                            y="${y + 49}"
                            text-anchor="middle"
                            fill="${isZero ? '#9ca3af' : '#0f172a'}"
                            font-size="22"
                            font-weight="700"
                            style="pointer-events: none;">
                            ${valueText}
                        </text>
                        <text
                            x="${width / 2}"
                            y="${y + segmentHeight + 14}"
                            text-anchor="middle"
                            fill="${conversionColor}"
                            font-size="11"
                            font-weight="500"
                            letter-spacing="0.4"
                            style="pointer-events: none;">
                            ${conversionText}
                        </text>
                    </g>
                `;

                y += segmentHeight + gap;
            });

            const svg = `<div style="width:100%;height:100%;"><svg width="100%" height="${svgHeight}" viewBox="0 0 ${width} ${svgHeight}" preserveAspectRatio="xMidYMid meet">
                ${defs}
                ${segments}
            </svg></div>`;
            // Set scroll and max-height on the parent .bg-white group
            const parent = container.closest('.bg-white.p-6.rounded-2xl.shadow-sm.border.border-gray-100.relative.group');
            if (parent) {
                parent.style.maxHeight = '420px';
                parent.style.overflowY = 'auto';
            }

            container.innerHTML = svg;
            return;
        }

        // Combo: bar (lead count) + line (value) in one chart
        if (config.type === 'combo') {
            const ctx = document.getElementById(chartIndex).getContext('2d');
            const colors = this.getColorScheme(config.colorScheme || 'default');

            const counts = chartData.comboCounts || chartData.values;
            const totals = chartData.comboValues || chartData.secondaryValues;

            if (!chartData.labels.length || !counts || !totals) {
                const container = ctx.canvas.parentElement;
                container.innerHTML = '<p class="text-sm text-gray-500">No data available for combo chart.</p>';
                return;
            }

            new Chart(ctx, {
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Leads',
                            data: counts,
                            backgroundColor: colors[0],
                            borderColor: colors[0],
                            yAxisID: 'y'
                        },
                        {
                            type: 'line',
                            label: 'Value',
                            data: totals,
                            borderColor: colors[1] || colors[0],
                            backgroundColor: (colors[1] || colors[0]) + '40',
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

        const ctx = document.getElementById(chartIndex).getContext('2d');
        const colors = this.getColorScheme(config.colorScheme || 'default');
        const chartType = config.type === 'area' ? 'line' : config.type;

        new Chart(ctx, {
            type: chartType,
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: config.title,
                    data: chartData.values,
                    backgroundColor: config.type === 'line' || config.type === 'area' ?
                        (config.type === 'area' ? colors[0] + '40' : 'transparent') : colors,
                    borderColor: config.type === 'line' || config.type === 'area' ? colors[0] : colors,
                    borderWidth: config.type === 'line' || config.type === 'area' ? 2 : 1,
                    fill: config.type === 'area',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: config.type === 'pie' || config.type === 'doughnut',
                        position: 'right'
                    }
                },
                scales: config.type === 'line' || config.type === 'bar' || config.type === 'area' ? {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                } : undefined
            }
        });
    },

    getChartData(config, summaryData, detailedData = null) {
        let labels = [];
        let values = [];
        let secondaryValues;
        let comboCounts;
        let comboValues;

        // Determine xAxis and yAxis (backwards compatible)
        const xAxis = config.xAxis || 'stage_id';

        // Build yAxis from metric + value (new format) or use legacy format
        let yAxis;
        if (config.yAxisMetric && config.yAxisValue) {
            if (config.yAxisMetric === 'count') {
                yAxis = 'count';
            } else if (config.yAxisMetric === 'sum' && config.yAxisValue === 'lead_value') {
                yAxis = 'sum_value';
            } else if (config.yAxisMetric === 'avg' && config.yAxisValue === 'lead_value') {
                yAxis = 'avg_value';
            } else if (config.yAxisMetric === 'show') {
                yAxis = 'show_' + config.yAxisValue;
            } else {
                yAxis = config.yAxisMetric + '_' + config.yAxisValue;
            }
        } else {
            yAxis = config.yAxis || (config.aggregation === 'sum' ? 'sum_value' : config.aggregation === 'avg' ? 'avg_value' : 'count');
        }

        // Normalize detailed leads payload (list API returns { data, meta })
        const detailedLeads = detailedData ? (detailedData.data || detailedData.leads || []) : [];

        const isNotesMetric = yAxis === 'count_notes';
        const isShowMetric = yAxis.startsWith('show_');

        // Helper to decide if a note is present
        const hasNote = (note) => {
            if (note === undefined || note === null) return false;
            const trimmed = String(note).trim();
            if (!trimmed) return false;
            if (trimmed === '-') return false;
            return true;
        };

        // STEP 3: Merge all custom_ handling into one block inside getChartData.
        if (xAxis.startsWith('custom_')) {
            const fieldName = xAxis.replace('custom_', '');
            // 1. Use summaryData.custom_fields[fieldName] if exists
            if (summaryData.custom_fields && summaryData.custom_fields[fieldName]) {
                const fieldData = summaryData.custom_fields[fieldName];
                let exclude = [];
                if (config.filters && config.filters.custom && Array.isArray(config.filters.custom.exclude)) {
                    exclude = config.filters.custom.exclude;
                }
                // Filter out excluded values
                const filteredData = fieldData.filter(d => {
                    const label = d.value === undefined || d.value === null || d.value === '' ? '(empty)' : d.value;
                    return !exclude.includes(label);
                });
                labels = filteredData.map(d => d.value === undefined || d.value === null || d.value === '' ? '(empty)' : d.value);
                if (yAxis === 'count' || yAxis === 'count_custom_' + fieldName) {
                    values = filteredData.map(d => d.count);
                } else if (yAxis === 'sum_custom_' + fieldName && filteredData[0] && 'sum' in filteredData[0]) {
                    values = filteredData.map(d => d.sum);
                } else if (yAxis === 'avg_custom_' + fieldName && filteredData[0] && 'sum' in filteredData[0]) {
                    values = filteredData.map(d => d.count > 0 ? d.sum / d.count : 0);
                } else {
                    values = filteredData.map(d => d.count);
                }
                return { labels, values };
            }
            // 2. ONLY fallback to detailedLeads if summary missing
            if (detailedLeads.length) {
                const dataMap = {};
                let exclude = [];
                if (config.filters && config.filters.custom && Array.isArray(config.filters.custom.exclude)) {
                    exclude = config.filters.custom.exclude;
                }
                detailedLeads.forEach(lead => {
                    let customData = {};
                    try {
                        customData = lead.custom_data ? JSON.parse(lead.custom_data) : {};
                    } catch (err) {
                        customData = {};
                    }
                    const rawVal = customData[fieldName];
                    const key = (rawVal === undefined || rawVal === null || rawVal === '') ? '(empty)' : rawVal;
                    const numVal = Number(rawVal);

                    if (!exclude.includes(key)) {
                        if (!dataMap[key]) {
                            dataMap[key] = { count: 0, sum: 0 };
                        }
                        dataMap[key].count++;
                        if (!Number.isNaN(numVal)) dataMap[key].sum += numVal;
                    }
                });

                labels = Object.keys(dataMap);
                values = labels.map(label => {
                    const bucket = dataMap[label];
                    if (yAxis === 'sum_custom_' + fieldName) return bucket.sum;
                    if (yAxis === 'avg_custom_' + fieldName) return bucket.count > 0 ? bucket.sum / bucket.count : 0;
                    return bucket.count;
                });
                return { labels, values };
            }
            // Handle stage_id and source dimension
            // Apply data filters FIRST before processing
            if (config.filters) {
                summaryData = this.applyDataFilters(summaryData, config);
            }

            if (xAxis === 'stage_id') {
                // Use filtered stages
                const stages = summaryData.by_stage.map(s => s.stage_id);
                labels = stages.map(s => s.charAt(0).toUpperCase() + s.slice(1));
                values = stages.map(stage => {
                    const found = summaryData.by_stage.find(s => s.stage_id === stage);
                    return found ? parseInt(found.count || 0, 10) : 0;
                });

                comboCounts = stages.map(stage => {
                    const found = summaryData.by_stage.find(s => s.stage_id === stage);
                    return found ? parseInt(found.count || 0, 10) : 0;
                });
                comboValues = stages.map(stage => {
                    const found = summaryData.by_stage.find(s => s.stage_id === stage);
                    return found ? parseFloat(found.total_value || 0) : 0;
                });
            } else if (xAxis === 'lead_value') {
                labels = ['Total'];
                const stageTotals = summaryData.by_stage.reduce((agg, s) => {
                    agg.value += parseFloat(s.total_value || 0);
                    agg.count += parseInt(s.count || 0, 10);
                    return agg;
                }, { value: 0, count: 0 });

                const sourceTotals = summaryData.by_source.reduce((agg, s) => {
                    agg.value += parseFloat(s.total_value || 0);
                    agg.count += parseInt(s.count || 0, 10);
                    return agg;
                }, { value: 0, count: 0 });

                const totals = (sourceTotals.value > stageTotals.value) ? sourceTotals : stageTotals;

                if (yAxis === 'avg_value') {
                    values = [totals.count > 0 ? totals.value / totals.count : 0];
                } else {
                    values = [totals.value];
                }
            } else if (xAxis === 'source') {
                // Use filtered sources
                const topSources = summaryData.by_source.slice(0, 8);
                labels = topSources.map(s => s.source || 'Unknown');
                const counts = topSources.map(s => parseInt(s.count || 0, 10));
                const totals = topSources.map(s => parseFloat(s.total_value || 0));

                secondaryValues = totals;
                comboCounts = counts;
                comboValues = totals;

                if (yAxis === 'sum_value') {
                    values = totals;
                } else if (yAxis === 'avg_value') {
                    values = counts.map((c, idx) => c > 0 ? totals[idx] / c : 0);
                } else {
                    values = counts;
                }
            }
            // The above block is misplaced and should be inside a detailed leads handler, not here. Remove stray code.
        }

        // Handle stage_id dimension
        // Apply data filters FIRST before processing
        if (config.filters) {
            summaryData = this.applyDataFilters(summaryData, config);
        }

        if (xAxis === 'stage_id') {
            const stages = ['new', 'contacted', 'qualified', 'won', 'lost'];
            labels = stages.map(s => s.charAt(0).toUpperCase() + s.slice(1));
            values = stages.map(stage => {
                const found = summaryData.by_stage.find(s => s.stage_id === stage);
                if (!found) return 0;
                const totalValue = parseFloat(found.total_value || 0);
                const count = parseInt(found.count || 0, 10);

                if (yAxis === 'sum_value') return totalValue;
                if (yAxis === 'avg_value') return count > 0 ? totalValue / count : 0;
                return count;
            });

            // Always expose counts and totals for combo charts
            comboCounts = stages.map(stage => {
                const found = summaryData.by_stage.find(s => s.stage_id === stage);
                return found ? parseInt(found.count || 0, 10) : 0;
            });
            comboValues = stages.map(stage => {
                const found = summaryData.by_stage.find(s => s.stage_id === stage);
                return found ? parseFloat(found.total_value || 0) : 0;
            });
        } else if (xAxis === 'lead_value') {
            labels = ['Total'];
            const stageTotals = summaryData.by_stage.reduce((agg, s) => {
                agg.value += parseFloat(s.total_value || 0);
                agg.count += parseInt(s.count || 0, 10);
                return agg;
            }, { value: 0, count: 0 });

            const sourceTotals = summaryData.by_source.reduce((agg, s) => {
                agg.value += parseFloat(s.total_value || 0);
                agg.count += parseInt(s.count || 0, 10);
                return agg;
            }, { value: 0, count: 0 });

            // Prefer the larger aggregate in case some lists are empty due to stage mismatch
            const totals = (sourceTotals.value > stageTotals.value) ? sourceTotals : stageTotals;

            if (yAxis === 'avg_value') {
                values = [totals.count > 0 ? totals.value / totals.count : 0];
            } else {
                values = [totals.value];
            }
        } else if (xAxis === 'source') {
            const topSources = summaryData.by_source.slice(0, 8);
            labels = topSources.map(s => s.source || 'Unknown');
            const counts = topSources.map(s => parseInt(s.count || 0, 10));
            const totals = topSources.map(s => parseFloat(s.total_value || 0));

            secondaryValues = totals;
            comboCounts = counts;
            comboValues = totals;

            if (yAxis === 'sum_value') {
                values = totals;
            } else if (yAxis === 'avg_value') {
                values = counts.map((c, idx) => c > 0 ? totals[idx] / c : 0);
            } else {
                values = counts;
            }
        }

        // Apply sorting if specified
        const chartSort = config.chartSort || config.chart_sort || 'default';
        if (chartSort !== 'default' && labels.length > 0) {
            let list = labels.map((label, i) => ({
                label,
                value: values[i] || 0,
                secondary: (secondaryValues && secondaryValues[i]) || 0,
                comboCount: (comboCounts && comboCounts[i]) || 0,
                comboValue: (comboValues && comboValues[i]) || 0
            }));

            list.sort((a, b) => {
                if (chartSort === 'value_desc') return b.value - a.value;
                if (chartSort === 'value_asc') return a.value - b.value;
                if (chartSort === 'label_desc') return b.label.localeCompare(a.label);
                if (chartSort === 'label_asc') return a.label.localeCompare(b.label);
                return 0;
            });

            labels = list.map(item => item.label);
            values = list.map(item => item.value);
            if (secondaryValues) secondaryValues = list.map(item => item.secondary);
            if (comboCounts) comboCounts = list.map(item => item.comboCount);
            if (comboValues) comboValues = list.map(item => item.comboValue);
        }

        return { labels, values, secondaryValues, comboCounts, comboValues };
    },

    getYAxisLabel(config) {
        // Handle new two-part format (yAxisMetric + yAxisValue)
        if (config.yAxisMetric && config.yAxisValue) {
            const metricLabels = {
                'count': 'Count of',
                'sum': 'Sum of',
                'avg': 'Average of',
                'show': ''
            };

            const valueLabels = {
                'leads': 'Leads',
                'lead_value': 'Lead Value',
                'notes': 'Notes',
                'email': 'Email',
                'phone': 'Phone',
                'company': 'Company',
                'title': 'Title',
                'source': 'Source'
            };

            // Handle custom fields
            let valueLabel = valueLabels[config.yAxisValue];
            if (!valueLabel && config.yAxisValue && config.yAxisValue.startsWith('custom_')) {
                const fieldId = config.yAxisValue.replace('custom_', '');
                // Safely check if customFields exists
                if (this.customFields && Array.isArray(this.customFields)) {
                    const field = this.customFields.find(f => f.id == fieldId);
                    valueLabel = field ? field.field_name : 'Custom Field';
                } else {
                    valueLabel = 'Custom Field';
                }
            }

            const metricLabel = metricLabels[config.yAxisMetric] || config.yAxisMetric;
            valueLabel = valueLabel || config.yAxisValue;

            return metricLabel ? `${metricLabel} ${valueLabel}` : valueLabel;
        }

        // Legacy format fallback
        return this.getAxisLabel(config.yAxis || 'count');
    },

    getAxisLabel(axis) {
        const labels = {
            'stage_id': 'Stage',
            'source': 'Source',
            'lead_name': 'Lead Name',
            'company': 'Company',
            'email': 'Email',
            'phone': 'Phone',
            'title': 'Title',
            'owner_email': 'Owner',
            'count': 'Lead Count',
            'count_notes': 'Count of Notes',
            'sum_value': 'Total Value',
            'avg_value': 'Average Value'
        };

        if (!axis) return 'Unknown';

        if (axis.startsWith('count_custom_')) {
            return 'Count of ' + axis.replace('count_custom_', '').replace('_', ' ');
        }
        if (axis.startsWith('sum_custom_')) {
            return 'Sum of ' + axis.replace('sum_custom_', '').replace('_', ' ');
        }
        if (axis.startsWith('avg_custom_')) {
            return 'Average of ' + axis.replace('avg_custom_', '').replace('_', ' ');
        }
        if (axis.startsWith('custom_')) {
            return axis.replace('custom_', '').replace('_', ' ');
        }

        return labels[axis] || axis;
    },

    toggleChartSort(chartId, column) {
        console.log('toggleChartSort called:', chartId, column);
        const chart = this.charts.find(c => c.id == chartId);
        if (!chart) {
            console.error('Chart not found for sorting:', chartId);
            return;
        }

        let currentSort = chart.chartSort || chart.chart_sort || 'default';
        let newSort = 'default';

        if (column === 'label') {
            if (currentSort === 'label_asc') newSort = 'label_desc';
            else newSort = 'label_asc';
        } else if (column === 'value') {
            if (currentSort === 'value_desc') newSort = 'value_asc';
            else newSort = 'value_desc';
        }

        console.log('New sort state:', newSort);

        // Update local state
        chart.chartSort = newSort;
        chart.chart_sort = newSort;

        // Re-render chart
        // We pass a clone to ensure reactivity if needed, but direct mutation works here provided we call renderCustomChart
        this.renderCustomChart(chart, this.reportData).then(() => {
            console.log('Chart re-rendered');
            // Optional: Save sort state to DB silently so it persists on reload
            this.api('/reports/save_chart.php', 'POST', {
                id: chart.id,
                title: chart.title,
                chart_type: chart.type || chart.chart_type,
                data_field: chart.yAxisValue || chart.dataField,
                aggregation: chart.yAxisMetric || chart.aggregation,
                x_axis: chart.xAxis,
                color_scheme: chart.colorScheme,
                chart_sort: newSort,
                show_total: chart.showTotal,
                global: chart.global
            }).catch(e => console.error('Failed to save sort state', e));
        });
    },

    // Data filtering for include/exclude functionality
    applyDataFilters(data, config) {
        if (!config.filters) return data;
        
        let filtered = { ...data };
        
        // Apply source filters
        if (config.filters.sources) {
            const { include, exclude } = config.filters.sources;
            if (include && include.length > 0) {
                filtered.by_source = filtered.by_source.filter(s => include.includes(s.source));
            }
            if (exclude && exclude.length > 0) {
                filtered.by_source = filtered.by_source.filter(s => !exclude.includes(s.source));
            }
        }
        
        // Apply stage filters
        if (config.filters.stages) {
            const { include, exclude } = config.filters.stages;
            console.log('Applying stage filters:', { include, exclude, originalStages: filtered.by_stage });
            if (include && include.length > 0) {
                filtered.by_stage = filtered.by_stage.filter(s => include.includes(s.stage_id || '(null)'));
            }
            if (exclude && exclude.length > 0) {
                filtered.by_stage = filtered.by_stage.filter(s => !exclude.includes(s.stage_id || '(null)'));
            }
            console.log('Filtered stages result:', filtered.by_stage);
        }
        
        // Apply custom field filters (for detailed data)
        if (config.filters.custom && config.xAxis && config.xAxis.startsWith('custom_')) {
            // Custom filtering will be handled in getChartData for detailed dimensions
            const { exclude } = config.filters.custom;
            if (exclude && exclude.length > 0) {
                // Mark that we need to filter custom data
                filtered._customFilter = { field: config.xAxis, exclude };
            }
        }
        
        return filtered;
    },

    // Show filter modal for data selection
    showFilterModal(chartId, filterType) {
        const chart = this.charts.find(c => c.id == chartId);
        if (!chart) return;
        
        console.log('showFilterModal called:', { chartId, filterType, chart });
        
        let items = [];
        
        if (filterType === 'sources') {
            items = this.reportData.by_source.map(item => ({
                id: item.source || '(null)',
                name: item.source || '(null)',
                count: item.count
            }));
        } else if (filterType === 'stages') {
            items = this.reportData.by_stage.map(item => ({
                id: item.stage_id || '(null)',
                name: item.stage_name || item.stage_id || '(null)',
                count: item.count
            }));
        } else if (filterType === 'custom') {
            // Get chart data to extract unique values
            const chartData = this.getChartData(chart, this.reportData);
            items = chartData.labels.map((label, idx) => ({
                id: label || '(null)',
                name: label || '(null)',
                count: chartData.values[idx] || 0
            }));
        }
        
        console.log('Filter items:', items);
        
        const currentFilters = chart.filters?.[filterType] || { include: [], exclude: [] };
        
        const modal = `
            <div class="filter-modal" id="filterModal">
                <div class="filter-content">
                    <h3>Filter ${filterType === 'sources' ? 'Sources' : filterType === 'stages' ? 'Stages' : 'Data'}</h3>
                    <div class="filter-actions">
                        <button onclick="App.selectAllFilters()">Select All</button>
                        <button onclick="App.clearAllFilters()">Clear All</button>
                    </div>
                    <div class="filter-list" id="filterList">
                        ${items.map(item => `
                            <div class="filter-item" data-value="${item.id}">
                                <i class="fas fa-grip-vertical drag-handle"></i>
                                <label>
                                    <input type="checkbox" 
                                           value="${item.id}" 
                                           ${!currentFilters.exclude.includes(item.id) ? 'checked' : ''}>
                                    <span>${item.name} (${item.count})</span>
                                </label>
                            </div>
                        `).join('')}
                    </div>
                    <div class="filter-buttons">
                        <button onclick="App.applyFilters('${chartId}', '${filterType}')">Apply</button>
                        <button onclick="App.closeFilterModal()">Cancel</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modal);
        
        // Make filter list sortable with drag & drop
        if (window.Sortable) {
            new Sortable(document.getElementById('filterList'), {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost'
            });
        }
    },

    applyFilters(chartId, filterType) {
        const chart = this.charts.find(c => c.id == chartId);
        const checkboxes = document.querySelectorAll('#filterModal input[type="checkbox"]');
        
        if (!chart.filters) chart.filters = {};
        if (!chart.filters[filterType]) chart.filters[filterType] = { include: [], exclude: [] };
        
        const selected = [];
        const unselected = [];
        
        checkboxes.forEach(cb => {
            if (cb.checked) {
                selected.push(cb.value);
            } else {
                unselected.push(cb.value);
            }
        });
        
        chart.filters[filterType].exclude = unselected;
        chart.filters[filterType].include = [];
        
        this.closeFilterModal();
        this.renderCustomChart(chart, this.reportData);
        
        // Save filters to database
        this.api('/reports/save_chart.php', 'POST', {
            id: chart.id,
            title: chart.title,
            chart_type: chart.type || chart.chart_type,
            data_field: chart.yAxisValue || chart.dataField,
            aggregation: chart.yAxisMetric || chart.aggregation,
            x_axis: chart.xAxis,
            color_scheme: chart.colorScheme,
            chart_sort: chart.chartSort,
            show_total: chart.showTotal,
            filters: JSON.stringify(chart.filters)
        }).catch(e => console.error('Failed to save filters', e));
    },

    closeFilterModal() {
        const modal = document.getElementById('filterModal');
        if (modal) modal.remove();
    },

    // Funnel sorting with drag and drop
    initFunnelSorting(chartId) {
        const chart = this.charts.find(c => c.id == chartId);
        if (!chart || chart.type !== 'funnel') return;
        
        const container = document.querySelector(`#chart-${chartId} .funnel-items`);
        if (!container) return;
        
        // Add sortable functionality
        new Sortable(container, {
            animation: 150,
            onEnd: (evt) => {
                const newOrder = Array.from(container.children).map(item => item.dataset.value);
                chart.funnelOrder = newOrder;
                this.renderCustomChart(chart, this.reportData);
            }
        });
    },

    sortFunnel(chartId, sortType) {
        const chart = this.charts.find(c => c.id == chartId);
        if (!chart) return;
        
        chart.funnelSort = sortType;
        this.renderCustomChart(chart, this.reportData);
    },

    // Helper functions for filter UI
    hasActiveFilters(config, filterType) {
        if (!config.filters) return false;
        const filters = config.filters[filterType];
        return filters && filters.exclude && filters.exclude.length > 0;
    },

    getFilterCount(config, filterType) {
        if (!config.filters || !config.filters[filterType]) return '';
        const count = config.filters[filterType].exclude ? config.filters[filterType].exclude.length : 0;
        return count > 0 ? `(${count})` : '';
    },

    // Helper functions for new features
    selectAllFilters() {
        const checkboxes = document.querySelectorAll('#filterModal input[type="checkbox"]');
        checkboxes.forEach(cb => cb.checked = true);
    },

    clearAllFilters() {
        const checkboxes = document.querySelectorAll('#filterModal input[type="checkbox"]');
        checkboxes.forEach(cb => cb.checked = false);
    },

    changeChartType(chartId, newType) {
        const chart = this.charts.find(c => c.id == chartId);
        if (!chart) return;
        
        chart.type = newType;
        chart.chart_type = newType;
        
        this.renderCustomChart(chart, this.reportData);
        
        // Show/hide funnel controls
        const controls = document.querySelector(`[data-chart-id="${chartId}"] .funnel-sort-controls`);
        if (controls) {
            controls.style.display = newType === 'funnel' ? 'block' : 'none';
        }
    },

    // New user context functions
    getUser() {
        // Example: get user from localStorage or global context
        // You may need to adjust this to your actual user logic
        try {
            const user = JSON.parse(localStorage.getItem('crm_user'));
            return user || {};
        } catch (e) {
            return {};
        }
    },

    // Add missing getColorScheme function
    getColorScheme(scheme = 'default') {
        const palettes = {
            default: [
                '#2563eb', // blue
                '#10b981', // emerald
                '#f59e42', // amber
                '#ef4444', // red
                '#6366f1', // indigo
                '#f43f5e', // pink
                '#14b8a6', // teal
                '#a3e635', // lime
                '#eab308', // yellow
                '#64748b'  // slate
            ],
            blue: ['#2563eb', '#3b82f6', '#60a5fa', '#93c5fd'],
            emerald: ['#10b981', '#34d399', '#6ee7b7', '#a7f3d0'],
            amber: ['#f59e42', '#fbbf24', '#fde68a', '#fef3c7'],
            red: ['#ef4444', '#f87171', '#fca5a5', '#fee2e2'],
            indigo: ['#6366f1', '#818cf8', '#a5b4fc', '#c7d2fe'],
            pink: ['#f43f5e', '#fb7185', '#fda4af', '#fbcfe8'],
            teal: ['#14b8a6', '#2dd4bf', '#5eead4', '#99f6e4'],
            lime: ['#a3e635', '#bef264', '#d9f99d', '#ecfccb'],
            yellow: ['#eab308', '#fde047', '#fef08a', '#fef9c3'],
            slate: ['#64748b', '#94a3b8', '#cbd5e1', '#f1f5f9']
        };
        return palettes[scheme] || palettes['default'];
    }
});
