(() => {
    const status = document.querySelector('.scholar-payment-receipt [data-settled="yes"].scholar-receipt-status');
    if (!status || !('IntersectionObserver' in window)) return;

    const observer = new IntersectionObserver(([entry]) => {
        status.classList.toggle('is-revealed', entry.intersectionRatio >= .35);
    }, { threshold: .35 });
    observer.observe(status.querySelector('.scholar-receipt-status-icon'));
})();
