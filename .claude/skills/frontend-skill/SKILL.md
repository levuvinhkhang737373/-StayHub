# RooCode Frontend Design Skill

This skill guides creation of distinctive, production-grade frontend interfaces that avoid generic "AI slop" aesthetics. Implement real working code with exceptional attention to aesthetic details and creative choices.

## When To Use

Use this skill whenever building, redesigning, or refining frontend UI, including:

- Components
- Pages
- Application shells
- Admin dashboards
- Auth screens
- Forms
- Modals
- Cards
- Tables
- Navigation
- Landing pages
- Tenant-facing or admin-facing interfaces

For this project, prefer **NextJS + Tailwind CSS + React Hooks** and keep existing business logic/API service behavior intact unless the user explicitly asks for logic changes.

## Core Mission

The user provides frontend requirements: a component, page, application, or interface to build. They may include context about purpose, audience, technical constraints, screenshots, references, or existing files.

The job is to produce UI that is:

- Production-grade and functional
- Visually striking and memorable
- Cohesive with a clear aesthetic point-of-view
- Meticulously refined in every detail
- Accessible and responsive
- Implemented as real working code, not mock-only decoration

Avoid generic, templated, predictable output. Every design should feel intentionally created for the specific product context.

## Design Thinking Before Coding

Before coding, understand the context and commit to a **BOLD aesthetic direction**.

### 1. Purpose

Answer internally:

- What problem does this interface solve?
- Who uses it?
- What action should the user take?
- What information matters most?
- What emotion should the interface create?

### 2. Tone

Pick a strong aesthetic direction. Do not default to safe SaaS sameness.

Possible tones:

- Brutally minimal
- Maximalist chaos
- Retro-futuristic
- Organic/natural
- Luxury/refined
- Playful/toy-like
- Editorial/magazine
- Brutalist/raw
- Art deco/geometric
- Soft/pastel
- Industrial/utilitarian
- Cyberpunk operational
- Monastic calm
- Museum-grade editorial
- Boutique hotel luxury
- Tactical command center
- Warm neighborhood service
- High-contrast architectural

Use these as inspiration, but design one direction that is true to the product and screen.

### 3. Constraints

Respect technical constraints:

- Framework: NextJS / React
- Styling: Tailwind CSS first
- Responsiveness: mobile-first and desktop-refined
- Accessibility: semantic markup, contrast, keyboard states
- Performance: avoid unnecessary heavy effects
- Maintainability: readable component structure
- Existing project conventions: do not break services, API types, routing, auth guards, or layout structure

### 4. Differentiation

Decide the memorable idea:

- What makes this screen unforgettable?
- What is the one visual or interaction detail someone remembers?
- What unique rhythm, composition, material, or motif defines it?

Examples:

- A diagonal command-grid layout for an admin dashboard
- Editorial oversized section numbers
- Boutique hotel-inspired room cards
- Monochrome layout with one sharp amber action color
- Layered glass panels over subtle blueprint textures
- Soft natural gradients for tenant-facing comfort

## Critical Principle

Choose a clear conceptual direction and execute it with precision.

Bold maximalism and refined minimalism both work. The key is intentionality, not intensity.

Never design randomly. Every typography choice, spacing value, color, shadow, border, and animation should support the selected direction.

## Frontend Aesthetic Guidelines

### Typography

Typography must feel designed, not default.

Rules:

- Choose fonts that are beautiful, unique, and interesting.
- Avoid generic fonts like Arial, Inter, Roboto, and default system stacks when possible.
- Prefer characterful display fonts paired with readable body fonts.
- Use typography scale intentionally: oversized headlines, compact labels, refined body copy.
- Use letter-spacing, uppercase labels, tabular numbers, and font weight contrast where appropriate.
- For production, ensure imported fonts are realistic for the project setup.

Recommended directions:

- Editorial: high-contrast serif display + restrained sans body
- Industrial: condensed grotesk + mono labels
- Luxury: elegant serif + quiet humanist sans
- Retro-futuristic: techno display + neutral readable body
- Minimal: one excellent font family used with precision

Avoid:

- Generic SaaS typography
- Everything same size and weight
- Random font mixing
- Overusing common AI choices repeatedly

### Color & Theme

Commit to a cohesive aesthetic.

Rules:

- Use CSS variables or centralized Tailwind tokens when practical.
- Dominant colors with sharp accents outperform timid, evenly distributed palettes.
- Pick a clear background, surface, border, text, muted text, and accent system.
- Use contrast intentionally.
- Let one or two accent colors carry interaction and hierarchy.
- Use gradients only when they support the concept.

Avoid:

- Cliched purple gradients on white backgrounds
- Random rainbow palettes
- Low-contrast gray-on-gray UI
- Generic blue SaaS buttons everywhere

### Motion

Use motion to create delight, hierarchy, and feedback.

Rules:

- Prefer CSS-only animations when possible.
- Use Motion library for React only when already available or clearly justified.
- Focus on high-impact moments.
- One well-orchestrated page load with staggered reveals can be better than many small animations.
- Use hover states that feel tactile and surprising.
- Use animation delays, transforms, opacity, blur, shadow, and clip effects deliberately.
- Keep motion accessible and not excessive.

Good motion examples:

- Cards rising with shadow bloom on hover
- Modal entering with slight scale and blur reduction
- Hero elements revealing in staggered order
- Active navigation indicator gliding instead of instantly switching
- Background gradient subtly shifting over time

Avoid:

- Random bouncing
- Over-animation that distracts from tasks
- Motion that slows down admin workflows

### Spatial Composition

Use space as a design tool.

