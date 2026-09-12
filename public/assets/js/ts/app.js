"use strict";
function getSettingsDraftKey(form) {
    return `sds.settings-draft:${window.location.pathname}:${form.id || 'default'}`;
}
function getDraftControls(form) {
    return Array.from(form.querySelectorAll('input, select, textarea'))
        .filter((control) => control.name !== '' && control.type !== 'hidden' && !control.readOnly);
}
function readSettingsDraft(form) {
    return getDraftControls(form).map((control) => ({
        name: control.name,
        type: control.type,
        value: control.type === 'checkbox' ? control.checked : control.value,
    }));
}
function writeSettingsDraft(form, draft) {
    const controls = getDraftControls(form);
    draft.forEach((item) => {
        const control = controls.find((candidate) => candidate.name === item.name);
        if (!control)
            return;
        if (control.type === 'checkbox' && typeof item.value === 'boolean') {
            control.checked = item.value;
        }
        else if (typeof item.value === 'string') {
            control.value = item.value;
        }
    });
}
function initialiseSettingsDraft(form) {
    const settingsDraftKey = getSettingsDraftKey(form);
    const storedDraft = window.localStorage.getItem(settingsDraftKey);
    if (storedDraft) {
        try {
            const draft = JSON.parse(storedDraft);
            if (Array.isArray(draft))
                writeSettingsDraft(form, draft);
        }
        catch {
            window.localStorage.removeItem(settingsDraftKey);
        }
    }
    form.addEventListener('input', () => {
        window.localStorage.setItem(settingsDraftKey, JSON.stringify(readSettingsDraft(form)));
    });
    form.addEventListener('change', () => {
        window.localStorage.setItem(settingsDraftKey, JSON.stringify(readSettingsDraft(form)));
    });
    form.addEventListener('submit', () => {
        window.localStorage.removeItem(settingsDraftKey);
    });
}
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ts-settings-form]').forEach(initialiseSettingsDraft);
});
