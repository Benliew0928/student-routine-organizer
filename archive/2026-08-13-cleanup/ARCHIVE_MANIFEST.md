# Archive Manifest - 13 August 2026

This folder contains recoverable project material moved out of the active application tree during the final report cleanup. Nothing in this archive is loaded by the current PHP application.

## Archived groups

- `tests/` - the complete automated PHP/JavaScript test suite used to verify the report's recorded 71 checks.
- `tmp/` - report builders, generated diagram PNGs, QA scripts, intermediate DOCX/PDF files and other temporary generation artifacts.
- `database/` - historical one-time migration scripts, the CLI migration runner and the obsolete `schema_draft.sql`. The maintained schema remains at `database/student_routine_organizer.sql` in the active project.
- `docs/` - legacy implementation plans/specifications and the superseded `Guide.md`. Current project/report guidance remains in the active `docs/` folder.
- `assets/img/` - two unreferenced exercise concept/mockup images. Images referenced by the application remain active.

## Restoration

To restore an item, move it back to the same relative path beneath the project root. Review conflicts first; the active application may have changed after this archive was created.

## Active deliverables retained outside this archive

- `docs/Student Routine Organizer Diagrams.drawio` - editable nine-page diagram source.
- `database/student_routine_organizer.sql` - final eleven-table schema.
- Revised report DOCX/PDF - stored in the user's OneDrive report folder, not in this repository.
