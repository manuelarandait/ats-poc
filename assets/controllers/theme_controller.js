import { Controller } from '@hotwired/stimulus';

/*
 * Light/dark toggle. The initial theme is applied by an inline script in
 * <head> (before paint); this controller only switches and remembers it.
 */
export default class extends Controller {
    toggle() {
        const dark = document.documentElement.classList.toggle('dark');

        try {
            localStorage.setItem('theme', dark ? 'dark' : 'light');
        } catch (e) {
            // Storage unavailable (private mode): the toggle still works for this page.
        }
    }
}
