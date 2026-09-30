import { Controller } from '@hotwired/stimulus';

/*
 * Rendered by the server inside a Turbo Frame only while some AI analysis is
 * pending. After a delay it reloads the enclosing frame; the fresh HTML either
 * contains this marker again (still pending → keep polling) or not (done →
 * polling stops). No client-side state to keep in sync.
 */
export default class extends Controller {
    static values = {
        url: String,
        interval: { type: Number, default: 2500 },
    };

    connect() {
        this.timer = setTimeout(() => {
            const frame = this.element.closest('turbo-frame');

            if (frame) {
                frame.src = this.urlValue;
                frame.reload();
            }
        }, this.intervalValue);
    }

    disconnect() {
        clearTimeout(this.timer);
    }
}
