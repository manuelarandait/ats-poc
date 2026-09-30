import { Controller } from '@hotwired/stimulus';

/*
 * Submits the filters form as the user types/chooses, so results update in
 * real time. Text input is debounced; selects submit immediately. The form
 * targets a Turbo Frame, so only the results are replaced.
 */
export default class extends Controller {
    static values = { delay: { type: Number, default: 300 } };

    connect() {
        this.timeout = null;
    }

    disconnect() {
        clearTimeout(this.timeout);
    }

    debounced() {
        clearTimeout(this.timeout);
        this.timeout = setTimeout(() => this.submit(), this.delayValue);
    }

    submit() {
        clearTimeout(this.timeout);
        this.element.requestSubmit();
    }
}
