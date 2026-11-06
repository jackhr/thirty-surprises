<!DOCTYPE html>
<html lang="en">
<?php include BASE_PATH . '/app/Views/partials/head.php'; ?>

<body class="bg-slate-100">
    <div class="text-white font-medium w-full h-12 sticky top-0 items-center justify-center flex z-10 shadow-md">
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

    <div id="surprises-container" class="flex justify-center flex-wrap gap-8 lg:gap-12 p-4 sm:p-8"></div>

    <div id="create-modal" class="top-0 hidden w-screen h-screen flex items-center justify-center fixed bg-blend-darken bg-black/20 backdrop-blur-sm">
        <div class="relative flex w-96 max-w-[calc(100vw-2rem)] max-h-[90vh] overflow-y-auto flex-col bg-white rounded-2xl shadow-2xl p-8 text-gray-900 gap-y-4">
            <div class="absolute text-3xl right-3 top-3 text-gray-400 hover:text-gray-700 flex items-center justify-center cursor-pointer w-8 h-8 rounded-full hover:bg-gray-100" onclick="closeCreateModal()">&times;</div>
            <h1 class="text-2xl font-semibold">Create Surprise</h1>
            <div class="flex flex-col gap-1 mt-2">
                <label class="field-label" for="new-type">Type</label>
                <select id="new-type" onchange="toggleExtraSlots('new')">
                    <option selected value="normal">Normal surprise</option>
                    <option value="expansion">More surprises (adds slots when opened)</option>
                </select>
            </div>
            <div class="flex flex-col gap-1 hidden" id="new-extra-slots-field">
                <label class="field-label" for="new-extra-slots">Extra surprises to add when opened</label>
                <input class="w-full" id="new-extra-slots" type="number" min="1" max="100" step="1" value="10">
            </div>
            <div class="flex flex-col gap-1">
                <label for="new-title" class="field-label">Title</label>
                <input id="new-title" type="text" class="w-full">
            </div>
            <div class="flex flex-col gap-1">
                <label class="field-label" for="new-icon-class">Icon Class</label>
                <input class="w-full" id="new-icon-class" type="text">
            </div>
            <div class="flex flex-col gap-1">
                <label for="new-description" class="field-label">Description</label>
                <textarea class="w-full" id="new-description"></textarea>
            </div>
            <div class="flex flex-col gap-1">
                <label class="field-label" for="new-variety">Variety</label>
                <select id="new-variety">
                    <option value="cute">cute</option>
                    <option value="romantic">romantic</option>
                    <option value="overdue">overdue</option>
                    <option selected value="sweet">sweet</option>
                    <option value="special">special</option>
                    <option value="mystery">mystery</option>
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="field-label" for="new-magnitude">Magnitude</label>
                <select id="new-magnitude">
                    <option value="small">small</option>
                    <option value="medium">medium</option>
                    <option value="large">large</option>
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="field-label" for="new-reveal-date">Reveal Date</label>
                <input type="datetime-local" id="new-reveal-date">
            </div>
            <div class="btn mt-2 bg-blue-500 hover:bg-blue-600 active:bg-blue-700" onclick="handleCreate()">Create</div>
        </div>
    </div>

    <div id="delete-modal" class="top-0 hidden w-screen h-screen flex items-center justify-center fixed bg-blend-darken bg-black/20 backdrop-blur-sm">
        <div class="relative flex w-96 max-w-[calc(100vw-2rem)] max-h-[90vh] overflow-y-auto flex-col bg-white rounded-2xl shadow-2xl p-8 text-gray-900 gap-y-4">
            <div class="absolute text-3xl right-3 top-3 text-gray-400 hover:text-gray-700 flex items-center justify-center cursor-pointer w-8 h-8 rounded-full hover:bg-gray-100" onclick="closeDeleteModal()">&times;</div>
            <h1 class="text-2xl font-semibold">Delete Surprise</h1>
            <p></p>
            <div class="btn mt-2 bg-red-500 hover:bg-red-600 active:bg-red-700" onclick="handleDelete()">Delete</div>
        </div>
    </div>

    <script>
        $(document).ready(() => {
            reloadSurprises();
        });

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

        async function handleDelete() {
            const deletingId = $('.deleting').data('id');
            if (!deletingId) {
                return;
            }

            try {
                await requestJson(`/admin/surprise/${deletingId}`, {
                    method: 'DELETE',
                });

                $('.deleting').remove();
                updateCardCount();
                closeDeleteModal();
            } catch (error) {
                swal({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Something went wrong',
                });
            }
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

        async function handleUpdate(surpriseID) {
            const revealDate = $(`#reveal-date-${surpriseID}`).val().trim();
            const data = new URLSearchParams({
                title: $(`#title-${surpriseID}`).val(),
                description: $(`#description-${surpriseID}`).val(),
                magnitude: $(`#magnitude-${surpriseID}`).val(),
                variety: $(`#variety-${surpriseID}`).val(),
                iconClass: $(`#icon-class-${surpriseID}`).val(),
                type: $(`#type-${surpriseID}`).val(),
                extraSlots: $(`#extra-slots-${surpriseID}`).val().trim(),
                completed: $(`#completed-${surpriseID}`).hasClass('completed') ? 'true' : 'false',
                viewed: $(`#viewed-${surpriseID}`).hasClass('viewed') ? 'true' : 'false',
                live: $(`#live-${surpriseID}`).hasClass('live') ? 'true' : 'false',
                revealDate,
            });

            try {
                const surprise = await requestJson(`/admin/surprise/${surpriseID}`, {
                    method: 'PUT',
                    body: data,
                });

                await reloadSurprises();
                swal({
                    title: 'Success',
                    icon: 'success',
                    text: `${surprise.title} successfully updated!`,
                });
            } catch (error) {
                swal({
                    title: 'Error',
                    icon: 'error',
                    text: error.message || 'Unable to update surprise',
                });
            }
        }

        function updateCardCount() {
            $('.surprise-num').each((idx, num) => $(num).text(idx + 1));
        }

        async function handleCreate() {
            const data = new URLSearchParams({
                title: $('#new-title').val().trim(),
                description: $('#new-description').val().trim(),
                variety: $('#new-variety').val().trim(),
                magnitude: $('#new-magnitude').val().trim(),
                iconClass: $('#new-icon-class').val().trim(),
                revealDate: $('#new-reveal-date').val().trim(),
                type: $('#new-type').val(),
                extraSlots: $('#new-extra-slots').val().trim(),
            });

            try {
                const res = await requestJson('/admin/surprise', {
                    method: 'POST',
                    body: data,
                });

                const alerts = [];
                if (!res.newSurprise.revealDate) {
                    alerts.push({
                        icon: 'warning',
                        title: 'Invalid Date',
                        text: "You didn't completely fill out the revealDate fyi, so it wasn't saved",
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

                if (alerts.length) {
                    handleMultipleSwals(alerts);
                }
            } catch (error) {
                swal({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Unable to create surprise',
                });
            }
        }

        function handleMultipleSwals(alerts) {
            swal(alerts[0]).then(() => alerts.shift() && alerts.length && handleMultipleSwals(alerts));
        }

        async function reloadSurprises() {
            try {
                const surprises = await requestJson('/surprises');

                if (surprises.length) {
                    $('#surprises-container').html('');
                    surprises.forEach((s, idx) => appendNewSurprise(s, idx + 1));
                } else {
                    $('#surprises-container').html('<div>No Surprises Yet!</div>');
                }
            } catch (error) {
                swal({
                    title: 'Error',
                    icon: 'error',
                    text: error.message || 'Unable to load surprises',
                });
            }
        }

        function appendNewSurprise(s, num) {
            $('#surprises-container').append(`
                <div class="isolate items-center relative flex h-fit w-96 max-w-full flex-col bg-white rounded-2xl shadow-md justify-center p-8 text-gray-900 gap-y-4 s-card border-2 ${s.completedAt ? 'border-green-500' : 'border-transparent'}" id="surprise-${s.id}" data-id="${s.id}">
                    <div class="icon-bg -z-10 flex items-center justify-center absolute inset-0 w-full h-full overflow-hidden rounded-2xl opacity-5 leading-none pointer-events-none" style="font-size: 20rem;">
                        <i class="${s.iconClass}"></i>
                    </div>
                    <div class="surprise-num absolute top-3 left-3 rounded-full bg-sky-100 text-sky-700 font-semibold text-xs w-7 h-7 flex items-center justify-center">${num}</div>
                    <div class="absolute text-3xl right-3 top-3 text-gray-400 hover:text-red-600 flex items-center justify-center cursor-pointer w-8 h-8 rounded-full hover:bg-red-50" title="Delete" onclick="openDeletemodal('${s.id}')">&times;</div>
                    <h1 class="text-2xl font-semibold w-full pt-2">${s.title}</h1>
                    ${s.type === 'expansion' ? `<div class="w-full text-sm font-bold text-indigo-500">More surprises: adds ${s.extraSlots} when opened</div>` : ''}
                    <div class="flex flex-wrap items-center gap-2 py-3 border-y border-gray-200 w-full">
                        <div id="completed-${s.id}" onclick="handleStatusChange('${s.id}', 'completed')" class="cursor-pointer select-none flex items-center justify-center px-3 py-0.5 text-sm rounded-full border-2 transition-colors ${s.completedAt ? 'completed bg-blue-400 text-white border-transparent' : 'border-black'}">${s.completedAt ? 'completed' : 'incomplete'}</div>
                        <div id="viewed-${s.id}" onclick="handleStatusChange('${s.id}', 'viewed')" class="cursor-pointer select-none flex items-center justify-center px-3 py-0.5 text-sm rounded-full border-2 transition-colors ${s.viewed ? 'viewed bg-blue-400 text-white border-transparent' : 'border-black'}">${s.viewed ? 'viewed' : 'not viewed yet'}</div>
                        <div id="live-${s.id}" onclick="handleStatusChange('${s.id}', 'live')" class="cursor-pointer select-none flex items-center justify-center px-3 py-0.5 text-sm rounded-full border-2 transition-colors ${s.live ? 'live bg-blue-400 text-white border-transparent' : 'border-black'}">${s.live ? 'live' : 'testing'}</div>
                    </div>
                    <div class="flex flex-col gap-1 w-full">
                        <label class="field-label" for="type-${s.id}">Type</label>
                        <select id="type-${s.id}" onchange="toggleExtraSlots('${s.id}')">
                            <option ${s.type !== 'expansion' ? 'selected' : ''} value="normal">Normal surprise</option>
                            <option ${s.type === 'expansion' ? 'selected' : ''} value="expansion">More surprises (adds slots when opened)</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1 w-full ${s.type === 'expansion' ? '' : 'hidden'}" id="${s.id}-extra-slots-field">
                        <label class="field-label" for="extra-slots-${s.id}">Extra surprises to add when opened</label>
                        <input class="w-full" id="extra-slots-${s.id}" type="number" min="1" max="100" step="1" value="${s.type === 'expansion' ? s.extraSlots : 10}">
                    </div>
                    <div class="flex flex-col gap-1 w-full">
                        <label class="field-label" for="title-${s.id}">Title</label>
                        <input class="w-full" id="title-${s.id}" type="text" value="${s.title}" oninput="handleChangeCardTitle('${s.id}')">
                    </div>
                    <div class="flex flex-col gap-1 w-full">
                        <label class="field-label" for="icon-class-${s.id}">Icon Class</label>
                        <input class="w-full" id="icon-class-${s.id}" type="text" value="${s.iconClass}" oninput="handleChangeIcon('${s.id}')">
                    </div>
                    <div class="flex flex-col gap-1 w-full">
                        <label class="field-label" for="description-${s.id}">Description</label>
                        <textarea class="w-full" id="description-${s.id}">${s.description}</textarea>
                    </div>
                    <div class="flex flex-col gap-1 w-full">
                        <label class="field-label" for="variety-${s.id}">Variety</label>
                        <select id="variety-${s.id}">
                            <option ${s.variety === 'cute' ? 'selected' : ''} value="cute">cute</option>
                            <option ${s.variety === 'romantic' ? 'selected' : ''} value="romantic">romantic</option>
                            <option ${s.variety === 'overdue' ? 'selected' : ''} value="overdue">overdue</option>
                            <option ${s.variety === 'sweet' ? 'selected' : ''} value="sweet">sweet</option>
                            <option ${s.variety === 'special' ? 'selected' : ''} value="special">special</option>
                            <option ${s.variety === 'mystery' ? 'selected' : ''} value="mystery">mystery</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1 w-full">
                        <label class="field-label" for="magnitude-${s.id}">Magnitude</label>
                        <select id="magnitude-${s.id}">
                            <option ${s.magnitude === 'small' ? 'selected' : ''} value="small">small</option>
                            <option ${s.magnitude === 'medium' ? 'selected' : ''} value="medium">medium</option>
                            <option ${s.magnitude === 'large' ? 'selected' : ''} value="large">large</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1 w-full">
                        <label class="field-label" for="reveal-date-${s.id}">Reveal Date</label>
                        <input type="datetime-local" class="reveal-date" id="reveal-date-${s.id}" data-reveal-date="${s.revealDate || ''}">
                    </div>
                    <div class="btn mt-2 bg-blue-500 hover:bg-blue-600 active:bg-blue-700" onclick="handleUpdate('${s.id}')" data-id="${s.id}">Update</div>
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
            $('#new-type').val('normal');
            $('#new-extra-slots').val('10');
            toggleExtraSlots('new');
        }

        // prefix is "new" for the create modal, or a surprise id for an edit card.
        function toggleExtraSlots(prefix) {
            const typeSelect = prefix === 'new' ? '#new-type' : `#type-${prefix}`;
            const isExpansion = $(typeSelect).val() === 'expansion';
            $(`#${prefix}-extra-slots-field`).toggleClass('hidden', !isExpansion);
        }

        function openCreateModal() {
            $('#create-modal').removeClass('hidden');
        }
    </script>

    <?php include BASE_PATH . '/app/Views/partials/nav-functions.php'; ?>
    <?php include BASE_PATH . '/app/Views/partials/footer.php'; ?>
