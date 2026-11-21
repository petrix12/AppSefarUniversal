(() => {
    const detected = (() => { try { return Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Bogota'; } catch (_) { return 'America/Bogota'; } })();
    let setting = 'auto';
    try { setting = localStorage.getItem('sefar_meeting_timezone') || 'auto'; } catch (_) {}
    function valid(zone) { try { new Intl.DateTimeFormat('es', {timeZone: zone}).format(); return true; } catch (_) { return false; } }
    function render(scope, zone) {
        scope.querySelectorAll('[data-kalendia-timezone-label]').forEach(node => { node.textContent = zone; });
        scope.querySelectorAll('[data-kalendia-instant]').forEach(node => {
            const date = new Date(node.dataset.kalendiaInstant);
            if (!Number.isFinite(date.getTime())) return;
            node.textContent = new Intl.DateTimeFormat('es', {timeZone:zone, year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).format(date);
            node.title = new Intl.DateTimeFormat('es', {timeZone:zone, dateStyle:'full', timeStyle:'long'}).format(date);
        });
        const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {timeZone:zone,year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date()).map(p=>[p.type,p.value]));
        const today = `${parts.year}-${parts.month}-${parts.day}`;
        scope.querySelectorAll('[data-kalendia-timezone-input]').forEach(input => { input.value = zone; });
        scope.querySelectorAll('[data-kalendia-local-date]').forEach(input => { input.min = today; });
    }
    window.SefarRenderMeetingTimezone = scope => render(scope, window.SefarMeetingTimezone || detected);
    function apply() {
        let zone = setting === 'auto' ? detected : setting;
        if (!valid(zone)) { zone = detected; setting = 'auto'; }
        window.SefarMeetingTimezone = zone;
        render(document, zone);
        const select = document.querySelector('[data-kalendia-timezone]');
        if (select) select.value = setting;
        document.dispatchEvent(new CustomEvent('sefar:meeting-timezone', {detail:{timezone:zone}}));
        const controls = document.querySelector('[data-kalendia-timezone-controls]');
        const token = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('[name="_token"]')?.value;
        if (controls && token) fetch(controls.dataset.preferenceUrl, {method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify({timezone:zone})}).catch(()=>{});
    }
    const init = () => {
        const select = document.querySelector('[data-kalendia-timezone]');
        if (select) {
            select.querySelector('[value="auto"]').textContent = `Automática · ${detected}`;
            if (setting !== 'auto' && !Array.from(select.options).some(o=>o.value===setting)) select.add(new Option(setting,setting));
            select.addEventListener('change',()=>{ setting=select.value;try { localStorage.setItem('sefar_meeting_timezone',setting); } catch (_) {} apply(); });
        }
        apply();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
