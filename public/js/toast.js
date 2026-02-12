/**
 * NGN 2.0.3 Toast System
 * Tailwind-First / Spotify-Killer Aesthetics
 */
window.NGN_Toast = {
    container: null,

    init() {
        if (this.container) return;
        this.container = document.createElement('div');
        this.container.className = 'fixed bottom-24 right-8 z-[200] flex flex-col gap-3 pointer-events-none max-w-sm w-full';
        document.body.appendChild(this.container);
    },

    show(message, type = 'info', duration = 5000) {
        this.init();
        
        const toast = document.createElement('div');
        toast.className = 'transform translate-x-full transition-all duration-500 ease-out pointer-events-auto bg-[#181818] border border-white/10 rounded-xl p-4 flex items-center gap-4 shadow-2xl shadow-black';
        
        const icons = {
            success: '<i class="bi-check-circle-fill text-[#FF5F1F] text-xl"></i>',
            error: '<i class="bi-exclamation-circle-fill text-red-500 text-xl"></i>',
            warning: '<i class="bi-exclamation-triangle-fill text-yellow-500 text-xl"></i>',
            info: '<i class="bi-info-circle-fill text-blue-500 text-xl"></i>'
        };

        toast.innerHTML = `
            <div class="flex-shrink-0">${icons[type] || icons.info}</div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-black text-white truncate">${message}</p>
            </div>
            <button class="text-zinc-500 hover:text-white transition-colors">
                <i class="bi-x-lg"></i>
            </button>
        `;

        const closeBtn = toast.querySelector('button');
        closeBtn.onclick = () => this.remove(toast);

        this.container.appendChild(toast);

        // Animate in
        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full');
        });

        // Auto remove
        if (duration > 0) {
            setTimeout(() => this.remove(toast), duration);
        }
    },

    remove(toast) {
        toast.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 500);
    },

    success(msg) { this.show(msg, 'success'); },
    error(msg) { this.show(msg, 'error'); },
    warn(msg) { this.show(msg, 'warning'); },
    info(msg) { this.show(msg, 'info'); }
};

// Global AJAX / Fetch Interceptor
(function() {
    // Intercept Fetch
    const originalFetch = window.fetch;
    window.fetch = async (...args) => {
        try {
            const response = await originalFetch(...args);
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                window.NGN_Toast.show(errorData.message || `Error: ${response.status}`, 'error');
            }
            return response;
        } catch (err) {
            window.NGN_Toast.show(err.message || 'Network request failed', 'error');
            throw err;
        }
    };

    // Intercept jQuery AJAX if exists
    if (window.jQuery) {
        $(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
            let msg = 'AJAX Error';
            try {
                const json = JSON.parse(jqXHR.responseText);
                msg = json.message || thrownError;
            } catch (e) {
                msg = thrownError || jqXHR.statusText;
            }
            window.NGN_Toast.show(msg, 'error');
        });
    }

    // Intercept Axios if exists
    if (window.axios) {
        window.axios.interceptors.response.use(
            response => response,
            error => {
                const msg = error.response?.data?.message || error.message || 'Axios error';
                window.NGN_Toast.show(msg, 'error');
                return Promise.reject(error);
            }
        );
    }
})();
