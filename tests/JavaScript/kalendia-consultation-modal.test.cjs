const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const script = fs.readFileSync(path.join(__dirname, '../../public/js/kalendia-consultation-modal.js'), 'utf8');
const deferred = () => {
    let resolve;
    const promise = new Promise(done => { resolve = done; });
    return {promise, resolve};
};
function node() {
    const listeners = new Map();
    return {
        dataset: {}, hidden: false, disabled: false, children: [], attributes: {}, textContent: '', innerHTML: '',
        classList: {toggle() {}}, focus() {},
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; },
        append(value) { this.children.push(value); },
        replaceChildren() { this.children = []; this.innerHTML = ''; },
        addEventListener(name, listener) { listeners.set(name, listener); },
        async fire(name, value = {}) {
            const event = {defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...value};
            await listeners.get(name)?.(event);
            return event;
        },
    };
}
function setup(responses) {
    const document = node(), modal = node(), content = node(), feedback = node(), timezone = node(), close = node();
    const steps = [1, 2, 3].map(number => Object.assign(node(), {dataset: {consultationStep: String(number)}}));
    const requests = [], mounted = [];
    let agendaCount = 0, paymentDisposals = 0, agendaDisposals = 0;
    modal.querySelector = selector => ({
        '[data-consultation-content]': content, '[data-consultation-feedback]': feedback, '[data-consultation-timezone]': timezone,
    })[selector];
    modal.querySelectorAll = selector => selector === '[data-consultation-step]' ? steps : [close];
    document.getElementById = () => modal;
    document.createElement = () => node();
    document.readyState = 'complete';
    const jquery = () => ({
        modal() {},
        on(name, listener) { modal.addEventListener(name, listener); return this; },
    });
    jquery.fn = {modal() {}};
    const window = {
        jQuery: jquery, location: {origin: 'https://app.test'},
        SefarMountConsultationPayment: (root, options) => { mounted.push(options); return () => { paymentDisposals++; }; },
        SefarInitRegistrationAgenda: () => { agendaCount++; return () => { agendaDisposals++; }; },
    };
    vm.runInNewContext(script, {
        window, document, URL, FormData: class {constructor(form) { this.form = form; }},
        fetch: async (url, options) => {
            requests.push({url, options});
            const data = await responses.shift();
            return {ok: true, json: async () => data};
        },
    });
    const offerButton = node();
    const offer = {action: 'https://app.test/purchase', closest: selector => selector === '[data-consultation-offer]' ? offer : null, querySelector: () => offerButton};
    const open = () => document.fire('submit', {target: offer, submitter: offerButton});
    const stage = () => steps.find(step => step.attributes['aria-current'] === 'step')?.dataset.consultationStep;
    return {open, stage, modal, content, feedback, timezone, requests, mounted,
        counts: () => ({agendaCount, paymentDisposals, agendaDisposals})};
}
const payment = {paid: false, complete_url: '/confirm', html: '<form>payment</form>'};

test('payment verification opens dates in the same modal and submitting ends with a request', async () => {
    const page = setup([
        payment, {paid: true, next_url: '/reserve'}, {stage: 'schedule', html: '<form>date and time</form>'},
        {message: 'Solicitud registrada', next_url: '/mis-reuniones'},
    ]);
    await page.open();
    assert.equal(page.stage(), '1');
    assert.equal(page.timezone.hidden, true);
    assert.equal(page.content.innerHTML, payment.html);
    await page.mounted[0].onPaid('/confirm');
    assert.equal(page.stage(), '2');
    assert.equal(page.timezone.hidden, false);
    assert.equal(page.content.innerHTML, '<form>date and time</form>');
    assert.deepEqual(page.counts(), {agendaCount: 1, paymentDisposals: 1, agendaDisposals: 0});
    const button = node();
    const form = {action: 'https://app.test/book', closest: () => form, reportValidity: () => true, querySelector: () => button};
    await page.modal.fire('submit', {target: form});
    assert.equal(page.stage(), '3');
    assert.equal(page.feedback.textContent, 'Solicitud registrada');
    assert.equal(page.content.children[0].href, 'https://app.test/mis-reuniones');
    assert.deepEqual(page.requests.map(item => item.url), [
        'https://app.test/purchase', 'https://app.test/confirm', 'https://app.test/reserve', 'https://app.test/book',
    ]);
});

test('an unconfirmed payment stays in payment and cannot be closed while verification is running', async () => {
    const pending = deferred();
    const page = setup([payment, pending.promise]);
    await page.open();
    const checking = page.mounted[0].onPaid('/confirm');
    const hide = await page.modal.fire('hide.bs.modal');
    assert.equal(hide.defaultPrevented, true);
    assert.equal(page.mounted[0].canSubmit(), false);
    pending.resolve({paid: false, next_url: null, message: 'Pago pendiente de confirmación'});
    await checking;
    assert.equal(page.stage(), '1');
    assert.equal(page.feedback.textContent, 'Pago pendiente de confirmación');
    assert.equal(page.counts().agendaCount, 0);
    assert.equal(page.requests.length, 2);
    assert.equal(page.mounted[0].canSubmit(), true);
});

test('closing while a purchase is loading ignores its late response', async () => {
    const pending = deferred();
    const page = setup([pending.promise]);
    const opening = page.open();
    await page.modal.fire('hidden.bs.modal');
    pending.resolve(payment); await opening;
    assert.equal(page.content.innerHTML, '');
    assert.equal(page.mounted.length, 0);
});

test('the modal does not submit a date form already rejected by agenda validation', async () => {
    const page = setup([]);
    const form = {closest: () => form};
    await page.modal.fire('submit', {target: form, defaultPrevented: true});
    assert.equal(page.requests.length, 0);
});
