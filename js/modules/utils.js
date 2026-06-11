/**
 * Small utilities shared across modules.
 */

const App = window.App || {};

function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

App.debounce = debounce;
App.handleSearch = debounce(function () {
    this.loadLeads(0);
}, 300);

window.debounce = debounce;
window.App = App;
