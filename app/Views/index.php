<!DOCTYPE html>
<html lang="en">
<?php include BASE_PATH . '/app/Views/partials/head.php'; ?>

<body class="bg-gray-100">
    <canvas id="confetti" class="fixed w-screen h-screen top-0 pointer-events-none"></canvas>
    <main class="h-screen flex flex-col overflow-hidden">
        <div id="intro" class="absolute w-full h-screen bg-purple-700 sticky top-0 items-center justify-center flex">
            <div class="text-4xl text-white flex flex-col md:flex-row items-center justify-center gap-3">
                <div class="flex items-center">
                    <div id="names-container" class="border-b-2 border-white h-10 w-40 relative flex items-center justify-end text-right">
                        <span class="name absolute h-10 w-40 opacity-0 duration-500 transition-all opacity-100">Ashley</span>
                        <span class="name absolute h-10 w-40 opacity-0 duration-500 transition-all">Baby</span>
                        <span class="name absolute h-10 w-40 opacity-0 duration-500 transition-all">Sexy</span>
                        <span class="name absolute h-10 w-40 opacity-0 duration-500 transition-all">My Love</span>
                        <span class="name absolute h-10 w-40 opacity-0 duration-500 transition-all">Darling</span>
                    </div>
                    <span>, you have </span>
                </div>
                <span id="surprises-left-value" class="text-pink-300 text-5xl">...</span>
                <span id="surprises-left-label">surprises left...</span>
            </div>
            <div id="arrow" class="absolute bottom-10 w-32 text-pink-300 cursor-pointer animate-bounce">
                <?php
                $chevronSvg = @file_get_contents(PUBLIC_PATH . '/images/cheveron-down.svg');
                echo $chevronSvg === false ? '' : $chevronSvg;
                ?>
            </div>
        </div>

        <?php if (($user['name'] ?? null) === 'admin'): ?>
            <div id="admin-actions" class="text-white w-full h-12 hidden items-center justify-center z-10">
                <div onclick="goToAdmin()" class="cursor-pointer w-full h-full bg-sky-600 hover:bg-sky-700 active:bg-sky-800 items-center justify-center flex">
                    Admin
                </div>
                <div onclick="handleLogout()" class="cursor-pointer w-full h-full bg-red-600 hover:bg-red-700 active:bg-red-800 items-center justify-center flex">
                    Logout
                </div>
            </div>
        <?php endif; ?>

        <div id="surprises" class="transition-all duration-1000 hidden opacity-0 m-auto max-w-screen-2xl w-full flex flex-wrap gap-8 sm:gap-12 p-6 justify-center items-center overflow-auto">
            <div class="text-gray-500">Loading surprises...</div>
        </div>
    </main>

    <script>
        const TOTAL_SURPRISE_SLOTS = 30;

        const STATE = {
            magnitudeClassLookup: {
                small: 'text-xs text-emerald-400',
                medium: 'text-xs sm:text-sm text-emerald-500 font-medium',
                large: 'text-xs sm:text-md text-emerald-600 font-bold',
            },
            varietyClassLookup: {
                cute: 'text-sky-500',
                romantic: 'text-rose-600',
                overdue: 'text-orange-400',
                sweet: 'text-green-500',
                special: 'text-yellow-400',
                mystery: 'text-indigo-500',
            },
            countdownIntervals: {},
            jiggleIntervals: {},
        };

        $(document).ready(async () => {
            setInterval(() => {
                const activeName = $('#names-container .name.opacity-100');
                const nextName = activeName.next().length ? activeName.next() : $('#names-container .name').first();
                activeName.removeClass('opacity-100');
                setTimeout(() => nextName.addClass('opacity-100'), 500);
            }, 3000);

            await loadSurprises();
        });

        $('#arrow').on('click', () => {
            const delay = 1000;
            $('#intro').slideUp(delay);
            setTimeout(() => {
                $('main').removeClass('overflow-hidden');
                $('#surprises').removeClass('hidden');
                $('#intro').remove();
                $('#admin-actions').removeClass('hidden').addClass('flex');
                setTimeout(() => $('#surprises').toggleClass('opacity-0 opacity-100'), 200);
            }, delay);
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

        async function loadSurprises() {
            try {
                const surprises = await requestJson('/surprises/live');
                if (!Array.isArray(surprises)) {
                    throw new Error('Invalid surprises response');
                }

                renderSurprises(surprises);
            } catch (error) {
                $('#surprises').html('<div class="text-red-600">Unable to load surprises right now.</div>');
                updateSurprisesLeftDisplay(null);
            }
        }

        function renderSurprises(surprises) {
            clearAllIntervals();

            const cards = [];
            for (let i = 1; i <= TOTAL_SURPRISE_SLOTS; i += 1) {
                cards.push(buildSurpriseCard(surprises[i - 1] || null, i));
            }

            $('#surprises').html(cards.join(''));
            applyTagClasses();
            bindCountdowns();

            const viewedCount = surprises.filter(s => Boolean(s && s.viewed)).length;
            const surprisesLeft = Math.max(TOTAL_SURPRISE_SLOTS - viewedCount, 0);
            updateSurprisesLeftDisplay(surprisesLeft);
        }

        function clearAllIntervals() {
            Object.values(STATE.countdownIntervals).forEach(intervalId => clearInterval(intervalId));
            Object.values(STATE.jiggleIntervals).forEach(intervalId => clearInterval(intervalId));
            STATE.countdownIntervals = {};
            STATE.jiggleIntervals = {};
        }

        function bindCountdowns() {
            $('.surprise-card.upcoming').each((idx, surpriseCard) => {
                const revealDate = $(surpriseCard).data('reveal-date');
                if (!revealDate) {
                    return;
                }

                const key = getCardKey(surpriseCard, idx);
                updateCountdown(surpriseCard, key);
                STATE.countdownIntervals[key] = setInterval(() => updateCountdown(surpriseCard, key), 1000);
            });
        }

        function getCardKey(surpriseCard, idx) {
            const surpriseID = $(surpriseCard).data('id');
            return surpriseID ? `surprise-${surpriseID}` : `slot-${idx}`;
        }

        function applyTagClasses() {
            $('.magnitude').each((idx, elem) => {
                const magnitude = $(elem).data('magnitude');
                const magnitudeClass = STATE.magnitudeClassLookup[magnitude] || '';
                $(elem).addClass(magnitudeClass);
            });

            $('.variety').each((idx, elem) => {
                const variety = $(elem).data('variety');
                const varietyClass = STATE.varietyClassLookup[variety] || '';
                $(elem).addClass(varietyClass);
            });
        }

        function updateSurprisesLeftDisplay(surprisesLeft) {
            if (typeof surprisesLeft !== 'number' || Number.isNaN(surprisesLeft)) {
                $('#surprises-left-value').text('...');
                $('#surprises-left-label').text('surprises left...');
                document.title = 'Surprises';
                return;
            }

            $('#surprises-left-value').text(surprisesLeft);

            const suffix = surprisesLeft === 1 ? '' : 's';
            const trailing = surprisesLeft === 0 ? ' left...' : ' left to go!';
            $('#surprises-left-label').text(`surprise${suffix}${trailing}`);
            document.title = `${surprisesLeft} Surprises Left`;
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatRevealDate(value) {
            if (!value) {
                return '';
            }

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return '';
            }

            return new Intl.DateTimeFormat('en-US', {
                timeZone: 'America/New_York',
                month: '2-digit',
                day: '2-digit',
                year: '2-digit',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
            }).format(date);
        }

        function buildSurpriseCard(surprise, number) {
            const cardClass = 'surprise-card relative w-40 h-40 sm:w-48 sm:h-48 border-2 border-gray-300 rounded-lg cursor-pointer overflow-hidden';
            const iconClass = 'absolute w-full h-full text-6xl sm:text-8xl text-gray-500 flex items-center justify-center';

            if (!surprise || (!surprise.viewed && !surprise.revealDate)) {
                return `
                    <div class="${cardClass} upcoming" onclick="handleClick(this)">
                        <div class="${iconClass} opacity-40">${number}</div>
                    </div>
                `;
            }

            const stateClass = surprise.viewed ? 'viewed' : (surprise.revealDate ? 'upcoming' : '');
            const revealDate = surprise.revealDate || '';
            const revealLabel = formatRevealDate(revealDate);
            const titleOpacity = surprise.viewed ? 'opacity-100' : 'opacity-0';
            const iconOpacity = surprise.viewed ? 'opacity-10' : 'opacity-30';
            const cardClasses = `${cardClass} ${stateClass}`.trim();

            return `
                <div
                    class="${cardClasses}"
                    data-reveal-date="${escapeHtml(revealDate)}"
                    data-id="${escapeHtml(surprise.id)}"
                    onclick="handleClick(this)"
                >
                    <div class="${iconClass} icon-container transition-all duration-1000 ${iconOpacity}">
                        <i class="${escapeHtml(surprise.iconClass)}"></i>
                    </div>
                    <div class="surprise-body w-full h-full relative p-2 pt-10">
                        <div class="absolute p-2 left-0 top-0 w-full flex justify-between items-center">
                            <span data-magnitude="${escapeHtml(surprise.magnitude)}" class="magnitude rounded bg-white text-black px-2">${escapeHtml(surprise.magnitude)}</span>
                            <span class="text-sm text-gray-500">${number}</span>
                            <span data-variety="${escapeHtml(surprise.variety)}" class="variety rounded bg-white text-black px-2 text-xs sm:font-semibold sm:tracking-wider">${escapeHtml(surprise.variety)}</span>
                        </div>
                        <h1 class="transition-all duration-1000 text-base sm:text-lg ${titleOpacity} mb-1 sm:mb-2 font-extrabold">${escapeHtml(surprise.title)}</h1>
                        <p class="transition-all duration-1000 text-xs sm:text-base overflow-auto h-24 ${titleOpacity}">${escapeHtml(surprise.description)}</p>
                        ${(!surprise.viewed && surprise.revealDate) ? `
                            <div class="countdown-container absolute w-full h-40 text-gray-500 text-lg sm:text-xl text-center bottom-0 left-0">
                                <div class="transition-all duration-1000 opacity-100 countdown absolute w-full bottom-0"></div>
                                <div class="transition-all duration-1000 opacity-0 reveal-date absolute w-full bottom-0 text-base">${escapeHtml(revealLabel)}</div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        async function handleClick(surpriseCard) {
            const surpriseID = $(surpriseCard).data('id');

            if ($(surpriseCard).hasClass('upcoming')) {
                $(surpriseCard).find('.countdown-container *').toggleClass('opacity-0 opacity-100');
                handleJiggle(surpriseCard);
                return;
            }

            if (!$(surpriseCard).hasClass('time-to-view')) {
                return;
            }

            try {
                await requestJson(`/surprises/${surpriseID}/viewed`, {
                    method: 'PUT',
                    body: new URLSearchParams({ viewed: 'true' }),
                });

                $(surpriseCard).removeClass('time-to-view');
                const duration = 10 * 1000;
                const animationEnd = Date.now() + duration;
                const defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 0 };

                const randomInRange = (min, max) => Math.random() * (max - min) + min;

                const interval = setInterval(() => {
                    const timeLeft = animationEnd - Date.now();

                    if (timeLeft <= 0) {
                        clearInterval(interval);
                        return;
                    }

                    const particleCount = 50 * (timeLeft / duration);
                    confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 } }));
                    confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 } }));
                }, 250);

                const jiggleKey = `surprise-${surpriseID}`;
                if (STATE.jiggleIntervals[jiggleKey]) {
                    clearInterval(STATE.jiggleIntervals[jiggleKey]);
                    delete STATE.jiggleIntervals[jiggleKey];
                }

                setTimeout(() => {
                    $(surpriseCard).find('.countdown').toggleClass('opacity-100 opacity-0');
                    setTimeout(() => {
                        $(surpriseCard).find('.icon-container').toggleClass('opacity-10 opacity-30');
                        setTimeout(() => {
                            $(surpriseCard).find('h1').toggleClass('opacity-0 opacity-100');
                            $(surpriseCard).find('p').toggleClass('opacity-0 opacity-100');
                        }, 1000);
                    }, 1000);
                }, 1000);

                const currentValue = parseInt($('#surprises-left-value').text(), 10);
                if (!Number.isNaN(currentValue)) {
                    updateSurprisesLeftDisplay(Math.max(currentValue - 1, 0));
                }
            } catch (error) {
                swal({
                    title: 'Error',
                    icon: 'error',
                    text: error.message || 'Unable to update surprise state',
                });
            }
        }

        function handleJiggle(surpriseCard) {
            $(surpriseCard).addClass('jiggle');
            setTimeout(() => $(surpriseCard).removeClass('jiggle'), 1500);
        }

        function updateCountdown(surpriseCard, intervalKey) {
            const targetDate = new Date($(surpriseCard).data('reveal-date'));
            const currentDate = new Date();
            const timeRemaining = targetDate - currentDate;

            if (Number.isNaN(timeRemaining)) {
                return;
            }

            if (timeRemaining <= 0) {
                if (STATE.countdownIntervals[intervalKey]) {
                    clearInterval(STATE.countdownIntervals[intervalKey]);
                    delete STATE.countdownIntervals[intervalKey];
                }

                if ($(surpriseCard).hasClass('upcoming')) {
                    $(surpriseCard)
                        .removeClass('upcoming')
                        .addClass('time-to-view')
                        .find('.countdown')
                        .html('Click to find out!');
                    handleJiggle(surpriseCard);

                    const surpriseID = $(surpriseCard).data('id');
                    if (surpriseID) {
                        const jiggleKey = `surprise-${surpriseID}`;
                        if (STATE.jiggleIntervals[jiggleKey]) {
                            clearInterval(STATE.jiggleIntervals[jiggleKey]);
                        }
                        STATE.jiggleIntervals[jiggleKey] = setInterval(() => {
                            handleJiggle(surpriseCard);
                        }, 2000);
                    }
                }
            } else {
                const days = Math.floor(timeRemaining / (1000 * 60 * 60 * 24));
                const hours = Math.floor((timeRemaining % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((timeRemaining % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeRemaining % (1000 * 60)) / 1000);

                $(surpriseCard).find('.countdown').html(`${days}d ${hours}h ${minutes}m ${seconds}s`);
            }
        }
    </script>

    <?php include BASE_PATH . '/app/Views/partials/nav-functions.php'; ?>
    <?php include BASE_PATH . '/app/Views/partials/footer.php'; ?>
