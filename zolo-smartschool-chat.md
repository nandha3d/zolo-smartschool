# Zolo SmartSchool Audit Chat

Source: https://claude.ai/share/4b2e35a8-ab25-4a22-ac90-8195010fbb27
Shared by Nandhu. Snapshot of a chat between Claude and Nandhu (Oct 22 – ~Oct 27). Some long user messages were collapsed in the share view, so only their visible first lines appear below.

---

## 1. User (Oct 22)

Repo: https://github.com/nandha3d/zolo-smartschool/tree/main

> audit this school management saas. and give me a full review of what are all need to be done, what are all missing, which one is best feature but others dont have etc. i need 360 degree full review.

### Claude

Cloned the repo and audited the code itself, not just the audit reports inside it. Some in-repo reports are out of date (see section 2).

**Scope and limits:** reviewed the Laravel backend (~94k lines PHP, 90 controllers, 123 models, 5 addons) and two Flutter apps (~850 Dart files). Read and searched only. Did not run the app, compile Flutter, or pen-test. Competitor comparisons come from general market knowledge, not a live benchmark.

**Verdict:** Broad and commercially promising. Newer addons (Timetable V2, GPS, Canteen, Hostel, Library) are better built than the inherited core. Hardening work is real but incomplete. Three things must be fixed before taking money from schools: the licensing question, the forgeable payment webhooks, and a child-location data leak.

### 1. Fix first

**1. Provenance and licensing (biggest business risk).**
- Code descends from eSchool SaaS, a commercial CodeCanyon product. The repo's own audit says so; migrations are still named `version1_x_0`.
- `NOTICE.md` calls it proprietary Animazon property; the in-repo audit says "no licence system exists".
- The code disagrees: installer controllers still check `env('APPSECRET')` and redirect to `install.purchase-code.index`, but that route and controller exist nowhere. That is the footprint of a removed purchase-code verification step.
- Can't tell from the repo whether a valid license is held. If bought, keep receipts and confirm the license tier allows charging many schools on a multi-tenant SaaS (a standard license usually doesn't), and allows presenting the code as proprietary.
- Without a valid license, it can't be legally sold; rebranding doesn't change that.
- Either way, don't give customers or investors the "no nulling found" audit as written.

**2. Forgeable payment webhooks.**
- `WebhookController::flutterwave` (school fees): computes the HMAC from the request body, then "verifies" the body against that same value, so any payload passes. Anyone who can start a payment can POST a fake `charge.completed` and mark fees paid.
- `SubscriptionWebhookController::paystack` and `::flutterwave` (SaaS billing): no signature check at all. An anonymous POST with `event: charge.success` and metadata like `{type: package, package_id, school_id}` creates a transaction and activates a package or addon for any school, unpaid. Both routes are CSRF-exempt.
- Stripe, Razorpay, and tenant-level Paystack do verify signatures.
- Subscription Stripe handler checks `->status != 1`, but the column is `payment_status`, so duplicate-delivery protection looks ineffective. Confirm with a replay test.
- Paystack and Flutterwave log the full payload, including customer PII.
- **Fix:** verify the signature header (`x-paystack-signature` HMAC-SHA512, Flutterwave `verif-hash`) using `hash_equals`, then re-confirm with the gateway's verify API before crediting. If selling only in India, disabling Paystack and Flutterwave removes two attack surfaces.

**3. Live bus and driver data leak.**
- `fleet-live` (`SchoolGpsApiController`) has no role check. Any logged-in user, including a student or parent token, can pull every vehicle's live position plus driver names and phone numbers.
- Parent `trackBus` and driver endpoints do check ownership and role correctly.

### 2. Other findings

**Already fixed (Sept audit calls these open):** Sanctum token expiry (30 days), CORS now defaults to `APP_URL`, payment secrets encrypted, backup restore HMAC-signed with a statement allow-list. Web login also appears throttled. Update that report.

