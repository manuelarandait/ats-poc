import { Controller } from '@hotwired/stimulus';

/*
 * Inline confirmation for an irreversible submit button (no browser dialog).
 * The trigger is a real submit button: without JS it submits straight away;
 * with JS the first click reveals the panel holding the actual confirmation.
 */
export default class extends Controller {
    static targets = ['trigger', 'panel', 'confirm'];

    ask(event) {
        event.preventDefault();
        this.triggerTarget.hidden = true;
        this.panelTarget.hidden = false;
        this.confirmTarget.focus();
    }

    cancel() {
        this.panelTarget.hidden = true;
        this.triggerTarget.hidden = false;
        this.triggerTarget.focus();
    }
}
