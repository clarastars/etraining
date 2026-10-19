# Training Disclosure (الإفصاح عن التدريب)

## Overview

Training Disclosure is a company recorded-course compliance program. Admins capture a disclosure request, prepare a recorded course (manually or by importing from Google Drive), enroll company employees, and send each employee a unique passwordless access link. The employee checks in, watches lessons on allowed weekdays, and marks them complete. When every lesson is done, the enrollment waits for certificate approval. Approving the certificate generates a PDF and emails it to the employee, with the company address CC’d.

The HQ copy summarizes the intent: enrollments, access links, attendance, progress, and certificate approval.

Disclosure **requests** are intake records only. They are not linked in the database to enrollments. Ops use the draft trainee list as a working note, then enroll real `Trainee` records into a recorded course.

## Process (as shown in the HQ UI)

1. Record the disclosure request (company, trainee count, and draft trainee details).
2. Create the recorded course and add lessons and videos.
3. Enroll company employees and email unique access links.
4. Employees take the course without logging in (check-in / check-out).
5. When all lessons are done, status becomes pending certificate approval.
6. Approve to send the PDF certificate to the employee with the company CC’d.

## Architecture

### Core Components

| Layer | Classes / files |
|---|---|
| **HQ** | `TrainingDisclosureController`, `resources/js/Pages/Back/TrainingDisclosure/Index.vue` |
| **Requests** | `TrainingDisclosureRequestsController`, `TrainingDisclosureRequest` |
| **Courses admin** | `RecordedCoursesController`, `RecordedCourseLessonsController`, `RecordedCourseLessonVideoChunkUploadController` |
| **Enrollments admin** | `RecordedCourseEnrollmentsController`, `TraineeRecordedCourseEnrollmentsController` |
| **Learner (public)** | `PublicRecordedCourseAccessController` + Blade views under `resources/views/recorded-courses/public/` |
| **Learner (logged in)** | `Trainees\RecordedCoursesController` + Vue pages under `resources/js/Pages/Trainees/RecordedCourses/` |
| **Models** | `RecordedCourse`, `RecordedCourseLesson`, `RecordedCourseEnrollment`, `RecordedCourseLessonProgress`, `TrainingDisclosureRequest` |
| **Services** | `RecordedCourseProgressService`, `RecordedCourseCertificateService`, `RecordedCourseLessonVideoChunkUploadService` |
| **Jobs / mail** | `SendRecordedCourseAccessLinkJob`, `SendRecordedCourseCertificateJob`, `RecordedCourseAccessLinkMail`, `RecordedCourseCertificateMail` |
| **Drive import** | `ImportRecordedCoursesFromDriveCommand` (`recorded-courses:import-from-drive`) |
| **Webhooks** | `Webhooks\MailController` (Mailgun delivery for access + certificate emails) |

`RecordedCourse` and `TrainingDisclosureRequest` use `TeamScope`. Enrollment lookups that load a course from the public or job context call `withoutGlobalScopes()` on the course.

### High-level flow

```
Disclosure request (draft list)
        │  manual ops
        ▼
RecordedCourse + Lessons (S3 video)
        │
        ▼
Enrollment (unique access_token)
        │
        ├── email: access link
        ▼
Public /ar/recorded-courses/access/{token}
        │  check-in → unlock → watch → mark complete
        ▼
All lessons complete → certificate_status = pending_approval
        │
        ▼
Admin approve → PDF → email employee + CC company
        │
        ▼
Mailgun webhook updates delivery fields
```

## Admin HQ

**Route**: `GET /back/training-disclosure` (`back.training-disclosure.index`)

**Permission**: `manage-recorded-courses`

**UI**: `resources/js/Pages/Back/TrainingDisclosure/Index.vue`

The hub shows:

- Stats: courses, distinct companies with enrollments, enrollments, checked-in, completed, pending certificate approval, certificates sent, disclosure request count.
- Cards for disclosure requests, recorded-course management, and company enrollment.
- Recent disclosure requests (8) and a pending-certificate queue (15). Users with `approve-recorded-course-certificates` can approve from the queue.
- Course list (latest 50) with readiness, Drive sync metadata, enrollment / completion / pending counts.

A course is **ready** when it has at least one lesson and every lesson has an S3 video. The settings wizard uses a stricter `ready_for_engineers` flag (names, at least one weekday, at least one lesson, all videos present).