**Still open:**
- **Session and DB settings:** session encryption off; SQL strict mode off by default, so values can be silently truncated in fees and marks.
- **Backup restore:** truncates then inserts with no transaction and no pre-restore snapshot. MySQL `TRUNCATE` auto-commits, so a failed restore means data loss. Signature is tied to `APP_KEY`, so rotating the key makes old backups unrestorable.
- **Authorization:** relies on per-method permission checks. Middleware named `CheckRole` only checks that someone is logged in, not their role. Full coverage can't be proven by reading. Add route-level `can:` middleware and an automated test that hits every route as every role.
- **Input validation:** only 2 FormRequest classes and 299 `$request->all()` calls. Raw SQL inspected is parameterized (good).
- **Sorting:** 95 places sort by a request-supplied column. Laravel quotes identifiers so it isn't injectable, but bad input causes 500s.
- **Possible stored XSS:** `{!! htmlspecialchars_decode($onlineExamQuestion->note) !!}` inside an input attribute in `edit_class_questions.blade.php`. 492 `{!! !!}` uses in total, most are form helpers, but need an audit.
- **Language upload:** `$request->code` goes unvalidated into a filename (path traversal). Needs language-create permission, so low severity.
- **No audit log** of who changed fees, marks, or attendance. Matters for disputes with parents.
- **Tenant switching:** all tenant databases share one credential; switch relies on global mutable `DB::setDefaultConnection`. Risky under queues or Octane.
- **Maps key:** a Google Maps key is committed in the staff iOS `Info.plist`. Check whose account it is; replace with own restricted key.
- **Error handling:** some API errors return HTTP 200, e.g. `CheckChild`.

### 3. What's missing

Each verified by searching the code.

**For the Tamil Nadu market:**
- No SMS or WhatsApp at all. Alerts are FCM push only. Parents live on WhatsApp, so fee reminders and OTP login need MSG91, Gupshup, or Meta WhatsApp API.
- No Tamil. Backend ships English only; apps have hi/ur/en. Tamil is just an entry in a language list.
- Fees: no concessions, sibling discounts, scholarships, or RTE-quota logic. No GST handling on SaaS bills or receipts.
- Report card, TC, bonafide generators: none dedicated. Certificate templates and exam-result PDFs exist, but Samacheer, Matric, CBSE formats don't.
- Government integrations: no EMIS/UDISE+ export, no Aadhaar/APAAR fields, no DigiLocker.
- Biometric and RFID attendance: none.

**General product gaps:** online admissions pipeline (only printable form and contact inquiry), parent-teacher meeting scheduler, health records, live classes (no Zoom/Meet/Jitsi), alumni, multi-branch roll-up (a trust with five branches is five isolated tenants), any AI features.

**Platform gaps:** no audit log; no Sentry or monitoring; no CI or Docker; sessions/cache/queue on files and DB, no Redis; only one queued job class; no data export, erasure, or retention tooling.

**Compliance:** India's DPDP Act adds duties for children's data, including parental consent and limits on tracking children. Bus GPS, canteen, and hostel gate passes all touch that; get legal advice on exemptions.

### 4. Best features (likely differentiators)

