export async function readStatusResponse(response, updating = false) {
    const result = await response.json().catch(() => null);
    if (!response.ok) {
        const fallback = response.status === 419 ? 'Your session expired. Reload the page and try again.' : 'Unable to complete the request. Please try again.';
        throw new Error(result?.errors ? Object.values(result.errors).flat().join('\n') : result?.message || fallback);
    }
    const trip = result?.trip;
    if (!trip || typeof trip.trip_id !== 'string' || typeof trip.current_status !== 'string'
        || typeof trip.created_at !== 'string' || !Number.isInteger(trip.version)
        || (updating && typeof result.message !== 'string')) {
        throw new Error('The server did not confirm this operation. Search for the trip again to check its current status.');
    }
    return result;
}
