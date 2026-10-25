---
name: Academic Governance System
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#434751'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#737783'
  outline-variant: '#c3c6d3'
  surface-tint: '#2b5cad'
  primary: '#003f8a'
  on-primary: '#ffffff'
  primary-container: '#2457a7'
  on-primary-container: '#bcd0ff'
  inverse-primary: '#adc6ff'
  secondary: '#4a5f81'
  on-secondary: '#ffffff'
  secondary-container: '#c0d5fd'
  on-secondary-container: '#475c7e'
  tertiary: '#543e00'
  on-tertiary: '#ffffff'
  tertiary-container: '#6f5513'
  on-tertiary-container: '#efcc7e'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d8e2ff'
  primary-fixed-dim: '#adc6ff'
  on-primary-fixed: '#001a41'
  on-primary-fixed-variant: '#004493'
  secondary-fixed: '#d5e3ff'
  secondary-fixed-dim: '#b2c7ee'
  on-secondary-fixed: '#021c3a'
  on-secondary-fixed-variant: '#324768'
  tertiary-fixed: '#ffdf9c'
  tertiary-fixed-dim: '#e5c276'
  on-tertiary-fixed: '#251a00'
  on-tertiary-fixed-variant: '#5b4300'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  headline-xl:
    fontFamily: IBM Plex Sans
    fontSize: 2rem
    fontWeight: '700'
    lineHeight: 2.5rem
  headline-xl-mobile:
    fontFamily: IBM Plex Sans
    fontSize: 1.5rem
    fontWeight: '700'
    lineHeight: 2rem
  headline-lg:
    fontFamily: IBM Plex Sans
    fontSize: 1.5rem
    fontWeight: '600'
    lineHeight: 2rem
  headline-lg-mobile:
    fontFamily: IBM Plex Sans
    fontSize: 1.25rem
    fontWeight: '600'
    lineHeight: 1.75rem
  headline-md:
    fontFamily: IBM Plex Sans
    fontSize: 1.25rem
    fontWeight: '600'
    lineHeight: 1.75rem
  headline-sm:
    fontFamily: IBM Plex Sans
    fontSize: 1.125rem
    fontWeight: '600'
    lineHeight: 1.5rem
  body-lg:
    fontFamily: Noto Sans
    fontSize: 1rem
    fontWeight: '400'
    lineHeight: 1.625rem
  body-md:
    fontFamily: Noto Sans
    fontSize: 0.875rem
    fontWeight: '400'
    lineHeight: 1.375rem
  body-sm:
    fontFamily: Noto Sans
    fontSize: 0.75rem
    fontWeight: '400'
    lineHeight: 1.125rem
  label-lg:
    fontFamily: Noto Sans
    fontSize: 0.875rem
    fontWeight: '600'
    lineHeight: 1.25rem
  label-md:
    fontFamily: Noto Sans
    fontSize: 0.75rem
    fontWeight: '500'
    lineHeight: 1rem
  label-sm:
    fontFamily: Noto Sans
    fontSize: 0.6875rem
    fontWeight: '500'
    lineHeight: 0.875rem
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-desktop: 1.5rem
  margin: 1rem
  margin-desktop: 2rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
---

## Brand & Style

This design system establishes an institutional, authoritative, and disciplined visual environment engineered for high-density academic and administrative governance. Built specifically for higher education institutions, it prioritizes bureaucratic clarity, auditable data precision, and role-delineated workflows across complex academic hierarchies (College Administrator, Department Head, Department Rapporteur, and Student Affairs Officer).

The aesthetic style follows a disciplined Corporate-Institutional Modern approach with an Arabic RTL-first structural orientation. Rather than relying on purely decorative flourishes or whimsical micro-interactions, the UI evokes stability, intellectual rigor, institutional permanence, and operational reliability. Visual noise is actively minimized to prioritize data density, table scanability, regulatory validation statuses, and error-free transactional throughput.

## Colors

The color system is built upon academic heritage tones anchored by an official corporate blue palette:

- **Primary (`#2457A7`)**: Applied to key interactive triggers, primary action buttons, active navigation states, and primary focal highlights.
- **Secondary (`#142B4A`)**: Institutional Navy, serving as the sovereign brand anchor. Utilized for top navigation banners, role badges, high-level headers, and high-contrast structural containers.
- **Tertiary (`#C5A45B`)**: Muted Academic Gold. Reserved strictly for formal credentials, honorific insignias, certified institutional statuses, and high-level deanery accreditations.
- **Neutral Surface & Text (`#F5F7FA`, `#FFFFFF`, `#1F2937`, `#64748B`, `#E2E8F0`)**: 
  - Canvas background: `#F5F7FA`
  - Component card and table background: `#FFFFFF`
  - Primary text: `#1F2937` (high legibility for Arabic script)
  - Secondary metadata & captions: `#64748B`
  - Structural perimeter borders: `#E2E8F0`
- **Functional Semantics**:
  - Success (`#15803D`): Academic pass records, approved petitions, validated clearances.
  - Warning (`#B7791F`): Pending administrative verifications, prerequisite warnings, probation alerts.
  - Error (`#B91C1C`): Academic holds, disciplinary flags, validation errors, dropped registrations.

## Typography

The typographic hierarchy accommodates Arabic-first content layout with seamless Latin script compatibility for codes, student IDs, and technical grading metrics:

