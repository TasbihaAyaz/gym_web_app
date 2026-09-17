/**
 * Generic table infinite scroll.
 *
 * Markup:
 *   <div class="infinite-scroll" data-infinite-scroll
 *        data-url="/members"
 *        data-target="#members-rows"
 *        data-next-page="2"
 *        data-params='{"search":"","status":""}'>
 *     <div class="infinite-spinner" hidden>...</div>
 *     <div class="infinite-end" hidden>...</div>
 *   </div>
 *
 * The endpoint must answer `partial=1` with JSON: { html, next_page }.
 * Every key in data-params is always sent, so an empty value keeps its
 * meaning server-side (e.g. an empty date means "all records").
 */
(function () {
  document.querySelectorAll('[data-infinite-scroll]').forEach(setup);

  function setup(root) {
    const target = document.querySelector(root.dataset.target || '');
    if (!target) return;

    const spinner = root.querySelector('.infinite-spinner');
    const endNote = root.querySelector('.infinite-end');

    let params = {};
    try {
      params = JSON.parse(root.dataset.params || '{}');
    } catch (_) {
      params = {};
    }

    let nextPage = parseInt(root.dataset.nextPage || '', 10);
    let loading = false;
    let observer = null;

    if (!Number.isFinite(nextPage)) {
      if (endNote) endNote.hidden = false;
      return;
    }

    function finish() {
      nextPage = NaN;
      if (spinner) spinner.hidden = true;
      if (endNote) endNote.hidden = false;
      if (observer) observer.disconnect();
    }

    function buildUrl(page) {
      const url = new URL(root.dataset.url, window.location.origin);
      url.searchParams.set('partial', '1');
      url.searchParams.set('page', String(page));
      Object.keys(params).forEach((key) => {
        url.searchParams.set(key, params[key] == null ? '' : String(params[key]));
      });
      return url.toString();
    }

    async function loadNext() {
      if (loading || !Number.isFinite(nextPage)) return;
      loading = true;
      if (spinner) spinner.hidden = false;

      try {
        const res = await fetch(buildUrl(nextPage), {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('Request failed: ' + res.status);

        const data = await res.json();
        if (data.html && data.html.trim()) {
          target.insertAdjacentHTML('beforeend', data.html);
        }

        if (data.next_page) {
          nextPage = Number(data.next_page);
          if (spinner) spinner.hidden = true;
        } else {
          finish();
        }
      } catch (err) {
        console.warn('[infinite-scroll] load failed', err);
        if (spinner) spinner.hidden = true;
      } finally {
        loading = false;
      }
    }

    observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting)) loadNext();
    }, { rootMargin: '300px 0px' });

    observer.observe(root);
  }
})();
