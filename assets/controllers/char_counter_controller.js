import { Controller } from '@hotwired/stimulus';

/*
 * Live character counter for a textarea with a maximum length.
 */
export default class extends Controller {
    static targets = ['input', 'count'];
    static values = { max: Number };

    connect() {
        this.update();
    }

    update() {
        const length = this.inputTarget.value.length;

        this.countTarget.textContent = `${length.toLocaleString('en')} / ${this.maxValue.toLocaleString('en')}`;
        this.countTarget.classList.toggle('text-rose-600', length > this.maxValue);
    }
}
