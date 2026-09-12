---
name: Apex Lounge OS
colors:
  surface: '#131313'
  surface-dim: '#131313'
  surface-bright: '#393939'
  surface-container-lowest: '#0e0e0e'
  surface-container-low: '#1c1b1b'
  surface-container: '#201f1f'
  surface-container-high: '#2a2a2a'
  surface-container-highest: '#353534'
  on-surface: '#e5e2e1'
  on-surface-variant: '#e9bcb8'
  inverse-surface: '#e5e2e1'
  inverse-on-surface: '#313030'
  outline: '#af8783'
  outline-variant: '#5e3f3c'
  surface-tint: '#ffb3ad'
  primary: '#ffb3ad'
  on-primary: '#680009'
  primary-container: '#ff544f'
  on-primary-container: '#5c0006'
  inverse-primary: '#c00019'
  secondary: '#d0bcff'
  on-secondary: '#3c0091'
  secondary-container: '#571bc1'
  on-secondary-container: '#c4abff'
  tertiary: '#4edea3'
  on-tertiary: '#003824'
  tertiary-container: '#00a572'
  on-tertiary-container: '#00311f'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#ffdad6'
  primary-fixed-dim: '#ffb3ad'
  on-primary-fixed: '#410003'
  on-primary-fixed-variant: '#930011'
  secondary-fixed: '#e9ddff'
  secondary-fixed-dim: '#d0bcff'
  on-secondary-fixed: '#23005c'
  on-secondary-fixed-variant: '#5516be'
  tertiary-fixed: '#6ffbbe'
  tertiary-fixed-dim: '#4edea3'
  on-tertiary-fixed: '#002113'
  on-tertiary-fixed-variant: '#005236'
  background: '#131313'
  on-background: '#e5e2e1'
  surface-variant: '#353534'
  bg-canvas: '#080808'
  surface-base: '#0D0D0D'
  surface-card: '#151515'
  surface-elevated: '#1A1A1A'
  surface-overlay: '#222222'
  border-subtle: rgba(255, 255, 255, 0.08)
  border-strong: rgba(255, 255, 255, 0.16)
  brand-crimson: '#FF1E2D'
  brand-crimson-dark: '#8B0000'
  status-active: '#10B981'
  status-busy: '#EF4444'
  status-maintenance: '#F59E0B'
  status-reserved: '#8B5CF6'
typography:
  display-xl:
    fontFamily: Space Grotesk
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
  display-xl-mobile:
    fontFamily: Space Grotesk
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  headline-lg:
    fontFamily: Space Grotesk
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  headline-lg-mobile:
    fontFamily: Space Grotesk
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
  headline-md:
    fontFamily: Space Grotesk
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-sm:
    fontFamily: Space Grotesk
    fontSize: 18px
    fontWeight: '600'
    lineHeight: 26px
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  label-lg:
    fontFamily: Space Grotesk
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 18px
  label-md:
    fontFamily: Space Grotesk
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
  label-sm:
    fontFamily: Space Grotesk
    fontSize: 10px
    fontWeight: '700'
    lineHeight: 14px
  telemetry-num:
    fontFamily: Space Grotesk
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 32px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-lg: 1.5rem
  margin: 1rem
  margin-md: 1.5rem
  margin-lg: 2rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
  space-2xl: 3rem
---

## Brand & Style

This design system is engineered for premier esports arenas, modern cyber lounges, and high-performance competitive gaming centers. It balances the adrenaline and intensity of competitive esports with the surgical precision and utilitarian density required by enterprise facility managers, floor operators, and POS cashiers.

The design philosophy combines a **High-Contrast Dark Technical** framework with refined **Glassmorphism & Tactical Cyberpunk** undertones. Rather than leaning into hyper-saturated, chaotic neon tropes, it projects an elite, arena-grade command center: ultra-deep near-black foundations, architectural charcoal surfaces, sharp razor borders, and deliberate pulses of tournament crimson red. Telemetry indicators, station countdowns, and hardware load metrics are prioritized with high legibility, authoritative typography, and ambient status luminescence.

