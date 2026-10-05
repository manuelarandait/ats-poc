import { Controller } from '@hotwired/stimulus';

/*
 * A flash message shown as a toast: it closes by itself after a few seconds
 * (paused while hovered) or with its close button. Without JS it simply stays.
 */
export default class extends Controller {
    static values = { delay: { type: Number, default: 5000 } };

    connect() {
        this.resume();
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    pause() {
        clearTimeout(this.timer);
    }

    resume() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.dismiss(), this.delayValue);
    }

    dismiss() {
        clearTimeout(this.timer);
        this.element.classList.add('opacity-0', 'translate-x-4');
        setTimeout(() => this.element.remove(), 300);
    }
}
