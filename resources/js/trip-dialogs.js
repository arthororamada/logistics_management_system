export async function readTripResponse(response) {
    const result = await response.json().catch(() => null);
    if (!response.ok) {
        throw new Error(result?.errors ? Object.values(result.errors).flat().join('\n')
            : (result?.message || `Request failed (${response.status}). Reload and try again.`));
    }
    if (!result || typeof result.message !== 'string' || typeof result.redirect !== 'string' || !result.trip?.id) {
        throw new Error('The server did not confirm this operation. Reload the trip to check its saved status before trying again.');
    }
    return result;
}

export function createTripDialogs(dialog) {
    return {
        async confirm(action, message) {
            const complete = action === 'complete';
            const result = await dialog.fire({
                title: complete ? 'Complete Trip?' : 'Dispatch Truck?',
                text: message || (complete
                    ? 'This trip will be marked as completed. Standard editing will no longer be available.'
                    : 'This truck will be dispatched and the trip form will become read-only.'),
                icon: 'warning', showCancelButton: true, focusCancel: true,
                confirmButtonText: complete ? 'Complete Trip' : 'Confirm Dispatch',
                cancelButtonText: 'Cancel',
                confirmButtonColor: complete ? '#15803d' : '#1d4ed8',
                cancelButtonColor: '#475569',
            });
            return result.isConfirmed === true;
        },
        success(message) {
            return dialog.fire({ title: 'Success', text: message, icon: 'success', confirmButtonColor: '#15803d' });
        },
        error(message) {
            return dialog.fire({ title: 'Unable to save trip', text: message, icon: 'error', confirmButtonColor: '#b91c1c' });
        },
    };
}

// Lock before opening the dialog so repeated submissions cannot open parallel confirmations.
export async function withTripSubmissionLock(form, operation) {
    if (form.dataset.busy) return;
    const buttons = [...form.querySelectorAll('button')];
    const states = buttons.map((button) => [button, button.disabled]);
    form.dataset.busy = '1';
    form.setAttribute('aria-busy', 'true');
    buttons.forEach((button) => { button.disabled = true; });
    try {
        return await operation();
    } finally {
        delete form.dataset.busy;
        form.removeAttribute('aria-busy');
        states.forEach(([button, disabled]) => { button.disabled = disabled; });
    }
}
