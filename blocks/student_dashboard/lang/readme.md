Student Dashboard Block (Scaffold)


1. Copy folder to `blocks/student_dashboard`.
2. Visit **Site administration → Notifications** to install.
3. Enable **Settings → Use placeholder data** to see mock content.
4. Add the block to **Dashboard** or a **Course**.


## Wiring Real Data (guide)
- Personal: use `core_user::get_user()`, `user_picture::render()` and your OU fields.
- KPI: join gradebook tables (`grade_grades`, `grade_items`), attendance plugin tables, or your custom local tables.
- Assets/Facilities: from `local_logistics` tables (hostel, weapon) keyed by user & OU.
- Tasks: query `course_modules` (assign/quiz), `mod_assign`, `mod_quiz` due dates; custom tables for leave/survey.
- Library: from `local_library` or your schema for loans & due dates.


Keep **XSS/SQLi hygiene**: parameterize queries with `?` and `PARAM_*` types; escape all output in Mustache (default escaped, triple braces used only for `picturehtml`).