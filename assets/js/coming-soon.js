/* ==========================================================================
   RUUT — LAUNCHING SOON SCRIPTS
   Early Access Circle • No Countdown Timer • VIP Privilege Unlock
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  // 1. VIP Private Launch Circle Subscription
  const subscribeForm = document.getElementById('comingSoonForm');
  const emailInput = document.getElementById('comingSoonEmail');
  const formWrap = document.getElementById('subscribeFormWrap');

  // Helper: Show Toast notification
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

  function revealPrivilegeCode(alreadySubscribed = false) {
    if (!formWrap) return;
    
    // Smooth transition
    formWrap.style.display = 'none';
    
    // Check if box already inserted
    if (document.getElementById('privilegeBox')) return;
    
    const revealHtml = `
      <div class="privilege-reveal-box" id="privilegeBox">
        <h3 class="privilege-title">${alreadySubscribed ? 'Welcome Back to the Private Circle' : 'Thank You for Joining Us'}</h3>
        <p class="privilege-desc">Your private early-access invitation has been reserved. Keep your launch privilege code safe:</p>
        <span class="privilege-code" id="privilegeCodeText">RUUT-LAUNCH-15</span>
        <p class="privilege-desc" style="margin-top:6px; font-weight:600; color:var(--ruut-mauve);">
          Enjoy 15% inaugural privileges on our premier batch release.
        </p>
      </div>
    `;
    formWrap.insertAdjacentHTML('afterend', revealHtml);
  }

  // Check if already subscribed in this browser session
  const isSubscribed = localStorage.getItem('ruut_newsletter_subscribed') === 'true';
  if (isSubscribed) {
    revealPrivilegeCode(true);
  }

  if (subscribeForm && emailInput) {
    subscribeForm.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const emailValue = emailInput.value.trim();
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      
      if (!emailValue || !emailRegex.test(emailValue)) {
        showToast('Please enter a valid email address.');
        return;
      }
      
      const submitBtn = subscribeForm.querySelector('.submit-btn');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Reserving...';
      }
      
      // Store subscription & reveal
      setTimeout(() => {
        try {
          localStorage.setItem('ruut_newsletter_subscribed', 'true');
          localStorage.setItem('ruut_subscriber_email', emailValue);
        } catch (err) {
          console.warn('Storage unavailable', err);
        }
        showToast('Private circle invitation confirmed! Unlocking privilege code...');
        revealPrivilegeCode(false);
      }, 900);
    });
  }
});