- **Timetable V2.** Real constraint solver (~3,300 lines): feasibility pre-check, then backtracking with hard constraints for period, class, teacher, resource availability; then soft-score ranking, repair step, post-validation. Most Indian school ERPs only offer manual grids, so auto-generation is a strong selling point. Only ~two dozen tests; not run. Trial on a messy real school (labs, electives, part-time teachers) before calling it flagship.
- **GPS transport.** Driver's phone is the tracker (no hardware cost). Parents get a live map using a configurable tile server (no Google Maps bill). Geofences, route deviation alerts, ETA, SOS and breakdown reporting, boarding attendance, trip replay. Weak spots: `vehicle_locations` has no retention/pruning; websocket is a single Ratchet PHP process; addon has no tests.
- **Canteen wallet.** Best-built code in the repo: row locks, ledger where balance equals sum of transactions, daily limits, parent controls, pre-orders, kitchen display, meal subscriptions. Prepaid wallet also helps cash flow.
- **Hostel and Library.** Hostel has gate passes needing both guardian and warden approval. Library has circulation, reservations, e-books.
- **Addon business model.** Feature-flagged addons sold through packages and subscriptions give an upsell ladder. With a separate DB per school, arguably the commercial moat.
- **Privacy posture.** Per repo's own audit: no CDNs, no telemetry, no licence phone-home. Not every asset re-verified. Good selling point for schools holding children's data.
- **Breadth in one product:** payroll slips, staff leave and attendance, online exams with Excel import, diaries, chat, website CMS, Razorpay.

Whether competitors lack these depends on who is compared; not benchmarked live.

### 5. Engineering quality

- **Tests:** ~24 PHP test methods for 94k lines, 1 Flutter test file in the student/parent app, none in staff app, no CI.
- **Core code:** inherited eSchool code with giant controllers (`TeacherApiController` >1,700 lines) and repository boilerplate for 80+ tables. Addons use cleaner service layers.
- **Flutter apps:** Bloc and GetX mixed; base URL hard-coded to zoloschools.com. Repo's audit says a 3,917-line package rename was never compiled, so run `flutter pub get && flutter analyze` first. It also flags dev tools (`curl_logger_dio_interceptor`, `device_preview`) in production dependencies (not verified).
- **Scale:** file/DB setup and per-request tenant reconnect suit dozens of schools. Rework (Redis, Horizon, connection pooling) before hundreds.
- **Repo hygiene:** one squashed commit (later corrected — see section 5 below), no history. Audit reports, .docx/.html files, AI-agent specs, README with machine-specific paths all at root. Move them out.

### 6. Priorities

**P0, this week:** resolve licence question; fix webhooks; role check on `fleet-live`; set `CRON_SECRET` and `CORS_ALLOWED_ORIGINS`; replace Maps key.
**P1, 30 days:** audit log; FormRequests on fees, marks, attendance; session encryption and strict mode (test on staging first); transactional restore with auto-snapshot; route-by-role authorization test; CI and Sentry.
**P2, 60–90 days:** WhatsApp/SMS; Tamil; concessions and GST; TN report-card and TC formats; EMIS export; biometrics; GPS data retention; Redis.
**P3:** online admissions, PTM, multi-branch roll-up, live classes, AI.

**Positioning:** lead with a "safe school" story for Tamil Nadu built on Timetable V2, GPS, and the canteen wallet; sell addons on top.

Offered next: write webhook fixes, build role-by-route authorization test, or run a competitor benchmark.

---

## 2. User (Oct 22)

> how to implement the real wallet?

### Claude

Assumes "real wallet" = parents top up the canteen wallet online (UPI/card) and the balance is spendable at the canteen. Today only staff can add balance, by hand. The ledger (row locks, `balance_after`, signed amounts) is a good base; the work is money in, money back out, and reconciliation.

### 1. Settle the money flow first

- **Keep it closed-loop.** RBI's FAQ says closed-system PPIs (can't pay third parties) aren't a payment system needing RBI authorisation. Wallet should only buy from the school's own canteen: no bank cash-out, no wallet-to-wallet transfers. Paying other merchants later = authorised-PPI territory.
- **Draft pending.** RBI's April 2026 draft Master Direction on PPIs continues to keep closed-system PPIs outside RBI regulation, but isn't final; recheck before launch.
- **School should receive the money, not Animazon.** Use each school's own Razorpay account (repo already stores per-school gateway credentials) so funds settle straight to the school. If money passes through your account you start looking like a payment aggregator. Confirm structure, GST treatment, unused-balance policy with a CA or lawyer.

### 2. Build order