Sidebar: `/back/training-disclosure` is shown when the user can `manage-recorded-courses`. It stays active for `back.training-disclosure.*` and `back.settings.recorded-courses.*`.

## Disclosure Requests

CRM-style intake. Creating a request does **not** create trainees or enrollments.

**UI**

| Page | Route |
|---|---|
| List | `back.training-disclosure.requests.index` |
| Create | `back.training-disclosure.requests.create` |
| Show / edit / delete | `back.training-disclosure.requests.show` |

**Fields**

- Optional `company_id` (existing company)
- Required `company_name`
- Required `trainees_count` (1–5000)
- Required `trainees` array of `{ name, phone?, email? }` stored as `trainees_draft` JSON

Empty names are skipped. Empty phone/email become `null`.

**Numbering**: `TrainingDisclosureRequest::nextNumber()` uses `MaxNumber::generateForPrefix('disclosure_request', 99, 3)` and prefixes `E`. First number is `E100`.

## Recorded Courses (settings)

**UI**: `resources/js/Pages/Back/Settings/RecordedCourses/`

| Page | Purpose |
|---|---|
| `Index.vue` | Catalog: Arabic/English name, lesson count, allowed weekdays, manage / delete |
| `Create.vue` / `Edit.vue` | Course shell |
| `Show.vue` | Course overview + step nav |
| `Details.vue` | Names and description |
| `Schedule.vue` | Allowed weekdays (the schedule save always sets `unlock_delay_hours` to `0`) |
| `Lessons/Index.vue` | Lesson titles and order |
| `Lessons/Video.vue` | Upload or chunk-upload video to S3 |
| `Enrollments.vue` | Single/bulk enroll, company summaries, resend link, approve certificate |

A course is a bilingual catalog item (`name_ar`, `name_en`, `description`) plus:

- `allowed_weekdays`: JSON ints `0` (Sunday) … `6` (Saturday). Empty list means unlock is never allowed.
- `unlock_delay_hours`: hours that must pass after completing lesson N before lesson N+1 can unlock. Config default is `0`. The schedule screen forces `0`; create/update and Drive import can still set a delay.
- Drive metadata: `drive_folder_id` (unique per team) and `drive_synced_at`.

### Lessons and video

Lessons belong to a course, ordered by `sort_order`. Video is **not** a column. It lives in Spatie media collection `video` on the `s3` disk (`RecordedCourseLesson::VIDEO_COLLECTION`). Accepted MIME types: `video/mp4`, `video/webm`, `video/quicktime`.

Large files use chunk upload:

1. `POST .../lesson-videos/chunk-uploads` — start
2. `POST .../lesson-videos/chunk-uploads/{upload}` — chunk
3. `POST .../lesson-videos/chunk-uploads/{upload}/complete` — assemble and attach to S3

Admin preview streams via `settings.recorded-courses.lessons.stream`.

Deleting a lesson clears its media collection.

### Readiness (`RecordedCoursesController::readiness`)

- `details_complete`: both names filled
- `schedule_complete`: at least one allowed weekday
- `all_lessons_have_video`: ≥1 lesson and every lesson has media
- `ready_for_engineers`: all of the above

## Enrollment

Each enrollment is unique on `(trainee_id, recorded_course_id)`. On create, the model assigns a 48-character `access_token` and `certificate_status = none`.

### How employees get enrolled

1. **Course enrollments page** — single trainee or bulk by company (`company-trainees` JSON endpoint marks already-enrolled people).
2. **Trainee profile** — `POST /back/trainees/{trainee}/recorded-course-enrollments` only if `trainee.is_engineer`.

Trainee and course must share the same `team_id`. Re-enrolling the same pair is a no-op (warning).

Every new enrollment dispatches `SendRecordedCourseAccessLinkJob`. Resend regenerates the token (`regenerateToken = true`) so the old URL stops working.

### Access email

- Job: `SendRecordedCourseAccessLinkJob`
- Mail: `RecordedCourseAccessLinkMail` → `emails.recorded-course-access-link`
- Subject: `رابط الدورة التدريبية — {course}`
- To: trainee email (skipped if missing)
- URL: `{APP_URL}/ar/recorded-courses/access/{token}`
- Side effects: `access_link_sent_at`, `delivery_status = pending`, prior delivery fields cleared
- Mailgun variables: `recorded_course_enrollment_id`, `type = recorded_course_access_link`

### Completion reminders

