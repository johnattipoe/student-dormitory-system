interface ProfileDraft {
  name: string;
  phone: string;
}

function getProfileDraftKey(): string {
  return `sds.account-profile-draft:${window.location.pathname}`;
}

function getProfileForm(): HTMLFormElement | null {
  const form = document.querySelector<HTMLFormElement>('[data-ts-profile-form]');
  return form;
}

function readProfileDraft(form: HTMLFormElement): ProfileDraft {
  const name = form.elements.namedItem('name');
  const phone = form.elements.namedItem('phone');

  return {
    name: name instanceof HTMLInputElement ? name.value : '',
    phone: phone instanceof HTMLInputElement ? phone.value : '',
  };
}

function writeProfileDraft(form: HTMLFormElement, draft: ProfileDraft): void {
  const name = form.elements.namedItem('name');
  const phone = form.elements.namedItem('phone');

  if (name instanceof HTMLInputElement) name.value = draft.name;
  if (phone instanceof HTMLInputElement) phone.value = draft.phone;
}

function initialiseProfileDraft(form: HTMLFormElement): void {
  const profileDraftKey = getProfileDraftKey();
  const storedDraft = window.localStorage.getItem(profileDraftKey);
  if (storedDraft) {
    try {
      const draft = JSON.parse(storedDraft) as Partial<ProfileDraft>;
      if (typeof draft.name === 'string' && typeof draft.phone === 'string') {
        writeProfileDraft(form, { name: draft.name, phone: draft.phone });
      }
    } catch {
      window.localStorage.removeItem(profileDraftKey);
    }
  }

  form.addEventListener('input', () => {
    window.localStorage.setItem(profileDraftKey, JSON.stringify(readProfileDraft(form)));
  });

  form.addEventListener('submit', () => {
    window.localStorage.removeItem(profileDraftKey);
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const form = getProfileForm();
  if (form) initialiseProfileDraft(form);
});
