# HR02-HAPPY — attempt 13 (interview email omits Manila timezone label)

- **Date:** 2026-11-04 UTC
- **Classification:** Product defect, Medium severity (FND-HR-005); no fix applied
- **Journey:** `HR02-HAPPY`, 13th invocation
- **Actors:** HR Officer, public applicant, System Admin observer
- **Command:** `E2E_AUTH_ACTORS=hr_officer,system_admin node scripts/e2e/runner.mjs test-split --grep HR02-HAPPY`
- **Run IDs:** Playwright run `26817554ad4e`; context `e7a51d29-3769-4d0b-90c4-1ddc85b7f01c`
- **Result:** Fresh E2E baseline and selected actor UI sign-ins passed. The selected HR02 journey ran by itself in headless Chromium with one worker after the guarded service restart. Steps 1–5 passed. Step 6 performed the HR UI transition to Interview and delivered the candidate email, then failed because the email omitted the expected `Asia/Manila` label. Steps 7–10 were blocked by that failure.

## Steps

| Step | Actor | Result | Evidence |
|---|---|---|---|
| 1. Create a run-scoped draft posting | HR Officer | **Passed.** Created and verified `JP-202611-0001` through Recruitment UI, including the draft state, one slot, creator, and audit invariant. | `assets/hr02-happy-attempt-13-interview-timezone-label/walkthrough/001-hr-creates-a-run-scoped-draft-posting-in-recruitment.png` |
| 2. Publish the posting | HR Officer | **Passed.** UI action changed the posting to Open and the posted timestamp/audit assertions passed. | `.../walkthrough/002-hr-publishes-the-posting.png` |
| 3. Apply through public Careers | Public applicant | **Passed.** Public form created application `JA-202611-0001`, tracking code `RCT-EBE7XF`; database, posting count, candidate confirmation email, and diagnostics checks passed. | `.../walkthrough/003-candidate-finds-the-live-posting-and-applies-through-careers.png` |
| 4. HR follows notification; System Admin observes application | HR Officer, System Admin | **Passed.** HR followed the in-app notification to the New application. System Admin opened Recruitment → Applications in its own session, found the same candidate, and opened the same record. Exact intended notification recipients/link and observer checks passed. | `.../walkthrough/004-hr-follows-the-notification-hand-off-and-an-administrator-opens-the-same-application-as-ob.png` |
| 5. Advance to Screening | HR Officer; System Admin observer | **Passed.** HR used the UI action; the observer updated without refresh, the `new → screening` event actor was HR Officer, and the candidate email checks passed. | `.../walkthrough/005-hr-advances-the-application-to-screening.png` |
| 6. Schedule interview and advance to Interview | HR Officer; System Admin observer | **Failed at the email content assertion.** HR submitted the schedule in the UI; both HR and observer showed Interview without refresh. The read-only application check reached stage `interview` and returned one matching interview. The exact-recipient candidate email arrived with the expected candidate, posting, interviewer, location, tracking code, and local time `November 6, 2026 8:23 PM`, but lacked the expected `(Asia/Manila)` label. A read-only query confirmed the stored local schedule `2026-11-06 20:23:00`, one pending interview, and the HR Officer as creator. The event log records `screening → interview` and `interview.scheduled`, both by HR Officer. | `.../test-failed-1.png`; trace; journey context; read-only database evidence summarized here. |
| 7. Record interview outcome Passed | HR Officer; System Admin observer | **Blocked.** Not run. | No step state or screenshot. |
| 8. Advance the passed candidate to Offer | HR Officer; System Admin observer | **Blocked.** Not run. | No step state or screenshot. |
| 9. Mark the accepted offer Hired | HR Officer; System Admin observer | **Blocked.** Not run. | No step state or screenshot. |
| 10. Track final Hired status | Public applicant | **Blocked.** Not run. | No step state or screenshot. |

## Finding and cause

The app timezone is `Asia/Manila`; the database stores this interview's local schedule as `2026-11-06 20:23:00`, matching the email's `November 6, 2026 8:23 PM`. The missing timezone label is independent of the local time conversion.

`InterviewScheduledMail` sets its public `$scheduledAt` property without the label, then passes a label-bearing `scheduledAt` value in `Content::with`. Laravel's `Mailable::buildViewData()` adds initialized public properties after view data, replacing the supplied value with the unlabeled property. The email template renders that variable. Evidence: `api/app/Modules/HR/Mail/InterviewScheduledMail.php:23,43-45,66-78`, `api/vendor/laravel/framework/src/Illuminate/Mail/Mailable.php:353-367`, and `api/resources/views/emails/recruitment/interview-scheduled.blade.php:9`.

This finding does not establish a wrong stored date or wrong local time. The broken behavior is the missing timezone indicator in the candidate notification, contrary to the mail content assembled by the source and the process invariant. No app code or test assertion was changed.

## Coverage and artifacts

- **Journey:** 5/10 steps passed, step 6 failed, and 4 downstream steps were blocked. `HR02-HAPPY` remains incomplete.
- **State transitions:** UI and read-only event evidence confirmed `new → screening` and `screening → interview`, performed by HR Officer. The interview record remains `pending`; Offer, Hired, posting fill, and public final tracking were not reached.
- **Side effects verified:** New-application notification hand-off and recipient set; candidate application-confirmation and screening emails; exact interview email recipient; observer live updates through Interview; interview record/event actor.
- **Run artifacts:** [Archived trace, failure context, screenshots, journey context, and walkthrough](assets/hr02-happy-attempt-13-interview-timezone-label/).
- **Environment:** The split runner completed the single journey without a service interruption. ENV-E2E-002 remains open because its stop-signal source is unknown.
