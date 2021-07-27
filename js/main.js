document.addEventListener('DOMContentLoaded', () => {
    const scMigrate = document.getElementById('sc-migrate');

    if (scMigrate) {
        initMigrate();
    }

    function initMigrate() {
        const http_headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Cache': 'no-cache',
            'X-WP-Nonce': window.wpApiSc.nonce,
        };
        const step1 = document.getElementById('sc-step-api_key');
        const step2 = document.getElementById('sc-step-destination_id');
        const step3 = document.getElementById('sc-step-run');
        const step4 = document.getElementById('sc-step-completed');
        const progress_label = document.getElementById('sc-progress-label');
        const progress = document.getElementById('sc-progress');

        /* === REUSABLE FUNCTIONS === */

        function get_api_key() {
            return document.getElementById('api_key').value;
        }

        function get_destination_id() {
            return document.getElementById('destination_id').value;
        }

        async function connection_check() {
            const response = await fetch('/wp-json/storychief/migrate/connection_check', {
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
            step4.hidden = true;

            fetch('/wp-json/storychief/migrate/destinations', {
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
            step4.hidden = true;
        }

        function show_step_4() {
            step1.hidden = true;
            step2.hidden = true;
            step3.hidden = true;
            step4.hidden = false;
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

            step3.querySelector('#sc-run-form').hidden = true;
            step3.querySelector('#sc-run-progress').hidden = false;

            const api_key = get_api_key();
            const destination_id = get_destination_id();
            let completed = (window.wpApiSc.total_completed === window.wpApiSc.total_posts);

            while (!completed) {
                const response = await fetch('/wp-json/storychief/migrate/run', {
                    method: 'post',
                    headers: http_headers,
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        api_key,
                        destination_id,
                    })
                });

                const json = await response.json();

                completed = json.data.completed; // Stop the script / loop if the value is true

                if (json.data.status >= 400) {
                    // step1.hidden = true;
                    // step2.hidden = true;
                    // step3.hidden = true;
                    // stepError.hidden = false;
                    // stepError.querySelector('.update-message p').innerHTML = json.message;
                    return false;
                } else {
                    progress.setAttribute('max', json.data.total_posts);
                    progress.setAttribute('value', json.data.total_completed);

                    progress_label.innerText = Math.ceil(json.data.total_completed / json.data.total_posts * 100) + '%';

                    await new Promise((resolve) => setTimeout(resolve, 3000)); // Pause
                }
            }

            show_step_4();
        });

        /*
        const stepError = document.getElementById('sc-section-error');
        const progress = document.getElementById('sc-progress');
        const total = +window.wpApiSc.total_posts;
        const completed = +window.wpApiSc.total_completed;
        const loops = Math.ceil((total - completed) / 10) + 1;

        let migrationRunning = false;

        formMigrateRun.addEventListener('submit', async (event) => {
            event.preventDefault();

            step1.hidden = true;
            step2.hidden = false;
            step3.hidden = true;

            for (let i = 0; i < loops; i++) {
                const response = await fetch('/wp-json/storychief/migrate/run', {
                    method: 'post',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'Cache': 'no-cache',
                        'X-WP-Nonce': window.wpApiSc.nonce,
                    },
                    credentials: 'same-origin',
                });

                const json = await response.json();

                if (json.data.status >= 400) {
                    step1.hidden = true;
                    step2.hidden = true;
                    step3.hidden = true;
                    stepError.hidden = false;
                    stepError.querySelector('.update-message p').innerHTML = json.message;

                    return false;
                } else {
                    progress.setAttribute('max', json.data.total);
                    progress.setAttribute('value', json.data.completed);

                    progress.innerText = (json.data.completed / json.data.total * 100) + '%';
                }
            }

            step1.hidden = true;
            step2.hidden = true;
            step3.hidden = false;
            stepError.hidden = true;
        });
        */
    }
});