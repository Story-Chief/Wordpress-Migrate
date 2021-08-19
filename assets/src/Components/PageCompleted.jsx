import React, {useContext, useEffect, useState} from "react";
import {fetchErrors, retry} from "../Services/Requests";
import {StoryChiefContext} from "../StoryChiefContext";

const styles = {
    textWarning: {
        color: '#d63638'
    },
};

function PageCompleted() {
    const {apiKey, setApiKey} = useContext(StoryChiefContext);
    const [errors, setErrors] = useState([]);

    useEffect(() => {
        fetchErrors().then(response => setErrors(response.data));
    }, []);

    function renderServerErrors(errors) {
        const items = [];

        for (const errorKey in errors) {
            items.push({
                key: errorKey,
                messages: errors[errorKey],
            });
        }

        return items.map(error => (
                <div className="scm-error" key={error.key}>
                    <strong>{error.key}</strong>
                    <ul className="scm-list">
                        {error.messages.map((message, i) => <li key={i}>{message}</li>)}
                    </ul>
                </div>
        ));
    }

    function handleRetry(event) {
        event.preventDefault();

        const ID = +event.currentTarget.dataset.id;
        const post = errors.find(post => post.ID === ID);

        post.loading = true;

        setErrors([...errors]);

        retry(apiKey, ID).then((response) => {

            const temp = [...errors];
            const index = temp.findIndex(post => +post.ID === ID);

            if (response.data.success) {
                temp.splice(index, 1);

            } else {
                temp[index] = response.data.error;
            }

            setErrors(temp);
        });
    }

    function renderRetry(post) {
        return (
                <a href="#" onClick={handleRetry} data-id={post.ID}
                   className={`scm-button-retry ${post.loading ? 'scm-button-retry--loading' : ''}`}>
                    <span className="dashicons dashicons-update"/>
                </a>
        );
    }

    return (
            <div className="scm">
                <h1>StoryChief Migrate</h1>
                <p>
                    We completed migrating all of your posts to StoryChief.
                </p>

                {errors.length ? (
                        <>
                            <h3>
                                Issues <span className="dashicons dashicons-warning" style={styles.textWarning}/>
                            </h3>
                            <p>
                                Sorry, while migrating we found {errors.length} problem(s) with some posts that failed.
                            </p>

                            <ul className="scm-list">
                                <li>
                                    Some posts may have failed due the fact, they had broken images or HTML
                                </li>
                                <li>
                                    Below is a list of posts that failed, with a description
                                </li>
                            </ul>

                            <table className="wp-list-table widefat fixed striped table-view-list posts">
                                <thead>
                                <tr>
                                    <th>
                                        Post
                                    </th>
                                    <th style={{width: '100px'}}>
                                        Error code
                                    </th>
                                    <th style={{width: '100px'}}>
                                        Error type
                                    </th>
                                    <th>
                                        Error message
                                    </th>
                                    <th>
                                        Errors
                                    </th>
                                    <th style={{textAlign: 'center', width: '50px'}}>
                                        Retry
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                {errors.map(post => (
                                        <tr key={post.ID}>
                                            <td>
                                                <a href={post.permalink} target="_blank">
                                                    {post.title}
                                                </a>
                                            </td>
                                            <td>
                                                {post.error.code}
                                            </td>
                                            <td>
                                                {post.error.type}
                                            </td>
                                            <td>
                                                {post.error.message}
                                            </td>
                                            <td>
                                                {renderServerErrors(post.error.errors)}
                                            </td>
                                            <td style={{textAlign: 'center', verticalAlign: 'middle'}}>
                                                {renderRetry(post)}
                                            </td>
                                        </tr>
                                ))}
                                </tbody>
                            </table>
                        </>
                ) : <p>As the next step you can deactivate or uninstall the plugin StoryChief Migrate.</p>}
            </div>
    );
}

export default PageCompleted;