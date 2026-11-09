/*
 | Componenti x-ui del gruppo "forms" (resources/views/components/ui).
 |
 | uiCharCounter: contatore di caratteri di <x-ui.textarea counter maxlength="…">.
 | Il numero visibile è aria-hidden; agli screen reader arriva solo un avviso "polite"
 | quando si è vicini al limite, e solo dopo una pausa nella digitazione.
 */
const ANNOUNCE_DELAY = 700;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('uiCharCounter', ({ max, messages }) => ({
        max,
        count: 0,
        announcement: '',
        timer: null,

        get remaining() {
            return Math.max(0, this.max - this.count);
        },

        // Avvisa negli ultimi 10% dei caratteri (almeno gli ultimi 10).
        get threshold() {
            return Math.max(10, Math.round(this.max * 0.1));
        },

        init() {
            this.count = this.$refs.control.value.length;
        },

        destroy() {
            clearTimeout(this.timer);
        },

        update() {
            this.count = this.$refs.control.value.length;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.announce(), ANNOUNCE_DELAY);
        },

        announce() {
            if (this.remaining > this.threshold) {
                this.announcement = '';
            } else if (this.remaining === 0) {
                this.announcement = messages.limitReached;
            } else if (this.remaining === 1) {
                this.announcement = messages.remainingOne;
            } else {
                this.announcement = messages.remaining.replace(':count', String(this.remaining));
            }
        },
    }));
});