**a) Top-up intent.** App calls `POST canteen/wallet/topups {amount}`. Server creates a `canteen_topups` row (pending) and a gateway order, returns order id to the app's Razorpay checkout. Never credit from the app's "payment success" callback; the app only polls top-up status.

```php
Schema::create('canteen_topups', function (Blueprint $t) {
    $t->id();
    $t->unsignedBigInteger('school_id')->index();
    $t->foreignId('wallet_id')->constrained('canteen_wallets');
    $t->unsignedBigInteger('amount_paise');            // integer minor units, not floats
    $t->string('gateway_order_id', 64)->unique();
    $t->string('gateway_payment_id', 64)->nullable()->unique();
    $t->string('status', 12)->default('pending');      // pending|succeeded|failed
    $t->timestamp('credited_at')->nullable();
    $t->timestamps();
});
```

**b) Credit only from a verified webhook.** Per-school URL (`/webhook/canteen/razorpay/{schoolCode}`) so tenant and secret come from the URL, not payload metadata. Verify before touching anything, then credit idempotently:

```php
$sig = $request->header('X-Razorpay-Signature', '');
$calc = hash_hmac('sha256', $request->getContent(), $secret);   // raw body
abort_unless(hash_equals($calc, $sig), 400);

DB::transaction(function () use ($orderId, $paymentId, $paidPaise) {
    $topup = CanteenTopup::where('gateway_order_id', $orderId)->lockForUpdate()->first();
    if (!$topup || $topup->status !== 'pending') return;          // replay = no-op
    if ($paidPaise !== $topup->amount_paise) {                    // trust your row, not the payload
        $topup->update(['status' => 'failed']); return;
    }
    $this->canteen->topUp($topup->wallet, $topup->amount_paise / 100, 'Online', $paymentId);
    $topup->update(['status' => 'succeeded', 'gateway_payment_id' => $paymentId, 'credited_at' => now()]);
});
```

This is exactly where the repo's Flutterwave webhook goes wrong: it computes the "expected" signature from the body itself. Don't copy that pattern.

**c) Harden the ledger.**
- `CanteenService` casts balances to `(float)`. Move to integer paise or bcmath.
- Make `canteen_wallet_transactions` append-only; corrections are new reversal rows, never edits/deletes.
- Unique idempotency key on purchases so a double-tapped POS or pre-order can't charge twice.
- Nightly job: each wallet's balance equals sum of its transactions; gateway-captured payments match `canteen_topups`. Alert on mismatch.

**d) Refunds and disputes.**
- Cancelled orders refund to wallet (already supported).
- Cash refund goes back to original payment via gateway refund API, admin-approved, never above the unspent top-up.
- Decide policy for chargeback after money is spent. Lock the wallet (`is_locked` exists) and define who absorbs loss.
- Publish terms covering refund on leaving school and no silent expiry of balances.

**e) Spending safety.** Min/max top-up and a balance cap. Keep daily limit and parent lock. Add low-balance alerts and student PIN or RFID check at the counter.

### 3. Tests before any real money

- Forged webhook (bad signature) rejected.
- Replayed webhook credits exactly once.
- Mismatched amount not credited.
- 50 parallel purchases never overdraw the wallet.
- Wallet balance equals sum of ledger after random operations.

Then run sandbox, pilot one school, reconcile daily for two weeks before rollout.

---

## 3. User (Oct 22)

> for example, if there is a lab in "A" block and and another is at "b" block, so the time table generation how it can handle?

### Claude

Checked how the generator models rooms, resources, and travel between buildings. "Block" in the generator only means a lesson block, not a building.

**What it does today**
- Labs are resources: `timetable_resources` (name, type, capacity, weekly unavailable slots), e.g. "Physics Lab – Block A" and "Physics Lab – Block B".
- Each subject requirement points to one lab (`resource_id`, `resource_required`).
- Hard rules: one class per lab per period; no placement during lab's blocked slots. Double-period practicals supported via consecutive lesson blocks.

