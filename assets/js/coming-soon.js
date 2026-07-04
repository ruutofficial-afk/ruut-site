/* Coming Soon Page Scripts — RUUT */
document.addEventListener('DOMContentLoaded', () => {
  // 1. Dynamic Countdown Timer Setup
  // Set launch date to 15 days in the future from current visit
  let launchDate = localStorage.getItem('ruut_launch_date');
  if (!launchDate) {
    const defaultDate = new Date();
    defaultDate.setDate(defaultDate.getDate() + 15);
    launchDate = defaultDate.getTime().toString();
    localStorage.setItem('ruut_launch_date', launchDate);
  }
  
  const targetTime = parseInt(launchDate, 10);
  
  const daysVal = document.getElementById('days');
  const hoursVal = document.getElementById('hours');
  const minutesVal = document.getElementById('minutes');
  const secondsVal = document.getElementById('seconds');
  
  const updateTimer = () => {
    const now = new Date().getTime();
    const distance = targetTime - now;
    
    if (distance < 0) {
      // If timer is expired, reset it to another 15 days for demo purposes
      const resetDate = new Date();
      resetDate.setDate(resetDate.getDate() + 15);
      localStorage.setItem('ruut_launch_date', resetDate.getTime().toString());
      return;
    }
    
    // Time calculations
    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
    
    // Render values with padded zero
    if (daysVal) daysVal.textContent = days.toString().padStart(2, '0');
    if (hoursVal) hoursVal.textContent = hours.toString().padStart(2, '0');
    if (minutesVal) minutesVal.textContent = minutes.toString().padStart(2, '0');
    if (secondsVal) secondsVal.textContent = seconds.toString().padStart(2, '0');
  };
  
  // Run immediately and update every second
  updateTimer();
  const timerInterval = setInterval(updateTimer, 1000);
  
  // 2. Newsletter Subscription & Privilege Code Reveal
  const subscribeForm = document.getElementById('comingSoonForm');
  const emailInput = document.getElementById('comingSoonEmail');
  const launchCard = document.getElementById('launchCard');
  
  // Helper: Show Toast message
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
  
  // Check if already subscribed in this browser
  const isSubscribed = localStorage.getItem('ruut_newsletter_subscribed') === 'true';
  if (isSubscribed) {
    revealPrivilegeCode(true); // Show privilege code directly if they subscribed earlier
  }
  
  function revealPrivilegeCode(alreadySubscribed = false) {
    const formWrap = document.getElementById('subscribeFormWrap');
    if (!formWrap) return;
    
    // Hide form
    formWrap.style.display = 'none';
    
    // Append privilege reveal card
    const revealHtml = `
      <div class="privilege-reveal-box" id="privilegeBox">
        <h3 class="privilege-title">${alreadySubscribed ? 'Welcome Back!' : 'Thank you for joining!'}</h3>
        <p class="privilege-desc">Here is your private early-access privilege code. Keep it safe for checkout.</p>
        <span class="privilege-code" id="privilegeCodeText">LAUNCH-15</span>
        <p class="privilege-desc" style="margin-top:5px; font-weight:600; color:var(--ruut-green);">Enjoy 15% off your first ritual blend.</p>
      </div>
    `;
    formWrap.insertAdjacentHTML('afterend', revealHtml);
  }
  
  if (subscribeForm && emailInput) {
    subscribeForm.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const emailValue = emailInput.value.trim();
      
      // Basic validation
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailValue || !emailRegex.test(emailValue)) {
        showToast('Please enter a valid email address.');
        return;
      }
      
      // Simulate subscribing
      const submitBtn = subscribeForm.querySelector('.submit-btn');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Subscribing...';
      }
      
      // Mimic AJAX delay
      setTimeout(() => {
        localStorage.setItem('ruut_newsletter_subscribed', 'true');
        showToast('You have been subscribed! Unlocking privileges...');
        revealPrivilegeCode(false);
      }, 1200);
    });
  }
});
