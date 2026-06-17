const App = window.App || {};
if (window.AppData) {
    Object.assign(App, window.AppData);
}

// Export for module usage
export default App;

Object.assign(App, {
    // --- Global State ---
    state: {
        dateRange: "all",
        custom: { start: null, end: null },
    },

    // --- Modal Management ---
    openModal(modalId) {
        console.log(`[CORE] openModal: ${modalId}`);
        this.closeAllModals();
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove("hidden");
            modal.style.display = "block";
            modal.style.zIndex = "2000";

            modal.setAttribute("aria-modal", "true");
            modal.setAttribute("role", "dialog");

            // Force a reflow to ensure transitions work (if any)
            void modal.offsetWidth;
        } else {
            console.warn("[CORE] Modal not found:", modalId);
        }
    },

    closeModal(modalId) {
        if (!modalId) {
            this.closeAllModals();
            return;
        }
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add("hidden");
            modal.style.display = "none";
            modal.removeAttribute("aria-modal");
            modal.removeAttribute("role");
            modal.style.zIndex = "";
        }
    },

    closeAllModals() {
        console.log("[CORE] closeAllModals");
        const modalIds = [
            "createLeadModal",
            "leadDetailPanel",
            "importModal",
            "manageColumnsModal",
            "deleteFieldModal",
            "chartConfigModal",
            "chartBuilderModal",
            "userModal",
            "changePasswordModal",
            "createMeetingModal",
            "dynamicModal",
        ];
        modalIds.forEach((id) => {
            const el = document.getElementById(id);
            if (el) {
                el.classList.add("hidden");
                el.style.display = "none";
                el.removeAttribute("aria-modal");
                el.removeAttribute("role");
                el.style.zIndex = "";
            }
        });
        // Remove any custom overlays
        document
            .querySelectorAll(".fixed.bg-black.bg-opacity-50.z-50")
            .forEach((el) => el.remove());
    },

    closeAllSidePanels(exclude = null) {
        console.log("[CORE] closeAllSidePanels", exclude);
        // Advanced Filters Panel
        if (exclude !== "advancedFilters") {
            const afOverlay = document.getElementById("advancedFiltersOverlay");
            const afPanel = document.getElementById("advancedFiltersPanel");
            if (afOverlay) {
                afOverlay.classList.add("opacity-0");
                setTimeout(() => {
                    // Only hide if it hasn't been reopened in the meantime
                    if (afOverlay.classList.contains("opacity-0")) {
                        afOverlay.classList.add("hidden");
                    }
                }, 300);
            }
            if (afPanel) afPanel.classList.add("translate-x-full");

            const afIcon = document.getElementById("advancedFiltersIcon");
            if (afIcon) afIcon.style.transform = "rotate(0deg)";
        }

        // Chart Selector Panel
        if (exclude !== "chartSelector") {
            const csOverlay = document.getElementById("chart-selector-overlay");
            const csPanel = document.getElementById("chart-selector-panel");
            if (csOverlay) csOverlay.classList.add("hidden");
            if (csPanel) csPanel.classList.add("hidden", "translate-x-full");
        }
    },

    showModal(config) {
        console.log("[CORE] showModal", config);
        this.closeAllModals();

        const modalId = config.id || "dynamicModal";
        const size = config.size || "max-w-md";
        const title = config.title || "";
        const content = config.content || "";
        const footer = config.footer || "";

        // Remove existing dynamic modal if any
        const existing = document.getElementById(modalId);
        if (existing) existing.remove();

        const modalHtml = `
            <div id="${modalId}" class="fixed inset-0 z-[1000] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="App.closeModal('${modalId}')"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle ${size} w-full">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start w-full">
                                <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                    <h3 class="text-xl leading-6 font-bold text-gray-900 mb-4" id="modal-title">${title}</h3>
                                    <div class="mt-2 text-sm text-gray-500">
                                        ${content}
                                    </div>
                                </div>
                            </div>
                        </div>
                        ${
                            footer
                                ? `
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            ${footer}
                        </div>
                        `
                                : ""
                        }
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML("beforeend", modalHtml);
        const modal = document.getElementById(modalId);
        void modal.offsetWidth; // Force reflow
    },

    // --- Date Range Management ---
    handleDateRangeClick(id) {
        console.log(`[CORE] handleDateRangeClick: ${id}`);
        const range = id.replace("date-range-", "");
        if (range === "custom") {
            this.openCustomDateModal();
            return;
        }
        this.state.dateRange = range;
        this.updateDateRangeUI();
        this.handleFilterChange();
    },

    openCustomDateModal() {
        if (typeof flatpickr !== "function") {
            console.error("[CORE] flatpickr not loaded");
            this.showToast("DatePicker not ready, please refresh", "error");
            return;
        }

        // Use the actual custom button for positioning if possible
        const targetBtn = document.getElementById("date-range-custom");
        const dummyInput = document.createElement("input");
        dummyInput.type = "text";
        dummyInput.style.position = "absolute";
        dummyInput.style.opacity = "0";
        dummyInput.style.pointerEvents = "none";

        if (targetBtn) {
            targetBtn.parentNode.appendChild(dummyInput);
        } else {
            document.body.appendChild(dummyInput);
        }

        const fp = flatpickr(dummyInput, {
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: [this.state.custom?.start, this.state.custom?.end],
            static: true, // Try to keep it near the dummy input
            onClose: (selectedDates) => {
                if (selectedDates.length === 2) {
                    const [start, end] = selectedDates;
                    this.state.dateRange = "custom";

                    // Manual formatting to respect local time (avoids toISOString offset issues)
                    const formatDateLocal = (date) => {
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(
                            2,
                            "0",
                        );
                        const day = String(date.getDate()).padStart(2, "0");
                        return `${year}-${month}-${day}`;
                    };

                    this.state.custom = {
                        start: formatDateLocal(start),
                        end: formatDateLocal(end),
                    };
                    this.updateDateRangeUI();
                    this.handleFilterChange();
                }
                setTimeout(() => {
                    if (typeof fp.destroy === "function") fp.destroy();
                    dummyInput.remove();
                }, 100);
            },
        });
        fp.open();
    },

    updateDateRangeUI() {
        const buttons = document.querySelectorAll(".date-range-btn");
        buttons.forEach((btn) => {
            const range = btn.id.replace("date-range-", "");
            const isActive = range === this.state.dateRange;
            btn.classList.toggle("bg-blue-100", isActive);
            btn.classList.toggle("text-blue-700", isActive);
            btn.classList.toggle("text-gray-700", !isActive);
        });
    },

    // --- Toast Notifications ---
    showToast(message, type = "success", duration = 3000) {
        let container = document.getElementById("toast-container");
        if (!container) {
            container = document.createElement("div");
            container.id = "toast-container";
            container.className = "fixed bottom-5 right-5 z-[5000] space-y-2";
            document.body.appendChild(container);
        }

        const id = "toast-" + Date.now();
        const colors = {
            success: "bg-green-600",
            error: "bg-red-600",
            info: "bg-blue-600",
            warning: "bg-yellow-600",
        };
        const color = colors[type] || colors.info;

        const html = `
            <div id="${id}" class="transform transition-all duration-300 translate-y-10 opacity-0">
                <div class="${color} text-white px-6 py-3 rounded-lg shadow-xl flex items-center gap-3">
                    <span class="text-sm font-medium">${message}</span>
                </div>
            </div>
        `;

        container.insertAdjacentHTML("beforeend", html);
        const el = document.getElementById(id);

        setTimeout(() => {
            el.classList.remove("translate-y-10", "opacity-0");
        }, 10);

        setTimeout(() => {
            el.classList.add("translate-y-10", "opacity-0");
            setTimeout(() => el.remove(), 300);
        }, duration);
    },

    async copyToClipboard(text) {
        try {
            if (navigator.clipboard) {
                await navigator.clipboard.writeText(text);
                this.showToast("Copied to clipboard!");
            } else {
                throw new Error("Clipboard API not available");
            }
        } catch (err) {
            console.error("Failed to copy: ", err);
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-9999px";
            textArea.style.top = "0";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand("copy");
                this.showToast("Copied to clipboard!");
            } catch (err) {
                this.showToast("Failed to copy", "error");
            }
            document.body.removeChild(textArea);
        }
    },

    initSidebarState() {
        const isCollapsed =
            localStorage.getItem("sidebar_collapsed") === "true";
        if (isCollapsed) {
            document.body.classList.add("sidebar-collapsed");
            const icon = document.getElementById("sidebarToggleIcon");
            if (icon) {
                icon.style.transform = "rotate(180deg)";
            }
        }
    },

    toggleSidebar() {
        const isCollapsed = document.body.classList.toggle("sidebar-collapsed");
        localStorage.setItem("sidebar_collapsed", isCollapsed);
        const icon = document.getElementById("sidebarToggleIcon");
        if (icon) {
            icon.style.transform = isCollapsed ? "rotate(180deg)" : "";
        }
    },
    apiUrl:
        window.AppData && window.AppData.config && window.AppData.config.apiUrl
            ? window.AppData.config.apiUrl
            : window.location.origin +
              window.location.pathname.substring(
                  0,
                  window.location.pathname.lastIndexOf("/"),
              ) +
              "/api",
    selectedLeadIds: new Set(),
    currentLeadsPage: [],
    handleSearch: null,

    // --- Utils ---
    escapeHtml(unsafe) {
        if (!unsafe) return "";
        return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    },
    formatDate(dateString) {
        if (!dateString) return "N/A";
        const date = new Date(dateString);
        return date.toLocaleDateString(undefined, {
            year: "numeric",
            month: "short",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        });
    },

    showConfirm(message, onConfirm, onCancel = null) {
        const overlay = document.createElement("div");
        overlay.className =
            "fixed inset-0 bg-black bg-opacity-50 z-[3000] flex items-center justify-center";
        overlay.style.zIndex = "3000"; // Ensure it's above other modals (which are 2000)
        // Use textContent for message to prevent XSS
        const safeMessage = this.escapeHtml(message);

        overlay.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-md mx-4 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Confirm Action</h3>
                <p class="text-gray-600 mb-6">${safeMessage}</p>
                <div class="flex justify-end space-x-3">
                    <button id="cancelBtn" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button id="confirmBtn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Confirm
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        document.getElementById("confirmBtn").onclick = () => {
            overlay.remove();
            if (onConfirm) onConfirm();
        };

        document.getElementById("cancelBtn").onclick = () => {
            overlay.remove();
            if (onCancel) onCancel();
        };

        overlay.onclick = (e) => {
            if (e.target === overlay) {
                overlay.remove();
                if (onCancel) onCancel();
            }
        };
    },

    // --- Authentication ---
    async login(email, password) {
        try {
            const response = await fetch(`${this.apiUrl}/auth/login.php`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ email, password }),
            });

            const data = await response.json();

            if (response.ok && data.success) {
                // Handle new response format (data.user) or old format (user)
                const user = data.data?.user || data.user;
                if (user) {
                    localStorage.removeItem("advanced_filters");
                    this.saveUserToStorage(user);
                    return { success: true, user: user };
                }
                return { success: false, error: "Invalid response format" };
            }
            return {
                success: false,
                error: data.error || data.message || "Login failed",
            };
        } catch (error) {
            console.error("Login error:", error);
            return { success: false, error: "Network error" };
        }
    },

    async register(
        email,
        password,
        orgName,
        planId,
        billingInterval = "monthly",
    ) {
        try {
            const response = await fetch(`${this.apiUrl}/auth/register.php`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    email,
                    password,
                    org_name: orgName,
                    plan_id: planId,
                    billing_interval: billingInterval,
                }),
            });

            const data = await response.json();

            if (response.ok) {
                return data;
            }
            return { success: false, error: data.error };
        } catch (error) {
            console.error("Registration error:", error);
            return { success: false, error: "Network error" };
        }
    },

    async logout() {
        try {
            await fetch(`${this.apiUrl}/auth/logout.php`);
        } catch (e) {
            console.error("Logout API failed:", e);
        }
        const user = this.getUser();
        const orgId = user ? user.org_id : null;
        localStorage.removeItem("crm_user");
        localStorage.removeItem("advanced_filters");
        if (orgId) {
            localStorage.removeItem("crm_reports_cache_" + orgId);
            localStorage.removeItem("crm_custom_charts_" + orgId);
            localStorage.removeItem("crm_chart_config_" + orgId);
            localStorage.removeItem("filter_bar_" + orgId);
        }
        window.location.href = "login.php";
    },

    saveUserToStorage(user) {
        localStorage.setItem("crm_user", JSON.stringify(user));
    },

    getCurrency() {
        const user = this.getUser();
        if (user && user.currency) return user.currency;
        // Fallback: check AppData directly (set by dashboard.php)
        if (
            window.AppData &&
            window.AppData.user &&
            window.AppData.user.currency
        ) {
            return window.AppData.user.currency;
        }
        return "USD";
    },

    getCurrencySymbol(currency) {
        const symbols = {
            USD: "$",
            EUR: "€",
            GBP: "£",
            INR: "₹",
            AUD: "A$",
            CAD: "C$",
            SGD: "S$",
            AED: "د.إ",
            JPY: "¥",
            CNY: "¥",
        };
        return symbols[currency] || currency;
    },

    formatCurrency(amount) {
        const currency = this.getCurrency();
        const symbol = this.getCurrencySymbol(currency);
        const num = Number(amount);
        const locale = currency === 'INR' ? 'en-IN' : 'en-US';
        return symbol + num.toLocaleString(locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    getUser() {
        // First priority: pre-injected data from PHP
        if (window.AppData && window.AppData.user) {
            return window.AppData.user;
        }

        try {
            const userStr = localStorage.getItem("crm_user");
            if (
                !userStr ||
                userStr === "null" ||
                userStr === "undefined" ||
                userStr.trim() === ""
            ) {
                return null;
            }
            return JSON.parse(userStr);
        } catch (error) {
            console.error("Error parsing user from localStorage:", error);
            // Clear corrupted data
            localStorage.removeItem("crm_user");
            return null;
        }
    },

    requireAuth() {
        const user = this.getUser();
        if (!user) {
            // Clear any corrupted data
            localStorage.removeItem("crm_user");
            window.location.href = "login.php";
            return null;
        }
        return user;
    },

    // Clear all stored data (logout helper)
    clearStorage() {
        localStorage.removeItem("crm_user");
    },

    // --- API Helpers ---
    async api(endpoint, method = "GET", body = null) {
        const user = this.getUser();
        if (!user) return null;

        const headers = {
            "Content-Type": "application/json",
            Accept: "application/json",
        };

        // Add CSRF Token
        if (window.AppData && window.AppData.csrf_token) {
            headers["X-CSRF-Token"] = window.AppData.csrf_token;
        }

        const options = {
            method,
            headers,
            credentials: 'include',
        };

        if (body) {
            options.body = JSON.stringify(body);
        }

        let url = `${this.apiUrl}${endpoint}`;

        if (method === "GET") {
            if (!url.includes("org_id=") && user && user.org_id) {
                const separator = url.includes("?") ? "&" : "?";
                url += `${separator}org_id=${user.org_id}`;
            }
        } else if (body) {
            if (body.org_id === undefined || body.org_id === null) {
                body.org_id = user.org_id;
            }
            options.body = JSON.stringify(body);
        }

        const response = await fetch(url, options);
        const text = await response.text();
        try {
            const json = text ? JSON.parse(text) : null;
            if (!response.ok) {
                return {
                    error:
                        json && json.error
                            ? json.error
                            : `Request failed (${response.status})`,
                };
            }
            return json;
        } catch (err) {
            console.error("API parse error:", err, text);
            return { error: "Invalid server response" };
        }
    },

    // --- View Controller ---
    router(view, pushState = true) {
        const appContent = document.getElementById("appContent");
        if (appContent) {
            appContent.innerHTML = ""; // Clear the content area
        }

        try {
            // Normalize view name (remove leading slash if present)
            view = view.replace(/^\//, "") || "dashboard";
            this.currentView = view;

            if (pushState) {
                const projectRoot = (
                    (window.AppData &&
                        window.AppData.config &&
                        window.AppData.config.projectRoot) ||
                    ""
                ).replace(/\/+$/, "");
                const newPath =
                    view === "dashboard"
                        ? projectRoot || "/"
                        : projectRoot + "/" + view;
                window.history.pushState({ view }, "", newPath);
            }

            // Ensure UI is clean on navigation
            this.closeAllModals();
            this.closeAllSidePanels();
            const navLinks = document.querySelectorAll("nav a");
            navLinks.forEach((link) => {
                const icon = link.querySelector("i");
                const href = link.getAttribute("href");

                link.className =
                    "text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md";
                if (icon)
                    icon.className =
                        "mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-300";

                const linkView = (href.split("/").pop() || "dashboard").split(
                    ".",
                )[0];

                if (linkView === view) {
                    link.className =
                        "bg-gray-900 text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md";
                    if (icon) icon.className = "mr-3 h-5 w-5 text-white";
                }
            });

            const leadActions = document.getElementById("leadActions");
            const dashboardActions =
                document.getElementById("dashboardActions");
            const leadFilters = document.getElementById("leadFilters");

            // Hide all action bars by default
            if (leadActions) leadActions.style.display = "none";
            if (dashboardActions) dashboardActions.style.display = "none";
            if (leadFilters) leadFilters.style.display = "none";

            // Control visibility based on view
            if (view === "dashboard") {
                if (dashboardActions) dashboardActions.style.display = "flex";
                const chartBtn = document.getElementById(
                    "toggle-chart-selector",
                );
                if (chartBtn) chartBtn.style.display = "inline-flex";
            } else if (view === "leads") {
                if (leadActions) leadActions.style.display = "flex";
                if (leadFilters) leadFilters.style.display = "block";
                // REVERT: Hide date filters on Leads page
                if (dashboardActions) dashboardActions.style.display = "none";
            }

            // Show facet filter bar for leads and pipeline views
            const facetFilterBar = document.getElementById("facetFilterBar");
            if (facetFilterBar) {
                facetFilterBar.style.display =
                    view === "leads" || view === "pipeline" ? "block" : "none";
            }
            // Close any open facet dropdown when navigating away
            if (
                view !== "leads" &&
                view !== "pipeline" &&
                typeof App !== "undefined" &&
                App.closeFacetDropdown
            ) {
                App.closeFacetDropdown();
            }

            this.updateDateRangeUI();

            switch (view) {
                case "dashboard":
                    document.getElementById("pageTitle").textContent =
                        "Dashboard";
                    if (typeof this.loadDashboard === "function") {
                        this.loadDashboard();
                    }
                    break;
                case "leads":
                    document.getElementById("pageTitle").textContent = "Leads";
                    this._kanbanDateFilter = {
                        preset: "all",
                        from: null,
                        to: null,
                    };
                    this.loadLeads();
                    break;
                case "pipeline":
                    document.getElementById("pageTitle").textContent =
                        "Pipeline";
                    this.loadKanban();
                    break;
                case "meetings":
                    document.getElementById("pageTitle").textContent =
                        "Meetings";
                    if (
                        typeof Meetings !== "undefined" &&
                        typeof Meetings.init === "function"
                    ) {
                        Meetings.init();
                    } else {
                        console.error("Meetings module not loaded");
                        appContent.innerHTML =
                            '<div class="p-6 text-center text-gray-500">Meetings module not available</div>';
                    }
                    break;
                case "invitations":
                    document.getElementById("pageTitle").textContent =
                        "User Invitations";
                    if (window.Invitations) {
                        window.Invitations.renderListView();
                    } else {
                        console.error("Invitations module not loaded");
                        appContent.innerHTML =
                            '<div class="p-6 text-center text-gray-500">Invitations module not available</div>';
                    }
                    break;
                case "audit":
                    document.getElementById("pageTitle").textContent =
                        "Audit Trail";
                    if (window.AuditLog) {
                        window.AuditLog.renderListView();
                    } else {
                        console.error("AuditLog module not loaded");
                        appContent.innerHTML =
                            '<div class="p-6 text-center text-gray-500">Audit Trail module not available</div>';
                    }
                    break;
                case "reports":
                    document.getElementById("pageTitle").textContent =
                        "Reports";
                    if (typeof this.loadReports === "function") {
                        this.loadReports();
                    } else {
                        throw new Error("loadReports function not found");
                    }
                    break;
                case "organizations":
                    document.getElementById("pageTitle").textContent =
                        "Organizations";
                    if (typeof this.loadOrganizations === "function") {
                        this.loadOrganizations();
                    } else if (typeof this.loadOrgSelector === "function") {
                        this.loadOrgSelector();
                    }
                    break;
                case "settings":
                    document.getElementById("pageTitle").textContent =
                        "Settings";
                    if (typeof this.loadSettings === "function") {
                        this.loadSettings();
                    }
                    break;
                case "partners":
                    document.getElementById("pageTitle").textContent =
                        "Partners";
                    if (typeof this.loadPartners === "function") {
                        this.loadPartners();
                    }
                    break;
                case "automations":
                    document.getElementById("pageTitle").textContent =
                        "Automations";
                    import(`./automations.js?v=${Date.now()}`)
                        .then((m) => {
                            if (m.Automations) {
                                m.Automations.init();
                            } else {
                                console.error("Automations module not found");
                            }
                        })
                        .catch((e) => {
                            console.error(
                                "Failed to load automations module:",
                                e,
                            );
                            appContent.innerHTML =
                                '<div class="p-6 text-center text-gray-500">Automations module not available</div>';
                        });
                    break;
                case "admin":
                    document.getElementById("pageTitle").textContent =
                        "Admin";
                    if (window.Admin && typeof window.Admin.init === "function") {
                        window.Admin.init();
                    } else {
                        appContent.innerHTML =
                            '<div class="p-6 text-center text-gray-500">Admin module not available</div>';
                    }
                    break;
                default:
                    document.getElementById("pageTitle").textContent =
                        "Dashboard";
                    if (typeof this.loadDashboard === "function") {
                        this.loadDashboard();
                    }
                    break;
            }
        } catch (e) {
            console.error("Router Error:", e);
            const appContent = document.getElementById("appContent");
            if (appContent) {
                appContent.innerHTML = `
                    <div class="p-6 bg-red-50 text-red-700 rounded-lg border border-red-200 m-4">
                        <h3 class="font-bold text-lg mb-2">⚠️ Application Error</h3>
                        <p class="mb-2">Failed to load view: <strong>${view}</strong></p>
                        <p class="font-mono text-sm bg-red-100 p-2 rounded">${e.message}</p>
                        <p class="text-xs text-gray-500 mt-4">Please check console for details.</p>
                    </div>`;
            }
        }
    },

    handleFilterChange() {
        let view = "dashboard";
        const path = window.location.pathname;
        const projectRoot =
            (window.AppData &&
                window.AppData.config &&
                window.AppData.config.projectRoot) ||
            "/crm-final";

        if (path.startsWith(projectRoot)) {
            const relativePath = path.substring(projectRoot.length);
            const segments = relativePath.split("/").filter(Boolean);
            if (segments.length > 0) {
                view = segments[0];
            }
        } else {
            const segments = path.split("/").filter(Boolean);
            if (segments.length > 0) {
                view = segments[segments.length - 1];
            }
        }

        if (view.endsWith(".php")) {
            view = view.replace(".php", "");
        }
        if (view === "index") view = "dashboard";

        console.log(`[CORE] handleFilterChange detected view: ${view}`);

        if (view === "pipeline") {
            this.loadKanban();
        } else if (view === "reports") {
            this.loadReports();
        } else if (view === "dashboard") {
            // Dashboard has its own date range filters,
            // but if global filters are changed while on dashboard (if visible), re-init it
            if (this.dashboardInstance && this.dashboardInstance.active) {
                this.dashboardInstance.refreshAllCharts();
            }
        } else {
            this.loadLeads(0);
        }
    },

    toggleAdvancedFilters() {
        const overlay = document.getElementById("advancedFiltersOverlay");
        const panel = document.getElementById("advancedFiltersPanel");
        const icon = document.getElementById("advancedFiltersIcon");

        if (!panel || !overlay) {
            console.error("Advanced filters elements not found");
            return;
        }

        const isHidden = overlay.classList.contains("hidden");

        if (isHidden) {
            // Close other panels first
            this.closeAllSidePanels("advancedFilters");

            // Show
            overlay.classList.remove("hidden");
            // Trigger reflow
            void overlay.offsetWidth;
            overlay.classList.remove("opacity-0");

            panel.classList.remove("translate-x-full");

            if (icon) icon.style.transform = "rotate(180deg)";
            this.renderAdvancedFilters();
        } else {
            // Hide
            overlay.classList.add("opacity-0");
            panel.classList.add("translate-x-full");

            if (icon) icon.style.transform = "rotate(0deg)";

            setTimeout(() => {
                overlay.classList.add("hidden");
                this.updateAdvancedFilterIndicator();
            }, 300);
        }
    },

    async renderAdvancedFilters() {
        const container = document.getElementById("advancedFiltersContainer");
        if (!container) {
            console.error("Advanced filters container not found");
            return;
        }

        // Load saved filter state (in-memory, resets on full page refresh)
        const savedFilters = this.getSavedAdvancedFilters();

        const customFields = await this.getCustomFields();
        const visibilitySettings = await this.getFieldVisibility();

        const standardFields = [
            { name: "name", label: "Full Name", type: "text" },
            { name: "first_name", label: "First Name", type: "text" },
            { name: "last_name", label: "Last Name", type: "text" },
            { name: "title", label: "Title", type: "text" },
            { name: "company", label: "Company", type: "text" },
            { name: "email", label: "Email", type: "text" },
            { name: "phone", label: "Phone", type: "text" },
            { name: "city", label: "City", type: "text" },
            { name: "state", label: "State", type: "text" },
            { name: "country", label: "Country", type: "text" },
            { name: "zip_code", label: "Zip Code", type: "text" },
            { name: "address", label: "Address", type: "text" },
            { name: "website", label: "Website", type: "text" },
            { name: "assigned_to", label: "Assigned To", type: "select" },
            { name: "lead_value", label: "Value", type: "number" },
            { name: "source", label: "Source", type: "select" },
            { name: "stage_id", label: "Stage", type: "select" },
            { name: "created_at", label: "Created Date", type: "date" },
            { name: "updated_at", label: "Updated Date", type: "date" },
        ];

        // For advanced filters, always show standard fields
        const visibleStandardFields = standardFields;
        const visibleCustomFields = customFields.filter((f) =>
            this.isFieldVisible(f.name, "custom", visibilitySettings),
        );

        // Build nicer layout: section for standard fields and (if any) a section for custom fields
        const renderSectionGrid = async (fields, isCustom = false) => {
            const items = [];
            for (const field of fields) {
                try {
                    if (field.type === "select") {
                        let options = [];
                        if (field.options && field.options.length > 0) {
                            options = field.options;
                        } else {
                            options = await this.getFieldOptions(field.name);
                        }
                        items.push(
                            this.renderFilterInput(
                                field,
                                options,
                                isCustom,
                                savedFilters,
                            ),
                        );
                    } else {
                        items.push(
                            this.renderFilterInput(
                                field,
                                [],
                                isCustom,
                                savedFilters,
                            ),
                        );
                    }
                } catch (e) {
                    console.error("Error rendering filter for", field.name, e);
                }
            }
            return items.join("");
        };

        const standardGrid = await renderSectionGrid(
            visibleStandardFields,
            false,
        );
        const customGrid = await renderSectionGrid(
            visibleCustomFields.map((f) => ({ ...f, label: f.name })),
            true,
        );

        container.innerHTML = `
            <div class="space-y-8">
                <section class="space-y-3">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 tracking-wide">Standard Fields</h4>
                        <p class="text-xs text-gray-500 mt-1">
                            Combine multiple conditions across core lead fields. All conditions are applied together.
                        </p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                        ${standardGrid}
                    </div>
                </section>

                ${
                    visibleCustomFields.length
                        ? `
                <section class="space-y-3">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 tracking-wide">Custom Fields</h4>
                        <p class="text-xs text-gray-500 mt-1">
                            Filter using your custom lead fields. Leave any field blank to ignore it.
                        </p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                        ${customGrid}
                    </div>
                </section>
                `
                        : ""
                }
            </div>
        `;

        // Restore saved values and auto-expand fields with values
        this.restoreAdvancedFilterValues(savedFilters);
    },

    renderFilterInput(
        field,
        options = [],
        isCustom = false,
        savedFilters = {},
    ) {
        const fieldId = `filter_${field.name}`;
        const baseClasses =
            "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm";

        // Get saved values for this field
        const savedValue = savedFilters[field.name] || "";
        const savedOp =
            savedFilters[`${field.name}_op`] ||
            (field.type === "text" ? "contains" : "equals");
        const savedMin = savedFilters[`${field.name}_min`] || "";
        const savedMax = savedFilters[`${field.name}_max`] || "";
        const savedFrom = savedFilters[`${field.name}_from`] || "";
        const savedTo = savedFilters[`${field.name}_to`] || "";

        // Check if field has any saved value (to auto-expand)
        const hasValue =
            savedValue || savedMin || savedMax || savedFrom || savedTo;

        let inputHtml = "";

        switch (field.type) {
            case "text":
                // Text: operator dropdown + value input (vertical)
                inputHtml = `
                    <div class="space-y-2">
                        <select id="${fieldId}_op" name="${field.name}_op"
                            class="${baseClasses} bg-white">
                            <option value="contains" ${savedOp === "contains" ? "selected" : ""}>Contains</option>
                            <option value="equals" ${savedOp === "equals" ? "selected" : ""}>Is equal to</option>
                            <option value="not_equals" ${savedOp === "not_equals" ? "selected" : ""}>Is not</option>
                            <option value="starts_with" ${savedOp === "starts_with" ? "selected" : ""}>Starts with</option>
                            <option value="ends_with" ${savedOp === "ends_with" ? "selected" : ""}>Ends with</option>
                        </select>
                        <input type="text" id="${fieldId}" name="${field.name}"
                            class="${baseClasses}" placeholder="Enter value" value="${(savedValue || "").replace(/"/g, "&quot;")}">
                    </div>
                `;
                break;
            case "number":
                // Min / Max
                inputHtml = `
                    <div class="flex flex-col space-y-2">
                        <input type="number" id="${fieldId}_min" name="${field.name}_min"
                            class="${baseClasses} w-full" placeholder="Min ${field.label}" value="${savedMin}">
                        <input type="number" id="${fieldId}_max" name="${field.name}_max"
                            class="${baseClasses} w-full" placeholder="Max ${field.label}" value="${savedMax}">
                    </div>
                `;
                break;
            case "date":
                // From / To
                inputHtml = `
                    <div class="flex flex-col space-y-2">
                        <input type="date" id="${fieldId}_from" name="${field.name}_from"
                            class="${baseClasses} w-full" value="${savedFrom}">
                        <input type="date" id="${fieldId}_to" name="${field.name}_to"
                            class="${baseClasses} w-full" value="${savedTo}">
                    </div>
                `;
                break;
            case "select":
                // Select with equals / not equals operator
                inputHtml = `
                    <div class="space-y-2">
                        <select id="${fieldId}_op" name="${field.name}_op" class="${baseClasses} bg-white">
                            <option value="equals" ${savedOp === "equals" ? "selected" : ""}>Is equal to</option>
                            <option value="not_equals" ${savedOp === "not_equals" ? "selected" : ""}>Is not</option>
                        </select>
                        <select id="${fieldId}" name="${field.name}" class="${baseClasses} bg-white">
                            <option value="">All ${field.label}${field.label.endsWith("s") ? "" : "s"}</option>
                            ${options
                                .map((opt) => {
                                    const val =
                                        typeof opt === "object" && opt !== null
                                            ? opt.id || opt.value
                                            : opt;
                                    const label =
                                        typeof opt === "object" && opt !== null
                                            ? opt.full_name ||
                                              opt.name ||
                                              opt.label
                                            : opt;
                                    return `<option value="${val}" ${savedValue == val ? "selected" : ""}>${label}</option>`;
                                })
                                .join("")}
                        </select>
                    </div>
                `;
                break;
            default:
                inputHtml = `
                    <input type="text" id="${fieldId}" name="${field.name}"
                        class="${baseClasses}" placeholder="Enter value" value="${(savedValue || "").replace(/"/g, "&quot;")}">
                `;
        }

        // Collapsible card per field - auto-expand if has value
        const bodyId = `adv_${isCustom ? "c" : "s"}_${field.name}_body`;
        const initiallyHidden = hasValue ? "" : "hidden";

        return `
            <div class="bg-gray-50 border border-gray-200 rounded-lg overflow-hidden">
                <button type="button"
                    class="w-full px-3 py-2 flex items-center justify-between text-left hover:bg-gray-100 focus:outline-none"
                    onclick="window.App && App.toggleFilterSection && App.toggleFilterSection('${bodyId}')">
                    <span class="text-xs font-semibold text-gray-700 tracking-wide uppercase">
                        ${field.label}
                    </span>
                    <i data-lucide="chevron-down" class="h-4 w-4 text-gray-400 transition-transform duration-150"
                       data-filter-chevron="${bodyId}" style="${hasValue ? "transform: rotate(0deg);" : "transform: rotate(-90deg);"}"></i>
                </button>
                <div id="${bodyId}" class="px-3 pb-3 pt-1 space-y-2 ${initiallyHidden}">
                    ${inputHtml}
                </div>
            </div>
        `;
    },

    clearAdvancedFilters() {
        const container = document.getElementById("advancedFiltersContainer");
        if (container) {
            const inputs = container.querySelectorAll("input, select");
            inputs.forEach((input) => {
                if (input.type === "checkbox" || input.type === "radio") {
                    input.checked = false;
                } else {
                    input.value = "";
                }
            });
        }
        // Clear state
        this.advancedFiltersState = {};
        localStorage.removeItem("advanced_filters");

        this.applyAdvancedFilters();
        this.updateAdvancedFilterIndicator();
    },

    // Toggle a single advanced filter card body (collapse / expand)
    toggleFilterSection(bodyId) {
        const body = document.getElementById(bodyId);
        if (!body) return;

        // Scope chevron lookup to this card only
        const card = body.parentElement;
        const chevron = card
            ? card.querySelector(`[data-filter-chevron="${bodyId}"]`)
            : null;
        const isHidden = body.classList.contains("hidden");

        if (isHidden) {
            body.classList.remove("hidden");
            if (chevron) chevron.style.transform = "rotate(0deg)";
        } else {
            body.classList.add("hidden");
            if (chevron) chevron.style.transform = "rotate(-90deg)";
        }
    },

    async applyAdvancedFilters() {
        // Save current filter state before closing
        this.saveAdvancedFilters();
        this.toggleAdvancedFilters(); // Close the panel
        await window.App.loadLeads(0);
    },

    // Save advanced filter state in memory (clears on full page refresh)
    saveAdvancedFilters() {
        const container = document.getElementById("advancedFiltersContainer");
        if (!container) return;

        const filterState = {};
        container.querySelectorAll("input, select").forEach((el) => {
            if (!el.name) return;
            const val = el.value?.trim() || "";
            if (val) {
                filterState[el.name] = val;
            }
        });

        this.advancedFiltersState = filterState;
        localStorage.setItem("advanced_filters", JSON.stringify(filterState));
    },

    // Get saved advanced filter state from localStorage or memory
    getSavedAdvancedFilters() {
        if (!this.advancedFiltersState) {
            try {
                const saved = localStorage.getItem("advanced_filters");
                this.advancedFiltersState = saved ? JSON.parse(saved) : {};
            } catch (e) {
                console.error(
                    "Failed to load advanced filters from localStorage",
                    e,
                );
                this.advancedFiltersState = {};
            }
        }
        return this.advancedFiltersState;
    },

    // New helper to update the UI indicator on the toggle button
    updateAdvancedFilterIndicator() {
        const filters = this.getSavedAdvancedFilters();
        const activeCount = Object.keys(filters).filter(
            (key) => !key.endsWith("_op"),
        ).length;

        const btn = document.getElementById("advancedFiltersToggle");
        if (!btn) return;

        let badge = btn.querySelector(".filter-badge");

        if (activeCount > 0) {
            if (!badge) {
                badge = document.createElement("span");
                badge.className =
                    "filter-badge ml-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold leading-none text-white bg-blue-600 rounded-full";
                btn.insertBefore(
                    badge,
                    btn.querySelector("#advancedFiltersIcon") || null,
                );
            }
            badge.textContent = activeCount;
            btn.classList.add("border-blue-500", "bg-blue-50");
        } else {
            if (badge) badge.remove();
            btn.classList.remove("border-indigo-500", "bg-indigo-50");
        }
    },

    // Restore filter values and update UI (called after rendering)
    restoreAdvancedFilterValues(savedFilters) {
        // Values are already set in renderFilterInput via value attributes
        // This function can be used for any additional restoration logic
        // Auto-expand is handled in renderFilterInput via initiallyHidden

        // Re-initialize Lucide icons for chevrons
        if (window.lucide) {
            setTimeout(() => {
                window.lucide.createIcons();
            }, 100);
        }
    },

    // Get field options from configuration
    async getFieldOptions(fieldName) {
        if (fieldName === "assigned_to") {
            try {
                const users = await this.getOrgUsers();
                return users.map((u) => ({
                    id: u.id,
                    name: u.full_name || u.email,
                }));
            } catch (e) {
                console.warn("Failed to load users for filter:", e);
                return [];
            }
        }

        // Source: merge hardcoded defaults with live DB values so nothing is lost
        if (fieldName === "source") {
            const defaults = [
                "Direct",
                "Website",
                "LinkedIn",
                "Referral",
                "Ads",
                "Cold Call",
            ];
            try {
                const res = await this.api("/leads/sources.php");
                if (res && res.success && Array.isArray(res.sources)) {
                    // Union: defaults first, then any DB value not already in defaults
                    const merged = [...defaults];
                    res.sources.forEach((s) => {
                        if (s && !merged.includes(s)) merged.push(s);
                    });
                    return merged;
                }
            } catch (e) {
                console.warn(
                    "Failed to load sources dynamically, using defaults:",
                    e,
                );
            }
            return defaults;
        }

        const stageDefaults = [
            "new",
            "contacted",
            "qualified",
            "proposal",
            "won",
            "lost",
        ];

        if (fieldName === "stage_id") {
            try {
                const res = await this.api("/settings/get_field_config.php");
                if (
                    res &&
                    res.success &&
                    res.fields &&
                    res.fields.stage_id &&
                    Array.isArray(res.fields.stage_id.options) &&
                    res.fields.stage_id.options.length > 0
                ) {
                    return res.fields.stage_id.options;
                }
            } catch (e) {
                console.warn("Failed to load stage config, using defaults:", e);
            }
            return stageDefaults;
        }

        try {
            const fieldConfigRes = await this.api(
                "/settings/get_field_config.php",
            );
            if (
                fieldConfigRes &&
                fieldConfigRes.success &&
                fieldConfigRes.fields &&
                fieldConfigRes.fields[fieldName]
            ) {
                return fieldConfigRes.fields[fieldName].options || [];
            }
        } catch (e) {
            console.warn("Failed to load field config, using defaults:", e);
        }

        return [];
    },
});

window.App = App;

// Initialize Sidebar State Immediately (since modules are deferred)
App.initSidebarState();

// Manual Start Function (called by app.js after all modules are loaded)
App.start = function () {
    // Initial routing based on URL
    const path = window.location.pathname;
    const projectRoot =
        (window.AppData &&
            window.AppData.config &&
            window.AppData.config.projectRoot) ||
        "/crm-final";

    let view = "dashboard";
    if (path.startsWith(projectRoot)) {
        const relativePath = path.substring(projectRoot.length);
        const segments = relativePath.split("/").filter(Boolean);
        if (segments.length > 0) {
            view = segments[0];
        }
    } else {
        // Fallback for direct access if projectRoot extraction fails
        const segments = path.split("/").filter(Boolean);
        if (segments.length > 0) {
            view = segments[segments.length - 1];
        }
    }

    // Handle specific php files direct access (legacy)
    if (view.endsWith(".php")) {
        view = view.replace(".php", "");
    }

    if (view && view !== "index") {
        App.router(view, false);
    } else {
        App.router("dashboard", false);
    }

    // Handle browser back/forward buttons
    window.addEventListener("popstate", (event) => {
        if (event.state && event.state.view) {
            App.router(event.state.view, false);
        } else {
            // Recalculate view from URL? Or default to dashboard?
            App.router("dashboard", false);
        }
    });

    // Add date range listeners
    document.querySelectorAll(".date-range-btn").forEach((btn) => {
        btn.addEventListener("click", (e) =>
            App.handleDateRangeClick(e.currentTarget.id),
        );
    });
    App.updateDateRangeUI();
    App.updateAdvancedFilterIndicator();

    console.log("[App] Started router");
};

window.App = App;