**What it can't do today**
- **No location or travel time.** No building column. A class can be in Block C in period 3 and Block A lab in period 4 with zero walking time. Same for a teacher covering both labs. Scoring has no distance weight.
- **No lab pooling.** A requirement is tied to one specific lab; can't choose "whichever Chemistry lab is free".
- **Capacity looks unused.** Lab occupancy is a yes/no per period; a 50-student class won't be split into batches for a 25-seat lab (from reading, not running).

**No-code workarounds**
- Assign each class-section to the lab in or nearest its home block.
- Mark labs unavailable except in periods right after assembly, break, or lunch (break = walking buffer).
- Keep practicals as double periods to cut block changes.

**Proper fix**
- Data: `timetable_locations` table; travel-minutes table between locations; `location_id` on resources; `home_location_id` on class-sections.
- Solver: periods already have `start_time`/`end_time`, breaks are their own non-instructional periods. If a class or teacher has adjacent lessons in different locations and the gap is shorter than travel time, reject the slot. Add next to existing hard constraints in `searchBacktrack`:

```php
// after "Hard constraint 4"
$loc = $resourceId ? ($resourceLoc[$resourceId] ?? null) : ($classHomeLoc[$classId] ?? null);
if ($this->travelConflict($occupancy, $day, $instPeriodsArr, $pIdx + $step, 'class',   $classId,   $loc)
 || $this->travelConflict($occupancy, $day, $instPeriodsArr, $pIdx + $step, 'teacher', $teacherId, $loc)) {
    $canPlace = false; break;        // or subtract a soft penalty instead of rejecting
}
```

`travelConflict` looks at the previous/next period, reads location from `$occupancy[$day][$pId]['class_loc' | 'teacher_loc']`, and returns true if locations differ and the gap is smaller than the travel matrix.

Details:
- **Undo on backtrack:** set/unset location entries wherever search sets/unsets resources (~lines 746, 797).
- **Locked and existing lessons:** seed the location map from them too (~lines 503–520).
- **Regular classrooms count:** ordinary lessons use the class's home location.
- **Feasibility check:** teach it about travel so impossible requests fail early.

Optional: soft `WEIGHT_TRAVEL_BUFFER` in scoring; `resource_group` so a requirement can say "any Chemistry lab".

Estimate: 2–3 days incl. settings screens and tests. Add tests: two-block school where naive solution is illegal, plus regression test that single-block schools generate exactly as before.

---

## 4. User (Oct 22)

> there are separate buildings with some distance. so we need to assign the lab in the same block itself right?

### Claude

Yes. For now, assigning each class to the lab in its own block is right and simplest. Since the generator can't see distance, you're effectively encoding "no walking between buildings" by hand.

**Setup**
1. Name labs by block ("Physics Lab – Block A"). Assign each class-section's lab requirement to the lab in its home block.
2. Check capacity: a lab has roughly (working days × periods/day) slots a week. Sum weekly lab periods needed by assigned sections. ≤ ~60% of slots: solver places easily. Near/over: some sections must use the other block's lab.
3. For sections that must cross blocks, schedule lab periods right after assembly/break/lunch (mark lab unavailable in other periods for those sections, or use double periods). Break covers the walk.
4. Teachers: keep each lab teacher in one block. If cross-block is needed, use teacher availability to give different blocks on different days, or separate cross-block lessons by a break.

**When same-block isn't enough:** a block lacks a lab for a subject; a lab is overloaded; shared rooms (computer lab, auditorium, ground) — keep those after a break.

**Why manual until the patch:** generator will happily place a Block C class in Block A's lab one period after a Block C lesson. Pre-assignment is the only safeguard. Worth adding a report listing every placed lab lesson where class home block ≠ lab block, and every back-to-back pair in different blocks with no break.

