<!DOCTYPE html>
<html lang="en">
<?php include BASE_PATH . '/app/Views/partials/head.php'; ?>

<body class="bg-sky-400">
    <div class="text-white w-full h-12 sticky top-0 items-center justify-center flex z-10">
        <div onclick="openCreateModal()" class="cursor-pointer w-full h-full bg-green-600 hover:bg-green-700 active:bg-green-800 sticky top-0 items-center justify-center flex">
            Create Surprise
        </div>
        <div onclick="goToSurprises()" class="cursor-pointer w-full h-full bg-sky-600 hover:bg-sky-700 active:bg-sky-800 sticky top-0 items-center justify-center flex">
            All Surprises
        </div>
        <div onclick="handleLogout()" class="cursor-pointer w-full h-full bg-red-600 hover:bg-red-700 active:bg-red-800 sticky top-0 items-center justify-center flex">
            Logout
        </div>
    </div>

    <div id="surprises-container" class="dark:bg-opacity-95 bg-opacity-95 flex justify-center flex-wrap gap-12 lg:gap-20 lg:overflow-hidden p-6"></div>

    <div id="create-modal" class="top-0 hidden w-screen h-screen flex items-center justify-center fixed bg-blend-darken bg-black/20 backdrop-blur-sm">
        <div class="relative flex h-fit w-96 flex flex-col bg-white rounded justify-center p-8 text-black gap-y-4">
            <div class="absolute text-4xl right-2 top-2 bg-white flex items-center justify-center cursor-pointer w-8 h-8" onclick="closeCreateModal()">&times;</div>
            <h1 class="text-2xl">Create Surprise</h1>
            <div class="flex flex-col gap-1 mt-4">
                <label for="new-title" class="text-xs">Title</label>
                <input id="new-title" type="text" class="rounded text-sm">
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs" for="new-icon-class">Icon Class</label>
                <input class="rounded text-sm" id="new-icon-class" type="text">
            </div>
            <div class="flex flex-col gap-1">
                <label for="new-description" class="text-xs">Description</label>
                <textarea class="rounded text-sm" id="new-description"></textarea>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs" for="new-variety">Variety</label>
                <select class="cursor-pointer rounded" id="new-variety">
                    <option value="cute">cute</option>
                    <option value="romantic">romantic</option>
                    <option value="overdue">overdue</option>
                    <option selected value="sweet">sweet</option>
                    <option value="special">special</option>
                    <option value="mystery">mystery</option>
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs" for="new-magnitude">Magnitude</label>
                <select class="cursor-pointer rounded" id="new-magnitude">
                    <option value="small">small</option>
                    <option value="medium">medium</option>
                    <option value="large">large</option>
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs" for="new-reveal-date">Reveal Date</label>
                <input type="datetime-local" class="rounded" id="new-reveal-date">
            </div>
            <div class="text-white h-8 w-full flex items-center justify-center bg-blue-500 hover:bg-blue-600 active:bg-blue-700 rounded cursor-pointer" onclick="handleCreate()">Create</div>
        </div>
    </div>

    <div id="delete-modal" class="top-0 hidden w-screen h-screen flex items-center justify-center fixed bg-blend-darken bg-black/20 backdrop-blur-sm">
        <div class="relative flex h-fit w-96 flex flex-col bg-white rounded justify-center p-8 text-black gap-y-4">
            <div class="absolute text-4xl right-2 top-2 bg-white flex items-center justify-center cursor-pointer w-8 h-8" onclick="closeDeleteModal()">&times;</div>
            <h1 class="text-2xl">Deleting Surprise</h1>
            <p></p>
            <div class="text-white h-8 w-full flex items-center justify-center bg-red-500 hover:bg-red-600 active:bg-red-700 rounded cursor-pointer" onclick="handleDelete()">Delete</div>
        </div>
    </div>

    <script>
        const INITIAL_SURPRISES = <?= json_encode($surprises ?? [], JSON_UNESCAPED_UNICODE) ?>;

        $(document).ready(() => {
            if (Array.isArray(INITIAL_SURPRISES) && INITIAL_SURPRISES.length) {
                $('#surprises-container').html('');
                INITIAL_SURPRISES.forEach((s, idx) => appendNewSurprise(s, idx + 1));
            } else {
                reloadSurprises();
            }
        });

        function handleChangeCardTitle(surpriseID) {
            const newTitle = $(`#title-${surpriseID}`).val();
            $(`#surprise-${surpriseID}`).find('h1').first().text(newTitle);
        }

        function handleChangeIcon(surpriseID) {
            const newIcon = $(`#icon-class-${surpriseID}`).val();
            const iconEl = $(`#surprise-${surpriseID}`).find('i').first();
            iconEl.attr('class', newIcon);
        }

        function convertDateString(inputDateString) {
            const inputDate = new Date(inputDateString);

            if (isNaN(inputDate)) {
                return 'Invalid Date';
            }

            const year = inputDate.getFullYear();
            const month = String(inputDate.getMonth() + 1).padStart(2, '0');
            const day = String(inputDate.getDate()).padStart(2, '0');
            const hours = String(inputDate.getHours()).padStart(2, '0');
            const minutes = String(inputDate.getMinutes()).padStart(2, '0');

            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }

        function handleStatusChange(surpriseID, selector) {
            let truthyStatus;
            let falsyStatus;
            const elem = $(`#${selector}-${surpriseID}`);
            switch (selector) {
                case 'live':
                    truthyStatus = 'live';
                    falsyStatus = 'testing';
                    break;

                case 'viewed':
                    truthyStatus = 'viewed';
                    falsyStatus = 'not yet viewed';
                    break;

                default:
                    truthyStatus = 'completed';
                    falsyStatus = 'incomplete';
                    break;
            }
            changeStatus(elem, selector, falsyStatus, truthyStatus);
        }

        function changeStatus(elem, selector, falsyStatus, truthyStatus) {
            const statusIsTrue = elem.hasClass(selector);
            elem
                .toggleClass(`${selector} bg-blue-400 text-white border-transparent border-black`)
                .text(statusIsTrue ? falsyStatus : truthyStatus);
        }

        function handleDelete() {
            $.ajax({
                url: `/admin/surprise/${$('.deleting').data('id')}`,
                method: 'DELETE',
                success: function(deletedSurprise, status) {
                    if (status === 'success') {
                        $('.deleting').remove();
                        updateCardCount();
                        closeDeleteModal();
                    } else {
                        swal({
                            icon: 'error',
                            title: 'Error',
                            text: 'Something went wrong'
                        });
                    }
                }
            });
        }

        function closeDeleteModal() {
            $('#delete-modal').addClass('hidden');
        }

        function openDeletemodal(surpriseID) {
            const title = $(`#title-${surpriseID}`).val();
            $('.s-card').removeClass('deleting');
            $(`#surprise-${surpriseID}`).addClass('deleting');
            $('#delete-modal').removeClass('hidden');
            $('#delete-modal')
                .find('p')
                .text(`Are you sure you want to delete the surprise: "${title}"? This cannot be undone`);
        }

        function updateTimeInput(surpriseId, dateVal, selector) {
            setTimeout(() => {
                const newDateVal = dateVal ? convertDateString(dateVal) : '';
                $(`${selector}-${surpriseId}`).val(newDateVal);
            }, 10);
        }

        function handleUpdate(surpriseID) {
            let revealDate;
            if ($(`#reveal-date-${surpriseID}`).val()) {
                revealDate = new Date($(`#reveal-date-${surpriseID}`).val());
            }
            const data = {
                title: $(`#title-${surpriseID}`).val(),
                description: $(`#description-${surpriseID}`).val(),
                magnitude: $(`#magnitude-${surpriseID}`).val(),
                variety: $(`#variety-${surpriseID}`).val(),
                iconClass: $(`#icon-class-${surpriseID}`).val(),
                completed: $(`#completed-${surpriseID}`).hasClass('completed'),
                viewed: $(`#viewed-${surpriseID}`).hasClass('viewed'),
                live: $(`#live-${surpriseID}`).hasClass('live'),
                revealDate,
            };
            $.ajax({
                url: `/admin/surprise/${surpriseID}`,
                method: 'PUT',
                data,
                success: surprise => {
                    const icon = surprise.error ? 'error' : 'success';
                    const title = surprise.error ? 'Error' : 'Success';
                    const text = surprise.error ? surprise.error : `${surprise.title} successfully updated!`;
                    reloadSurprises();

                    swal({ title, icon, text });
                }
            });
        }

        function updateCardCount() {
            $('.surprise-num').each((idx, num) => $(num).text(idx + 1));
        }

        function handleCreate() {
            const data = {
                title: $('#new-title').val().trim(),
                description: $('#new-description').val().trim(),
                variety: $('#new-variety').val().trim(),
                magnitude: $('#new-magnitude').val().trim(),
                iconClass: $('#new-icon-class').val().trim(),
                revealDate: $('#new-reveal-date').val().trim(),
            };

            $.ajax({
                url: '/admin/surprise',
                method: 'POST',
                data,
                success: res => {
                    const alerts = [];
                    if (res.error) {
                        alerts.push({
                            icon: 'error',
                            title: 'Error',
                            text: res.error,
                        });
                    } else {
                        if (!res.newSurprise.revealDate) {
                            alerts.push({
                                icon: 'warning',
                                title: 'Invalid Date',
                                text: "You didn't completely fill out the revealDate fyi, so it wasn't saved"
                            });
                        }
                        if (res.emailRes && res.emailRes.error) {
                            alerts.push({
                                icon: 'error',
                                title: 'Error Sending Email',
                                text: res.emailRes.error,
                            });
                        }
                        $('#surprises-container').html('');
                        res.surprises.forEach((s, idx) => appendNewSurprise(s, idx + 1));
                        closeCreateModal();
                        resetCreateModal();

                        if (alerts.length) handleMultipleSwals(alerts);
                    }
                }
            });
        }

        function handleMultipleSwals(alerts) {
            swal(alerts[0]).then(() => alerts.shift() && alerts.length && handleMultipleSwals(alerts));
        }

        function reloadSurprises() {
            $.ajax({
                url: '/surprises',
                success: surprises => {
                    if (surprises.error) {
                        swal({
                            title: 'Error',
                            icon: 'error',
                            text: surprises.error
                        });
                    } else {
                        if (surprises.length) {
                            $('#surprises-container').html('');
                            surprises.forEach((s, idx) => appendNewSurprise(s, idx + 1));
                        } else {
                            $('#surprises-container').html('<div>No Surprises Yet!</div>');
                        }
                    }
                },
                error: function() { console.log(arguments); },
            });
        }

        function appendNewSurprise(s, num) {
            $('#surprises-container').append(`
                <div class="items-center bg-gray-200 relative flex h-fit w-96 flex flex-col bg-white rounded justify-center p-8 text-black gap-y-4 s-card border-2 ${s.completedAt ? 'border-green-500' : ''}" id="surprise-${s.id}" data-id="${s.id}">
                    <div class="icon-bg flex items-center justify-center absolute w-full h-full opacity-5 leading-none pointer-events-none" style="font-size: 20rem;">
                        <i class="${s.iconClass}"></i>
                    </div>
                    <div class="surprise-num absolute top-2 left-2 rounded-full bg-gray-200 text-black text-sm w-6 h-6 flex items-center justify-center">${num}</div>
                    <div class="absolute text-4xl right-2 top-2 bg-white flex items-center justify-center cursor-pointer w-8 h-8" onclick="openDeletemodal('${s.id}')">&times;</div>
                    <h1 class="text-2xl w-full">${s.title}</h1>
                    <div class="flex flex-wrap items-center gap-2 py-2 border-y border-gray-300 w-full">
                        <div id="completed-${s.id}" onclick="handleStatusChange('${s.id}', 'completed')" class="cursor-pointer flex items-center justify-center px-2 rounded border-2 ${s.completedAt ? 'completed bg-blue-400 text-white border-transparent' : 'border-black'}">${s.completedAt ? 'completed' : 'incomplete'}</div>
                        <div id="viewed-${s.id}" onclick="handleStatusChange('${s.id}', 'viewed')" class="cursor-pointer flex items-center justify-center px-2 rounded border-2 ${s.viewed ? 'viewed bg-blue-400 text-white border-transparent' : 'border-black'}">${s.viewed ? 'viewed' : 'not viewed yet'}</div>
                        <div id="live-${s.id}" onclick="handleStatusChange('${s.id}', 'live')" class="cursor-pointer flex items-center justify-center px-2 rounded border-2 ${s.live ? 'live bg-blue-400 text-white border-transparent' : 'border-black'}">${s.live ? 'live' : 'testing'}</div>
                    </div>
                    <div class="flex flex-col gap-1 w-full">
                        <label class="text-xs" for="title-${s.id}">Title</label>
                        <input class="rounded text-sm" id="title-${s.id}" type="text" value="${s.title}" oninput="handleChangeCardTitle('${s.id}')">
                    </div>
                    <div class="flex flex-col gap-1 w-full">
                        <label class="text-xs" for="icon-class-${s.id}">Icon Class</label>
                        <input class="rounded text-sm" id="icon-class-${s.id}" type="text" value="${s.iconClass}" oninput="handleChangeIcon('${s.id}')">
                    </div>
                    <div class="flex flex-col gap-1 w-full">
                        <label class="text-xs" for="description-${s.id}">Description</label>
                        <textarea class="rounded text-sm" id="description-${s.id}">${s.description}</textarea>
                    </div>
                    <div class="flex flex-col gap-1 w-full">
                        <label class="text-xs" for="variety-${s.id}">Variety</label>
                        <select class="cursor-pointer rounded" id="variety-${s.id}">
                            <option ${s.variety === 'cute' ? 'selected' : ''} value="cute">cute</option>
                            <option ${s.variety === 'romantic' ? 'selected' : ''} value="romantic">romantic</option>
                            <option ${s.variety === 'overdue' ? 'selected' : ''} value="overdue">overdue</option>
                            <option ${s.variety === 'sweet' ? 'selected' : ''} value="sweet">sweet</option>
                            <option ${s.variety === 'special' ? 'selected' : ''} value="special">special</option>
                            <option ${s.variety === 'mystery' ? 'selected' : ''} value="mystery">mystery</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1 w-full">
                        <label class="text-xs" for="magnitude-${s.id}">Magnitude</label>
                        <select class="cursor-pointer rounded" id="magnitude-${s.id}">
                            <option ${s.magnitude === 'small' ? 'selected' : ''} value="small">small</option>
                            <option ${s.magnitude === 'medium' ? 'selected' : ''} value="medium">medium</option>
                            <option ${s.magnitude === 'large' ? 'selected' : ''} value="large">large</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1 w-full">
                        <label class="text-xs" for="reveal-date-${s.id}">Reveal Date</label>
                        <input type="datetime-local" class="rounded reveal-date" id="reveal-date-${s.id}" data-reveal-date="${s.revealDate || ''}">
                    </div>
                    <div class="text-white h-8 w-full flex items-center justify-center bg-blue-500 hover:bg-blue-600 active:bg-blue-700 rounded cursor-pointer" onclick="handleUpdate('${s.id}')" data-id="${s.id}">Update</div>
                </div>
            `);

            updateTimeInput(s.id, s.revealDate, '#reveal-date');
        }

        function closeCreateModal() {
            $('#create-modal').addClass('hidden');
        }

        function resetCreateModal() {
            $('#new-title').val('');
            $('#new-description').val('');
            $('#new-icon-class').val('');
            $('#new-reveal-date').val('');
            $('#new-variety').val('sweet');
            $('#new-magnitude').val('medium');
        }

        function openCreateModal() {
            $('#create-modal').removeClass('hidden');
        }
    </script>

    <?php include BASE_PATH . '/app/Views/partials/nav-functions.php'; ?>
    <?php include BASE_PATH . '/app/Views/partials/footer.php'; ?>