Learners who were invited and have not finished the course (`completed_at` is null) get a reminder email every 12 hours. That covers both people who never checked in and people who checked in but did not finish the lessons.

- Command: `recorded-courses:send-completion-reminders`
- Schedule: hourly in production; the command only sends when the invite and the previous reminder are both at least `reminder_interval_hours` old (default 12, `TRAINING_DISCLOSURE_REMINDER_INTERVAL_HOURS`)
- Job: `SendRecordedCourseReminderJob`
- Mail: `RecordedCourseReminderMail` → `emails.recorded-course-reminder`
- Subject: `تذكير بإكمال الدورة التدريبية — {course}`
- Same access token and URL. The reminder does not regenerate the link.
- Skipped when the invite `delivery_status` is `failed`, or the last reminder bounced (`reminder_delivery_status = failed`)
- Resending the access link clears the reminder failure so a corrected address can be reminded again
- Mailgun variables: `recorded_course_enrollment_id`, `type = recorded_course_reminder` (does not change invite delivery or certificate status)

## Learner paths

### Public passwordless (primary disclosure path)

Unauthenticated routes under `/ar/recorded-courses/access/{token}`:

| Method | Path | Action |
|---|---|---|
| GET | `/{token}` | Show course (Blade) |
| POST | `/{token}/check-in` | Set `checked_in_at` once |
| POST | `/{token}/check-out` | Set `checked_out_at` (requires check-in) |
| POST | `/{token}/unlock` | Unlock next pending lesson |
| POST | `/{token}/lessons/{lesson}/complete` | Mark lesson complete |
| GET | `/{token}/lessons/{lesson}/stream` | Stream video (S3 temp URL, 30 minutes) |

Until `checked_in_at` is set, unlock/stream/complete return **403**. Check-in is for disclosure/documentation (حضور / انصراف), not a login.

Invalid token → 404.

### Authenticated trainee

`/recorded-courses` (middleware: `auth:sanctum`, `verified`, `approved-application`). Same unlock / complete / stream rules via `RecordedCourseProgressService`, **without** a check-in requirement.

## Progress rules

Implemented in `RecordedCourseProgressService`. Completion is **explicit** (the learner clicks “mark complete”). There is no server-side watch-percentage or minimum viewing time. Admin `progress_percent` is `round(lessons_completed / lessons_total * 100)`.

### Weekdays

Current weekday (`Carbon::dayOfWeek`, 0 = Sunday) must be in `allowed_weekdays`. Config default for Drive-imported courses: Sunday–Thursday `[0, 1, 2, 3, 4]`. The create-course UI defaults to all seven days.

### Sequential unlock (“catch-up queue”)

`nextPendingLesson()` is the first lesson (by `sort_order`) that has no `unlocked_at`.

- Lesson 1: unlock allowed on an allowed weekday.
- Lesson N>1: previous lesson must have `completed_at`, and `now >= previous.completed_at + unlock_delay_hours`.
- If `unlock_delay_hours` is `0`, completing a lesson immediately unlocks the next one (when the weekday still allows it).

Unlock writes `recorded_course_lesson_progress.unlocked_at`. Stream requires that row. Complete requires `unlocked_at` and then sets `completed_at`.

### Enrollment completion

When **every** lesson has `completed_at`:

- Set `enrollments.completed_at` (if null).
- If `certificate_status` is `none` / empty, set it to `pending_approval`.
- No certificate email is sent automatically.

### Admin entitlement labels

| `entitlement` | Rule |
|---|---|
| `not_started` | no `checked_in_at` |
| `in_progress` | started, not in the certificate pipeline |
| `eligible` | `completed_at` set, or status is pending / approved / failed |
| `certificate_sent` | `certificate_status === sent` |

The enrollments page also groups rows into company summaries (enrolled, checked in, completed, eligible, certificates sent).

## Certificates

### Statuses (`RecordedCourseEnrollment`)

- `none`
- `pending_approval`
- `approved`
- `sent`
- `failed`

### Approval

`POST .../enrollments/{enrollment}/approve-certificate`

- Permission: **`approve-recorded-course-certificates`** (not only `manage-recorded-courses`)
- Allowed when status is `pending_approval` or `failed`
- Sets `approved`, `certificate_approved_at`, `certificate_approved_by`
- Dispatches `SendRecordedCourseCertificateJob`

### PDF

`RecordedCourseCertificateService` renders `pdf.recorded-course-certificate.show` with wkhtmltopdf (landscape A4): trainee name, identity number, course name (Arabic preferred). Stored on the **local** disk at `storage/app/recorded-course-certificates/{enrollment_id}.pdf`.

