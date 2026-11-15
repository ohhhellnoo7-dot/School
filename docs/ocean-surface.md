# Ocean surface, fishing and lighting cycle

The Ocean theme adds a shallow sky/surface band above page content. Native SVG/CSS draws the boat, fisherman, rod, line, hook, waves, sun, moon and clouds. No additional image generation is used.

The boat travels between the content area's upper corners. Its position persists in tab-session storage. When a swimming clownfish is available, the boat pauses and the fisherman casts. The sea-life manager atomically reserves that fish, so the shark cannot eat it during fishing. It approaches the hook, bites, and is reeled into the bucket. A fresh clownfish appears from another edge after 1.4 seconds. Fishing attempts resume after a short traveling interval. Resizing or switching themes cancels the reservation and resets the line safely.

The cast lowers the hook along the visible line. Reel-in uses one trajectory and the same document-timeline start time for the fish, hook and changing line endpoint. The fish's SVG mouth is the rotation/scaling anchor, so it remains attached while being lifted. The line's SVG viewBox uses the fixed layer's actual dimensions, excluding the scrollbar, to preserve screen-pixel alignment.

The lighting cycle lasts 32 seconds: sunrise for the first 4 seconds, daytime until 16 seconds, sunset finishing at 20 seconds, moonlit night until 28 seconds, then dawn returns over the remaining 4 seconds. Sky gradients crossfade; sun and moon movement, stars, underwater darkness and hero-image tint share the same tab-session clock. Text and controls remain bright. Only a few decorative layers animate; there are no per-frame JavaScript layout updates or animated inherited theme variables.

Reduced-motion settings show a static daytime scene and disable fishing. Theme cleanup cancels timers, fishing reservations and line animations. The decorative surface does not intercept input and is hidden in print.

Marine spawn routes start below the waterline and the free-swimming layer is clipped to the underwater region. The shark uses the same surface boundary. Only a reserved fishing catch is temporarily moved into the surface layer so it can rise into the boat. A seven-bird SVG flock crosses the sky only during the 16–20 second sunset window and remains hidden during daytime, night, and reduced-motion mode.

Two moving wave layers roll across the surface. The boat's heave and tilt synchronize to the primary wave's phase at its current horizontal position. Its rocking rate accounts for the boat traveling with or against the swell and resynchronizes on turns, after fishing and when returning to the tab. During fishing, horizontal travel pauses while rocking continues at the swell's rate. An animation-frame callback follows the moving rod tip and hook to keep the line connected; it stops when fishing ends or the tab is hidden. Wave and boat motion use compositor transforms.

The opaque fixed sky sits above scrolling page content and below the sticky topbar. Content disappears behind the sky when it crosses the waterline; waves and fishing boat remain in their foreground surface layer. This uses CSS stacking without scroll listeners.
