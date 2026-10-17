# Hostel Management

Blocks, room types and rooms (beds created to match capacity), a bed board for allocation,
vacating and transfers, morning / night roll call with guardian alerts, gate passes that need
both the guardian and the warden, a gate scan desk, a visitor log, and hostel dues.

## Turning it on

```bash
php artisan migrate          # registers the "Hostel Management" feature (off by default)
php artisan migrate:school   # creates the hostel tables in every school database
```

A Super Admin adds **Hostel Management** to a package or sells it as an addon. The tenant
migration creates the `hostel-*` permissions and grants them to School Admin; give wardens and
gate staff a custom role with the ones they need.

| Permission | Allows |
|---|---|
| `hostel-list` | dashboard, bed board, residents, blocks & rooms |
| `hostel-create` / `-edit` / `-delete` | setup, allocating, vacating and moving residents |
| `hostel-attendance` | roll call |
| `hostel-gate-pass` | gate passes, the security desk scan, visitors |
| `hostel-fees` | raising and collecting hostel dues |

## Rules

- One active bed per student. Boys / Girls blocks check the student's gender; Staff blocks
  refuse students. Rooms under maintenance cannot take new residents.
- Shrinking a room's capacity never removes an occupied bed.
- **Roll call**: students on an approved / active gate pass are pre-marked *On Leave*. Students
  newly marked *Absent* trigger a push to their guardian.
- **Gate passes**: requested by the student (app), the guardian (app — counts as guardian
  approval) or the warden (web). The pass becomes *Approved* only when both guardian and warden
  have approved. The security desk scans or types the 8-character code: the first scan checks
  the student out (from an hour before the planned time), the second checks them in and flags a
  late return.
- **Dues**: *Raise dues* creates one due per resident for the cycle containing the chosen month,
  using the room type fee (Monthly / Quarterly / Annual) or the resident's override. Re-running
  it does not duplicate. Dues are collected or waived on the Hostel Fees page with a receipt no.

Hostel dues are a separate ledger; they are not posted into the core Fees module.

## Mobile API (`school-code` header + Sanctum token; guardians add `child_id`)

| Method | Path | |
|---|---|---|
| GET | `api/hostel/overview` | room, bed, roommates, roll call history, unpaid dues, gate passes |
| POST | `api/hostel/gate-pass` | `pass_type` (Day_Out / Night_Out / Home_Visit), `reason`, `out_time`, `expected_in_time` |
| POST | `api/hostel/gate-pass/{id}/decision` | guardians only: `decision` = approve / reject |

The pass code is only returned once the pass is approved.
