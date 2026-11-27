---
name: "nexcx-visual-qa"
description: "NEXVARY CX visual QA — Playwright screenshots, Arabic RTL, English LTR, mobile responsiveness, missing UI elements, browser console errors. Use before declaring any frontend feature complete or when visual regressions are suspected. Triggers on: 'visual qa', 'ui check', 'screenshot', 'rtl check', 'mobile check', 'nexcx-visual-qa', 'check the ui', 'verify the ui'."
---

# NEXVARY CX Visual QA

You are a frontend QA engineer for NEXVARY CX Cloud. Apply this checklist to verify UI correctness before declaring a frontend task complete.

## UI Context

- **Framework:** React (Inertia.js v2) + TypeScript
- **Styling:** Tailwind CSS with custom NEXVARY dark theme
- **Design:** Deep black background, neon green accents, metallic silver, fluid rounded cards
- **Languages:** Arabic (RTL) and English (LTR) — both must work correctly
- **Breakpoints:** Mobile (≤ 640px), Tablet (641–1024px), Desktop (≥ 1025px)
- **Icons:** Real multicolor icons — not generic or placeholder
- **Testing:** Playwright for browser automation and screenshot capture

## Visual QA Checklist

### 1 — Playwright Setup

- [ ] Playwright is installed: `npx playwright --version` returns a version
- [ ] `playwright.config.ts` exists in the project root
- [ ] At least one browser (Chromium) is installed: `npx playwright install chromium`
- [ ] The dev server is accessible at the configured `baseURL`
- [ ] Screenshots output directory exists or is created before the test run

### 2 — English (LTR) Layout

Take a screenshot of each changed/new page or component in English:

```typescript
test('English dashboard layout', async ({ page }) => {
    await page.goto('/');
    await page.screenshot({ path: 'screenshots/en-dashboard.png', fullPage: true });
});
```

For each screenshot, verify:

- [ ] No text overflow or truncation in headings, labels, table cells
- [ ] All buttons are visible and not obscured by other elements
- [ ] Navigation links are present and correctly labeled
- [ ] No broken images (placeholder icons, 404 image URLs)
- [ ] The design palette matches: deep black background, neon green accents, metallic silver
- [ ] Cards have fluid rounded corners (not square)
- [ ] No unexpected whitespace or collapsed sections

### 3 — Arabic (RTL) Layout

Switch locale to Arabic and repeat screenshots:

```typescript
test('Arabic dashboard layout (RTL)', async ({ page }) => {
    await page.goto('/?lang=ar');
    // or navigate through language switcher
    await page.screenshot({ path: 'screenshots/ar-dashboard.png', fullPage: true });
});
```

For each Arabic screenshot, verify:

- [ ] `dir="rtl"` is set on `<html>` or the root container
- [ ] Text flows right-to-left correctly — no mixed LTR/RTL text runs in paragraphs
- [ ] Icons that imply directionality (back/forward arrows, chevrons) are mirrored
- [ ] Form inputs and labels are right-aligned
- [ ] Table headers and columns maintain logical RTL order
- [ ] Navigation/sidebar items are right-aligned with correct indentation
- [ ] No LTR text (English strings, numbers with decimals) breaks RTL paragraph flow
- [ ] Arabic numerals (٠١٢) vs Western numerals (012) display as configured

### 4 — Mobile Responsiveness (≤ 640px)

```typescript
test('Mobile layout', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 }); // iPhone 14
    await page.goto('/dashboard');
    await page.screenshot({ path: 'screenshots/mobile-dashboard.png', fullPage: true });
});
```

- [ ] Navigation collapses to hamburger menu
- [ ] All content is accessible (no horizontal scroll)
- [ ] Tap targets are ≥ 44×44px
- [ ] Text is readable without zooming (≥ 14px font)
- [ ] Forms are usable on mobile (inputs don't overlap, dropdowns work)
- [ ] Tables scroll horizontally within a container (not the entire page)

### 5 — Functional UI Checks

- [ ] All buttons trigger their intended actions (verify via Playwright `click` + network assertion)
- [ ] Forms submit correctly and display success/error states
- [ ] Loading spinners/skeletons appear during data fetch and disappear when data loads
- [ ] Empty states (no extensions, no SMS conversations) display a helpful message, not a blank screen
- [ ] Pagination / infinite scroll works correctly
- [ ] Search/filter inputs update results correctly

### 6 — Browser Console Errors

```typescript
page.on('console', (msg) => {
    if (msg.type() === 'error') errors.push(msg.text());
});
```

- [ ] Zero console errors during normal navigation
- [ ] No `404 Not Found` for JS/CSS assets
- [ ] No `Uncaught TypeError` or `ReferenceError`
- [ ] No React key warnings in production build
- [ ] No `Failed to load resource` for fonts or icons

### 7 — Cross-Page Navigation

Navigate through these pages and verify no blank screens or crashes:

- [ ] Dashboard / Home
- [ ] Extensions list + detail
- [ ] DIDs list
- [ ] SMS Conversations
- [ ] Ring Groups
- [ ] IVR Menus
- [ ] Billing / Plan
- [ ] Settings / Profile
- [ ] Admin panel (if applicable)

### 8 — Screenshot Artifact Requirements

For PR evidence, capture and attach:

| Screenshot | Required |
|---|---|
| `en-dashboard.png` | ✓ |
| `ar-dashboard.png` | ✓ |
| `mobile-en-dashboard.png` | ✓ |
| `mobile-ar-dashboard.png` | ✓ |
| Any page with significant changes | ✓ |

Screenshots must be **real Playwright captures** — not design mockups, not AI-generated images.

## Running Playwright Tests

```bash
# Install dependencies
npm ci
npx playwright install chromium

# Run visual QA suite
npx playwright test tests/visual/

# Or run a specific test
npx playwright test tests/visual/dashboard.spec.ts --reporter=html
```

## Completion Criteria

Visual QA passes when:

1. All Playwright tests pass with no console errors
2. RTL Arabic layout is verified in screenshots
3. Mobile layout is verified at 390px width
4. All functional UI checks pass
5. Screenshots are attached to the PR or GitHub issue as artifacts

## Known Limitations

- Playwright requires a running dev server or CI environment — cannot run against static files
- RTL verification requires Arabic translations to be loaded (check `lang/ar/*.php` exists)
- Full-page screenshots of infinite-scroll pages capture only the initial viewport by default — scroll and capture in sections if needed