The location/travel-time patch would make the solver enforce this. If only two or three schools have multiple buildings, manual is fine; build the patch once multi-block schools are common.

---

## 5. User (7 days ago)

> we need a detailed lab management, like student group and group leader etc for the lab. also class leader, group leader event group leader, event management and incharge etc. to be implemented like a professional and feature rich, complete audit and give me a detailed implementation plan with the new audit by checking each files and functions etc. give it as a prompt that can be doable by a low level ai agent can completely finish without mistake 100%.

### Claude

Recorded the requirement, then audited the code, since the plan must follow the repo's real conventions. Findings (steps only, no final plan text appears in the shared snapshot):

- No leader, group, event, or lab-management code exists; greenfield module.
- Audited addon scaffolding conventions, provider registration, sidebar, tenant migration mechanics, routes, and controller/model/view style references.
- Audited user/student/teacher model relationships the new tables must hook into.
- Checked shared helpers: `ResponseService`, `BelongsToSchool` trait, controller helpers, how the Hostel API resolves students/guardians and sends notifications.
- Checked core tables/models (students, class sections, teachers, staff, users), how tenant migrations are applied, how the current session year is read, view/sidebar patterns, how the Teacher role gets permissions (decides lab in-charge access), and JS pattern in views.

The snapshot ends before the agent prompt itself.

---

## 6. User (6 days ago)

Repo: https://github.com/nandha3d/zolo-smartschool

> Now the repo is updated, audit again for complete review, ... Again audit the full files and give me a review what we have done and what needs to be done..

### Claude

Re-cloned fresh and compared against the earlier audited version.

**Summary:** Of 18 commits since the audited version, the new work is mostly UI polish plus the first slice of the groups and leadership feature. None of the critical issues flagged previously has been fixed, and the roll-number change introduced a new regression expected to crash both mobile apps.

**Correction:** earlier claim that the repo was a single squashed commit came from a shallow clone. Real history is 33 commits.

**Verification limits:** read and searched only. Did not run the app or migrations; PHP not installed in sandbox so no lint; Flutter apps not built.

### What changed

18 commits touched 93 files (+14.9k / −3.6k). Models 123 → 134, tenant migrations 28 → 35, 14 new web routes. Test files stayed at 4; API routes stayed at 193.

| Area | Done | Quality |
|---|---|---|
| UI/design system | Inter font, 20 theme presets, button components, sidebar fixes | Fine |
| Timetable | Manual place/clear slot, card view, class and resource block, same-block preference in solver | Properly permission-checked |
| Groups and leadership | Cockpit page for class leader, vice leader, bench leader, group leader | Works, but no authorization |
| Exam blocks | Page to schedule exam and revision blocks | Works, but no authorization |
| Lab, attendance, leaderboard | 6 tables and models | Nothing uses them yet |
| Roll numbers | Prefix, padding, per-class auto prefix | Causes a regression |

### Previous findings, re-checked

| Finding | Status |
|---|---|
| Flutterwave school-fee webhook self-signs | Not fixed |
| Subscription Paystack/Flutterwave webhooks unauthenticated | Not fixed |
| `fleet-live` has no role check | Not fixed |
| Licence remnants in installer | Not fixed |
| Session encryption off, DB strict mode off | Not fixed |
| Audit log, CI, SMS/WhatsApp, Tamil | Still absent |

### New problems in the new code

