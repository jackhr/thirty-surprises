<!DOCTYPE html>
<html lang="en">
<?php include BASE_PATH . '/app/Views/partials/head.php'; ?>

<body class="bg-slate-100">
    <div class="w-full min-h-screen flex items-center justify-center p-4 text-gray-900">
        <form id="login-form" method="POST" action="/login" class="w-full max-w-sm p-8 bg-white rounded-2xl shadow-xl flex flex-col gap-y-4">
            <h1 class="text-2xl font-semibold mb-2">Log in</h1>
            <div class="flex flex-col gap-1 name">
                <label class="field-label" for="name">Name</label>
                <input id="name" type="text" name="name" autocomplete="username">
            </div>
            <div class="flex flex-col gap-1 password">
                <label class="field-label" id="password-label" for="password">Password</label>
                <div class="relative">
                    <input id="password" type="password" name="password" class="w-full pr-12" autocomplete="current-password">
                    <img class="cursor-pointer absolute right-3 top-1/2 -translate-y-1/2 hide hidden" src="/images/eye-strikethrough.svg" alt="Hide password">
                    <img class="cursor-pointer absolute right-3 top-1/2 -translate-y-1/2 view" src="/images/eye.svg" alt="Show password">
                </div>
            </div>
            <button class="btn mt-2 bg-blue-500 hover:bg-blue-600 active:bg-blue-700">Login</button>
            <div id="login-error" class="hidden text-xs text-red-600 w-full text-center"></div>
        </form>
    </div>

    <script>
        async function requestJson(url, options = {}) {
            const response = await fetch(url, {
                credentials: 'same-origin',
                ...options,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    ...options.headers,
                },
            });
            let payload;
            try {
                payload = await response.json();
            } catch (error) {
                throw new Error(`Invalid server response (${response.status})`);
            }

            if (!response.ok || payload.error) {
                const message = payload.error || `Request failed (${response.status})`;
                throw new Error(message);
            }

            return payload;
        }

        $('#login-form').on('submit', async event => {
            event.preventDefault();

            const form = event.currentTarget;
            const data = new URLSearchParams(new FormData(form));
            $('#login-error').addClass('hidden').text('');

            try {
                const response = await requestJson('/login', {
                    method: 'POST',
                    body: data,
                });

                location.href = response.redirect || '/admin';
            } catch (error) {
                $('#login-error').removeClass('hidden').text(error.message || 'Login failed');
            }
        });

        $('img').on('click', () => {
            $('img').toggleClass('hidden');
            const viewingPassword = $('#password').attr('type') === 'text';
            $('#password').attr('type', viewingPassword ? 'password' : 'text');
        });
    </script>

    <?php include BASE_PATH . '/app/Views/partials/footer.php'; ?>
