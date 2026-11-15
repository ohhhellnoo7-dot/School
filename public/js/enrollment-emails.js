(() => {
    const emailFromName = (name) => {
        const localPart = name.normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-zA-Z0-9]/g, '')
            .slice(0, 64).toLowerCase();
        return localPart ? `${localPart}@gmail.com` : '';
    };

    const linkEmail = (nameId, emailId) => {
        const name = document.getElementById(nameId);
        const email = document.getElementById(emailId);
        if (!name || !email) return;

        let lastGenerated = emailFromName(name.value);
        let automatic = !email.value || email.value === lastGenerated;

        const update = () => {
            // Keep manually entered emails when the name changes.
            if (automatic && (!email.value || email.value === lastGenerated)) {
                lastGenerated = emailFromName(name.value);
                email.value = lastGenerated;
            }
        };

        email.addEventListener('input', () => {
            email.value = email.value.toLowerCase();
            automatic = !email.value || email.value === emailFromName(name.value);
        });
        name.addEventListener('input', update);
        name.addEventListener('change', update);
        update();
    };

    linkEmail('name', 'email');
    linkEmail('guardian_name', 'guardian_email');
})();
