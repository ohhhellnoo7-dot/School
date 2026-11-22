(() => {
    const panel = document.getElementById('current-program-tuition');
    const dialog = document.querySelector('[data-tuition-banner-dialog]');
    if (!panel || !dialog) return;

    const form = dialog.querySelector('form');
    const input = form.elements.banner;
    const preview = dialog.querySelector('[data-tuition-banner-preview]');
    const reset = dialog.querySelector('[value="reset"]');
    let currentImage = '';
    let previewUrl;
    let trigger;

    const showPreview = source => {
        if (source) preview.src = source;
        else preview.removeAttribute('src');
        preview.hidden = !source;
    };
    const releasePreview = () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = undefined;
    };
    const openEditor = banner => {
        if (!banner || dialog.open) return;
        trigger = banner.querySelector('[data-tuition-customize-button]');
        form.reset();
        form.setAttribute('action', banner.dataset.tuitionCustomize);
        currentImage = banner.dataset.currentBanner;
        reset.hidden = !currentImage;
        dialog.querySelector('[data-tuition-banner-program]').textContent = banner.dataset.programLabel;
        showPreview(currentImage);
        dialog.showModal();
    };

    panel.addEventListener('dblclick', event => openEditor(event.target.closest('[data-tuition-customize]')));
    panel.addEventListener('click', event => {
        const button = event.target.closest('[data-tuition-customize-button]');
        if (button) openEditor(button.closest('[data-tuition-customize]'));
    });
    input.addEventListener('change', () => {
        releasePreview();
        if (input.files[0]) previewUrl = URL.createObjectURL(input.files[0]);
        showPreview(previewUrl || currentImage);
    });
    dialog.querySelector('[data-tuition-banner-cancel]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        releasePreview();
        showPreview('');
        trigger?.focus({preventScroll: true});
    });
})();
