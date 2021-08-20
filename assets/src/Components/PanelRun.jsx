import React, {memo, useContext, useReducer, useState} from 'react';
import PropTypes from 'prop-types';
import {StoryChiefContext} from "../StoryChiefContext";
import PanelHeading from "./Partials/PanelHeading";
import {prepareFiltersToSearch} from "../Services/Requests";
import FormError from "./FormError";

const INITIAL_STATE_DATA = {
    total_posts: '?',
    total_completed: '?',
    total_success: '?',
    total_failed: '?',
    total_percentage: 0,
};

const propTypes = {
    open: PropTypes.bool.isRequired,
    disabled: PropTypes.bool.isRequired,
}

let migratingIsRunning = false;


function run({completed, setCompleted, setRunning, apiKey, filters, dispatchData, setError}) {
    // Place this function outside the component scope, and use vanilla JS to only have one instance
    (async () => {
        const restApiUrl = window.scm.rest_api_url;
        const nonce = window.scm.nonce;

        window.onbeforeunload = (event) => {
            return window.confirm('Are you sure you want to leave?');
        };

        while (migratingIsRunning && !completed) {
            // https://developer.mozilla.org/en-US/docs/Web/API/WindowOrWorkerGlobalScope/fetch
            const response = await fetch(restApiUrl + 'storychief/migrate/run', {
                method: 'post',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'Cache': 'no-cache',
                    'X-WP-Nonce': nonce,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    ...prepareFiltersToSearch(filters),
                    api_key: apiKey,
                }),
            });

            const json = await response.json();

            dispatchData({type: 'update', value: json.data});
            setCompleted(completed = json.data.completed);

            if (json.data && json.data.completed) {
                setRunning(migratingIsRunning = false);
                window.onbeforeunload = null;
                return false;
            }

            if (json.data.status >= 400) {
                // Show the error message

                if ([403, 500].includes(json.data.status === 403)) {
                    setRunning(migratingIsRunning = false);
                    dispatchData({type: 'reset'});
                    setError(json.message);
                    window.onbeforeunload = null;

                    // Edge case: The api_key or destination is no longer available
                    return false;
                } else {
                    // Pause the request, maybe there where to many requests
                    await new Promise((resolve) => setTimeout(resolve, 30 * 1000));
                }
            }

            // Delay the next request, to throttle the amount of requests per minute
            await new Promise((resolve) => setTimeout(resolve, 3000));
        }

        window.onbeforeunload = null;
    })();
}

function PanelApiKey({open, disabled}) {
    const {
        completed,
        setCompleted,
        apiKey,
        filters,
        setActivePanel,
        running,
        setRunning
    } = useContext(StoryChiefContext);
    const [data, dispatchData] = useReducer((state, {type, value}) => {
        if (type === 'update') {
            return {
                total_posts: value.total_posts,
                total_completed: value.total_completed,
                total_failed: value.total_failed,
                total_success: value.total_completed - value.total_failed,
                total_percentage: Math.ceil(value.total_percentage),
            };
        }

        if (type === 'reset') {
            return {...INITIAL_STATE_DATA};
        }

        return state;
    }, INITIAL_STATE_DATA);

    const [error, setError] = useState();

    function handleStart(event) {
        event.preventDefault();

        setRunning(migratingIsRunning = true);

        run({completed, setCompleted, setRunning, apiKey, filters, dispatchData, setError});
    }

    function handleStop(event) {
        event.preventDefault();

        setRunning(migratingIsRunning = false);
        dispatchData({type: 'reset'});
    }

    function handleToggle(event) {
        event.preventDefault();

        setActivePanel(open ? null : 'run');
    }

    return <>
        <article className="scm-panel">
            <PanelHeading open={open} disabled={disabled} onClick={handleToggle}>
                RUN
            </PanelHeading>
            <section className="scm-panel-body" hidden={!open}>
                {error && <FormError message={error}/>}
                {!running && (
                        <form method="post" onSubmit={handleStart}>
                            <p>
                                You can press the button to start the migration.
                            </p>
                            <p className="submit">
                                <button type="submit" className="button button-primary">
                                    Run migration
                                </button>
                            </p>
                        </form>
                )}
                {running && (
                        <div className="scm-progress">
                            <h2>
                                Please do not close this tab&nbsp;
                                <span className="scm-progress-icon dashicons dashicons-image-rotate"/>
                            </h2>
                            <p>
                                Please wait while we are migrating your existing posts to
                                StoryChief.
                            </p>
                            <div>
                                <div className="scm-progress-bar">

                                    <div className="scm-progress-bar-label">
                                        {data.total_percentage}%
                                    </div>
                                    <progress
                                            className="scm-progress-bar-meter"
                                            max="100"
                                            value={data.total_percentage}/>
                                </div>
                            </div>
                            <p>
                                <small>
                                    Total posts: {data.total_posts},
                                    Total completed: {data.total_completed},
                                    Total success: {data.total_success},
                                    Total failed: {data.total_failed}
                                </small>
                            </p>

                            <button type="button" className="button button-primary" onClick={handleStop}>
                                Stop
                            </button>
                        </div>
                )}
            </section>
        </article>
    </>
}

PanelApiKey.propTypes = propTypes;

export default memo(PanelApiKey);