- **Headlines (IBM Plex Sans)**: Geometric, authoritative, and strictly proportioned. Provides structural clarity to module headings, role consoles, and deanery reports.
- **Body & Labels (Noto Sans)**: Chosen for open counters and legibility across Arabic glyphs and complex diacritics. Provides high scanability across dense data grids, record ledgers, and formal university decrees.
- **RTL Considerations**: Ensure line-height values maintain at least 1.5x in body Arabic blocks to avoid diacritic clipping. Maintain `text-align: right` as the default foundation, mirroring tabular numbers (`font-variant-numeric: tabular-nums`) left-to-right for grade point averages, credit hour counters, and academic registry IDs.

## Layout & Spacing

The layout is built upon an 8pt architectural rhythm using an adaptable fluid grid:

- **Desktop (1024px and above)**: 12-column grid system with 1.5rem (`24px`) gutters and 2rem (`32px`) external margins. Persistent right-aligned sidebar navigation (RTL layout order) housing role-based administrative modules.
- **Tablet (768px - 1023px)**: 8-column layout with 1rem (`16px`) gutters and 1.5rem (`24px`) margins. Sidebar folds into a collapsible right-hand drawer.
- **Mobile (Below 768px)**: 4-column layout with 1rem (`16px`) gutters and 1rem (`16px`) margins. Complex administrative tables convert to card stacks with key-value data rows.

Data grids adhere strictly to compact vertical spacing (`space-xs` and `space-sm`) to maximize visible information density per viewport, ensuring administrative staff can review transcripts and rosters without unnecessary scrolling.

## Elevation & Depth

Visual hierarchy is maintained through flat structural delineation, surface tone separation, and low-contrast outlines rather than heavy drop shadows:

- **Base Layer (0dp)**: Canvas surface (`#F5F7FA`), serving as the background for administrative dashboards.
- **Card & Grid Layer (1dp)**: Solid white (`#FFFFFF`) surface enclosed by a 1px border (`#E2E8F0`). Shadows are avoided in favor of precise, hairline boundaries.
- **Dropdowns & Popovers (2dp)**: Background `#FFFFFF` with a 1px border (`#E2E8F0`) and an ambient, low-opacity shadow (`box-shadow: 0 4px 12px rgba(20, 43, 74, 0.08)`).
- **Modals & Overlays (3dp)**: Academic petition verification dialogs and student grade overrides appear over an institutional navy overlay (`rgba(20, 43, 74, 0.45)`) with an elevated container shadow (`box-shadow: 0 8px 24px rgba(20, 43, 74, 0.16)`).

## Shapes

The design system employs a soft, restrained corner radius (`0.25rem` / `4px`) throughout interactive components, data containers, and form fields.

- Standard inputs, buttons, table cell borders, and status badges use `0.25rem` (`4px`).
- Larger card panels and administrative dialogs use `rounded-lg` (`0.5rem` / `8px`).
- Fully rounded pills (`rounded-full`) are reserved exclusively for compact numeric badges (such as unread petition counters and role indicators).

This subtle curvature balances modern software aesthetics with the seriousness and permanence required of an institutional academic portal.

## Components

### Buttons
- **Primary**: Background `#2457A7`, text `#FFFFFF`, border-radius `0.25rem`. Hover state: `#1D4585`. Focus ring: 2px offset with `#2457A7`.
- **Secondary (Outline)**: Background transparent, border 1px solid `#2457A7`, text `#2457A7`. Hover state: `#F0F4FA`.
- **Destructive**: Background `#B91C1C`, text `#FFFFFF`. Used for registration cancellations and academic dismissal confirmations.
- **Height**: Standard `2.25rem` (36px) for high-density administrative screens; large `2.75rem` (44px) for prominent form submissions.

### Role Indicator Badges & Status Chips
- **Role Indicators**: Solid `#142B4A` background with `#FFFFFF` text for College Administrator; `#2457A7` with `#FFFFFF` for Department Head; `#C5A45B` with `#FFFFFF` for Rapporteur; `#64748B` with `#FFFFFF` for Student Affairs Officer.
- **Academic Status Chips**:
  - Valid / Passed: `#F0FDF4` background, `#15803D` border and text.
  - Pending / Review: `#FEFCE8` background, `#B7791F` border and text.
  - Hold / Failed: `#FEF2F2` background, `#B91C1C` border and text.

### Form Inputs & Select Fields
- **Container**: Background `#FFFFFF`, 1px border `#E2E8F0`, padding `0.5rem 0.75rem`, border-radius `0.25rem`.
- **Label**: Above input, right-aligned, typography `label-md`, color `#1F2937`.
- **States**: Focus border `#2457A7` with `0 0 0 1px #2457A7`; Error border `#B91C1C` with error text below in `label-sm`.

### Data Grids & Tables
- **Header**: Background `#F8FAFC`, border-bottom 2px solid `#E2E8F0`, typography `label-lg`, text `#1F2937`.
- **Rows**: Alternating hover `#F1F5F9`, border-bottom 1px solid `#E2E8F0`, cell height `2.5rem` (40px) default. Numbers and codes displayed with tabular layout.
- **RTL Alignment**: Text columns align right; status tags, actions, and numeric metrics align left.

### Navigation
- **Right Sidebar (RTL)**: Width `16rem` (256px), background `#FFFFFF`, border-left 1px solid `#E2E8F0`. Active item styled with `#F0F4FA` background and a right border accent of 3px solid `#2457A7`.