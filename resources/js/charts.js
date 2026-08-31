import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

function getCssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

function formatBs(value) {
    return 'Bs ' + new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
}

const defaults = Chart.defaults;
defaults.font.family = '"Montserrat", system-ui, sans-serif';
defaults.font.size = 12;
defaults.color = getCssVar('--muted') || '#6f6670';
defaults.plugins.tooltip.backgroundColor = getCssVar('--fg') || '#2a232b';
defaults.plugins.tooltip.titleFont = { weight: '600', size: 13 };
defaults.plugins.tooltip.bodyFont = { size: 13 };
defaults.plugins.tooltip.padding = { x: 12, y: 8 };
defaults.plugins.tooltip.cornerRadius = 6;
defaults.plugins.tooltip.displayColors = false;

function parseConfig(el) {
    try {
        return JSON.parse(el.getAttribute('data-chart'));
    } catch {
        return null;
    }
}

const brand = getCssVar('--brand') || '#ff8fda';
const brandSoft = getCssVar('--brand-soft') || '#ffddf4';
const muted = getCssVar('--muted') || '#6f6670';
const border = getCssVar('--border') || '#ece4ec';

function buildBarDataset(rawData, isHorizontal) {
    const colors = rawData.map((v) => (v > 0 ? brand : border));

    return {
        data: rawData,
        backgroundColor: colors,
        borderRadius: 6,
        borderSkipped: false,
        maxBarThickness: isHorizontal ? 22 : 48,
    };
}

function createChart(el, cfg) {
    const isHorizontal = cfg.indexAxis === 'y';
    const labels = cfg.labels || [];
    const data = cfg.data || [];
    const maxVal = Math.max(...data, 1);

    const scaleOptions = isHorizontal
        ? {
              x: {
                  beginAtZero: true,
                  max: Math.ceil(maxVal * 1.15),
                  grid: { color: border, drawBorder: false },
                  ticks: {
                      callback: (v) => (v >= 1000 ? (v / 1000).toFixed(1) + 'k' : v),
                      font: { size: 11 },
                  },
              },
              y: {
                  grid: { display: false },
                  ticks: { font: { size: 12, weight: '600' } },
              },
          }
        : {
              x: {
                  grid: { display: false },
                  ticks: { font: { size: 11, weight: '600' } },
              },
              y: {
                  beginAtZero: true,
                  max: Math.ceil(maxVal * 1.15),
                  grid: { color: border, drawBorder: false },
                  ticks: {
                      callback: (v) => (v >= 1000 ? (v / 1000).toFixed(1) + 'k' : v),
                      font: { size: 11 },
                  },
              },
          };

    const tooltipCallbacks = {
        title: () => '',
        label: (ctx) => {
            const label = labels[ctx.dataIndex] || '';
            return label + ': ' + formatBs(ctx.parsed[isHorizontal ? 'x' : 'y']);
        },
    };

    new Chart(el, {
        type: 'bar',
        data: {
            labels,
            datasets: [buildBarDataset(data, isHorizontal)],
        },
        options: {
            indexAxis: cfg.indexAxis || 'x',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: tooltipCallbacks,
                },
            },
            scales: scaleOptions,
            animation: {
                duration: 600,
                easing: 'easeOutQuart',
            },
        },
    });
}

export function initCharts() {
    document.querySelectorAll('[data-chart]').forEach((el) => {
        const cfg = parseConfig(el);
        if (cfg) createChart(el, cfg);
    });
}