Rules:

- Consider asymmetry, overlap, diagonal flow, grid-breaking elements.
- Use generous negative space for refined designs.
- Use controlled density for operational dashboards.
- Build clear hierarchy: primary action, primary data, supporting context.
- Align intentionally but do not make every screen a predictable centered card.
- Use section rhythm: compressed control areas, spacious content areas, strong headers.

Ideas:

- Offset hero blocks
- Sticky side panels
- Large editorial headings behind content
- Dense dashboard metrics with one oversized focal card
- Cards overlapping subtle background geometry

Avoid:

- Cookie-cutter card grids
- Equal spacing everywhere without hierarchy
- Default centered layouts for every screen

### Backgrounds & Visual Details

Create atmosphere and depth instead of defaulting to plain solid colors.

Use contextual effects that match the aesthetic:

- Gradient meshes
- Noise textures
- Geometric patterns
- Blueprint grids
- Layered transparencies
- Dramatic shadows
- Decorative borders
- Grain overlays
- Subtle radial highlights
- Architectural linework
- Custom focus rings
- Material-inspired surfaces

Rules:

- Background detail should support content, not reduce readability.
- Use pseudo-elements or layered divs where appropriate.
- Keep performance reasonable.
- Prefer CSS/Tailwind utilities before adding dependencies.

Avoid:

- Decoration with no relationship to the product
- Random blobs copied from generic AI designs
- Excessive glassmorphism everywhere

## Anti-AI-Slop Rules

Never use generic AI-generated aesthetics such as:

- Overused font families: Inter, Roboto, Arial, default system fonts
- Cliched purple-blue gradients on white backgrounds
- Predictable SaaS hero/card/component patterns
- Same rounded-xl cards with same shadow everywhere
- Generic icon + title + description grids
- Layouts that could belong to any product
- Vague decorative blobs with no concept
- Cookie-cutter design that lacks context-specific character

Interpret creatively and make unexpected choices that feel genuinely designed for the context.

No design should be the same. Vary between:

- Light and dark themes
- Dense and spacious layouts
- Sharp and soft shapes
- Editorial and utilitarian compositions
- Calm and dramatic moods
- Different fonts and typographic systems

Never converge on common choices across generations. Avoid repeatedly using the same popular fonts, color formulas, or layout tricks.

## Implementation Complexity Rule

Match implementation complexity to the aesthetic vision.

### If Maximalist

Use elaborate code when justified:

- Multiple background layers
- More detailed animation choreography
- Custom decorative elements
- Complex responsive composition
- Strong hover/active states
- Rich visual hierarchy

### If Minimalist or Refined

Use restraint:

- Fewer elements
- Precise spacing
- Excellent typography
- Subtle borders and shadows
- Quiet motion
- Strong alignment and rhythm

Elegance comes from executing the vision well, not from adding more decoration.

## Production Quality Checklist

Before finishing frontend work, verify:

- UI is responsive across mobile, tablet, desktop.
- Buttons and interactive elements have hover/focus/disabled states.
- Text contrast is readable.
- Loading/empty/error states are considered when relevant.
- Existing API/service logic is preserved unless requested.
- Components are readable and maintainable.
- Tailwind classes are organized enough to understand.
- No unnecessary dependencies are added.
- No broken imports.
- No hardcoded data replacing real data unless explicitly intended.
- No generic placeholder UI remains.

## Project-Specific Rules

For this StayHub project:

- Use Tailwind CSS for styling.
- Prefer React Hooks for state and effects.
- Keep API calls in existing service layers where possible.
- Do not break the API envelope shape: `{ status, message, errorCode, result }`.
- Admin UI may lean more operational, architectural, command-center, or refined SaaS.
- Tenant UI may lean warmer, calmer, more human, and hospitality-oriented.
- Preserve route structure and auth guard behavior.
- Keep frontend changes compatible with the Laravel backend.

## Suggested Design Directions For StayHub

### Admin Facilities / Buildings

Potential aesthetic:

- Architectural command center
- Blueprint grid background
- Ivory/charcoal base
- Brass or amber accent
- Dense but premium building cards
- Strong status chips
- Map-like region hierarchy
- Subtle linework and measurement motifs

### Admin Login

Potential aesthetic:

- Boutique security portal
- Dark cinematic hotel lobby mood
- Warm light gradients
- Strong form focus states
- Premium card surface with subtle grain

### Tenant Dashboard

Potential aesthetic:

- Calm residential companion
- Soft warm neutrals
- Friendly cards
- Clear rent/payment/maintenance hierarchy
- Gentle micro-interactions

### Forms

Potential aesthetic:

- Structured editorial form layout
- Clear section numbers
- Floating helper text
- Strong input focus rings
- Sticky submit summary on desktop

## Working Method

When asked to design or code frontend:

1. Read the relevant files.
2. Identify existing component structure and data flow.
3. Choose a strong aesthetic direction.
4. Preserve business logic unless instructed otherwise.
5. Implement real working UI with Tailwind CSS.
6. Add responsive behavior.
7. Add tasteful motion and interaction states.
8. Keep code maintainable.
9. Report the changed files and the design direction used.

## Prompt Shortcut

Use this prompt when invoking this skill:

```text
Apply the full frontend design skill from DESIGN_SKILL.md. Create a distinctive production-grade UI, avoid generic AI slop, use Tailwind CSS, preserve existing logic, and execute one clear bold aesthetic direction.
```

## Final Reminder

The model is capable of extraordinary creative work. Do not hold back. Think outside the box, commit fully to a distinctive vision, and implement the result as polished production frontend code.
