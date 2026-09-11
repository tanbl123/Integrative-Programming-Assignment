/*
 * EcoCampus scheduling web-service integration.
 * Author : Ng Zi Zhang (2406898)
 * Module : Collection Scheduling & Assignment
 */
(() => {
    const form = document.getElementById('schedule-form');
    const strategy = document.getElementById('strategy');
    const preview = document.getElementById('service-preview');
    if (!form || !strategy || !preview) return;

    let requestNumber = 0;

    function ifaTimestamp() {
        const now = new Date();
        const part = value => String(value).padStart(2, '0');
        return `${now.getFullYear()}-${part(now.getMonth() + 1)}-${part(now.getDate())} `
            + `${part(now.getHours())}:${part(now.getMinutes())}:${part(now.getSeconds())}`;
    }

    function trackedUrl(rawUrl, prefix) {
        const target = new URL(rawUrl, window.location.href);
        target.searchParams.set('requestID', `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2, 10)}`);
        target.searchParams.set('timeStamp', ifaTimestamp());
        return target.toString();
    }

    async function refresh() {
        const currentRequest = ++requestNumber;
        if (!strategy.value) {
            preview.textContent = 'Select a strategy to preview eligible work.';
            return;
        }
        preview.textContent = 'Checking module web services…';
        try {
            const cleanersRequest = fetch(trackedUrl(form.dataset.userApi, 'SCH-USR'), {credentials: 'same-origin'});
            let workUrl = form.dataset.binApi;
            if (strategy.value === 'Full Bins') {
                const fullBinsUrl = new URL(workUrl, window.location.href);
                fullBinsUrl.searchParams.set('status', 'Full');
                workUrl = fullBinsUrl.toString();
            }
            if (strategy.value === 'Complaint Priority') workUrl = form.dataset.complaintApi;
            const [cleanersResponse, workResponse] = await Promise.all([
                cleanersRequest,
                fetch(trackedUrl(workUrl, 'SCH-WRK'), {credentials: 'same-origin'}),
            ]);
            const cleaners = await cleanersResponse.json();
            const work = await workResponse.json();
            if (currentRequest !== requestNumber) return;
            if (!cleaners.success || !work.success) throw new Error('Service denied the request.');
            const noun = strategy.value === 'Complaint Priority' ? 'unresolved complaint(s)' : 'eligible bin(s)';
            preview.textContent = `Web services found ${work.meta.count} ${noun} and ${cleaners.meta.count} active cleaner(s).`;
        } catch (error) {
            if (currentRequest !== requestNumber) return;
            preview.textContent = 'Web-service preview is unavailable; server validation will still protect submission.';
        }
    }
    strategy.addEventListener('change', refresh);
    refresh();
})();
