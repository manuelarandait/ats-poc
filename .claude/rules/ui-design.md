---
paths:
  - "templates/**"
  - "assets/**"
  - "src/**/Infrastructure/Http/**"
---

# UI design rules

Goal: a **simple, calm, professional** recruiter tool — think Linear / Ashby, not a marketing site. Usability beats decoration.

## Visual system (Tailwind)

- **Palette**: neutral `slate` for text, borders and surfaces; a single accent `indigo-600` (hover `indigo-700`) for primary actions, links and focus. No other decorative colours.
- **Semantic colours** only for meaning:
  - Status badges: `received` slate · `in_review` sky · `interviewing` violet · `hired` emerald · `rejected` rose (final list decided in iteration 2).
  - AI score: `≥ 70` emerald · `40–69` amber · `< 40` rose. Always show the number too — never colour alone.
- **Avatars** are the one decorative exception: initials on a soft tone stable per name (indigo, cyan, fuchsia, orange, teal — hues not used by status or score), always next to the written name.
- **Typography**: system font stack (`font-sans`), no web fonts. Sizes: page title `text-2xl font-semibold`, section title `text-lg font-medium`, body `text-sm`, meta/timestamps `text-xs text-slate-500`. Numbers in tables use `tabular-nums`.
- **Surfaces**: page background `bg-slate-50`; content in white cards `rounded-xl border border-slate-200 shadow-sm`. Consistent spacing scale (`gap-4`, `p-6`); no arbitrary values (`w-[337px]`).
- **Layout**: centred container `max-w-6xl mx-auto px-4 sm:px-6`, top nav with product name + "Jobs" / "Applications" + theme toggle. Forms max `max-w-2xl`.
- **Language**: all UI copy in English.

## Dark mode

- Every surface, text, border and badge has a `dark:` counterpart — never ship a component that only works in light mode.
- Class strategy (`.dark` on `<html>`): defaults to the OS preference, a toggle in the nav overrides it and is remembered (`localStorage`). An inline script in `<head>` applies it before paint (no flash).
- Dark palette: page `dark:bg-slate-950`, cards `dark:bg-slate-900 dark:border-slate-800`, body text `dark:text-slate-100`, secondary `dark:text-slate-400`, accent `dark:text-indigo-400` / buttons `dark:bg-indigo-500`.
- Semantic colours in dark mode use tinted backgrounds (`dark:bg-emerald-500/15 dark:text-emerald-300`), keeping AA contrast.

## Components (Twig)

- Reusable pieces as anonymous **Twig Components** in `templates/components/` (`<twig:StatusBadge :status="…" />`, `Score`, `ScoreRing`, `ScreeningBadge`, `EmptyState`, `Time`, `Avatar`, `Icon`, `Logo`, `Pipeline`, `Pagination`, `JobDescription`, `SkillMatches`); buttons, cards and inputs as CSS component classes (`.btn`, `.card`, `.input`). Never duplicate the class soup of a component in two templates.
- **Icons**: only from `<twig:Icon name="…" />` (24×24 outline, `currentColor`), decorative (`aria-hidden`) unless they are the only content of a control, which then gets an `aria-label`.
- **Flash messages** are toasts (top right, auto-dismiss after 5 s, paused on hover, closable).
- **Buttons**: one primary per screen; secondary are white with border.
- **Forms**: visible `<label>` for every field, helper text under the field, inline error messages in `text-rose-600` next to the field, required fields marked. The CV textarea is large (`rows="14"`), monospace, with a character counter.
- **Tables**: sticky header, row hover, whole row clickable to the detail, right-aligned numeric columns, relative time with absolute time in `title`.

## States (always design all of them)

- **Empty**: friendly message + call to action ("No applications yet — share the job offer").
- **Loading / filtering**: keep the table, dim it (`opacity-60`) while fetching; no layout jump.
- **AI pending**: badge "Analysing…" with a subtle pulse; **AI failed**: neutral message, never a stack trace.
- **Success after submit**: confirmation screen with next steps and a link to the application.

## Interaction

- Real-time filtering with a Stimulus controller: debounce text search (~300 ms), filters and search reflected in the URL query string (shareable, back button works). Works without JS as a plain GET form (progressive enhancement).
- Stimulus controllers are small, single-purpose, in `assets/controllers/`. No other JS frameworks.

## Accessibility (non-negotiable)

- WCAG AA contrast; visible focus ring (`focus-visible:ring-2 ring-indigo-500`) on every interactive element.
- Semantic HTML (`<main>`, `<nav>`, `<table>`, `<dl>` for detail fields); status/score changes announced with `aria-live="polite"`.
- Fully usable by keyboard; responsive down to 375 px (tables collapse to cards on mobile).
