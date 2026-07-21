<x-guest-layout>
    <x-sefar-auth-shell
        title="Verificacion en dos pasos"
        copy="Antes de entrar, confirma el codigo de seguridad asociado a tu cuenta. Es una capa adicional para proteger tu expediente."
        card-id="sefar-two-factor-title"
        card-title="Codigo de seguridad"
        card-eyebrow="Doble verificacion"
    >
        <div x-data="{ recovery: false }">
            <p class="sefar-login-text" x-show="! recovery">
                Ingresa el codigo generado por tu aplicacion de autenticacion.
            </p>

            <p class="sefar-login-text" x-show="recovery">
                Ingresa uno de tus codigos de recuperacion para acceder a tu cuenta.
            </p>

            <x-validation-errors class="sefar-login-errors" />

            <form class="sefar-login-form" method="POST" action="{{ route('two-factor.login') }}">
                @csrf

                <label for="code" class="sefar-login-field" x-show="! recovery">
                    <span>Codigo de autenticacion</span>
                    <input id="code" type="text" inputmode="numeric" name="code" autofocus x-ref="code" autocomplete="one-time-code">
                </label>

                <label for="recovery_code" class="sefar-login-field" x-show="recovery">
                    <span>Codigo de recuperacion</span>
                    <input id="recovery_code" type="text" name="recovery_code" x-ref="recovery_code" autocomplete="one-time-code">
                </label>

                <div class="sefar-login-actions">
                    <button
                        type="button"
                        class="sefar-login-link-button"
                        x-show="! recovery"
                        x-on:click="
                            recovery = true;
                            $nextTick(() => { $refs.recovery_code.focus() })
                        "
                    >
                        Usar codigo de recuperacion
                    </button>

                    <button
                        type="button"
                        class="sefar-login-link-button"
                        x-show="recovery"
                        x-on:click="
                            recovery = false;
                            $nextTick(() => { $refs.code.focus() })
                        "
                    >
                        Usar codigo de autenticacion
                    </button>

                    <button class="sefar-login-button sefar-login-button--compact" type="submit">
                        <span>Verificar y entrar</span>
                    </button>
                </div>
            </form>
        </div>
    </x-sefar-auth-shell>
</x-guest-layout>
