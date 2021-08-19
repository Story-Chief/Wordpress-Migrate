import React, {useContext} from 'react';
import {render} from 'react-dom';
import {ContextWrapper, StoryChiefContext} from "./StoryChiefContext";
import PageGeneral from "./Components/PageGeneral";
import PageCompleted from "./Components/PageCompleted";

const scmRootNode = document.getElementById('scm-root');

if (scmRootNode) {
    function App() {
        const {completed} = useContext(StoryChiefContext);

        return completed ? <PageCompleted/> : <PageGeneral/>;
    }

    render(<ContextWrapper><App/></ContextWrapper>, scmRootNode);
}
