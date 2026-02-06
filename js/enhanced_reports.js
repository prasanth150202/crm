// Enhanced renderReports with dynamic chart support
// Replace the existing renderReports function (around line 977) with this:

renderReports(data) {
    const container = document.getElementById('appContent');
    const chartConfig = this.getChartConfig();

    const totalValue = data.by_stage.reduce((sum, s) => sum + parseFloat(s.total_value || 0), 0);
    const formattedTotalValue = totalValue.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });
    const topSource = data.by_source.length > 0 ? data.by_source[0].source + ` (${data.by_source[0].count})` : 'N/A';

    container.innerHTML = `
        <div class="space-y-6">
            <!-- Header with Configure Button -->
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-900">Analytics Dashboard</h2>
                <button onclick="App.openChartConfig()" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50">
                    <i data-lucide="settings" class="h-4 w-4 mr-2"></i>
                    Configure Charts
                </button>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <p class="text-sm font-medium text-gray-500">Total Leads</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">${data.total_leads}</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <p class="text-sm font-medium text-gray-500">Pipeline Value</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">${formattedTotalValue}</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <p class="text-sm font-medium text-gray-500">Top Source</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 truncate">${topSource}</p>
                </div>
            </div>

            <!-- Charts Grid -->
            <div id="chartsGrid" class="grid grid-cols-1 md:grid-cols-2 gap-6"></div>
        </div>
    `;

    lucide.createIcons();
    this.renderDynamicCharts(data, chartConfig);
},

renderDynamicCharts(data, chartConfig) {
    const chartsGrid = document.getElementById('chartsGrid');
    let chartIndex = 0;

    // Render Stage Chart if enabled
    if (chartConfig.stage) {
        const chartHtml = `
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Leads by Stage</h4>
                <div class="h-64"><canvas id="chart_${chartIndex}"></canvas></div>
            </div>
        `;
        chartsGrid.insertAdjacentHTML('beforeend', chartHtml);

        const ctx = document.getElementById(`chart_${chartIndex}`).getContext('2d');
        const stages = ['new', 'contacted', 'qualified', 'won', 'lost'];
        const stageCounts = stages.map(label => {
            const found = data.by_stage.find(s => s.stage_id === label);
            return found ? found.count : 0;
        });

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: stages.map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                datasets: [{
                    data: stageCounts,
                    backgroundColor: ['#60A5FA', '#FCD34D', '#34D399', '#818CF8', '#F87171']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right' } }
            }
        });
        chartIndex++;
    }

    // Render Source Chart if enabled
    if (chartConfig.source) {
        const chartHtml = `
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Leads by Source</h4>
                <div class="h-64"><canvas id="chart_${chartIndex}"></canvas></div>
            </div>
        `;
        chartsGrid.insertAdjacentHTML('beforeend', chartHtml);

        const ctx = document.getElementById(`chart_${chartIndex}`).getContext('2d');
        const topSources = data.by_source.slice(0, 6);

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: topSources.map(s => s.source),
                datasets: [{
                    label: '# of Leads',
                    data: topSources.map(s => s.count),
                    backgroundColor: '#4F46E5',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
        chartIndex++;
    }

    // Render Custom Field Charts
    if (data.custom_fields) {
        const customFields = this.getCustomFields();

        for (const fieldName in data.custom_fields) {
            if (chartConfig.custom_fields && chartConfig.custom_fields.includes(fieldName)) {
                const fieldData = data.custom_fields[fieldName];

                // Only create charts for fields with meaningful data
                if (fieldData.length > 0) {
                    const chartHtml = `
                        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                            <h4 class="text-lg font-medium text-gray-900 mb-4">${fieldName}</h4>
                            <div class="h-64"><canvas id="chart_${chartIndex}"></canvas></div>
                        </div>
                    `;
                    chartsGrid.insertAdjacentHTML('beforeend', chartHtml);

                    const ctx = document.getElementById(`chart_${chartIndex}`).getContext('2d');

                    new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: fieldData.map(d => d.value || '(empty)'),
                            datasets: [{
                                data: fieldData.map(d => d.count),
                                backgroundColor: [
                                    '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
                                    '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'right' } }
                        }
                    });
                    chartIndex++;
                }
            }
        }
    }
},
