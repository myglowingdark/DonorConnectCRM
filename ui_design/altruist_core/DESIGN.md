---
name: Altruist Core
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
  on-surface-variant: '#444651'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#757682'
  outline-variant: '#c5c5d3'
  surface-tint: '#4059aa'
  primary: '#00236f'
  on-primary: '#ffffff'
  primary-container: '#1e3a8a'
  on-primary-container: '#90a8ff'
  inverse-primary: '#b6c4ff'
  secondary: '#006b5f'
  on-secondary: '#ffffff'
  secondary-container: '#6df5e1'
  on-secondary-container: '#006f64'
  tertiary: '#0d0097'
  on-tertiary: '#ffffff'
  tertiary-container: '#2724b8'
  on-tertiary-container: '#a1a4ff'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dce1ff'
  primary-fixed-dim: '#b6c4ff'
  on-primary-fixed: '#00164e'
  on-primary-fixed-variant: '#264191'
  secondary-fixed: '#71f8e4'
  secondary-fixed-dim: '#4fdbc8'
  on-secondary-fixed: '#00201c'
  on-secondary-fixed-variant: '#005048'
  tertiary-fixed: '#e1e0ff'
  tertiary-fixed-dim: '#c0c1ff'
  on-tertiary-fixed: '#07006c'
  on-tertiary-fixed-variant: '#2f2ebe'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  display:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '700'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '600'
    lineHeight: '1.3'
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.3'
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.4'
  headline-sm:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: '1.4'
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.6'
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.5'
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: '1'
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: '1'
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  unit: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  2xl: 48px
  container-margin: 24px
  gutter: 16px
---

## Brand & Style
The design system is engineered for a high-trust CRM environment where emotional connection meets data-driven efficiency. The brand personality is **Professional, Dependable, and Compassionate**. It balances the rigor required for financial management with the approachability needed for non-profit volunteers.

The chosen design style is **Corporate Modern**, characterized by generous whitespace, high-legibility typography, and a "Tonal Layering" approach. It avoids unnecessary decorative elements, focusing instead on structural clarity and a calm, systematic interface that reduces cognitive load for users managing complex donor relationships.

## Colors
The palette is anchored by **Deep Blue**, evoking stability and institutional trust. **Teal** serves as the secondary action color, used for positive reinforcement and secondary conversion points. 

- **Primary (#1E3A8A):** Used for main navigation, primary buttons, and active states.
- **Secondary (#14B8A6):** Used for growth indicators, "New Donor" actions, and success highlights.
- **Neutral Slate:** A tiered gray scale is used for text hierarchy. `#64748B` is the standard for secondary text, while `#F1F5F9` is used for subtle backgrounds and UI borders.
- **Semantic Accents:** 
  - **Success:** Green-700 on Green-100 (Donation received).
  - **Warning:** Amber-700 on Amber-100 (Lapsed donor).
  - **Error:** Rose-700 on Rose-100 (Failed transaction).

## Typography
This design system utilizes **Inter** exclusively to ensure a systematic, utilitarian aesthetic that remains highly readable at small sizes in data tables.

The hierarchy focuses on "Scanning." Headlines use slightly tighter letter-spacing and heavier weights to stand out against white surfaces. Body text maintains a generous 1.6 line-height to improve readability during long sessions of data entry or donor profile review. Label-sm is reserved for table headers and metadata, utilizing uppercase styling to differentiate from interactive text.

## Layout & Spacing
The system employs a **Fluid Grid** model with a 12-column structure for desktop. 

- **Sidebar:** Fixed at 280px to accommodate long organization names and a clear navigation hierarchy.
- **Margins:** Desktop views utilize a 24px outer margin.
- **Gaps:** A standard 16px (md) gutter is used between cards and table columns.
- **Mobile Reflow:** On screens <768px, the 12-column grid collapses to a 1-column stack. The sidebar transitions to a bottom-sheet or a full-screen overlay menu.
- **Currency:** Financial figures should always be formatted using the Indian Numbering System (e.g., ₹1,00,000) with `Inter` tabular figures enabled to ensure alignment in tables.

## Elevation & Depth
Depth is established through **Tonal Layers** and **Ambient Shadows**. This design system avoids heavy borders in favor of soft shadows that suggest elevation without adding visual clutter.

- **Level 0 (Background):** White (#FFFFFF) or Soft Slate (#F8FAFC).
- **Level 1 (Cards):** White background with a 1px border in `#F1F5F9` and a subtle shadow: `0 4px 6px -1px rgba(0, 0, 0, 0.05)`.
- **Level 2 (Dropdowns/Modals):** Floating elements use a more pronounced shadow: `0 10px 15px -3px rgba(0, 0, 0, 0.1)`.
- **Interactions:** On hover, cards may lift slightly (increase shadow spread) to indicate interactivity.

## Shapes
The shape language is friendly yet structured. This design system uses a **Rounded** philosophy.

- **Small Elements (Inputs/Checkboxes):** 0.5rem (8px) radius.
- **Standard Components (Buttons/Chips):** 0.5rem (8px) radius.
- **Containers (Cards/Modals):** 1rem (16px) radius, corresponding to `rounded-lg`.
- **Organization Avatar/Switcher:** Circular (full rounded) to differentiate between "People" (circular) and "Objects" (rounded square).

## Components
- **Buttons:** Primary buttons are Solid Deep Blue. Secondary buttons use a Ghost style (Teal text, no background) or an Outline style.
- **Cards:** Defined by a 16px radius. Content inside cards should have at least 24px of internal padding.
- **Data Tables:** Use a fixed header. Rows have a 1px bottom border in `#F1F5F9`. Hover states on rows use `#F8FAFC`. Tabular numbers are mandatory for currency columns.
- **Status Badges:** Use a "Pill" shape (full rounded). Backgrounds are 10% opacity of the semantic color, with text at 100% opacity (WCAG AA compliant).
- **Organization Switcher:** Located at the top of the sidebar. It should display the organization's logo or a 2-letter initial, the name, and a "chevron-down" icon.
- **Input Fields:** Use a 1px border in `#E2E8F0`. Focus state utilizes a 2px Teal ring.
- **Activity Feed:** A vertical timeline component with soft lines connecting circular icons, used to track donor interaction history.