# Domain services

The algorithmic core of HoopFlow lives here. These classes must be **plain PHP**: no HTTP, no Eloquent
queries hidden inside business rules, no `request()` or `auth()` calls. That is what keeps them cheap to unit
test, and the working agreement makes those tests mandatory.

| Folder | Owns | Does not own |
|---|---|---|
| `Bracket/` | Seeding, single/double elimination generation, advancing winners, bye handling | Rendering, notifications |
| `Scheduling/` | Slot generation, court availability, rest rules, conflict detection | Persistence of the calendar |
| `Standings/` | Win/loss records, point differential, tiebreakers, seeding order | Display formatting |
| `Eligibility/` | Age-group checks, roster locks, guardian consent, document status | File upload handling |

## Conventions

- One public method per service entry point, named for the action (`generate`, `advance`, `rank`, `assertEligible`).
- Accept and return plain value objects or arrays, not models, where practical.
- Throw domain exceptions (`DomainException` subclasses) rather than returning `false`.
- Scheduling rules must be enforced by PostgreSQL as well (the `tstzrange` exclusion constraint); the service is
  the friendly error, the database is the guarantee.
- Tests live in `tests/Unit/{Bracket,Scheduling,Standings,Eligibility}`.
