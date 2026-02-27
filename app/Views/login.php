<!DOCTYPE html>
<html lang="en">
<?php include BASE_PATH . '/app/Views/partials/head.php'; ?>

<body>
    <div class="w-full h-screen flex items-center justify-center text-black">
        <form id="login-form" method="POST" action="/login" class="p-6 bg-gray-100 rounded flex flex-col items-center gap-y-4">
            <div class="flex items-center name">
                <label class="w-24" for="name">Name:</label>
                <input id="name" type="text" name="name" class="rounded">
            </div>
            <div class="flex items-center relative password">
                <label class="w-24" id="password-label" for="password">Password:</label>
                <input id="password" type="password" name="password" class="rounded">
                <img class="cursor-pointer absolute right-4 hide hidden" src="/images/eye-strikethrough.svg" alt="Closed eye icon.">
                <img class="cursor-pointer absolute right-4 view" src="/images/eye.svg" alt="Open eye icon.">
            </div>
            <button class="text-white h-8 w-full flex items-center justify-center bg-blue-500 rounded">Login</button>
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