### Certificate email

- Mail: `RecordedCourseCertificateMail` → `emails.recorded-course-certificate` + PDF attachment
- Subject: `شهادة تدريبية — {course} — {name}`
- To: trainee email
- CC: `trainee.company.email` when present
- Success: `certificate_status = sent`, `certificate_sent_at = now()`
- Failure: `certificate_status = failed`, error logged, exception rethrown
- Mailgun variables: `recorded_course_enrollment_id`, `type = recorded_course_certificate`

### Mailgun webhook

`Webhooks\MailController` updates the enrollment only when the event `recipient` matches the **trainee** email (company CC events are ignored).

- Access-link delivered / failed updates `delivery_status` only (does not change `certificate_status`).
- Certificate delivered sets `certificate_status = sent`.
- Certificate failed / bounced / complained sets `certificate_status = failed`.

## Google Drive import

**Command**: `php artisan recorded-courses:import-from-drive` (run from `backend/`)

Each **direct child folder** of the parent Drive folder becomes one `RecordedCourse`, matched by `team_id` + `drive_folder_id`. Nested videos and videos inside `.zip` archives become lessons. Files are downloaded to a temp dir, then attached to Spatie media on the `s3` disk.

### Prerequisites

1. Share the parent Drive folder with `GOOGLE_DRIVE_CLIENT_EMAIL` (service account; credentials from `storage/app/google-drive-credentials.json` or `config('services.google.*')`, scope `DRIVE_READONLY`).
2. Set `TRAINING_DISCLOSURE_DEFAULT_TEAM_ID` or pass `--team=`.
3. S3 must be configured.

### Options

| Option | Meaning |
|---|---|
| `--team=` | Team UUID (default: `TRAINING_DISCLOSURE_DEFAULT_TEAM_ID`) |
| `--parent=` | Override parent folder ID |
| `--folder=` | Import only this child folder |
| `--dry-run` | List courses/files without writing |
| `--force` | Re-download even when S3 size matches |

Always run `--dry-run` first.

### Import behavior

1. List child folders; recurse for videos (`mp4`, `webm`, `mov`, `m4v`) and `.zip` archives.
2. Zip entries are extracted with path-traversal guards. Synthetic Drive id for zip videos: `{archiveId}::{entryPath}`.
3. Lessons ordered by natural sort of relative paths. Titles cleaned by `RecordedCourseLessonTitle` (strips Zoom/Drive indexes and redundant course/day/session prefixes).
4. New courses get `unlock_delay_hours` and `allowed_weekdays` from `config/training-disclosure.php`. Existing courses keep schedule settings; names are updated from the folder name.
5. Skip re-download when S3 media size matches (unless `--force`).
6. Delete Drive-sourced lessons whose `drive_file_id` disappeared from Drive. Manually created lessons (null `drive_file_id`) are left alone.
7. Set `drive_synced_at` after a successful folder import.

## Permissions

| Permission | Seeded to | Used for |
|---|---|---|
| `manage-recorded-courses` | `{team_id}_admins` | HQ, disclosure requests, courses, lessons, enrollments, resend link |
| `approve-recorded-course-certificates` | `{team_id}_admins` | Approve + queue certificate email |

Migrations: `2026_05_02_100001_seed_manage_recorded_courses_permission.php`, `2026_09_08_210100_seed_approve_recorded_course_certificates_permission.php`.

## Config and environment

`backend/config/training-disclosure.php`

| Key | Env | Default |
|---|---|---|
| `parent_drive_folder_id` | `TRAINING_DISCLOSURE_DRIVE_PARENT_FOLDER_ID` | hardcoded fallback folder id |
| `default_team_id` | `TRAINING_DISCLOSURE_DEFAULT_TEAM_ID` | none (required for import without `--team`) |
| `unlock_delay_hours` | `TRAINING_DISCLOSURE_UNLOCK_DELAY_HOURS` | `0` |
| `allowed_weekdays` | config only | `[0, 1, 2, 3, 4]` (Sun–Thu) |

Related: Google service account (`GOOGLE_*`), S3 for lesson video, Mailgun for delivery webhooks.

## Database schema

### `training_disclosure_requests`

