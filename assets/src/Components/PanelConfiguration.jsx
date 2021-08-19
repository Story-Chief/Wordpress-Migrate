import React, {memo, useCallback, useContext, useEffect, useState} from 'react';
import PropTypes from 'prop-types';
import {StoryChiefContext} from "../StoryChiefContext";
import PanelHeading from "./Partials/PanelHeading";
import {fetchDestinations, fetchPreview, prepareFiltersToSearch} from "../Services/Requests";
import {debounce} from "lodash/function";

const propTypes = {
    open: PropTypes.bool.isRequired,
    disabled: PropTypes.bool.isRequired,
}

function PanelConfiguration({open, disabled}) {
    const {postTypes, filters, dispatchFilters, apiKey, setActivePanel} = useContext(StoryChiefContext);
    const [postType, setPostType] = useState(filters.postType);
    const [destinations, setDestinations] = useState([]);
    const [destination, setDestination] = useState();
    const [postStatus, setPostStatus] = useState(filters.postStatus);
    const [taxonomies, setTaxonomies] = useState(filters.postType?.taxonomy_objects || []);
    const [category, setCategory] = useState();
    const [tag, setTag] = useState();
    const [filterTaxonomy, setFilterTaxonomy] = useState(filters.filterTaxonomy);
    const [preview, setPreview] = useState();

    const callbackPreview = useCallback(debounce((apiKey, params) => {
        const searchParams = prepareFiltersToSearch(params);

        fetchPreview(apiKey, searchParams)
                .then((response) => {
                    setPreview({...response.data});
                })
    }, 2000), []);

    useEffect(() => {
        if (destination && postType) {
            const value = {
                destination,
                postType,
                postStatus,
                category,
                tag,
                filterTaxonomy,
            };

            // Update the global state
            dispatchFilters({
                type: 'update',
                value
            })

            // Update the preview
            callbackPreview(apiKey, {...value});
        }
    }, [destination, postType, postStatus, category, tag, filterTaxonomy]);

    useEffect(() => {
        if (filters.apiKeyReady) {
            fetchDestinations(apiKey).then((response) => {
                setDestinations(response.data);
                setDestination(response.data[0]);
            });
        }
    }, [filters.apiKeyReady]);

    async function handleSubmit(event) {
        event.preventDefault();

        if (filters.configurationReady) {
            setActivePanel('run');
        }
    }

    function handleDestination({target}) {
        setDestination(destinations.find(channel => +channel.id === +target.value));
    }

    function handlePostType({target}) {
        const type = postTypes[target.value];

        setPostType(type);
        setTaxonomies(type.taxonomy_objects
                .map(tax => ({
                    ...tax,
                    checked: true,
                }))
        );
        setCategory(type.taxonomies[0] || null);
        setTag(type.taxonomies[1] || null);
    }

    function handlePostStatus({target}) {
        setPostStatus(Array.from(document.querySelectorAll('[name="post_status[]"]:checked')).map(checkbox => checkbox.value));
    }

    function handleCategory({target}) {
        setCategory(postType.taxonomies.find(tax => tax.name === target.value));
    }

    function handleTag({target}) {
        setTag(postType.taxonomies.find(tax => tax.name === target.value));
    }

    function handleTaxonomyFilter({target}) {
        setFilterTaxonomy(taxonomies.find(tax => tax.name === target.value));
    }

    function handleFilterTerm(i, checked) {
        filterTaxonomy.items[i].checked = checked;

        setFilterTaxonomy({
            ...filterTaxonomy,
        });
    }

    function handleToggle(event) {
        setActivePanel(open ? null : 'configuration');
    }

    return <>
        <article className="scm-panel">
            <PanelHeading open={open} disabled={disabled} onClick={handleToggle}>
                Configuration
            </PanelHeading>
            <section className="scm-panel-body" hidden={!open}>
                <form action="#" method="post" onSubmit={handleSubmit}>
                    <table className="form-table">
                        <tbody>
                        <tr>
                            <th>
                                <label htmlFor="destination">
                                    Please select a destination *
                                </label>
                            </th>
                            <td>
                                <select
                                        name="destination"
                                        id="destination"
                                        required
                                        value={destination?.id || ''}
                                        onChange={handleDestination}>
                                    {destinations.map((channel) => <option
                                            value={channel.id}
                                            key={channel.id}>
                                        {channel.name}
                                    </option>)}
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label htmlFor="post_type">
                                    Please select a post-type *
                                </label>
                            </th>
                            <td>
                                <select
                                        name="post_type"
                                        id="post_type"
                                        required
                                        value={postType?.name || ''}
                                        onChange={handlePostType}>
                                    {!postType && <option>Please select a post-type</option>}
                                    {Object.values(postTypes).map((type) => <option
                                            value={type.name}
                                            key={type.name}>
                                        {type.label}
                                    </option>)}
                                </select>
                            </td>
                        </tr>
                        {postType && <>
                            <tr>
                                <th className="scm-th-heading" colSpan="2">
                                    Mapping
                                    <span className="dashicons dashicons-admin-links"/>
                                </th>
                            </tr>
                            <tr>
                                <th>
                                    <label htmlFor="category">Categories</label>
                                </th>
                                <td>
                                    <select
                                            name="category"
                                            id="category"
                                            required
                                            value={category?.name || ''}
                                            onChange={handleCategory}
                                    >
                                        <option value="">Please select a category</option>
                                        {postType.taxonomies.map(tax => <option key={tax.name}
                                                                                value={tax.name}>{tax.label}</option>)}
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <label htmlFor="tag">Tags</label>
                                </th>
                                <td>
                                    <select
                                            name="tag"
                                            id="tag"
                                            required
                                            value={tag?.name || ''}
                                            onChange={handleTag}
                                    >
                                        <option value="">Please select a tag</option>
                                        {postType.taxonomies.map(tax => <option key={tax.name}
                                                                                value={tax.name}>{tax.label}</option>)}
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th className="scm-th-heading" colSpan="2">
                                    Filtering
                                    <span className="dashicons dashicons-filter"/>
                                </th>
                            </tr>
                            <tr>
                                <th>
                                    <label>
                                        Status*
                                    </label>
                                </th>
                                <td>
                                    <ul className="scm-taxonomies">
                                        <li>
                                            <label htmlFor="status_published">
                                                <input
                                                        id="status_published"
                                                        type="checkbox"
                                                        name="post_status[]"
                                                        checked={postStatus.includes('publish')}
                                                        onChange={handlePostStatus}
                                                        value="publish"/> Published
                                            </label>
                                        </li>
                                        <li>
                                            <label htmlFor="status_draft">
                                                <input
                                                        id="status_draft"
                                                        type="checkbox"
                                                        name="post_status[]"
                                                        checked={postStatus.includes('draft')}
                                                        onChange={handlePostStatus}
                                                        value="draft"/> Draft
                                            </label>
                                        </li>
                                        <li>
                                            <label htmlFor="status_future">
                                                <input
                                                        id="status_future"
                                                        type="checkbox"
                                                        name="post_status[]"
                                                        checked={postStatus.includes('future')}
                                                        onChange={handlePostStatus}
                                                        value="future"/> Scheduled
                                            </label>
                                        </li>
                                        <li>
                                            <label htmlFor="status_private">
                                                <input
                                                        id="status_private"
                                                        type="checkbox"
                                                        name="post_status[]"
                                                        checked={postStatus.includes('private')}
                                                        onChange={handlePostStatus}
                                                        value="private"/> Private (admin and editors only)
                                            </label>
                                        </li>
                                    </ul>
                                </td>
                            </tr>

                            {postType && postType.taxonomy_objects.length ? <>
                                <tr>
                                    <th>
                                        <label>Filter by taxonomy</label>
                                    </th>
                                    <td>
                                        <p>
                                            You can specify the migration, by filtering posts by selecting terms.
                                        </p>
                                        <select
                                                name="filterTaxonomy"
                                                id="filterTaxonomy"
                                                value={filterTaxonomy?.name || ''}
                                                onChange={handleTaxonomyFilter}
                                        >
                                            <option value="">Please select your filterTaxonomy</option>
                                            {taxonomies.map((tax) => <option
                                                    value={tax.name}
                                                    key={tax.name}
                                            >
                                                {tax.label}
                                            </option>)}
                                        </select>

                                        {filterTaxonomy && (
                                                <ul className="scm-taxonomies">
                                                    {filterTaxonomy.items.map((term, i) => {
                                                        const formId = `taxonomy_${term.value}`;
                                                        return (
                                                                <li key={term.value}>
                                                                    <label
                                                                            htmlFor={formId}
                                                                            key={term.value}
                                                                            className="scm-filterTaxonomy">
                                                                        <input
                                                                                type="checkbox"
                                                                                id={formId}
                                                                                name={`taxonomies[]`}
                                                                                className="scm-taxonomy__input"
                                                                                checked={term.checked || false}
                                                                                onChange={({target}) => handleFilterTerm(i, target.checked)}
                                                                                value={term.value}
                                                                        />
                                                                        {term.label} ({term.total})
                                                                    </label>
                                                                </li>
                                                        )
                                                    })}
                                                </ul>
                                        )}
                                    </td>
                                </tr>
                            </> : null}
                        </>}

                        {preview && (
                                <tr>
                                    <th className="scm-th-heading">
                                        Summary <span className="dashicons dashicons-chart-line"/>
                                    </th>
                                    <td>
                                        <ul>
                                            <li>
                                                <strong>Total posts:</strong> {preview.total_posts}
                                            </li>
                                            <li>
                                                <strong>Total categories:</strong> {preview.total_categories}
                                            </li>
                                            <li>
                                                <strong>Total tags:</strong> {preview.total_tags}
                                            </li>
                                        </ul>
                                    </td>
                                </tr>
                        )}
                        </tbody>
                    </table>

                    <p className="submit">
                        <button
                                type="submit"
                                name="submit"
                                id="submit"
                                className="button button-primary"
                                disabled={!filters.configurationReady}>
                            Next
                        </button>
                    </p>
                </form>
            </section>
        </article>
    </>
}

PanelConfiguration.propTypes = propTypes;

export default memo(PanelConfiguration);