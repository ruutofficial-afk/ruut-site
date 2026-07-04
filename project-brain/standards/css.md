# Standards — CSS

## Architecture
Single global stylesheet: `assets/css/styles.css` (~99KB)
Custom fonts: `assets/css/fonts.css` (~5KB)

## Design Tokens (CSS Custom Properties)
All colors, fonts, and spacing use CSS custom properties:
```css
--ruut-accent     /* Brand accent color (orange/amber) */
--ruut-text       /* Primary text color */
--ruut-gray       /* Secondary/muted text */
--ruut-white      /* White backgrounds */
--ruut-border     /* Subtle border color */
--font-serif      /* Serif heading font */
```

### Usage Rules
```css
/* ✅ Correct: Use tokens */
color: var(--ruut-accent);
font-family: var(--font-serif);
border: 1px solid var(--ruut-border);

/* ❌ Wrong: Hardcoded values */
color: #CF6400;
font-family: 'Playfair Display', serif;
border: 1px solid rgba(0,0,0,0.05);
```

## Cart-Specific Classes

### Cart Drawer
```
.cart-drawer                    /* Main drawer container */
.cart-drawer.open               /* Open state */
.cart-drawer-overlay            /* Backdrop */
.cart-drawer-overlay.open       /* Visible backdrop */
.cart-drawer-header             /* Header with title + close */
.cart-drawer-content            /* Scrollable content area */
.cart-scrollable-area           /* Inner scroll container */
.cart-drawer-actions            /* Bottom action buttons */
```

### Cart Items
```
.cart-item                      /* Single item row */
.cart-item-img-wrap             /* Thumbnail container */
.cart-item-details              /* Text content area */
.cart-item-name                 /* Product title */
.cart-item-price                /* Current price */
.cart-item-original-price       /* Crossed-out price */
.cart-item-volume               /* Size/volume label */
.cart-item-qty-row              /* Quantity controls row */
.qty-selector                   /* +/- button group */
.qty-btn                        /* Increment/decrement button */
.qty-val                        /* Current quantity display */
```

### Progress Bar
```
.cart-promo-banner              /* Progress bar wrapper */
.cart-promo-text                /* "Add X more..." text */
.cart-progress-bar-wrap         /* Track container */
.cart-progress-bar              /* Fill element */
.cart-progress-dot              /* Milestone dot */
.ruut-dot-reached               /* Active dot state */
```

### Upsell
```
.cart-upsell-section            /* Section wrapper */
.cart-upsell-title              /* "Complete The Ritual" */
.cart-upsell-card               /* Product suggestion card */
.cart-upsell-img-wrap           /* Thumbnail */
.cart-upsell-name               /* Product name */
.cart-upsell-price              /* Price */
.btn-upsell-add                 /* "ADD +" button */
```

## Responsive Breakpoints
```css
@media (max-width: 768px)  { /* Tablet */ }
@media (max-width: 600px)  { /* Mobile */ }
@media (max-width: 480px)  { /* Small mobile */ }
```

## Animation Standards
- Transitions: `0.8s cubic-bezier(0.25, 0.8, 0.25, 1)` for entrance animations
- Cart drawer slide: CSS transition on `transform: translateX()`
- Badge pop: CSS keyframe animation triggered by `.pop` class
- Hover effects: `0.3s ease` for interactive elements

## Inline Styles Policy
Cart page (`cart.html`) uses extensive inline styles for layout.
New cart page additions should follow the same inline pattern for consistency.
Mini-cart drawer uses CSS classes from `styles.css`.
