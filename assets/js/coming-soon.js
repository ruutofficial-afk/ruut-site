/* ==========================================================================
   RUUT — MINIMAL CINEMATIC COMING SOON SCRIPTS
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  const waitlistForm = document.getElementById('comingSoonForm');
  const emailInput = document.getElementById('comingSoonEmail');
  const formWrap = document.getElementById('waitlistWrap');

  const showToast = (msg) => {
    let toast = document.getElementById('comingSoonToast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'comingSoonToast';
      toast.className = 'toast-msg';
      document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.classList.add('show');
    setTimeout(() => {
      toast.classList.remove('show');
    }, 4000);
  };

  const renderSuccess = (alreadySubscribed = false) => {
    if (!formWrap) return;
    if (document.getElementById('waitlistSuccessCard')) return;

    formWrap.innerHTML = `
      <div class="waitlist-success-card" id="waitlistSuccessCard">
        <span class="success-badge">Waitlist Confirmed</span>
        <h3 class="success-title">${alreadySubscribed ? 'Welcome back to the circle' : 'You are on the private waitlist'}</h3>
        <p class="success-desc">An early-access invitation will be dispatched to your inbox the moment our doors open.</p>
        <div style="margin-top: 10px;">
          <span class="privilege-pill">RUUT-LAUNCH-15</span>
        </div>
      </div>
    `;
  };

  if (localStorage.getItem('ruut_waitlist_joined') === 'true') {
    renderSuccess(true);
  }

  if (waitlistForm && emailInput) {
    waitlistForm.addEventListener('submit', (e) => {
      e.preventDefault();

      const email = emailInput.value.trim();
      const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

      if (!email || !regex.test(email)) {
        showToast('Please enter a valid email address.');
        return;
      }

      const btn = waitlistForm.querySelector('.waitlist-btn');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'JOINING...';
      }

      setTimeout(() => {
        try {
          localStorage.setItem('ruut_waitlist_joined', 'true');
          localStorage.setItem('ruut_waitlist_email', email);
        } catch (err) {
          console.warn('Storage unavailable', err);
        }
        showToast('You have joined the private waitlist!');
        renderSuccess(false);
      }, 700);
    });
  }
});
