import React, {memo} from "react";

function FormError({message}) {
    return <>
        <div className="sc-error update-message notice inline notice-warning notice-alt">
            {message}
        </div>
    </>
}

export default memo(FormError);