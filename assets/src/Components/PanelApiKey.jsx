import React, {useState} from 'react';
import PropTypes from 'prop-types';

const propTypes = {
    activePanel: PropTypes.string.isRequired,
    handleApiKey: PropTypes.func.isRequired,
}

function PanelApiKey({open, handleApiKey}) {
    const [apiKey, setApiKey] = useState(null);
    const [showError, setShowError] = useState(null);

    async function handleSubmit(event) {
        event.preventDefault();

        setShowError(false);

        const rest_api_url = window.wpStoryChiefMigrate.rest_api_url;
        const response = await fetch(rest_api_url + 'storychief/migrate/connection_check', {
            method: 'post',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Cache': 'no-cache',
                'X-WP-Nonce': window.wpStoryChiefMigrate.nonce,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                api_key: apiKey,
            }),
        });

        const json = await response.json();

        if (json.data.success) {
            handleApiKey(apiKey);
        } else {
            setShowError(true);
        }
    }

    return <>
        <details className="sc-panel" open={open}>
            <summary className="sc-panel-heading">API-key</summary>
            <div className="sc-panel-body">
                <form action="#" method="post" onSubmit={handleSubmit}>
                    <table className="form-table">
                        <tbody>
                        <tr>
                            <th scope="row">
                                <label htmlFor="api_key">
                                    Enter your StoryChief API Key
                                </label>
                            </th>
                            <td>
                                <textarea name="api_key" id="api_key" rows="10" autoComplete="off" value={apiKey}
                                          onChange={({target}) => setApiKey(target.value)}/>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </form>
            </div>
        </details>
    </>
}

PanelApiKey.propTypes = propTypes;

export default PanelApiKey;