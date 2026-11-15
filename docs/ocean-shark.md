# Ocean shark and replenishing sea life

The shark replaces the former mermaid companion. Its native SVG body, tail, fins, eyes and mouth animate with CSS transforms, while the Web Animations API moves it around the viewport. Clicking pauses travel and shows a friendly smile, fin wave and personalized greeting. Its normalized position persists between pages in tab-session storage.

Every 250 ms during active Ocean viewing, the shark checks the four visible marine animals. It chases the nearest one with a faster tail beat, updates the pursuit at most every 900 ms, and bites when its mouth is near the animal's center. A cartoon mouth animation and bubbles accompany the animal shrinking into its mouth. There is no blood or injury imagery.

The sea-life module owns animal lifecycles. An accepted `ocean:consume` event changes a swimming animal to the eating state, animates its removal for 480 ms, then spawns a fresh animal of that species after 1.4–2.8 seconds. Spawn routes cycle through four corners and four edges and cross the viewport. The population is bounded to four animals, including animals awaiting replacement. Existing SVG fin, tail and tentacle movements continue along these routes.

Hidden tabs pause marine movement and pursuit. Reduced-motion preferences disable swimming, chasing and eating. Theme changes cancel pending replacements and animations. Decorations remain clipped to the viewport and transparent to pointer input, except for the shark's clickable silhouette.
