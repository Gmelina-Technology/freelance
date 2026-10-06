{{--
    Cookie consent state. Runs synchronously in <head> so the banner is resolved before first paint.

    The choice lives only in the visitor's browser (localStorage key `cookie-consent`); nothing is sent to the server.

    Public API, exposed as `window.cookieConsent`:
        get()        'accepted' | 'rejected' | null   (null = not chosen yet, or expired)
        isGranted()  true only after an unexpired "accepted" choice
        accept()     record "accepted", close the banner
        reject()     record "rejected", close the banner
        open()       show the banner again

    Every accept()/reject() dispatches `cookie-consent:change` on document with `detail.choice`.
    The same state is mirrored on <html data-consent="accepted|rejected|unset">.

    Gating a future optional script:
        if (window.cookieConsent.isGranted()) { loadAnalytics(); }
        document.addEventListener('cookie-consent:change', (e) => {
            if (e.detail.choice === 'accepted') { loadAnalytics(); }
        });

    A script that has already run cannot be unloaded when consent is withdrawn. Scripts that set cookies must stop
    themselves on a `rejected` event, and the page may need a reload to be fully clean. Bump VERSION to ask everyone again.
--}}
<script>
    (() => {
        const STORAGE_KEY = 'cookie-consent';
        const VERSION = 1;
        const TTL_DAYS = 180;
        const CHOICES = ['accepted', 'rejected'];

        const root = document.documentElement;
        let memoryRecord = null;

        const read = () => {
            let raw;

            try {
                raw = localStorage.getItem(STORAGE_KEY);
            } catch (e) {
                return memoryRecord;
            }

            if (raw === null) {
                return memoryRecord;
            }

            try {
                const record = JSON.parse(raw);
                const age = Date.now() - record.at;

                if (
                    record !== null
                    && record.v === VERSION
                    && CHOICES.includes(record.choice)
                    && Number.isFinite(record.at)
                    && age >= 0
                    && age <= TTL_DAYS * 86400000
                ) {
                    return record;
                }
            } catch (e) {
                // Invalid JSON is treated as unset.
            }

            try {
                localStorage.removeItem(STORAGE_KEY);
            } catch (e) {
                // Nothing to clean up if storage is unavailable.
            }

            return null;
        };

        const get = () => {
            const record = read();

            return record ? record.choice : null;
        };

        const sync = () => {
            root.setAttribute('data-consent', get() ?? 'unset');
        };

        const open = () => {
            root.setAttribute('data-cookie-banner', 'open');
        };

        const close = () => {
            root.removeAttribute('data-cookie-banner');
        };

        const choose = (choice) => {
            memoryRecord = { v: VERSION, choice, at: Date.now() };

            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(memoryRecord));
            } catch (e) {
                // Kept in memory for this page only.
            }

            sync();
            close();
            document.dispatchEvent(new CustomEvent('cookie-consent:change', { detail: { choice } }));
        };

        window.cookieConsent = Object.freeze({
            get,
            isGranted: () => get() === 'accepted',
            accept: () => choose('accepted'),
            reject: () => choose('rejected'),
            open,
        });

        sync();

        if (get() === null) {
            open();
        }

        document.addEventListener('click', (event) => {
            const preferences = event.target.closest('[data-cookie-preferences]');

            if (preferences) {
                open();
                document.getElementById('cookie-banner-title')?.focus();

                return;
            }

            const choice = event.target.closest('[data-cookie-accept]')
                ? 'accepted'
                : (event.target.closest('[data-cookie-reject]') ? 'rejected' : null);

            if (choice === null) {
                return;
            }

            choose(choice);
            document.querySelector('[data-cookie-preferences]')?.focus();
        });
    })();
</script>
