# Contributing / coding standards

1. **Form Requests** for every write endpoint.
2. **Policies** before mutating or viewing tenant data.
3. Keep business rules in **Services**, not React components.
4. Prefer **Enums** for roles, outcomes, statuses.
5. Never expose API secrets in Inertia props.
6. Update `docs/changelog.md` when shipping a milestone.
7. Add feature tests for tenancy-sensitive changes.
8. Match Altruist Core tokens from `ui_design/altruist_core/DESIGN.md`.
