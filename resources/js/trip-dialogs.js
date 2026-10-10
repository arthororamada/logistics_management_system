export { withSubmissionLock as withTripSubmissionLock } from './notification-core.js';

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

export function createTripDialogs(notifications) {
    return {
        confirm: async (action, message) => {
            const complete = action === 'complete';
            return notifications.confirm(message || (complete
                ? 'This trip will be marked as completed. Standard editing will no longer be available.'
                : 'This truck will be dispatched and the trip form will become read-only.'), {
                title: complete ? 'Complete Trip?' : 'Dispatch Truck?',
                confirmButtonText: complete ? 'Complete Trip' : 'Confirm Dispatch',
                confirmButtonColor: complete ? '#15803d' : '#1d4ed8',
            });
        },
        success(message) {
            return notifications.success(message);
        },
        error(message) {
            return notifications.error(message, 'Unable to save trip');
        },
    };
}
