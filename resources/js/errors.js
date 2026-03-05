export async function getFriendlyErrorMessage(error, lang = 'en') {
    const reference = error?.response?.data?.error_reference;
    if (!reference) {
        return { message: fallback(lang), reference: null };
    }

    try {
        localStorage.setItem('last_error_reference', reference);
    } catch (_) {
        // Ignore storage issues (private mode, etc.)
    }

    try {
        const res = await fetch(`/api/error-message/${reference}?lang=${lang}`);
        if (!res.ok) throw new Error('Failed');
        const data = await res.json();
        return { message: data.message, reference };
    } catch (_) {
        return { message: fallback(lang), reference };
    }
}

export function getLastErrorReference() {
    try {
        return localStorage.getItem('last_error_reference');
    } catch (_) {
        return null;
    }
}

function fallback(lang) {
    if (lang === 'nl') {
        return 'Er ging iets mis. Probeer het opnieuw.';
    }
    if (lang === 'de') {
        return 'Es ist etwas schiefgelaufen. Bitte erneut versuchen.';
    }
    return 'Something went wrong. Please try again.';
}
