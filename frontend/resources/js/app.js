import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';

// Components & Page Modules
import fastApiHeaderTelemetry from './components/header-telemetry';
import initDashboard from './pages/dashboard';
import analysisDetail from './pages/analyses-show';
import analysisForm from './pages/analyses-create';
import analysesIndex from './pages/analyses-index';
import liveClassifier from './pages/predict';
import scraperTelemetry from './pages/scraper-status';

// Register Alpine data components BEFORE Alpine.start()
Alpine.data('fastApiHeaderTelemetry', fastApiHeaderTelemetry);
Alpine.data('analysisDetail', analysisDetail);
Alpine.data('analysisForm', analysisForm);
Alpine.data('analysesIndex', analysesIndex);
Alpine.data('liveClassifier', liveClassifier);
Alpine.data('scraperTelemetry', scraperTelemetry);

// Mount globally for backwards compatibility and direct Blade/Alpine access
window.Alpine = Alpine;
window.ApexCharts = ApexCharts;

// Start Alpine
Alpine.start();

// Initialize non-Alpine DOM modules on page load
document.addEventListener('DOMContentLoaded', () => {
    initDashboard();
});
