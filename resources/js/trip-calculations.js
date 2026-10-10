// Rate definitions come from TripRate::personnelRateDefinitions; no designation is fixed here.
export function personnelSalaryCents(definition, drops) {
    if (!definition) return null;
    const extraDrops = Math.max(0, Number(drops) - definition.minimum_drops);
    return definition.base_cents + extraDrops * definition.additional_cents;
}