| Column | Notes |
|---|---|
| `id` | UUID |
| `team_id` | FK → `teams`, cascade delete, TeamScope |
| `number` | unique, e.g. `E100` |
| `company_id` | nullable FK → `companies`, null on delete |
| `company_name` | string |
| `trainees_count` | unsigned int |
| `trainees_draft` | JSON array of `{ name, phone, email }` |

No FK to enrollments or trainees.

### `recorded_courses`

| Column | Notes |
|---|---|
| `id` | UUID |
| `team_id` | FK → `teams` |
| `name_ar`, `name_en` | required |
| `description` | nullable |
| `unlock_delay_hours` | unsigned int (migration default 24; app/config default 0) |
| `allowed_weekdays` | JSON ints 0–6 |
| `drive_folder_id` | nullable, unique with `team_id` |
| `drive_synced_at` | nullable |

### `recorded_course_lessons`

| Column | Notes |
|---|---|
| `id` | UUID |
| `recorded_course_id` | FK cascade |
| `sort_order` | unsigned int |
| `title_ar`, `title_en` | |
| `drive_file_id` | nullable stable key from Drive / zip |

Video: Spatie media, collection `video`, disk `s3`.

### `recorded_course_enrollments`

| Column | Notes |
|---|---|
| `team_id`, `trainee_id`, `recorded_course_id` | FKs |
| `enrolled_at` | default now |
| Unique | `(trainee_id, recorded_course_id)` |
| `access_token` | unique, 64 chars |
| `access_link_sent_at` | |
| `checked_in_at`, `checked_out_at` | public attendance |
| `completed_at` | all lessons done |
| `certificate_status` | default `none` |
| `certificate_approved_at`, `certificate_approved_by` | |
| `certificate_sent_at`, `certificate_path` | local PDF path |
| `mailgun_message_id`, `delivery_status`, `delivered_at`, `failed_at`, `delivery_failure_reason` | shared by access + cert emails |

### `recorded_course_lesson_progress`

| Column | Notes |
|---|---|
| `recorded_course_enrollment_id`, `recorded_course_lesson_id` | unique pair |
| `unlocked_at`, `completed_at` | nullable |

## Routes (summary)

**Public** (no auth) — `/ar/recorded-courses/access/...`

**Trainee** — `/recorded-courses...` behind `auth:sanctum`, `verified`, `approved-application`

**Admin** — `/back/training-disclosure...` and `/back/settings/recorded-courses...` (plus `POST /back/trainees/{trainee}/recorded-course-enrollments`)

**Webhook** — Mailgun `webhooks.mail`

## How requests relate to enrollments

There is **no database relationship**.

1. A request stores company identity and a draft trainee list.
2. Ops create or import a recorded course and enroll real `Trainee` rows (company picker, bulk enroll, or engineer profile).
3. Each enrollment gets its own `access_token` and email job.
4. HQ stats join enrollments → trainees → companies for operational metrics. Disclosure request count is a separate table count.

The intended sequence is the six HQ process steps: request → course → enroll + links → passwordless attendance → pending approval → approve + send.

## Tests

| File | Covers |
|---|---|
| `tests/Feature/TrainingDisclosureRequestsTest.php` | E-numbering, CRUD, HQ stats |
| `tests/Feature/RecordedCourseDisclosureFlowTest.php` | Public check-in, completion → pending, approve + job, access link, Mailgun, HQ, S3 collection |
| `tests/Feature/RecordedCourseLearnerFlowTest.php` | Weekdays, delay between lessons, zero-delay auto-unlock, catch-up, engineer enroll |
| `tests/Feature/RecordedCoursesTest.php` | Admin course / lesson CRUD |
| `tests/Feature/RecordedCourseVideoChunkUploadTest.php` | Chunked video upload |
| `tests/Unit/RecordedCourseLessonTitleTest.php` | Drive/Zoom title cleanup |

## Frontend map

| Surface | Path |
|---|---|
| HQ | `resources/js/Pages/Back/TrainingDisclosure/Index.vue` |
| Requests | `resources/js/Pages/Back/TrainingDisclosure/Requests/{Index,Create,Show}.vue` |
| Course catalog | `resources/js/Pages/Back/Settings/RecordedCourses/Index.vue` |
| Course wizard | `Show.vue`, `Details.vue`, `Schedule.vue`, `Lessons/*`, `Enrollments.vue` |
| Trainee (logged in) | `resources/js/Pages/Trainees/RecordedCourses/{Index,Show}.vue` |
| Public (token) | `resources/views/recorded-courses/public/show.blade.php` |
