const colors = { success: '#15803d', error: '#b91c1c', warning: '#a16207', info: '#1d4ed8' };

export function createNotifications(dialog) {
    let pending = Promise.resolve();
    const enqueue = (operation) => {
        const next = pending.then(operation, operation);
        pending = next.catch(() => {});
        return next;
    };
    const notify = (icon, message, title) => enqueue(() => dialog.fire({
        title, text: message, icon, confirmButtonText: 'OK', confirmButtonColor: colors[icon],
    }));
    return {
        success: (message, title = 'Success') => notify('success', message, title),
        error: (message, title = 'Unable to complete operation') => notify('error', message, title),
        warning: (message, title = 'Please review') => notify('warning', message, title),
        info: (message, title = 'Information') => notify('info', message, title),
        confirm: (message, options = {}) => enqueue(async () => {
            const result = await dialog.fire({
                title: options.title || 'Confirm action', text: message, icon: 'warning',
                showCancelButton: true, focusCancel: true,
                confirmButtonText: options.confirmButtonText || 'Confirm', cancelButtonText: 'Cancel',
                confirmButtonColor: options.confirmButtonColor || colors.error, cancelButtonColor: '#475569',
            });
            return result.isConfirmed === true;
        }),
        input: (message, options = {}) => enqueue(async () => {
            const result = await dialog.fire({
                title: options.title || 'Enter information', text: message,
                input: options.type || 'text', inputValue: options.value ?? '',
                inputPlaceholder: options.placeholder || '', inputValidator: options.validate,
                showCancelButton: true, confirmButtonText: options.confirmButtonText || 'Submit',
                cancelButtonText: 'Cancel', confirmButtonColor: colors.info, cancelButtonColor: '#475569',
            });
            return result.isConfirmed === true ? result.value : null;
        }),
        run: (title, operation) => enqueue(async () => {
            const opened = dialog.fire({
                title, allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false,
                didOpen: () => dialog.showLoading(),
            });
            try { return await operation(); }
            finally { dialog.close(); await opened; }
        }),
    };
}

export async function withSubmissionLock(form, operation) {
    if (form.dataset.busy) return;
    const states = [...form.querySelectorAll('button, input[type="submit"], input[type="image"]')]
        .map((button) => [button, button.disabled]);
    form.dataset.busy = '1';
    form.setAttribute('aria-busy', 'true');
    states.forEach(([button]) => { button.disabled = true; });
    try { return await operation(); }
    finally {
        delete form.dataset.busy;
        form.removeAttribute('aria-busy');
        states.forEach(([button, disabled]) => { button.disabled = disabled; });
    }
}

export function confirmedNotifications(payload, response) {
    const errors = Array.isArray(payload?.errors) ? payload.errors : [];
    const messages = Array.isArray(payload?.messages) ? payload.messages : [];
    const failure = messages.find((message) => message.type === 'error');
    if (errors.length || failure) throw new Error(errors.length ? errors.join('\n') : failure.message);
    if (!response.ok) {
        const labels = { 401: 'Please sign in again.', 403: 'You are not authorized to perform this action.',
            419: 'Your session expired. Reload the page before trying again.',
            422: 'Please review the entered information.', 429: 'Too many requests. Please try again shortly.' };
        throw new Error(labels[response.status] || `Request failed (${response.status}). Please try again.`);
    }
    if (!messages.some((message) => message.type === 'success')) {
        throw new Error('The server did not confirm this operation. Check the saved record before trying again.');
    }
    return messages;
}
