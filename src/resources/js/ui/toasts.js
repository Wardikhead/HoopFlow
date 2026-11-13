const MAX_TOASTS = 3;
const DEFAULT_DURATION = 4200;

export function registerToasts() {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('hfToasts', () => ({
            toasts: [],

            push(detail = {}) {
                const toast = {
                    id: `${Date.now()}-${Math.random()}`,
                    title: detail.title || '',
                    message: detail.message || '',
                    tone: ['success', 'warning', 'danger', 'info'].includes(detail.tone) ? detail.tone : 'info',
                    actionLabel: detail.actionLabel || '',
                    actionHref: detail.actionHref || '',
                    duration: Number(detail.duration) || DEFAULT_DURATION,
                    paused: false,
                    remaining: Number(detail.duration) || DEFAULT_DURATION,
                    startedAt: performance.now(),
                    timer: null,
                };

                this.toasts.push(toast);
                if (this.toasts.length > MAX_TOASTS) this.toasts.shift();
                this.schedule(toast);
            },

            schedule(toast) {
                toast.startedAt = performance.now();
                toast.timer = window.setTimeout(() => this.dismiss(toast.id), toast.remaining);
            },

            dismiss(id) {
                const toast = this.toasts.find((item) => item.id === id);
                if (toast?.timer) window.clearTimeout(toast.timer);
                this.toasts = this.toasts.filter((item) => item.id !== id);
            },

            pause(id) {
                const toast = this.toasts.find((item) => item.id === id);
                if (!toast || toast.paused) return;
                toast.paused = true;
                window.clearTimeout(toast.timer);
                toast.remaining = Math.max(0, toast.remaining - (performance.now() - toast.startedAt));
            },

            resume(id) {
                const toast = this.toasts.find((item) => item.id === id);
                if (!toast || !toast.paused) return;
                toast.paused = false;
                this.schedule(toast);
            },
        }));
    });
}
