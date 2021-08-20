import React, {useContext, useEffect, useState} from 'react';
import PropTypes from 'prop-types';
import {StoryChiefContext} from "../StoryChiefContext";
import FormError from "./FormError";
import {connectionCheck, getApiKey, saveApiKey} from "../Services/Requests";
import PanelHeading from "./Partials/PanelHeading";

const propTypes = {
    open: PropTypes.bool.isRequired,
}

function PanelApiKey({open, disabled}) {
    const {apiKey, setApiKey, dispatchFilters, setActivePanel} = useContext(StoryChiefContext);
    const [showError, setShowError] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        getApiKey().then(response => {
            setApiKey(response?.data?.api_key);
        });
    }, []);

    async function handleSubmit(event) {
        event.preventDefault();

        setShowError(false);
        setLoading(true);

        const success = await connectionCheck(apiKey);

        setShowError(!success);
        setLoading(false);

        if (success) {
            setActivePanel('configuration');
            dispatchFilters({
                type: 'update',
                value: {},
            });

            saveApiKey(apiKey).then(() => null);
        }
    }

    return <>
        <article className="scm-panel">
            <PanelHeading open={open} disabled={disabled}>
                API-key
            </PanelHeading>
            {open && <section className="scm-panel-body">
                <form action="#" method="post" onSubmit={handleSubmit}>
                    <p>
                        Please enter the API-key you created in StoryChief, under Account Settings > API > Your keys.
                    </p>

                    <table className="form-table">
                        <tbody>
                        <tr>
                            <th scope="row">
                                <label htmlFor="api_key">
                                    Enter your StoryChief API Key
                                </label>
                            </th>
                            <td>
                                <textarea
                                        name="api_key"
                                        id="api_key"
                                        rows="10"
                                        autoComplete="off"
                                        value={apiKey || ''}
                                        style={{width: '500px'}}
                                        disabled={loading}
                                        onChange={({target}) => setApiKey(target.value)}/>
                                <p className="scm-help-text">
                                    <em>Your key will be stored and encrypted.</em>
                                </p>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    {showError && <FormError message="Sorry, the API-key you entered is incorrect."/>}

                    <p className="submit">
                        <button
                                type="submit"
                                name="submit"
                                id="submit"
                                className="button button-primary"
                                disabled={!apiKey || loading}>
                            Next
                        </button>
                    </p>
                </form>
            </section>}
        </article>
    </>
}

PanelApiKey.propTypes = propTypes;

export default PanelApiKey;