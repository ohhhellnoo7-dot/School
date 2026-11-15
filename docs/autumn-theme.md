# Autumn theme

Select **Autumn** in the Seasonal group. The shared registry saves the selection and makes it available on every page, including login. Its chestnut sidebar, orange controls, cream panels and pastel statistic cards use the shared theme tokens.

The school banner uses `public/images/autumn-school-hero.png`. A stationary picnic sits at the bottom-left above the sidebar school card, with space reserved below the navigation. The couple breathe, blink, lean toward each other, lift sandwiches to their mouths and chew in alternating twelve-second eating sequences; hair, floating hearts and tea steam animate independently. The old full-width garden strip is removed. On mobile, the picnic appears inside the navigation drawer rather than covering page content. Collapsing the desktop sidebar hides the picnic.

One large autumn maple tree uses `public/images/autumn-maple-tree.png` and fills the sidebar behind the navigation, above the picnic couple. The canopy extends beyond the sidebar edges for a large tree silhouette. The desktop tree uses the naturally curved `autumn-curved-maple.png` asset, with overhanging branches extending into the upper page. A clipped decorative layer keeps the extension away from page controls. Navigation sits above the tree with chestnut scrims for readable labels. The main content uses its normal width, with no extra tree column. On mobile, the tree and couple appear inside the navigation drawer. Wind bends the tree and releases eight leaves from its branches; leaves lift, tumble sideways and settle. Session storage preserves the gust cycle across navigation. All animation uses CSS transforms and opacity. Hidden tabs pause scenes; reduced motion stops animation; print and theme changes remove decorations.

A layered grassy mound supports the tree roots and picnic blanket, with grass tufts swaying gently. Reduced motion stops the grass and eating gestures as well.

The bottom of the main page has a full-width layered autumn ground with grass and fallen leaves. Content reserves bottom padding so the decorative ground does not hide the final controls. The upper tree extension uses a soft two-part mask instead of an angular clip, keeping the sidebar trunk solid and fading the overhang above the heading. The land pauses in hidden tabs, respects reduced motion and is removed when switching themes.

The earlier flat vector ground has been replaced with `autumn-forest-ground.png`, a transparent realistic grass, soil, pebbles and fallen-leaf border. It is shared by the picnic and full-width foreground land. The tree sits above the sidebar background, with navigation labels above it and lighter scrims so bark and foliage stay visible.

The tree overhang is now unmasked so its natural transparent branches sit visibly in front of the banner edge. Gust motion uses a single gentle 0.65-degree lean, then returns to rest for the remainder of the cycle; the old repeated side-to-side shake is removed. Browser checks sample the lean, still interval and leaf-release interval explicitly.

A noninteractive wind field shares the twelve-second gust clock: twenty small airborne pollen/leaf fragments accelerate across the viewport, while six ground leaves lift, tumble and settle. The tree leans and releases canopy leaves during the same gust, then rests. Mobile halves the particle count; reduced motion and print hide the wind field. Theme changes remove it, and hidden tabs pause its animations.

The canopy starts at the top of the sidebar on desktop and mobile, with a 32px fade at its upper edge. Navigation has no extra top margin, keeping Main Menu and Overview directly below the brand.

The sidebar clips the tree at its own boundary, including on desktop. The canopy stays at the top, but cannot extend into the cream topbar or page content; this removes the recurring rectangular header cutoff.
