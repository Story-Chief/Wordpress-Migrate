import React from "react";

function Progress({data}) {
    return (
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
    );
}

export default Progress;