1. **Roll-number change will likely crash both mobile apps.** Migration turns `students.roll_number` into a string. Both Flutter apps parse it with `as int?` / `as int`, which throws on a string — hits every school, not only those using prefixes. Six places do `orderBy('roll_number')`; on a string column that sorts "1, 10, 11, 2", breaking attendance sheets, diary, reports, student lists. `PromoteStudentController` uses `max(roll_number)` and `(int)` casts, wrong on strings (and already gave every promoted student the same number). **Fix:** keep `roll_number` integer; add a display column such as `roll_number_label`.
2. **Two new controllers have no authorization.** `StudentLeadershipController` and `ExamScheduleBlockController` have no permission or feature checks. Students/guardians can't use the web panel, so any staff account can create/delete leaders, groups, and exam blocks for any class. Both return raw exception text to the browser. Exam blocks also have no double-booking or overlap validation.
3. **Weak data integrity in new tables.** No foreign keys; nothing stops two active "Class Leaders" in one section. Group leader stored in three places (can drift). `removeRole` hard-deletes (no history; leader's badge remains after group deletion). Student IDs not validated against the class. Roles/types are SQL enums (schema change per new role).
4. **Same-block lab swapping is fragile.** Solver swaps a lab for any same-block resource with same type; `type` and `block` are free text, so "Block A" ≠ "block a", and a Chemistry class could land in a Computer lab if both are typed "Lab". Solver budget cut from 60 s to 5 s and 100k to 20k nodes, which may hurt large schools.
5. **Tests don't run elsewhere.** Still point at `zolo_schools_3_demo` and school 3 on the author's machine. "25/25 passing" true only there. No tests for new features; no CI.
6. **Smaller issues.** New migrations duplicated into the central migrations folder (would create tenant tables in the master DB). `backups/backup_log.json` malformed with empty snapshot fields (backups may not be succeeding). JSON endpoints use `noFeatureThenRedirect`.

### Requested features vs reality

| Feature | Status |
|---|---|
| Class leader | Assign/remove only; no powers or permissions |
| Group leader | Group creation only. No edit, add/remove members, change leader |
| Lab batches and batch leader | Tables only, no screens |
| Lab in-charge, equipment, incidents, lab attendance | Not built |
| Event groups, event management, event in-charge | Not started |
| Audit log | None |
| Mobile API or notifications for any of this | None |

### Recommended order

1. **Immediately:** webhooks, `fleet-live`, licence confirmation.
2. **This week:** revert `roll_number` to integer plus label column; add permission and feature checks to both new controllers.
3. **Then:** foreign keys and constraints on new tables; finish labs, groups, events on existing tables.
4. **Alongside:** make tests portable, then add CI.

The agent prompt should now extend the existing `student_groups`, `lab_batches`, `lab_sessions` tables instead of creating new ones, with roll-number and authorization fixes as first tasks. Offered to write it that way.

---

## 7. User (3 days ago)

Repo: https://github.com/nandha3d/zolo-smartschool.git

> Why schools are not adopting for this digital conversion

### Claude (after "Retry" 2 days ago)

No new commits; repo as last audited. Market evidence is mostly vendor marketing (one a demo site with suspiciously tidy stats), so treat as directional, not proven. Best guess: schools aren't refusing digital in general; this product doesn't yet solve the one problem a principal buys software for, and hasn't earned trust.

### Product reasons (from the audit)

- **Parents live on WhatsApp**, and Zolo has neither WhatsApp nor SMS (push only). Competing Tamil Nadu vendors advertise SMS/WhatsApp notifications as standard.
- **No Tamil and no Tamil Nadu formats.** One vendor says the biggest blocker in smaller cities is parents opening an English-only interface (marketing quote). Samacheer and Matric report cards missing.
- **Fee handling falls short:** no concessions, sibling discounts, scholarships, GST receipts. In Tamil Nadu over 82,000 RTE seats are listed across 7,740 schools, so quota-student fee handling is routine. Schools must also now display approved fee structures on notice boards (a sellable compliance feature).
- **Double data entry with government portals.** No EMIS export. Sources disagree on whether private schools are required on EMIS; check with a real school.
- **First impressions risky:** roll-number change likely crashes both apps, payment webhooks can be forged, no CI, tests only run on the author's machine.
- **Too much product, no clear starting point.** ~30 modules; schools buy "collect fees faster" or "tell parents instantly", not modules. One competitor pitch: INR 39,999 one-time or INR 3,999/month (vendor claim) including data migration. Compare pricing and onboarding.

### Market and behaviour reasons (general, vendor-sourced)

- Teacher resistance and training; cost, user adoption, technical support as main hurdles.
- Small-school budgets and owner-driven decisions.
- Fear of lock-in and data loss.
- Unreliable internet in some areas.

### Go-to-market gaps (inference)

- No local proof: schools in Erode trust a school they know. Get 3–5 reference schools; use the Lions Club network.
- No done-for-you onboarding: import their Excel/Tally data, run a 30-day pilot before asking for money.
- No local support person: a WhatsApp number answered in Tamil within hours beats features.
- Selling the whole suite: start with Fees + Attendance + WhatsApp alerts in Tamil; sell timetable, GPS, canteen as upgrades.

### How to find the real reason

Ask 10 schools that said no or went quiet: What did you use before and what annoyed you most? Who decides, and what did they object to? What would make you switch next month? Count where they drop off: demo, pilot, or paid.

---

## 8. User (2 days ago)

> We haven't started pitching it yet, but why this long they haven't converted?

### Claude

If no pitch has happened, no school has had the chance to convert, so there's no market verdict to explain. The earlier answer treated silence as rejection, which was wrong. The real question is why pitching hasn't started.

**Likely holding back**
- Not pitch-ready: forgeable webhooks, bus-location leak, licence question, roll-number crash risk.
- New features keep winning over launch work: recent commits are almost all features while critical fixes stayed untouched.
- No simple core pitch: ~30 modules; a principal needs one sentence, e.g. "collect fees faster and tell parents instantly, in Tamil".
- No pilot school yet.
- Tamil and WhatsApp missing.

**Plan to first pitch in ~3 weeks**
- **Week 1 — make it safe:** fix or disable Paystack/Flutterwave webhooks; role check on `fleet-live`; revert `roll_number` to integer plus display label; permission checks on the two new controllers; settle the licence question.
- **Week 2 — build the wedge, then freeze features:** fees + attendance + parent app with WhatsApp alerts; Tamil labels on parent-facing screens and receipts; no new modules until a school is live.
- **Week 3 — pilot:** one or two friendly schools via the Lions Club; free 30-day pilot, import their Excel data yourself; ask what they'd pay at the end.

Then pitch with a real reference school instead of a feature list.

---

## 9. User (2 days ago)

> I am not asking about our project, generally asking why schools are still using traditional methods?

### Claude

General question: paper works well enough, and the benefits and costs of switching fall on different people. No good independent studies found; most sources are vendor writing, so these are commonly cited reasons, not hard data.

**Why schools stay on paper and WhatsApp**
- **Free and familiar.** Registers, Excel, WhatsApp. Nothing forces change; extra work lands on teachers while benefits go to management.
- **Cost.** Tight budgets in smaller schools; implementation guides name cost, user adoption, technical support (customisation, maintenance).
- **Teacher resistance, skills, turnover.** Most cited barrier; training takes time and leaves when staff leave.
- **Weak infrastructure.** Internet, devices, power not guaranteed. One vendor blog claims only ~54% of Indian schools had functional on-campus internet in 2024 (unverified).
- **Parents.** Not all have smartphones or data plans; language barrier; WhatsApp already feels good enough.
- **Schools don't know what they need.** Vendors sell feature lists, not one clear problem solved.
- **Bad past experiences and trust.** Vendors disappear, data gets locked in, privacy worries.
- **Duplicate work with government systems.** Official portal entry still needed, so new software feels like double work.
- **Buying is personal.** One owner or trustee decides on relationships; cold pitches rarely land; weaker urgency outside big cities.
- **Some owners prefer less traceability.** Anecdotal, unproven: informal fee handling is easier when not everything is recorded.

**What tips schools:** a painful, measurable problem (fees not collected on time), a respected peer school already using it, and the vendor doing setup and training.
