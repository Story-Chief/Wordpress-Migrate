import React, {createContext, useReducer, useState} from 'react';

export const StoryChiefContext = createContext();

export const CONTENT_INITIAL_FILTER = {
    destination: null,
    postType: null,
    postStatus: ['publish', 'draft', 'future'],
    taxonomy: null,
    apiKeyReady: false,
    configurationReady: false,
};

export function ContextWrapper({children}) {
    const postTypes = window.scm.post_types;
    const [completed, setCompleted] = useState(!!window.scm.completed);
    const [running, setRunning] = useState(false);
    const [apiKey, setApiKey] = useState(null);
    const [activePanel, setActivePanel] = useState('api_key');
    const [filters, dispatchFilters] = useReducer((state, action) => {
        const data = {
            ...{
                destination: state.destination,
                postType: state.postType,
                postStatus: state.postStatus,
                category: state.category,
                tag: state.tag,
                filterTaxonomy: state.filterTaxonomy || null,
            },
            ...action.value
        };

        data.apiKeyReady = !!(apiKey);
        data.configurationReady = !!(data.destination && data.postType && data.postStatus);

        return data;
    }, CONTENT_INITIAL_FILTER);

    const contextProps = {
        completed,
        setCompleted,
        apiKey,
        setApiKey,
        activePanel,
        setActivePanel,
        running,
        setRunning,
        postTypes,
        filters,
        dispatchFilters
    };

    return <StoryChiefContext.Provider value={contextProps}>
        {children}
    </StoryChiefContext.Provider>
}