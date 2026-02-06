/* =========================================================
   POWER BI STYLE ANALYTICS – FULLY FIXED DROP-IN VERSION
   ========================================================= */

App.renderReports = function (data) {
    console.log('renderReports:', data);

    const container = document.getElementById('appContent');
    if (!container) return;

    // Safety guards
    data.by_stage = data.by_stage || [];
    data.by_source = data.by_source || [];
    data.custom_fields = data.custom_fields || {};

    this.getSavedCharts().then(charts => {
        this.charts = charts || [];

        const totalValue = data.by_stage.reduce(
            (sum, s) => sum + Number(s.total_value || 0),
            0
        );

        const formattedTotalValue = totalValue.toLocaleString('en-US', {
            style: 'currency',
            currency: 'USD',
            maximumFractionDigits: 0
        });

        const topSource = data.by_source.length
            ? `${data.by_source[0].source} (${data.by_source[0].count})`
            : 'N/A';

        container.innerHTML = `
            <div class="space-y-6">

                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-semibold">Analytics Dashboard</h2>
                    <button onclick="App.openChartBuilder()" 
                        class="px-4 py-2 bg-blue-600 text-white rounded">
                        <i data-lucide="plus"></i> Add Chart
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white p-6 border rounded">
                        <p class="text-gray-500">Total Leads</p>
                        <p class="text-3xl font-bold">${data.total_leads}</p>
                    </div>

                    <div class="bg-white p-6 border rounded">
                        <p class="text-gray-500">Pipeline Value</p>
                        <p class="text-3xl font-bold">${formattedTotalValue}</p>
                    </div>

                    <div class="bg-white p-6 border rounded">
                        <p class="text-gray-500">Top Source</p>
                        <p class="text-3xl font-bold truncate">${topSource}</p>
                    </div>
                </div>

                <div id="customChartsGrid"
                    class="grid grid-cols-1 md:grid-cols-2 gap-6"></div>

                ${this.charts.length === 0 ? `
                    <div class="text-center py-12 border-2 border-dashed rounded">
                        <p class="text-gray-500 mb-4">No custom charts yet</p>
                        <button onclick="App.openChartBuilder()"
                            class="px-4 py-2 bg-blue-100 text-blue-700 rounded">
                            Create First Chart
                        </button>
                    </div>
                ` : ''}
            </div>
        `;

        lucide.createIcons();

        if (this.charts.length) {
            this.renderAllCustomCharts(this.charts, data);
        }
    });
};


/* =======================
   CHART CRUD
   ======================= */

App.getSavedCharts = async function () {
    try {
        return await this.api('/reports/get_charts.php') || [];
    } catch {
        return [];
    }
};

App.saveChartToDB = async function (chart) {
    const res = await this.api('/reports/save_chart.php', 'POST', chart);
    return res && res.success;
};

App.deleteChart = async function (id) {
    if (!confirm('Delete this chart?')) return;
    await this.api('/reports/delete_chart.php', 'POST', { id });
    this.loadReports();
};


/* =======================
   CHART BUILDER
   ======================= */

App.openChartBuilder = function (chartId = null) {
    const modal = document.getElementById('chartBuilderModal');
    const form = document.getElementById('chartBuilderForm');

    form.reset();
    document.getElementById('chartEditId').value = '';

    if (chartId) {
        const chart = this.charts.find(c => c.id == chartId);
        if (!chart) return;

        document.getElementById('chartEditId').value = chart.id;
        document.getElementById('chartTitle').value = chart.title;
        document.getElementById('chartType').value = chart.chart_type;
        document.getElementById('chartYAxisValue').value = chart.data_field;
        document.getElementById('chartYAxisMetric').value = chart.aggregation;
    }

    modal.classList.remove('hidden');
};

App.closeChartBuilder = function () {
    document.getElementById('chartBuilderModal').classList.add('hidden');
};

App.saveChartFromBuilder = async function (e) {
    e.preventDefault();

    const payload = {
        id: document.getElementById('chartEditId').value || undefined,
        title: document.getElementById('chartTitle').value,
        chart_type: document.getElementById('chartType').value,
        data_field: document.getElementById('chartYAxisValue').value,
        aggregation: document.getElementById('chartYAxisMetric').value
    };

    const ok = await this.saveChartToDB(payload);
    if (ok) {
        this.closeChartBuilder();
        this.loadReports();
    }
};


/* =======================
   RENDERING
   ======================= */

App.renderAllCustomCharts = async function (charts, summary) {
    const grid = document.getElementById('customChartsGrid');
    grid.innerHTML = '';
    for (const c of charts) {
        this.renderCustomChart(c, summary);
    }
};

App.renderCustomChart = function (cfg, summary) {
    const grid = document.getElementById('customChartsGrid');
    const id = `chart_${cfg.id}`;

    grid.insertAdjacentHTML('beforeend', `
        <div class="bg-white p-4 border rounded">
            <div class="flex justify-between mb-2">
                <h4 class="font-semibold">${cfg.title}</h4>
                <div>
                    <button onclick="App.openChartBuilder('${cfg.id}')">
                        <i data-lucide="edit-2"></i>
                    </button>
                    <button onclick="App.deleteChart('${cfg.id}')">
                        <i data-lucide="trash-2"></i>
                    </button>
                </div>
            </div>
            <div class="h-64">
                <canvas id="${id}"></canvas>
            </div>
        </div>
    `);

    lucide.createIcons();

    const { labels, values } = this.getChartData(cfg, summary);
    const ctx = document.getElementById(id);

    new Chart(ctx, {
        type: cfg.chart_type === 'area' ? 'line' : cfg.chart_type,
        data: {
            labels,
            datasets: [{
                data: values,
                label: cfg.title,
                fill: cfg.chart_type === 'area',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: cfg.chart_type === 'pie' } },
            scales: cfg.chart_type !== 'pie' ? {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v =>
                            cfg.aggregation === 'sum'
                                ? '$' + v.toLocaleString()
                                : v
                    }
                }
            } : {}
        }
    });
};


/* =======================
   DATA MAPPING
   ======================= */

App.getChartData = function (cfg, summary) {
    let labels = [], values = [];

    if (cfg.data_field === 'stage_id') {
        summary.by_stage.forEach(s => {
            labels.push(s.stage_id);
            values.push(
                cfg.aggregation === 'sum'
                    ? Number(s.total_value || 0)
                    : Number(s.count || 0)
            );
        });
    }

    if (cfg.data_field === 'source') {
        summary.by_source.forEach(s => {
            labels.push(s.source);
            values.push(Number(s.count || 0));
        });
    }

    if (cfg.data_field.startsWith('custom_')) {
        const f = cfg.data_field.replace('custom_', '');
        (summary.custom_fields[f] || []).forEach(r => {
            labels.push(r.value || '(empty)');
            values.push(Number(r.count || 0));
        });
    }

    return { labels, values };
};
