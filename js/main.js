/** @global object window.wpStoryChiefMigrate */

document.addEventListener('DOMContentLoaded', () => {
    const scMigrate = document.getElementById('sc-migrate');

    if (scMigrate) {
        initMigrate();
    }

    function initMigrate() {
        const rest_api_url = window.wpStoryChiefMigrate.rest_api_url;
        const http_headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Cache': 'no-cache',
            'X-WP-Nonce': window.wpStoryChiefMigrate.nonce,
        };
        const step1 = document.getElementById('sc-step-api_key');
        const step2 = document.getElementById('sc-step-destination_id');
        const step3 = document.getElementById('sc-step-run');

        /* === REUSABLE FUNCTIONS === */

        function get_api_key() {
            return document.getElementById('api_key').value;
        }

        function get_destination_id() {
            return document.getElementById('destination_id').value;
        }

        async function connection_check() {
            const response = await fetch(rest_api_url + 'storychief/migrate/connection_check', {
                method: 'post',
                headers: http_headers,
                credentials: 'same-origin',
                body: JSON.stringify({
                    api_key: get_api_key(),
                }),
            });

            const json = await response.json();

            return json.data.success;
        }

        function show_step_2() {
            step1.hidden = true;
            step2.hidden = false;
            step3.hidden = true;

            fetch(rest_api_url + 'storychief/migrate/destinations', {
                method: 'post',
                headers: http_headers,
                credentials: 'same-origin',
                body: JSON.stringify({
                    api_key: get_api_key(),
                }),
            })
                .then((response) => response.json())
                .then((response) => {
                    const select = document.getElementById('destination_id');

                    response.data.forEach((destination) => {
                        const option = document.createElement('option');

                        option.value = destination.id;
                        option.innerText = destination.name;

                        select.appendChild(option);
                    });
                });
        }

        function show_step_3() {
            step1.hidden = true;
            step2.hidden = true;
            step3.hidden = false;
        }

        function show_step_4() {
            step1.hidden = true;
            step2.hidden = true;
            step3.hidden = true;

            window.location = window.wpStoryChiefMigrate.settings_url + '&v=' + Date.now();
        }

        document.getElementById('sc-form-api_key').addEventListener('submit', async (event) => {
            event.preventDefault();

            if (await connection_check()) {
                show_step_2();
            } else {
                step1.querySelector('.sc-error').hidden = false;
            }
        });

        document.getElementById('sc-form-destination_id').addEventListener('submit', async (event) => {
            event.preventDefault();

            const destination_id = get_destination_id();

            if (destination_id && destination_id > 0) {
                show_step_3();
            }
        });

        document.getElementById('sc-run-form').addEventListener('submit', async (event) => {
            event.preventDefault();

            const form = step3.querySelector('#sc-run-form');
            const progress = step3.querySelector('#sc-run-progress');

            const progress_bar = document.getElementById('sc-progress-bar');
            const progress_label = document.getElementById('sc-progress-label');
            const error = document.getElementById('sc-progress-error');

            form.hidden = true;
            error.hidden = true;
            progress.hidden = false;

            const api_key = get_api_key();
            const destination_id = get_destination_id();

            window.onbeforeunload = (event) => {
                if (!window.wpStoryChiefMigrate.completed) {
                    return window.confirm('Are you sure you want to leave?');
                }

                return false;
            };

            while (!window.wpStoryChiefMigrate.completed) {
                progress_label.hidden = false;
                progress_bar.hidden = false;
                error.hidden = true;

                const response = await fetch(rest_api_url + 'storychief/migrate/run', {
                    method: 'post',
                    headers: http_headers,
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        api_key,
                        destination_id,
                    })
                });

                const json = await response.json();

                window.wpStoryChiefMigrate.completed = json.data.completed;  // Stop the script / loop if the value is true
                window.wpStoryChiefMigrate.total_posts = json.data.total_posts;
                window.wpStoryChiefMigrate.total_completed = json.data.total_completed;
                window.wpStoryChiefMigrate.total_percentage = json.data.total_percentage;

                if (json.data.status >= 400) {
                    // Show the error message
                    error.innerHTML = `<p>${json.message}</p>`;
                    error.hidden = false;

                    if (json.data.status === 403) {
                        // Edge case: The api_key or destination is no longer available
                        progress_label.hidden = true;
                        progress_bar.hidden = true;
                        return false;
                    } else {
                        await new Promise((resolve) => setTimeout(resolve, 60 * 1000)); // Pause
                        progress_label.hidden = false;
                        progress_bar.hidden = false;

                    }
                } else {
                    error.hidden = true;
                    progress_bar.setAttribute('max', json.data.total_posts);
                    progress_bar.value = json.data.total_completed;
                    progress_label.innerText = Math.ceil(json.data.total_percentage) + '%';
                }

                // Delay the next request, to throttle the amount of requests per minute
                await new Promise((resolve) => setTimeout(resolve, 1500));
            }

            show_step_4();
        });
    }
});