# UI/UX Design Brief

Use this note when adding or updating pages in the Student Routine Organizer.

## Theme

- Calm student-productivity dashboard.
- Light background, white surfaces, green primary actions, warm accent colors for module variety.
- Keep cards, panels, tables, alerts, and forms consistent through the shared classes in `assets/css/style.css`.

## UX Rules

- Buttons should lift on hover and compress slightly when clicked.
- Clickable cards should feel responsive with hover lift and active press states.
- Inputs must show clear focus rings for keyboard and accessibility support.
- Tables should use readable spacing and row hover states.
- Empty module pages should use `.empty-state` with a clear title and next-action hint.
- Motion should stay subtle and respect `prefers-reduced-motion`.

## Current Interactivity Level

Full UI interactivity with static app logic where CRUD modules are not implemented yet. Do not fake unfinished CRUD behavior; build the real module logic when each phase starts.
