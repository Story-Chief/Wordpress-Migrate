import React from "react";
import PropTypes from "prop-types";

const propTypes = {
    open: PropTypes.bool.isRequired,
    disabled: PropTypes.bool,
    children: PropTypes.any,
    onClick: PropTypes.func,
}
const defaultPropTypes = {
    onClick: () => null,
};

function PanelHeading({open, disabled, children, onClick}) {
    return (
            <header className={`scm-panel-heading ${disabled ? 'scm-panel-heading--disabled' : ''}`} onClick={onClick}>
                <div className="scm-panel-heading__title">
                    {children}
                    {disabled && <span className="dashicons dashicons-lock"/>}
                </div>
                <div className="scm-panel-heading__toggle">
                    {open ? <span className="dashicons dashicons-arrow-up"/> :
                            <span className="dashicons dashicons-arrow-down"/>}
                </div>
            </header>
    );
}

PanelHeading.propTypes = propTypes;
PanelHeading.defaultPropTypes = defaultPropTypes;

export default PanelHeading;