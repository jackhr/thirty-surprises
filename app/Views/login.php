<!DOCTYPE html>
<html lang="en">
<?php include BASE_PATH . '/app/Views/partials/head.php'; ?>

<body>
    <div class="w-full h-screen flex items-center justify-center text-black">
        <form method="POST" action="/login" class="p-6 bg-gray-100 rounded flex flex-col items-center gap-y-4">
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
        </form>
    </div>

    <script>
        $('img').on('click', () => {
            $('img').toggleClass('hidden');
            const viewingPassword = $('#password').attr('type') === 'text';
            $('#password').attr('type', viewingPassword ? 'password' : 'text');
        });
    </script>

    <?php include BASE_PATH . '/app/Views/partials/footer.php'; ?>