The interface evokes focus, physical presence, tactical control, and technical luxury.

## Colors

The color architecture is built for prolonged screen operations under low-light ambient arena conditions. It relies on a five-tier dark luminance scale anchored by a master `#080808` canvas, maintaining contrast ratios strictly above WCAG AA (and predominantly AAA) for critical information.

### Functional Roles
- **Primary Accent (`#FF1E2D` / Brand Crimson):** Denotes critical interactions, live active tournaments, active session triggers, primary CTA buttons, and high-priority alarms. Supported by deep blood red (`#8B0000`) for sunken states or active header bands.
- **Secondary Accent (`#8B5CF6` / Reserved Purple):** Used specifically for upcoming bookings, VIP club designations, and pre-allocated rigs.
- **Status Green (`#10B981`):** Available rigs, healthy system vitals, completed POS transactions, and network latency under 20ms.
- **Status Amber (`#F59E0B`):** Rig cleaning cycles, maintenance hold, hardware thermal alerts, and expiring session thresholds (<10m).
- **Status Red (`#EF4444` / Busy):** Occupied stations, locked terminals, or hardware offline faults.

### Surface Hierarchy
All surfaces are derived from tinted true-neutral charcoals to prevent color muddiness:
- Canvas: `#080808`
- Base Panels / Sidebar: `#0D0D0D`
- Standard Cards & Rigs: `#151515`
- Modals, Popovers, & Elevated Tiles: `#1A1A1A`
- Hover Highlights & Inputs: `#222222`
- Ghost Dividers: `rgba(255, 255, 255, 0.08)`

## Typography

The typographic hierarchy enforces high-velocity situational awareness. **Space Grotesk** serves as the headline, metric, and telemetry font—its geometric incisions and mechanical letterforms yield an aggressive, technical esports aesthetic. **Inter** handles all dense operational data tables, cashier forms, system logs, and transactional receipts, guaranteeing peak legibility at compact sizes.

### Numeric & Telemetry Rules
All numeric data (station timers, hourly rates, latency, framerates, player balances) rendered in Space Grotesk must enable tabular lining figures (`font-variant-numeric: tabular-nums`) to prevent jitter across live telemetry updates. 

Labels applied to hardware tags, station statuses, and tournament stages must be rendered in uppercase using `label-sm` or `label-md` with `letter-spacing: 0.06em`.

## Layout & Spacing

The layout model employs a responsive 12-column grid designed for dense information presentation across manager workstations, POS terminals, overhead arena monitors, and mobile floor tablets.

### Grid & Responsiveness
- **Desktop / Arena Master Display (1440px+):** 12-column fluid grid, `gutter-lg` (24px), `margin-lg` (32px). Station floorplans utilize auto-fill CSS grids with minimum cell widths of 280px.
- **Tablet / Floor Staff Terminals (768px - 1439px):** 8-column layout, `gutter` (16px), `margin-md` (24px). Sidebar collapses to an icon rail (64px width).
- **Mobile Handheld (POS / Quick Inspect, <768px):** 4-column layout, `gutter` (16px), `margin` (16px). Cards stack vertically with horizontal scrollers for telemetry filter chips.

Vertical rhythm adheres to a strict 4px base increment. Spacing tokens (`space-xs` through `space-2xl`) must govern internal module padding and multi-element grouping. Structural margins are reserved solely for canvas boundary spacing.

## Elevation & Depth

Visual hierarchy uses physical-plane surfacing reinforced by dark glassmorphism and surgical border illumination instead of muddy, heavy drop shadows.

### Elevation Architecture
1. **Canvas (Ground 0, `#080808`):** Deep background, completely non-interactive.
2. **Floor Grid / Base Surface (`#0D0D0D`):** Docked sidebars, global navigation, and zone containers. Outlined by `border-subtle` (`rgba(255, 255, 255, 0.08)`).
3. **Interactive Station Tiles (`#151515`):** Station unit cards, quick-order POS tiles, inventory modules. Surface features a top-edge highlight using a linear gradient: `linear-gradient(180deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0) 100%)`.
4. **Active Session Glow (State Layer):** Active rigs project an inner radial glow tinted by the station status:
   - Occupied / In-Session: `box-shadow: 0 0 24px -4px rgba(255, 30, 45, 0.25), inset 0 1px 0 0 rgba(255, 30, 45, 0.4)`
   - Available: `box-shadow: 0 0 20px -4px rgba(16, 185, 129, 0.2), inset 0 1px 0 0 rgba(16, 185, 129, 0.3)`
