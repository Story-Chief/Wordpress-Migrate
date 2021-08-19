import React, {useContext, useState} from 'react';
import PropTypes from 'prop-types';
import {StoryChiefContext} from "../StoryChiefContext";
import FormError from "./FormError";
import {connectionCheck} from "../Services/Requests";
import PanelHeading from "./Partials/PanelHeading";

const propTypes = {
    open: PropTypes.bool.isRequired,
}

function PanelApiKey({open}) {
    const {apiKey, setApiKey, dispatchFilters, setActivePanel} = useContext(StoryChiefContext);
    const [showError, setShowError] = useState(null);
    const [disabled, setDisabled] = useState(false);

    async function handleSubmit(event) {
        event.preventDefault();

        setShowError(false);

        const success = await connectionCheck(apiKey);

        if (success) {
            dispatchFilters({
                type: 'update',
                value: {},
            });
            setDisabled(true);
            setActivePanel('configuration');
        } else {
            setShowError(true);
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
                                        onChange={({target}) => setApiKey(target.value)}/>

                            </td>
                        </tr>
                        </tbody>
                    </table>

                    {showError && <FormError message="Sorry, the API-key you entered is incorrect." />}

                    <p className="submit">
                        <button type="submit" name="submit" id="submit" className="button button-primary" disabled={!apiKey}>
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