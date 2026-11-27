const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const script = fs.readFileSync(path.join(__dirname, '../../public/js/kalendia-consultation-payment.js'), 'utf8');
const flush = () => new Promise(setImmediate);
const deferred = () => {
    let resolve;
    const promise = new Promise(done => { resolve = done; });
    return {promise, resolve};
};

function setup({response, confirm} = {}) {
    const listeners = new Map();
    const button = {disabled: true};
    const message = {textContent: ''};
    const target = {};
    const form = {
        dataset: {intentUrl: '/intent', returnUrl: 'https://app.test/confirm', stripeKey: 'pk_test'},
        querySelector: selector => ({
            '[data-payment-submit]': button, '[data-payment-message]': message,
            '[name="_token"]': {value: 'csrf'}, '[data-payment-element]': target,
        })[selector],
        addEventListener: (name, callback) => listeners.set(name, callback),
        removeEventListener: (name, callback) => { if (listeners.get(name) === callback) listeners.delete(name); },
    };
    const events = {}, requests = [], redirects = [], confirmations = [];
    let mounts = 0, destroys = 0;
    const payment = {
        on: (name, callback) => { events[name] = callback; },
        mount: value => { assert.equal(value, target); mounts++; },
        destroy: () => { destroys++; },
    };
    const elements = {create: type => { assert.equal(type, 'payment'); return payment; }};
    const stripe = {
        elements: () => elements,
        confirmPayment: input => { confirmations.push(input); return confirm ? confirm(input) : Promise.resolve({}); },
    };
    const window = {location: {replace: url => redirects.push(url)}};
    vm.runInNewContext(script, {
        window, document: {readyState: 'complete', querySelector: () => null}, AbortController,
        Stripe: () => stripe,
        fetch: async (url, options) => {
            requests.push({url, options});
            if (response) return response;
            return {ok: true, json: async () => ({client_secret: 'pi_secret'})};
        },
    });
    return {
        mount: options => window.SefarMountConsultationPayment({querySelector: () => form}, options),
        submit: () => listeners.get('submit')?.({preventDefault() {}}),
        ready: () => events.ready(), button, message, requests, redirects, confirmations,
        mounts: () => mounts, destroys: () => destroys,
    };
}

test('reopening a mounted payment uses one intent and disposes the Stripe element once', async () => {
    const page = setup();
    const dispose = page.mount();
    assert.equal(page.mount(), dispose);
    await flush();
    assert.equal(page.requests.length, 1);
    assert.equal(page.mounts(), 1);
    page.ready(); assert.equal(page.button.disabled, false);
    dispose(); dispose();
    assert.equal(page.destroys(), 1);
    assert.equal(page.requests[0].options.signal.aborted, true);
});

test('closing before the intent response arrives cannot mount a stale payment', async () => {
    const pending = deferred();
    const page = setup({response: pending.promise});
    page.mount()();
    pending.resolve({ok: true, json: async () => ({client_secret: 'pi_secret'})});
    await flush();
    assert.equal(page.mounts(), 0);
    assert.equal(page.button.disabled, true);
});

test('a double submit confirms once and waits for the app verification callback', async () => {
    const pending = deferred();
    const checked = [], busy = [];
    const page = setup({confirm: () => pending.promise});
    page.mount({onPaid: async url => checked.push(url), onBusy: value => busy.push(value)});
    await flush(); page.ready();
    const submitted = page.submit();
    await page.submit();
    assert.equal(page.confirmations.length, 1);
    assert.equal(page.button.disabled, true);
    assert.deepEqual(checked, []);
    pending.resolve({paymentIntent: {status: 'succeeded'}});
    await submitted;
    assert.deepEqual(checked, ['https://app.test/confirm']);
    assert.deepEqual(busy, [true, false]);
    assert.deepEqual(page.redirects, []);
    assert.equal(page.confirmations[0].redirect, 'if_required');
});

test('declined payment can retry the same intent without another initialization', async () => {
    let attempt = 0;
    const checked = [];
    const page = setup({confirm: async () => ++attempt === 1 ? {error: {message: 'Tarjeta rechazada'}} : {}});
    page.mount({onPaid: url => checked.push(url)});
    await flush(); page.ready();
    await page.submit();
    assert.equal(page.message.textContent, 'Tarjeta rechazada');
    assert.equal(page.button.disabled, false);
    assert.deepEqual(checked, []);
    await page.submit();
    assert.equal(checked.length, 1);
    assert.equal(page.requests.length, 1);
});

test('an uncertain confirmation asks to check the same purchase before another attempt', async () => {
    const page = setup({confirm: async () => { throw new Error('Network lost'); }});
    page.mount(); await flush(); page.ready();
    await page.submit();
    assert.match(page.message.textContent, /Comprobar el estado de este pago/);
    assert.equal(page.button.disabled, true);
    assert.deepEqual(page.redirects, []);
});

test('a server verification in progress blocks another payment submission', async () => {
    const page = setup();
    page.mount({canSubmit: () => false}); await flush(); page.ready();
    await page.submit();
    assert.equal(page.confirmations.length, 0);
});

test('already paid orders advance through verification without mounting Stripe', async () => {
    const checked = [];
    const page = setup({response: Promise.resolve({ok: true, json: async () => ({paid: true})})});
    page.mount({onPaid: url => checked.push(url)}); await flush();
    assert.deepEqual(checked, ['https://app.test/confirm']);
    assert.equal(page.mounts(), 0);
    assert.equal(page.confirmations.length, 0);
});

test('standalone checkout preserves the server return URL', async () => {
    const page = setup();
    page.mount(); await flush(); page.ready(); await page.submit();
    assert.deepEqual(page.redirects, ['https://app.test/confirm']);
});
