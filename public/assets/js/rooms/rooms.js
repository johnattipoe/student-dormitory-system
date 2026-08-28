// rooms.js — room create/edit validation and allocation-screen helpers.
document.addEventListener('DOMContentLoaded', function () {
  // On the edit-room form, don't let capacity drop below the current occupancy.
  const capacityInput = document.querySelector('input[name="capacity"]');
  if (capacityInput && capacityInput.min) {
    capacityInput.addEventListener('input', function () {
      const min = parseInt(this.min, 10) || 0;
      if (parseInt(this.value, 10) < min) {
        this.setCustomValidity(`Capacity can't be lower than the ${min} student(s) already assigned.`);
      } else {
        this.setCustomValidity('');
      }
      this.reportValidity();
    });
  }

  // Room Allocation screen: disable the Allocate button while a full room is selected,
  // and refresh the room dropdown's disabled state whenever a room fills up client-side.
  const roomSelect = document.querySelector('select[name="roomId"]');
  const allocateForm = roomSelect ? roomSelect.closest('form') : null;
  if (roomSelect && allocateForm) {
    const submitBtn = allocateForm.querySelector('button[type="submit"], button:not([type])');
    roomSelect.addEventListener('change', function () {
      const opt = this.options[this.selectedIndex];
      if (submitBtn) submitBtn.disabled = !!(opt && opt.disabled);
    });
  }

  // Occupancy progress bars: animate width in from 0 on load for a nicer feel.
  document.querySelectorAll('.progress-bar[style*="width"]').forEach(bar => {
    const target = bar.style.width;
    bar.style.width = '0%';
    requestAnimationFrame(() => { bar.style.transition = 'width .6s ease'; bar.style.width = target; });
  });
});
