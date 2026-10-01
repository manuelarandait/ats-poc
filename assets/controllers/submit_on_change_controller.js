import { Controller } from '@hotwired/stimulus';

/*
 * Submits the form this field belongs to when its value changes. Works with
 * the HTML `form="…"` attribute, so a control can live outside its form
 * (here: the page-size select inside the results frame belongs to the
 * filters form, so changing it keeps every filter and goes back to page 1).
 */
export default class extends Controller {
    submit() {
        this.element.form?.requestSubmit();
    }
}
