/* EcoCampus scheduling web-service integration. Author: Ong Kar Heng (2408830). */
(() => {
    const form = document.getElementById('schedule-form');
    const strategy = document.getElementById('strategy');
    const preview = document.getElementById('service-preview');
    if (!form || !strategy || !preview) return;

    let requestNumber = 0;
    async function refresh() {
        const currentRequest = ++requestNumber;
        if (!strategy.value) {
            preview.textContent = 'Select a strategy to preview eligible work.';
            return;
        }
        preview.textContent = 'Checking module web services…';
        try {
            const cleanersRequest = fetch(form.dataset.userApi, {credentials: 'same-origin'});
            let workUrl = form.dataset.binApi;
            if (strategy.value === 'Full Bins') workUrl += '?status=Full';
            if (strategy.value === 'Complaint Priority') workUrl = form.dataset.complaintApi;
            const [cleanersResponse, workResponse] = await Promise.all([
                cleanersRequest,
                fetch(workUrl, {credentials: 'same-origin'}),
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
