(function () {
    function ready(callback) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", callback);
            return;
        }

        callback();
    }

    ready(function () {
        var root = document.querySelector("[data-register-checkout]");

        if (!root) {
            return;
        }

        var PROGRESS_STORAGE_KEY = "sefar.register.checkout.progress.v1";
        var form = document.getElementById("registerCheckoutForm");
        var loader = document.getElementById("ajaxload");
        var serviceSelect = document.getElementById("servicioSelect");
        var couponInput = document.getElementById("coupon");
        var couponButton = document.getElementById("valcoupon");
        var referralInput = document.getElementById("referral_code");
        var submitButton = document.getElementById("submit-button");
        var buttonText = document.getElementById("button-text");
        var buttonSpinner = document.getElementById("spinner");
        var statusBox = document.getElementById("checkoutStatus");
        var summaryServiceName = document.getElementById("summaryServiceName");
        var summaryItems = document.getElementById("summaryItems");
        var summaryTotal = document.getElementById("summaryTotal");
        var familiarSelect = document.getElementById("tieneHermanos");
        var familiarField = document.getElementById("familiarField");
        var familiarInput = document.getElementById("nombreFamiliar");
        var nameOnCard = document.getElementById("name_on_card");
        var cardErrors = document.getElementById("card-errors");
        var wizardStage = document.querySelector("[data-wizard-stage]");
        var wizardSteps = Array.prototype.slice.call(document.querySelectorAll("[data-wizard-step]"));
        var wizardProgress = Array.prototype.slice.call(document.querySelectorAll("[data-wizard-progress]"));
        var servicePickButtons = Array.prototype.slice.call(document.querySelectorAll("[data-service-pick]"));
        var serviceInsight = document.querySelector("[data-service-insight]");
        var serviceInsightTitle = document.querySelector("[data-service-insight-title]");
        var serviceInsightPitch = document.querySelector("[data-service-insight-pitch]");
        var serviceInsightBest = document.querySelector("[data-service-insight-best]");
        var serviceInsightProof = document.querySelector("[data-service-insight-proof]");
        var serviceInsightLink = document.querySelector("[data-service-insight-link]");
        var contactModal = document.getElementById("registerContactModal");
        var contactOpeners = Array.prototype.slice.call(document.querySelectorAll("[data-open-contact-modal]"));
        var contactClosers = Array.prototype.slice.call(document.querySelectorAll("[data-close-contact-modal]"));
        var confirmationMessage = document.getElementById("confirmationMessage");
        var confirmationAction = document.getElementById("confirmationAction");

        var state = {
            prepared: false,
            preparing: false,
            currentStepIndex: 0,
            wizardAnimating: false,
            redirectTimer: null,
            restoreInProgress: false,
            saveTimer: null,
            csrf: root.dataset.csrf,
            stripe: null,
            cardElement: null,
        };

        restoreSavedProgress();
        initWizard();
        initStripe();
        initThreeBackground();
        initGsapAnimations();
        initPromos();
        bindEvents();
        syncServicePreview();
        syncServiceCards();
        syncServiceConditionals();
        syncFamiliarField();
        syncBillingFromRegistration();
        saveProgress();
        window.__suRegisterCheckoutReady = true;
        document.documentElement.dataset.suRegisterReady = "1";

        function bindEvents() {
            form.addEventListener("submit", submitPayment);

            Array.prototype.slice.call(document.querySelectorAll("[data-wizard-next]")).forEach(function (button) {
                button.addEventListener("click", function (event) {
                    event.preventDefault();
                    nextWizardStep();
                });
            });

            Array.prototype.slice.call(document.querySelectorAll("[data-wizard-prev]")).forEach(function (button) {
                button.addEventListener("click", function (event) {
                    event.preventDefault();
                    previousWizardStep();
                });
            });

            couponButton.addEventListener("click", function (event) {
                event.preventDefault();
                validateCoupon();
            });

            if (serviceSelect) {
                serviceSelect.addEventListener("change", function () {
                    syncServicePreview();
                    syncServiceConditionals();
                    syncServiceCards();
                    scheduleSaveProgress();
                });
            }

            servicePickButtons.forEach(function (button) {
                button.addEventListener("click", function (event) {
                    event.preventDefault();
                    selectServiceFromCard(button.dataset.servicePick, button.dataset.servicePickContinue === "1");
                });
            });

            contactOpeners.forEach(function (button) {
                button.addEventListener("click", function (event) {
                    event.preventDefault();
                    openContactModal();
                });
            });

            contactClosers.forEach(function (button) {
                button.addEventListener("click", function (event) {
                    event.preventDefault();
                    closeContactModal();
                });
            });

            document.addEventListener("keydown", function (event) {
                if (event.key === "Escape") {
                    closeContactModal();
                }
            });

            if (familiarSelect) {
                familiarSelect.addEventListener("change", function () {
                    syncFamiliarField();
                    scheduleSaveProgress();
                });
            }

            ["nombres", "apellidos", "email", "phone"].forEach(function (name) {
                var input = fieldByName(name);
                if (input) {
                    input.addEventListener("input", function () {
                        syncBillingFromRegistration();
                        scheduleSaveProgress();
                    });
                }
            });

            [couponInput, referralInput, nameOnCard].forEach(function (input) {
                if (input) {
                    input.addEventListener("input", function () {
                        input.value = input.value.toUpperCase();
                        scheduleSaveProgress();
                    });
                }
            });

            Array.prototype.slice.call(form.querySelectorAll("input[name], select[name], textarea[name]")).forEach(function (field) {
                field.addEventListener("input", scheduleSaveProgress);
                field.addEventListener("change", scheduleSaveProgress);
            });

            window.addEventListener("beforeunload", saveProgress);
        }

        function initWizard() {
            if (!wizardSteps.length) {
                return;
            }

            wizardSteps.forEach(function (step, index) {
                var isActive = index === state.currentStepIndex;
                step.hidden = !isActive;
                step.classList.toggle("is-active", isActive);
            });

            updateWizardProgress();
        }

        function nextWizardStep() {
            if (state.wizardAnimating || !wizardSteps.length) {
                return;
            }

            if (!validateCurrentWizardStep()) {
                return;
            }

            var nextIndex = Math.min(state.currentStepIndex + 1, wizardSteps.length - 1);
            goToWizardStep(nextIndex, 1);
        }

        function previousWizardStep() {
            if (state.wizardAnimating || !wizardSteps.length) {
                return;
            }

            var previousIndex = Math.max(state.currentStepIndex - 1, 0);
            goToWizardStep(previousIndex, -1);
        }

        function validateCurrentWizardStep() {
            var step = wizardSteps[state.currentStepIndex];

            if (!step) {
                return true;
            }

            if (step.dataset.wizardStep === "cliente") {
                return validateFieldsWithin(step, "[data-client-field]");
            }

            if (step.dataset.wizardStep === "servicio") {
                return validateFieldsWithin(step, "[data-service-field]");
            }

            return true;
        }

        function goToWizardStep(nextIndex, direction) {
            if (!wizardSteps.length || nextIndex === state.currentStepIndex) {
                return;
            }

            var currentStep = wizardSteps[state.currentStepIndex];
            var nextStep = wizardSteps[nextIndex];

            if (!nextStep || !currentStep) {
                return;
            }

            if (nextStep.dataset.wizardStep === "pago") {
                syncBillingFromRegistration();
                setStatus("Revisa tu resumen, aplica un cupon o completa el pago.", "working");
            }

            state.wizardAnimating = true;
            state.currentStepIndex = nextIndex;
            updateWizardProgress();
            saveProgress();

            var previousHeight = currentStep.offsetHeight;
            currentStep.hidden = true;
            currentStep.classList.remove("is-active");
            nextStep.hidden = false;
            nextStep.classList.add("is-active");
            scrollWizardIntoView();

            if (!window.gsap) {
                state.wizardAnimating = false;
                focusFirstStepField(nextStep);
                return;
            }

            var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
            var motionScale = reduceMotion ? 0.45 : 1;
            var distance = (direction || 1) * 42 * motionScale;

            window.gsap.killTweensOf([currentStep, nextStep]);
            window.gsap.timeline({
                defaults: { ease: "power3.out" },
                onComplete: function () {
                    state.wizardAnimating = false;
                    if (wizardStage) {
                        wizardStage.style.minHeight = "";
                    }
                    focusFirstStepField(nextStep);
                },
            })
                .set(wizardStage, { minHeight: Math.max(previousHeight, nextStep.offsetHeight, 520) })
                .fromTo(nextStep, {
                    x: distance,
                    scale: 0.985,
                    autoAlpha: 0,
                }, {
                    x: 0,
                    scale: 1,
                    autoAlpha: 1,
                    duration: 0.46 * motionScale,
                    clearProps: "transform,opacity,visibility",
                })
                .from(stepAccentTargets(nextStep), {
                    y: 18 * motionScale,
                    autoAlpha: 0,
                    stagger: 0.035 * motionScale,
                    duration: 0.34 * motionScale,
                    ease: "power2.out",
                    clearProps: "transform,opacity,visibility",
                }, "<0.08");
        }

        function updateWizardProgress() {
            var activeStep = wizardSteps[state.currentStepIndex];
            var activeName = activeStep ? activeStep.dataset.wizardStep : null;
            var activeProgress = null;

            root.dataset.currentStep = activeName || "";

            wizardProgress.forEach(function (progress) {
                var isActive = progress.dataset.wizardProgress === activeName;
                progress.classList.toggle("is-active", isActive);
                if (isActive) {
                    activeProgress = progress;
                }
            });

            if (activeProgress && window.gsap) {
                window.gsap.fromTo(activeProgress, {
                    scale: 0.96,
                }, {
                    scale: 1,
                    duration: 0.34,
                    ease: "back.out(1.8)",
                    clearProps: "transform",
                });
            }
        }

        function stepAccentTargets(step) {
            return Array.prototype.slice.call(step.querySelectorAll(
                ".su-field, .su-step-guide, .su-service-insight, .su-welcome__proof, .su-guide-list span, .su-card-element, .su-confirmation .su-primary-btn, .su-nationality-card, .su-contact-strip"
            ));
        }

        function focusFirstStepField(step) {
            var field = step.querySelector("input:not([type='hidden']):not(:disabled), select:not(:disabled), button:not(:disabled), a[href]");

            if (field && !["nacionalidades", "bienvenida"].includes(step.dataset.wizardStep)) {
                field.focus({ preventScroll: true });
            }
        }

        function scrollWizardIntoView() {
            if (!root || window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
                return;
            }

            var top = root.getBoundingClientRect().top + window.pageYOffset;
            window.scrollTo({ top: Math.max(top - 10, 0), behavior: "smooth" });
        }

        function initStripe() {
            if (!window.Stripe || !root.dataset.stripeDefaultKey) {
                setStatus("Stripe no esta configurado.", "error");
                return;
            }

            state.stripe = window.Stripe(root.dataset.stripeDefaultKey);
            var elements = state.stripe.elements();
            state.cardElement = elements.create("card", {
                hidePostalCode: true,
                style: {
                    base: {
                        color: "#102b3a",
                        fontFamily: "Nunito, Arial, sans-serif",
                        fontSize: "16px",
                        "::placeholder": {
                            color: "#80909c",
                        },
                    },
                    invalid: {
                        color: "#b42318",
                        iconColor: "#b42318",
                    },
                },
            });

            state.cardElement.mount("#card-element");
            state.cardElement.on("change", function (event) {
                cardErrors.textContent = event.error ? event.error.message : "";
                document.querySelector(".su-card-element").classList.toggle("has-error", Boolean(event.error));
            });
        }

        function submitPayment(event) {
            event.preventDefault();

            runAsync(async function () {
                if (!state.stripe || !state.cardElement) {
                    throw new Error("Stripe no esta disponible en este momento.");
                }

                await ensureRegistration();

                if (!validateFields("[data-payment-field]")) {
                    throw new Error("Completa los datos de facturacion antes de pagar.");
                }

                setButtonBusy(true);
                setStatus("Validando tarjeta...", "working");
                markStep("payment");

                var paymentMethodResult = await state.stripe.createPaymentMethod({
                    type: "card",
                    card: state.cardElement,
                    billing_details: {
                        name: valueOf("name_on_card"),
                        email: valueOf("email"),
                        phone: valueOf("phone"),
                        address: {
                            line1: valueOf("address_line1"),
                            line2: valueOf("address_line2"),
                            city: valueOf("city"),
                            state: valueOf("state"),
                            postal_code: valueOf("postal_code"),
                            country: valueOf("country"),
                        },
                    },
                });

                if (paymentMethodResult.error) {
                    cardErrors.textContent = paymentMethodResult.error.message;
                    throw new Error(paymentMethodResult.error.message);
                }

                setStatus("Procesando pago...", "working");

                var payload = await fetchJson(root.dataset.paymentUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": state.csrf,
                    },
                    body: JSON.stringify({
                        checkout_context: "registration",
                        payment_method_id: paymentMethodResult.paymentMethod.id,
                        first_name: valueOf("first_name"),
                        last_name: valueOf("last_name"),
                        email: valueOf("email"),
                        phone: valueOf("phone"),
                        address_line1: valueOf("address_line1"),
                        address_line2: valueOf("address_line2"),
                        city: valueOf("city"),
                        state: valueOf("state"),
                        postal_code: valueOf("postal_code"),
                        country: valueOf("country"),
                        referral_code: valueOf("referral_code"),
                    }),
                });

                if (!payload.success) {
                    throw new Error(payload.message || "No se pudo procesar el pago.");
                }

                var nextUrl = payload.next_url || root.dataset.getinfoUrl;
                setStatus("Pago completado. Preparando el siguiente paso...", "success");
                markStep("next");
                showConfirmation("Pago confirmado. Te llevaremos al siguiente formulario para completar la informacion detallada del caso.", nextUrl);
            }, {
                errorTitle: "No se proceso el pago",
            }).finally(function () {
                setButtonBusy(false);
            });
        }

        function validateCoupon() {
            runAsync(async function () {
                if (!couponInput.value.trim()) {
                    throw new Error("Ingresa un codigo de cupon.");
                }

                await ensureRegistration();

                setStatus("Validando cupon...", "working");
                markStep("coupon");
                couponButton.disabled = true;

                var url = new URL(root.dataset.couponUrl, window.location.origin);
                url.searchParams.set("cpn", couponInput.value.trim().toUpperCase());
                url.searchParams.set("referral_code", referralInput.value.trim().toUpperCase());

                var payload = await fetchJson(url.toString(), {
                    headers: {
                        "Accept": "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                });

                if (payload.status === "true") {
                    setStatus("Cupon aplicado. Preparando el siguiente paso...", "success");
                    markStep("next");
                    showConfirmation("Cupon aplicado. Tu registro quedo confirmado y continuaremos con el siguiente formulario.", payload.next_url || root.dataset.getinfoUrl);
                    return;
                }

                if (payload.status === "halftrue" || payload.status === "promo") {
                    couponInput.readOnly = true;
                    await refreshSummary();
                    notify("success", "Cupon aplicado", "El resumen fue actualizado.");
                    setStatus("Cupon aplicado.", "success");
                    return;
                }

                if (payload.status === "fechabad") {
                    throw new Error("El cupon esta vencido.");
                }

                if (payload.status === "referralfalse") {
                    throw new Error(payload.message || "El codigo de referido no existe o no esta activo.");
                }

                throw new Error("Cupon invalido.");
            }).finally(function () {
                couponButton.disabled = false;
            });
        }

        async function ensureRegistration() {
            if (state.prepared || state.preparing) {
                return;
            }

            if (!validateFields("[data-registration-field]")) {
                throw new Error("Completa los datos de registro antes de continuar.");
            }

            state.preparing = true;
            setLoading(true);
            setStatus("Creando registro...", "working");

            try {
                var formData = new FormData(form);
                var payload = await fetchJson(root.dataset.prepareUrl, {
                    method: "POST",
                    headers: {
                        "Accept": "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": state.csrf,
                    },
                    body: formData,
                });

                state.prepared = true;
                updateCsrf(payload.csrf_token);
                updateSummary(payload.summary);
                lockRegistrationFields();
                syncBillingFromRegistration();
                markStep("register");
                setStatus("Registro creado. Puedes pagar o aplicar un cupon.", "success");

                if (buttonText) {
                    buttonText.textContent = "Pagar registro";
                }
            } catch (error) {
                if (error.payload && error.payload.login_url) {
                    notify("info", "Usuario existente", error.message);
                    setTimeout(function () {
                        window.location.href = error.payload.login_url;
                    }, 1800);
                    error.silent = true;
                }

                throw error;
            } finally {
                state.preparing = false;
                setLoading(false);
            }
        }

        async function refreshSummary() {
            var payload = await fetchJson(root.dataset.summaryUrl, {
                headers: {
                    "Accept": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            updateCsrf(payload.csrf_token);
            updateSummary(payload.summary);
        }

        function updateSummary(summary) {
            if (!summary || !summary.has_items) {
                syncServicePreview();
                return;
            }

            summaryItems.innerHTML = "";

            summary.items.forEach(function (item) {
                var node = document.createElement("div");
                node.className = "su-summary__item";
                node.innerHTML = "<strong></strong><span></span>";
                node.querySelector("strong").textContent = item.description;
                node.querySelector("span").textContent = item.amount_label + (item.coupon_applied ? " - cupon aplicado" : "");
                summaryItems.appendChild(node);
            });

            summaryTotal.textContent = summary.total_label;

            if (window.gsap) {
                window.gsap.fromTo(summaryTotal, { scale: 0.96 }, { scale: 1, duration: 0.35, ease: "back.out(1.6)" });
            }
        }

        function syncServicePreview() {
            if (!serviceSelect) {
                return;
            }

            var option = serviceSelect.options[serviceSelect.selectedIndex];

            if (!option || !option.value) {
                summaryServiceName.textContent = "Selecciona un servicio";
                summaryItems.innerHTML = '<div class="su-summary__empty">El total se confirmara antes de cobrar.</div>';
                summaryTotal.textContent = "0,00 EUR";
                syncServiceInsight(null);
                return;
            }

            var name = option.dataset.name || option.textContent.trim();
            var price = Number(option.dataset.price || 0);
            var currency = option.dataset.currency || "EUR";
            summaryServiceName.textContent = name;
            summaryItems.innerHTML = "";

            var node = document.createElement("div");
            node.className = "su-summary__item";
            node.innerHTML = "<strong></strong><span></span>";
            node.querySelector("strong").textContent = name;
            node.querySelector("span").textContent = price > 0
                ? formatMoney(price, currency)
                : "Monto a confirmar";
            summaryItems.appendChild(node);
            summaryTotal.textContent = price > 0 ? formatMoney(price, currency) : "0,00 " + currency;
            syncServiceInsight(option);
        }

        function syncServiceInsight(option) {
            if (!serviceInsight) {
                return;
            }

            var title = option && option.value ? option.dataset.name || option.textContent.trim() : "";
            var pitch = option && option.value ? option.dataset.pitch || "" : "";
            var bestFor = option && option.value ? option.dataset.bestFor || "" : "";
            var proof = option && option.value ? option.dataset.proof || "" : "";
            var landingUrl = option && option.value ? option.dataset.landingUrl || "" : "";
            var shouldShow = Boolean(title || pitch || bestFor || proof || landingUrl);

            serviceInsight.hidden = !shouldShow;

            if (!shouldShow) {
                return;
            }

            setInsightText(serviceInsightTitle, title);
            setInsightText(serviceInsightPitch, pitch);
            setInsightText(serviceInsightBest, bestFor);
            setInsightText(serviceInsightProof, proof);

            if (serviceInsightLink) {
                serviceInsightLink.hidden = !landingUrl;
                serviceInsightLink.href = landingUrl || "#";
            }

            if (window.gsap) {
                window.gsap.fromTo(serviceInsight, {
                    y: 10,
                    autoAlpha: 0.85,
                }, {
                    y: 0,
                    autoAlpha: 1,
                    duration: 0.24,
                    ease: "power2.out",
                    clearProps: "transform,opacity,visibility",
                });
            }
        }

        function setInsightText(node, value) {
            if (!node) {
                return;
            }

            node.textContent = value || "";
            node.hidden = !value;
        }

        function selectServiceFromCard(serviceValue, shouldContinue) {
            if (!serviceSelect || !serviceValue) {
                return;
            }

            var option = Array.prototype.slice.call(serviceSelect.options).find(function (item) {
                return item.value === serviceValue;
            });

            if (!option) {
                notify("info", "Servicio no disponible", "Este servicio no esta disponible para registro en este momento.");
                return;
            }

            serviceSelect.value = serviceValue;
            serviceSelect.dispatchEvent(new Event("change", { bubbles: true }));
            setStatus("Servicio seleccionado. Puedes continuar el registro.", "success");
            saveProgress();

            if (!shouldContinue) {
                return;
            }

            var welcomeIndex = wizardSteps.findIndex(function (step) {
                return step.dataset.wizardStep === "bienvenida";
            });

            if (welcomeIndex >= 0) {
                goToWizardStep(welcomeIndex, 1);
            }
        }

        function syncServiceCards() {
            if (!servicePickButtons.length || !serviceSelect) {
                return;
            }

            servicePickButtons.forEach(function (button) {
                button.classList.toggle("is-selected", button.dataset.servicePick === serviceSelect.value);
            });
        }

        function syncServiceConditionals() {
            var profile = selectedServiceProfile();

            Array.prototype.slice.call(form.querySelectorAll("[data-service-conditional]")).forEach(function (wrapper) {
                var shouldShow = matchesServiceRule(wrapper.dataset.serviceConditional, profile);
                setConditionalFieldVisibility(wrapper, shouldShow);
            });
        }

        function selectedServiceProfile() {
            var option = serviceSelect && serviceSelect.options[serviceSelect.selectedIndex];
            var label = option
                ? normalizeText([
                    option.value,
                    option.dataset.name,
                    option.dataset.category,
                    option.textContent,
                ].join(" "))
                : "";

            var isItalian = containsAny(label, ["italia", "italian", "italiana"]);
            var isSpanish = containsAny(label, ["espana", "espanol", "espanola", "carta de naturaleza"]);
            var isPortuguese = containsAny(label, ["portugal", "portugues", "portuguesa"]);
            var isSefardi = containsAny(label, ["sefardi", "sefard"]);
            var isNationality = containsAny(label, ["nacionalidad", "ciudadania"]) || isItalian || isSpanish || isPortuguese || isSefardi;

            return {
                hasService: Boolean(option && option.value),
                italian: isItalian,
                spanish: isSpanish,
                portuguese: isPortuguese,
                sefardi: isSefardi,
                nationality: isNationality,
                alzada: containsAny(label, ["alzada", "recurso de alzada"]),
            };
        }

        function matchesServiceRule(rule, profile) {
            if (!profile.hasService || !rule) {
                return false;
            }

            return rule.split(",").some(function (part) {
                var key = part.trim();
                return Boolean(key && profile[key]);
            });
        }

        function setConditionalFieldVisibility(wrapper, shouldShow) {
            var fields = Array.prototype.slice.call(wrapper.querySelectorAll("input, select, textarea"));

            wrapper.classList.toggle("is-hidden", !shouldShow);

            fields.forEach(function (field) {
                if (field.dataset.requiredWhenVisible == null && field.hasAttribute("required")) {
                    field.dataset.requiredWhenVisible = "1";
                }

                if (shouldShow) {
                    field.disabled = false;

                    if (field.dataset.requiredWhenVisible === "1") {
                        field.setAttribute("required", "required");
                    }

                    if (field.tagName === "SELECT" && !field.value && field.options.length) {
                        field.value = field.options[0].value;
                    }

                    if (!field.value && field.defaultValue && field.type !== "checkbox" && field.type !== "radio") {
                        field.value = field.defaultValue;
                    }

                    return;
                }

                field.disabled = true;
                field.removeAttribute("required");
                field.closest(".su-field")?.classList.remove("has-error");

                if (field.type === "checkbox" || field.type === "radio") {
                    field.checked = false;
                    return;
                }

                field.value = "";
            });

            if (shouldShow && window.gsap && !state.restoreInProgress) {
                window.gsap.fromTo(wrapper, {
                    y: 8,
                    autoAlpha: 0,
                }, {
                    y: 0,
                    autoAlpha: 1,
                    duration: 0.24,
                    ease: "power2.out",
                    clearProps: "transform,opacity,visibility",
                });
            }
        }

        function normalizeText(value) {
            return String(value || "")
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .toLowerCase();
        }

        function containsAny(text, patterns) {
            return patterns.some(function (pattern) {
                return text.indexOf(pattern) !== -1;
            });
        }

        function syncFamiliarField() {
            var needsFamily = familiarSelect && familiarSelect.value === "1";

            familiarField.classList.toggle("is-hidden", !needsFamily);

            if (needsFamily) {
                familiarInput.setAttribute("required", "required");
            } else {
                familiarInput.removeAttribute("required");
                familiarInput.value = "";
            }
        }

        function syncBillingFromRegistration() {
            setIfEmpty("first_name", valueOfByName("nombres"));
            setIfEmpty("last_name", valueOfByName("apellidos"));
            setIfEmpty("email", valueOfByName("email"));
            setIfEmpty("phone", valueOfByName("phone"));
        }

        function validateFields(selector) {
            return validateFieldsWithin(form, selector);
        }

        function validateFieldsWithin(container, selector) {
            var fields = Array.prototype.slice.call(container.querySelectorAll(selector));
            var valid = true;
            var firstInvalid = null;

            fields.forEach(function (field) {
                if (field.disabled || field.closest(".is-hidden")) {
                    return;
                }

                var wrapper = field.closest(".su-field") || field.closest(".su-terms") || field;
                var isValid = field.checkValidity();
                wrapper.classList.toggle("has-error", !isValid);

                if (!isValid && !firstInvalid) {
                    firstInvalid = field;
                    valid = false;
                }
            });

            if (!valid && firstInvalid) {
                firstInvalid.reportValidity();
                firstInvalid.focus({ preventScroll: false });
            }

            return valid;
        }

        function lockRegistrationFields() {
            Array.prototype.slice.call(form.querySelectorAll("[data-registration-field]")).forEach(function (field) {
                field.disabled = true;
            });
        }

        async function fetchJson(url, options) {
            var response = await fetch(url, Object.assign({
                credentials: "same-origin",
            }, options || {}));
            var payload = await response.json().catch(function () {
                return {};
            });

            if (!response.ok) {
                var message = payload.message || firstValidationMessage(payload.errors) || "No se pudo completar la accion.";
                var error = new Error(message);
                error.payload = payload;
                throw error;
            }

            return payload;
        }

        function firstValidationMessage(errors) {
            if (!errors) {
                return null;
            }

            var firstKey = Object.keys(errors)[0];
            return firstKey && errors[firstKey] && errors[firstKey][0] ? errors[firstKey][0] : null;
        }

        function runAsync(task, options) {
            options = options || {};
            setLoading(true);

            return Promise.resolve()
                .then(task)
                .catch(function (error) {
                    if (!error.silent) {
                        notify("error", options.errorTitle || "Revisa los datos", error.message || "No se pudo completar la accion.");
                        setStatus(error.message || "No se pudo completar la accion.", "error");
                    }
                })
                .finally(function () {
                    setLoading(false);
                });
        }

        function setButtonBusy(isBusy) {
            submitButton.disabled = isBusy;
            buttonSpinner.hidden = !isBusy;
            buttonText.hidden = isBusy;
        }

        function setLoading(isLoading) {
            loader.hidden = !isLoading;
        }

        function setStatus(message, type) {
            statusBox.textContent = message;
            statusBox.dataset.status = type || "idle";
        }

        function markStep(step) {
            Array.prototype.slice.call(document.querySelectorAll("[data-step-dot]")).forEach(function (dot) {
                dot.classList.toggle("is-active", dot.dataset.stepDot === step);
            });
        }

        function showConfirmation(message, nextUrl) {
            var targetUrl = nextUrl || root.dataset.getinfoUrl;
            var confirmationIndex = wizardSteps.findIndex(function (step) {
                return step.dataset.wizardStep === "confirmacion";
            });

            if (confirmationMessage) {
                confirmationMessage.textContent = message || "Pago confirmado. Te llevaremos al siguiente formulario para completar la informacion detallada del caso.";
            }

            if (confirmationAction) {
                confirmationAction.href = targetUrl;
            }

            if (confirmationIndex >= 0) {
                goToWizardStep(confirmationIndex, 1);
            }

            clearSavedProgress();

            if (state.redirectTimer) {
                window.clearTimeout(state.redirectTimer);
            }

            state.redirectTimer = window.setTimeout(function () {
                window.location.href = targetUrl;
            }, 2200);
        }

        function restoreSavedProgress() {
            var saved = readSavedProgress();

            if (!saved) {
                return;
            }

            state.restoreInProgress = true;

            try {
                Object.keys(saved.fields || {}).forEach(function (name) {
                    Array.prototype.slice.call(form.querySelectorAll('[name="' + name + '"]')).forEach(function (field) {
                        if (!shouldPersistField(field)) {
                            return;
                        }

                        if (field.type === "checkbox" || field.type === "radio") {
                            field.checked = Boolean(saved.fields[name]);
                            return;
                        }

                        field.value = saved.fields[name];
                    });
                });

                if (Number.isFinite(saved.stepIndex)) {
                    var maxRestorableStep = Math.max(wizardSteps.length - 2, 0);
                    state.currentStepIndex = Math.min(Math.max(saved.stepIndex, 0), maxRestorableStep);
                }
            } finally {
                state.restoreInProgress = false;
            }
        }

        function readSavedProgress() {
            var storage = getProgressStorage();

            if (!storage) {
                return null;
            }

            try {
                var raw = storage.getItem(PROGRESS_STORAGE_KEY);

                if (!raw) {
                    return null;
                }

                var parsed = JSON.parse(raw);
                var maxAge = 1000 * 60 * 60 * 24 * 14;

                if (!parsed.savedAt || Date.now() - parsed.savedAt > maxAge) {
                    clearSavedProgress();
                    return null;
                }

                return parsed;
            } catch (error) {
                clearSavedProgress();
                return null;
            }
        }

        function scheduleSaveProgress() {
            if (state.restoreInProgress) {
                return;
            }

            window.clearTimeout(state.saveTimer);
            state.saveTimer = window.setTimeout(saveProgress, 180);
        }

        function saveProgress() {
            var storage = getProgressStorage();

            if (state.restoreInProgress || !storage) {
                return;
            }

            try {
                var fields = {};

                Array.prototype.slice.call(form.querySelectorAll("input[name], select[name], textarea[name]")).forEach(function (field) {
                    if (!shouldPersistField(field)) {
                        return;
                    }

                    fields[field.name] = field.type === "checkbox" || field.type === "radio"
                        ? field.checked
                        : field.value;
                });

                storage.setItem(PROGRESS_STORAGE_KEY, JSON.stringify({
                    savedAt: Date.now(),
                    stepIndex: state.currentStepIndex,
                    fields: fields,
                }));
            } catch (error) {
                // El formulario debe seguir funcionando aunque el navegador bloquee localStorage.
            }
        }

        function clearSavedProgress() {
            var storage = getProgressStorage();

            if (!storage) {
                return;
            }

            try {
                storage.removeItem(PROGRESS_STORAGE_KEY);
            } catch (error) {
                // Sin efecto visible para el cliente.
            }
        }

        function getProgressStorage() {
            try {
                return window.localStorage || null;
            } catch (error) {
                return null;
            }
        }

        function shouldPersistField(field) {
            if (!field || !field.name) {
                return false;
            }

            var type = (field.type || "").toLowerCase();
            var excludedTypes = ["hidden", "password", "file", "submit", "button", "reset"];

            return excludedTypes.indexOf(type) === -1 && !isCardSensitiveField(field);
        }

        function isCardSensitiveField(field) {
            var fingerprint = normalizeText([
                field.name,
                field.id,
                field.getAttribute("autocomplete"),
                field.getAttribute("aria-label"),
            ].join(" "));

            if (field.closest(".su-card-element")) {
                return true;
            }

            return containsAny(fingerprint, [
                "name_on_card",
                "card",
                "tarjeta",
                "cc-name",
                "cc-number",
                "cc-exp",
                "cc-csc",
                "cvc",
                "cvv",
            ]);
        }

        function notify(icon, title, text) {
            if (window.Swal) {
                window.Swal.fire({
                    icon: icon,
                    title: title,
                    text: text,
                    showConfirmButton: icon === "error",
                    timer: icon === "error" ? undefined : 2600,
                });
                return;
            }

            window.alert(title + "\n" + text);
        }

        function openContactModal() {
            if (!contactModal) {
                return;
            }

            contactModal.hidden = false;
            document.documentElement.classList.add("su-modal-open");

            var firstAction = contactModal.querySelector("a, button");
            if (firstAction) {
                firstAction.focus({ preventScroll: true });
            }

            if (window.gsap) {
                window.gsap.fromTo(contactModal.querySelector(".su-contact-modal__dialog"), {
                    y: 22,
                    scale: 0.98,
                    autoAlpha: 0,
                }, {
                    y: 0,
                    scale: 1,
                    autoAlpha: 1,
                    duration: 0.28,
                    ease: "power2.out",
                    clearProps: "transform,opacity,visibility",
                });
            }
        }

        function closeContactModal() {
            if (!contactModal || contactModal.hidden) {
                return;
            }

            contactModal.hidden = true;
            document.documentElement.classList.remove("su-modal-open");
        }

        function updateCsrf(token) {
            if (!token) {
                return;
            }

            state.csrf = token;
            var csrfInput = form.querySelector('input[name="_token"]');
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');

            if (csrfInput) {
                csrfInput.value = token;
            }

            if (csrfMeta) {
                csrfMeta.setAttribute("content", token);
            }
        }

        function valueOf(id) {
            var input = document.getElementById(id);
            return input ? input.value.trim() : "";
        }

        function valueOfByName(name) {
            var input = fieldByName(name);
            return input ? input.value.trim() : "";
        }

        function fieldByName(name) {
            return form.querySelector('[name="' + name + '"]');
        }

        function setIfEmpty(id, value) {
            var input = document.getElementById(id);
            if (input && (!input.value || !state.prepared)) {
                input.value = value || "";
            }
        }

        function formatMoney(value, currency) {
            return new Intl.NumberFormat("es-ES", {
                style: "currency",
                currency: currency || "EUR",
                currencyDisplay: "code",
            }).format(value).replace("EUR", "EUR");
        }

        function initGsapAnimations() {
            if (!window.gsap) {
                return;
            }

            var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
            var motionScale = reduceMotion ? 0.45 : 1;
            var timeline = window.gsap.timeline({
                defaults: { duration: 0.55 * motionScale, ease: "power2.out" },
            });

            timeline.from("[data-animate-panel]", {
                y: 24 * motionScale,
                autoAlpha: 0,
                stagger: 0.08,
            });

            window.gsap.to(".su-summary__inner", {
                y: -4 * motionScale,
                duration: 2.8 / motionScale,
                repeat: -1,
                yoyo: true,
                ease: "sine.inOut",
            });
        }

        function initThreeBackground() {
            if (!window.THREE) {
                return;
            }

            var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
            var motionScale = reduceMotion ? 0.45 : 1;
            var container = document.getElementById("registerConstellationScene");
            var width = window.innerWidth;
            var height = window.innerHeight;
            var scene = new window.THREE.Scene();
            var camera = new window.THREE.PerspectiveCamera(55, width / height, 0.1, 100);
            var renderer = new window.THREE.WebGLRenderer({ antialias: true, alpha: true });
            var clock = new window.THREE.Clock();
            var count = width < 700 ? 58 : 118;
            var points = [];
            var velocities = [];
            var pointerTarget = new window.THREE.Vector2(0, 0);
            var pointerCurrent = new window.THREE.Vector2(0, 0);
            var pointerPulse = 0;
            var pointPositions = new Float32Array(count * 3);
            var linePositions = new Float32Array(count * count * 3);

            renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
            renderer.setSize(width, height);
            renderer.setClearColor(0xffffff, 0);
            renderer.domElement.classList.add("su-register__scene-canvas");
            container.appendChild(renderer.domElement);

            camera.position.z = 17.5;

            for (var i = 0; i < count; i += 1) {
                var point = new window.THREE.Vector3(
                    (Math.random() - 0.5) * 34,
                    (Math.random() - 0.5) * 18,
                    (Math.random() - 0.5) * 8
                );
                points.push(point);
                velocities.push(new window.THREE.Vector3(
                    (Math.random() - 0.5) * 0.16,
                    (Math.random() - 0.5) * 0.12,
                    (Math.random() - 0.5) * 0.06
                ));
                point.toArray(pointPositions, i * 3);
            }

            var pointGeometry = new window.THREE.BufferGeometry();
            pointGeometry.setAttribute("position", new window.THREE.BufferAttribute(pointPositions, 3));
            var pointMaterial = new window.THREE.PointsMaterial({
                color: 0x7b8a94,
                size: width < 700 ? 0.1 : 0.078,
                transparent: true,
                opacity: 0.66,
            });
            var pointMesh = new window.THREE.Points(pointGeometry, pointMaterial);
            scene.add(pointMesh);

            var lineGeometry = new window.THREE.BufferGeometry();
            lineGeometry.setAttribute("position", new window.THREE.BufferAttribute(linePositions, 3));
            var lineMaterial = new window.THREE.LineBasicMaterial({
                color: 0xb8c1c7,
                transparent: true,
                opacity: 0.22,
            });
            var lineMesh = new window.THREE.LineSegments(lineGeometry, lineMaterial);
            scene.add(lineMesh);

            function animate() {
                var delta = Math.min(clock.getDelta(), 0.033);
                var elapsed = clock.getElapsedTime();
                window.__suConstellationFrames = (window.__suConstellationFrames || 0) + 1;
                document.documentElement.dataset.suConstellationFrames = String(window.__suConstellationFrames);

                updatePoints(delta, elapsed);
                pointerCurrent.lerp(pointerTarget, 0.08 * motionScale);
                pointerPulse = Math.max(pointerPulse - delta * 0.75, 0);
                camera.position.x = pointerCurrent.x * 1.9 * motionScale;
                camera.position.y = pointerCurrent.y * 1.15 * motionScale;
                camera.lookAt(0, 0, 0);
                renderer.domElement.style.transform = "translate3d("
                    + (pointerCurrent.x * 34 * motionScale).toFixed(2)
                    + "px,"
                    + (pointerCurrent.y * 24 * motionScale).toFixed(2)
                    + "px,0) scale(1.08)";
                pointMaterial.opacity = 0.48 + Math.sin(elapsed * 1.55) * 0.07 + pointerPulse * 0.28;
                pointMaterial.size = (width < 700 ? 0.1 : 0.078) + pointerPulse * 0.035;
                lineMaterial.opacity = 0.16 + Math.sin(elapsed * 1.2) * 0.045 + pointerPulse * 0.18;
                pointMesh.rotation.z += delta * (0.12 + pointerPulse * 0.18) * motionScale;
                lineMesh.rotation.z -= delta * (0.04 + pointerPulse * 0.08) * motionScale;
                scene.rotation.x = Math.sin(elapsed * 0.22) * (0.045 + pointerPulse * 0.035) * motionScale;
                scene.rotation.y = Math.cos(elapsed * 0.18) * (0.06 + pointerPulse * 0.045) * motionScale;

                updateLines();
                renderer.render(scene, camera);
                window.requestAnimationFrame(animate);
            }

            function updatePoints(delta, elapsed) {
                var cursorX = pointerCurrent.x * 15;
                var cursorY = pointerCurrent.y * 8;

                for (var i = 0; i < points.length; i += 1) {
                    points[i].addScaledVector(velocities[i], delta * 34 * motionScale);
                    points[i].y += Math.sin(elapsed * 0.9 + i * 0.37) * delta * 0.22 * motionScale;
                    points[i].x += Math.cos(elapsed * 0.7 + i * 0.21) * delta * 0.18 * motionScale;

                    if (pointerPulse > 0.02) {
                        var dx = cursorX - points[i].x;
                        var dy = cursorY - points[i].y;
                        var distance = Math.sqrt(dx * dx + dy * dy);

                        if (distance < 8) {
                            var influence = (1 - distance / 8) * pointerPulse * delta * motionScale;
                            points[i].x += dx * influence * 0.42;
                            points[i].y += dy * influence * 0.42;
                            points[i].z += Math.sin(elapsed * 2 + i) * influence * 2.2;
                        }
                    }

                    if (points[i].x > 17 || points[i].x < -17) {
                        velocities[i].x *= -1;
                    }
                    if (points[i].y > 9 || points[i].y < -9) {
                        velocities[i].y *= -1;
                    }
                    if (points[i].z > 4 || points[i].z < -4) {
                        velocities[i].z *= -1;
                    }

                    points[i].toArray(pointPositions, i * 3);
                }

                pointGeometry.attributes.position.needsUpdate = true;
                pointMesh.rotation.z += delta * 0.04 * motionScale;
            }

            function updateLines() {
                var ptr = 0;
                var maxDistance = 4.8;
                var maxSegments = count < 70 ? 120 : 240;
                var segments = 0;

                for (var i = 0; i < count; i += 1) {
                    for (var j = i + 1; j < count; j += 1) {
                        if (segments >= maxSegments) {
                            break;
                        }

                        if (points[i].distanceTo(points[j]) < maxDistance) {
                            points[i].toArray(linePositions, ptr);
                            ptr += 3;
                            points[j].toArray(linePositions, ptr);
                            ptr += 3;
                            segments += 1;
                        }
                    }
                }

                lineGeometry.setDrawRange(0, segments * 2);
                lineGeometry.attributes.position.needsUpdate = true;
            }

            window.addEventListener("resize", function () {
                width = window.innerWidth;
                height = window.innerHeight;
                camera.aspect = width / height;
                camera.updateProjectionMatrix();
                renderer.setSize(width, height);
            });

            window.addEventListener("pointermove", function (event) {
                pointerTarget.x = (event.clientX / Math.max(width, 1) - 0.5) * 2;
                pointerTarget.y = -(event.clientY / Math.max(height, 1) - 0.5) * 2;
                pointerPulse = Math.min(pointerPulse + 0.28, 1);
            }, { passive: true });

            animate();
        }

        function initPromos() {
            var script = document.getElementById("registerAlertas");
            var container = document.getElementById("registerAlertContainer");

            if (!script || !container) {
                return;
            }

            var alerts = [];

            try {
                alerts = JSON.parse(script.textContent || "[]");
            } catch (error) {
                alerts = [];
            }

            if (!Array.isArray(alerts) || alerts.length === 0) {
                return;
            }

            alerts.slice(0, 2).forEach(function (alerta) {
                var node = document.createElement("div");
                node.className = "su-alert";
                node.innerHTML = '<img alt=""><div class="su-alert__body"><strong></strong><button type="button">Copiar cupon</button></div>';
                node.querySelector("img").src = alerta.image || "";
                node.querySelector("strong").textContent = alerta.title || "Promocion";
                node.querySelector("button").addEventListener("click", function () {
                    var code = alerta.title || "";
                    if (couponInput) {
                        couponInput.value = code.toUpperCase();
                    }
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(code);
                    }
                    notify("success", "Cupon copiado", code);
                });
                container.appendChild(node);
            });
        }
    });
})();
