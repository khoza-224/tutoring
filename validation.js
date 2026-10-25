'use strict';

/* =====================================================================
   validation.js - shared client-side validation (Question 7.1)
   ===================================================================== */
const TV = (() => {

  const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  const WEEKDAYS  = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
  const OPEN  = '08:00';
  const CLOSE = '18:00';

  /* ---------- Basic format checks ---------- */
  const isEmail = v => v.length <= 150 && /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);
  const isName  = v => /^[\p{L}' \-]{2,60}$/u.test(v);
  const isPhone = v => /^0\d{9}$/.test(v);
  const isTime  = v => /^([01]\d|2[0-3]):[0-5]\d$/.test(v);

  const passwordMessage = v => {
    if (!v) return 'Enter a password.';
    const missing = [];
    if (v.length < 8)     missing.push('at least 8 characters');
    if (!/[a-z]/.test(v)) missing.push('a lowercase letter');
    if (!/[A-Z]/.test(v)) missing.push('an uppercase letter');
    if (!/\d/.test(v))    missing.push('a number');
    if (v.length > 72)    return 'Password is too long (maximum 72 characters).';
    return missing.length ? 'Password needs ' + missing.join(', ') + '.' : '';
  };

  // Luhn algorithm: catches mistyped card numbers
  const luhn = digits => {
    let sum = 0, alt = false;
    for (let i = digits.length - 1; i >= 0; i--) {
      let d = Number(digits[i]);
      if (alt) { d *= 2; if (d > 9) d -= 9; }
      sum += d;
      alt = !alt;
    }
    return sum % 10 === 0;
  };

  /* ---------- Date and time helpers (local time, no UTC shifts) ---------- */
  const pad        = n => String(n).padStart(2, '0');
  const toMinutes  = t => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
  const formatDate = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const dayName    = d => DAY_NAMES[d.getDay()];
  const isWeekday  = d => d.getDay() >= 1 && d.getDay() <= 5;

  const parseDate = s => {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
    if (!m) return null;
    const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    return (d.getFullYear() === Number(m[1]) && d.getMonth() === Number(m[2]) - 1 &&
            d.getDate() === Number(m[3])) ? d : null;
  };

  /* ---------- Error display ---------- */
  function setError(input, message) {
    let box = document.querySelector('.error[data-for="' + input.id + '"]');
    if (!box) {
      box = document.createElement('span');
      box.className = 'error';
      box.dataset.for = input.id;
      box.setAttribute('role', 'alert');
      input.insertAdjacentElement('afterend', box);
    }
    box.textContent = message;
    input.classList.toggle('has-error', message !== '');
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
  }

  const readValue = input =>
    input.type === 'checkbox' ? input.checked
    : input.type === 'password' ? input.value
    : input.value.trim();

  /* ---------- Generic form validator ----------
     rules = { fieldId: (value, form) => 'error message' or '' }                  */
  function attach(formId, rules) {
    const form = document.getElementById(formId);
    if (!form) return null;

    form.noValidate = true;
    const touched = new Set();

    const validateField = id => {
      const input = form.querySelector('#' + id);
      if (!input || input.disabled) return true;
      const message = rules[id](readValue(input), form) || '';
      setError(input, message);
      return message === '';
    };

    const validateAll = () => {
      let firstBad = null;
      Object.keys(rules).forEach(id => {
        if (!validateField(id) && !firstBad) firstBad = form.querySelector('#' + id);
      });
      return firstBad;
    };

    const revalidateTouched = () => touched.forEach(id => validateField(id));

    Object.keys(rules).forEach(id => {
      const input = form.querySelector('#' + id);
      if (!input) return;
      const visit = () => { touched.add(id); revalidateTouched(); };
      input.addEventListener('blur', visit);
      input.addEventListener('change', visit);
      input.addEventListener('input', () => { if (touched.has(id)) revalidateTouched(); });
    });

    form.addEventListener('submit', ev => {
      const bad = validateAll();
      if (bad) {
        ev.preventDefault();
        bad.focus();
      }
    });

    return {
      form,
      validateAll,
      reset() {
        touched.clear();
        form.querySelectorAll('.error').forEach(box => { box.textContent = ''; });
        form.querySelectorAll('.has-error').forEach(i => i.classList.remove('has-error'));
      }
    };
  }

  /* ---------- Booking rules (shared by booking.php and the planner) ---------- */
  const bookingRules = {
    session_date: (v, form) => {
      if (!v) return 'Select a session date.';
      const d = parseDate(v);
      if (!d) return 'Enter a valid date.';
      if (!isWeekday(d)) return `Sessions run Monday to Friday only (${dayName(d)} is not allowed).`;
      const input = form.querySelector('#session_date');
      if (input.min && v < input.min) return 'This date is before the booking period opens.';
      if (input.max && v > input.max) return 'This date is after the booking period closes.';
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      if (d < today) return 'The date cannot be in the past.';
      return '';
    },
    start_time: (v, form) => {
      if (!v) return 'Select a start time.';
      if (!isTime(v)) return 'Enter a valid time.';
      if (v < OPEN) return 'Sessions start from 08:00.';
      if (v >= CLOSE) return 'Sessions must start before 18:00.';
      const d = parseDate(form.querySelector('#session_date').value);
      if (d && formatDate(d) === formatDate(new Date())) {
        const now = new Date();
        if (toMinutes(v) <= now.getHours() * 60 + now.getMinutes()) return 'That time has already passed today.';
      }
      return '';
    },
    end_time: (v, form) => {
      if (!v) return 'Select an end time.';
      if (!isTime(v)) return 'Enter a valid time.';
      if (v > CLOSE) return 'Sessions must end by 18:00.';
      const start = form.querySelector('#start_time').value;
      if (isTime(start) && v <= start) return 'The end time must be after the start time.';
      return '';
    }
  };

  /* =====================================================================
     Wire up the forms (each call does nothing if the form is not on the page)
     ===================================================================== */

  attach('loginForm', {
    role:     v => v ? '' : 'Select your role.',
    email:    v => !v ? 'Email is required.' : isEmail(v) ? '' : 'Enter a valid email address.',
    password: v => !v ? 'Password is required.' : v.length < 8 ? 'Password must be at least 8 characters.' : ''
  });

  attach('registerForm', {
    first_name: v => !v ? 'First name is required.' : isName(v) ? '' : 'Use letters, spaces, apostrophes or hyphens (2 to 60 characters).',
    last_name:  v => !v ? 'Last name is required.'  : isName(v) ? '' : 'Use letters, spaces, apostrophes or hyphens (2 to 60 characters).',
    email:      v => !v ? 'Email is required.' : isEmail(v) ? '' : 'Enter a valid email address.',
    phone:      v => !v ? 'Phone number is required.' : isPhone(v) ? '' : 'Enter 10 digits starting with 0.',
    password:   v => passwordMessage(v),
    confirm_password: (v, form) =>
      !v ? 'Confirm your password.' : v !== form.querySelector('#password').value ? 'Passwords do not match.' : '',
    terms:      v => v ? '' : 'You must accept the terms and conditions.'
  });

  const paymentForm = document.getElementById('paymentForm');
  if (paymentForm) {
    const cardFields = ['card_name', 'card_number', 'card_expiry', 'card_cvv'];
    const methodSelect = document.getElementById('payment_method');

    // Card fields are only used (and only sent to the server) for card payments
    const toggleCardFields = () => {
      const isCard = methodSelect.value === 'Card';
      cardFields.forEach(id => {
        const input = document.getElementById(id);
        input.disabled = !isCard;
        if (!isCard) setError(input, '');
      });
    };
    methodSelect.addEventListener('change', toggleCardFields);
    toggleCardFields();

    attach('paymentForm', {
      programme_id:   v => v ? '' : 'Select a programme.',
      payment_method: v => v ? '' : 'Select a payment method.',
      card_name: v => !v ? 'Enter the name on the card.' : /^[\p{L}' \-]{2,100}$/u.test(v) ? '' : 'Use letters and spaces only.',
      card_number: v => {
        const digits = v.replace(/\s+/g, '');
        if (!digits) return 'Enter the card number.';
        if (!/^\d{13,19}$/.test(digits)) return 'Card number must be 13 to 19 digits.';
        return luhn(digits) ? '' : 'This card number is not valid.';
      },
      card_expiry: v => {
        if (!v) return 'Enter the expiry month.';
        if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(v)) return 'Use the format YYYY-MM.';
        const now = new Date();
        const current = `${now.getFullYear()}-${pad(now.getMonth() + 1)}`;
        return v < current ? 'This card has expired.' : '';
      },
      card_cvv: v => /^\d{3,4}$/.test(v) ? '' : 'CVV must be 3 or 4 digits.'
    });
  }

  attach('bookingForm', {
    enrolment_id: v => v ? '' : 'Select your programme.',
    ...bookingRules
  });

  attach('availabilityForm', {
    day_of_week: v => WEEKDAYS.includes(v) ? '' : 'Select a day from Monday to Friday.',
    start_time: v => {
      if (!v) return 'Select a start time.';
      if (v < OPEN) return 'Availability starts from 08:00.';
      return v >= CLOSE ? 'The start time must be before 18:00.' : '';
    },
    end_time: (v, form) => {
      if (!v) return 'Select an end time.';
      if (v > CLOSE) return 'Availability ends by 18:00.';
      const start = form.querySelector('#start_time').value;
      return (isTime(start) && v <= start) ? 'The end time must be after the start time.' : '';
    }
  });

  attach('deleteSlotForm', {
    availability_id: v => /^[1-9]\d*$/.test(v) ? '' : 'Enter a valid slot number.'
  });

  return { attach, setError, bookingRules, parseDate, formatDate, dayName, isWeekday, toMinutes, OPEN, CLOSE };
})();