5. **Floating Panels & Modals (`#1A1A1A` + Glass Blur):** Station management flyouts, checkout trays, and hardware monitors use `backdrop-filter: blur(16px)` combined with an ambient deep drop shadow: `0 16px 40px -8px rgba(0, 0, 0, 0.85)`.

## Shapes

The design system incorporates a disciplined, technical shape profile (Level 1: Soft). Elements leverage tight corner radii (`0.25rem` / 4px base; `0.5rem` / 8px for containers and modal panels) to echo the milled, precision-engineered metal frames of high-end PC towers and tournament stage equipment.

- Base inputs, buttons, spec pills, and table headers: `rounded` (4px).
- Station cards, modal dialogs, and flyout drawer panels: `rounded-lg` (8px).
- Dynamic telemetry badges and circular state beacons: fully circular (`rounded-full`).
- Avoid rounded pill-shapes on standard primary action buttons; keep them technical, structural, and architectonic.

## Components

### Buttons
- **Primary (Tournament Crimson):** Background `#FF1E2D`, text `#FFFFFF`, font `Space Grotesk` (600), radius `4px`, padding `0.5rem 1.25rem`. Hover triggers `#E0101F` with a soft glow `0 0 16px rgba(255, 30, 45, 0.4)`. Active state drops scale to `0.98`.
- **Secondary / Ghost:** Background `rgba(255, 255, 255, 0.04)`, border `1px solid rgba(255, 255, 255, 0.08)`, text `#FFFFFF`. On hover: `background: rgba(255, 255, 255, 0.08)`, `border-color: rgba(255, 255, 255, 0.2)`.
- **Destructive:** Background `transparent`, border `1px solid #EF4444`, text `#EF4444`. Hover fills with `rgba(239, 68, 68, 0.12)`.

### Gaming Station Cards
- Dual-zone structure: Top status header displaying Station ID (e.g., `RIG-04`) and Hardware Tier (e.g., `RTX 4090 // 360Hz`).
- Body section contains real-time countdown timer in `telemetry-num`, active gamer handle, and game icon badge.
- Bottom action rail features quick actions: "End Session", "Add Time", and "Remote Lock".
- Border transitions from `border-subtle` to a status-colored hairline border when occupied or in error.

### Hardware Spec & Status Chips
- Height: 24px. Padding: `0 8px`. Font: `Space Grotesk` uppercase (`label-sm`).
- Enclosed with `rgba(255, 255, 255, 0.06)` background and hairline border.
- Preceded by a 6px status dot emitting a faint pulse animation when in live status.

### Form Inputs & Terminal POS Fields
- Height: 40px standard. Background: `#0D0D0D`. Border: `1px solid rgba(255, 255, 255, 0.12)`.
- Text: `#FFFFFF`, `Inter` 14px. Placeholder: `rgba(255, 255, 255, 0.3)`.
- Focus state: Border transitions to `#FF1E2D` with `box-shadow: 0 0 0 1px #FF1E2D`.

### Checkboxes & Segmented Selectors
- Checkboxes: 18x18px squares, 2px corner radius, background `#151515`, border `1px solid rgba(255, 255, 255, 0.2)`. Checked state fills `#FF1E2D` with an optical white check icon.
- Station Floor Filters: Segmented dark tabs with smooth slide indicator, highlighting active floor zones (e.g., "MAIN STAGE", "BOOTCAMP A", "STREAM PODS", "CONSOLE DEN").

### Telemetry HUD & Stat Meters
- Compact stat cards featuring a subtle mini-sparkline or load arc. 
- Critical stats include Real-Time Occupancy %, Active Arena Revenue/hr, Network Latency, and Power Draw.