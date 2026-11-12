<template id="scan-loading-template">
    <section class="scan-loading" aria-labelledby="scan-loading-heading">
        <div class="scan-loading-brand"><span><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span><div><strong>Scholar</strong><small>School Portal</small></div></div>
        <div class="scan-loading-art" aria-hidden="true">
            <span class="scan-cloud scan-cloud--one"></span><span class="scan-cloud scan-cloud--two"></span>
            <div class="scan-map-paper"><span></span><span></span><span></span></div>
            <div class="scan-map-rings"></div>
            <i class="bi bi-geo-alt-fill scan-map-pin"></i>
            <i class="bi bi-building-fill scan-map-school"></i>
            <i class="bi bi-tree-fill scan-map-tree"></i>
        </div>
        <h2 id="scan-loading-heading" data-loading-heading>Finding your current location…</h2>
        <p data-loading-description>Please allow location access and wait a moment.</p>
        <div class="scan-loading-progress" role="progressbar" aria-label="Location scan in progress" aria-valuetext="Waiting for device location"><span></span></div>
        <ol class="scan-loading-steps">
            <li class="is-active"><i class="bi bi-send" aria-hidden="true"></i><span>Accessing<br>location</span></li>
            <li><i class="bi bi-crosshair" aria-hidden="true"></i><span>Getting<br>coordinates</span></li>
            <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>Preparing<br>check-in</span></li>
        </ol>
    </section>
</template>
