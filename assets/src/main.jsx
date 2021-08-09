import React, {useState} from 'react';
import {render} from 'react-dom';
import PanelApiKey from "./Components/PanelApiKey";

const scMigrateNode = document.getElementById('sc-migrate');

if (scMigrateNode) {
    function App() {
        const [apiKey, setApiKey] = useState(null);

        const [activePanel, setActivePanel] = useState('configuration');

        function handleApiKey(apiKey) {
            apiKey(apiKey);
        }

        return <>
            <h1>StoryChief Migrate</h1>
            <ul className="sc-list">
                <li>
                    We recommend running the migration first, through a staging environment if possible
                </li>
                <li>
                    We will copy all of your drafts and published posts
                </li>
                <li>
                    Please keep this tab open, while the migration is running
                </li>
            </ul>

            <section>
                <PanelApiKey activePanel={activePanel} open={activePanel==='api_key'} handleApiKey={handleApiKey} />
            </section>
        </>;
    }

    render(<App settings={window.wpStoryChiefMigrate}/>, scMigrateNode)
}
