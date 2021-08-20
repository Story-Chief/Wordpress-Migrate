import React, {useContext} from "react";
import PanelApiKey from "./PanelApiKey";
import PanelConfiguration from "./PanelConfiguration";
import PanelRun from "./PanelRun";
import {StoryChiefContext} from "../StoryChiefContext";

function PageGeneral() {
    const {activePanel, filters, running} = useContext(StoryChiefContext);

    return (
            <div className="scm">
                <h1>StoryChief Migrate</h1>
                <p>We recommend running the migration first, through a staging environment if possible</p>

                <section>
                    <PanelApiKey open={activePanel === 'api_key'} disabled={filters.apiKeyReady}/>
                    <PanelConfiguration open={activePanel === 'configuration'} disabled={!filters.apiKeyReady || running}/>
                    <PanelRun open={activePanel === 'run'} disabled={!filters.configurationReady || running}/>
                </section>
            </div>
    );
}

export default PageGeneral;