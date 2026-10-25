'use strict';

/* =====================================================================
   schedule.js - managing and displaying sessions with arrays and objects
   (Question 7.2). Uses helpers from validation.js (the TV object).
   ===================================================================== */

/* ---------- 1. Data: arrays of objects (mirrors the MySQL sample data) ---------- */
const programmes = [
  { id: 1, name: 'Mathematics' },
  { id: 2, name: 'Data Science' }
];

const tutors = [
  {
    id: 1, name: 'Thabo Mokoena', programmes: [1],
    availability: {
      Monday:    [{ start: '09:00', end: '13:00' }],
      Wednesday: [{ start: '09:00', end: '13:00' }]
    }
  },
  {
    id: 2, name: 'Lerato Dlamini', programmes: [2],
    availability: {
      Monday:    [{ start: '13:00', end: '17:00' }],
      Tuesday:   [{ start: '13:00', end: '17:00' }],
      Wednesday: [{ start: '13:00', end: '17:00' }]
    }
  },
  {
    id: 3, name: 'Sipho Nkosi', programmes: [1, 2],
    availability: {
      Thursday: [{ start: '09:00', end: '17:00' }],
      Friday:   [{ start: '09:00', end: '17:00' }]
    }
  }
];

let bookings = [
  { id: 1, programmeId: 1, tutorId: 1, date: '2026-09-28', start: '10:00', end: '11:00', status: 'completed' },
  { id: 2, programmeId: 2, tutorId: 2, date: '2026-09-30', start: '14:00', end: '15:00', status: 'completed' },
  { id: 3, programmeId: 1, tutorId: 1, date: '2026-10-07', start: '10:00', end: '11:00', status: 'confirmed' },
  { id: 4, programmeId: 1, tutorId: 3, date: '2026-10-08', start: '15:00', end: '16:00', status: 'confirmed' },
  { id: 5, programmeId: 2, tutorId: 3, date: '2026-10-09', start: '09:00', end: '10:00', status: 'requested' },
  { id: 6, programmeId: 2, tutorId: 2, date: '2026-10-12', start: '14:00', end: '15:00', status: 'cancelled' }
];
let nextId = bookings.length + 1;

const state = {
  status: 'all',
  sortBy: 'date',
  search: '',
  weekStart: mondayOf(new Date())
};

/* ---------- 2. Small helper functions ---------- */
const byId          = (list, id) => list.find(item => item.id === id);
const programmeName = id => byId(programmes, id).name;
const tutorName     = id => byId(tutors, id).name;
const capitalise    = s => s.charAt(0).toUpperCase() + s.slice(1);

function mondayOf(date) {
  const m = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  m.setDate(m.getDate() - ((m.getDay() + 6) % 7));
  return m;
}

// Create an element and set its text safely (textContent, never innerHTML)
function el(tag, text, className) {
  const node = document.createElement(tag);
  if (text !== undefined && text !== null) node.textContent = text;
  if (className) node.className = className;
  return node;
}

/* ---------- 3. Sorting, filtering and business rules ---------- */
const sorters = {
  date:   (a, b) => (a.date + a.start).localeCompare(b.date + b.start),
  tutor:  (a, b) => tutorName(a.tutorId).localeCompare(tutorName(b.tutorId)) || sorters.date(a, b),
  status: (a, b) => a.status.localeCompare(b.status) || sorters.date(a, b)
};

function visibleBookings() {
  const term = state.search.toLowerCase();
  return bookings
    .filter(b => state.status === 'all' || b.status === state.status)
    .filter(b => !term ||
      `${tutorName(b.tutorId)} ${programmeName(b.programmeId)} ${b.date}`.toLowerCase().includes(term))
    .sort(sorters[state.sortBy]);
}

function tutorIsAvailable(tutor, date, start, end) {
  const day = TV.dayName(TV.parseDate(date));
  return (tutor.availability[day] || []).some(slot => slot.start <= start && slot.end >= end);
}

function findClash(candidate) {
  return bookings.find(b =>
    b.status !== 'cancelled' &&
    b.tutorId === candidate.tutorId &&
    b.date === candidate.date &&
    b.start < candidate.end &&
    b.end > candidate.start);
}

/* ---------- 4. Rendering (dynamic display) ---------- */
function renderSummary() {
  const counts = bookings.reduce((acc, b) => {
    acc[b.status] = (acc[b.status] || 0) + 1;
    return acc;
  }, {});
  const parts = Object.entries(counts).map(([status, n]) => `${capitalise(status)}: ${n}`);
  document.getElementById('summary').textContent =
    `Total sessions: ${bookings.length}` + (parts.length ? ' | ' + parts.join(' | ') : '');
}

function renderTable() {
  const body = document.getElementById('bookingBody');
  body.replaceChildren();
  const rows = visibleBookings();

  if (rows.length === 0) {
    const tr = el('tr');
    const td = el('td', 'No sessions match your filters.');
    td.colSpan = 7;
    tr.append(td);
    body.append(tr);
    return;
  }

  rows.forEach(b => {
    const tr = el('tr');
    const dayShort = TV.dayName(TV.parseDate(b.date)).slice(0, 3);
    [b.id, programmeName(b.programmeId), tutorName(b.tutorId),
     `${b.date} (${dayShort})`, `${b.start} - ${b.end}`, capitalise(b.status)]
      .forEach(value => tr.append(el('td', String(value))));

    const action = el('td');
    if (b.status === 'requested' || b.status === 'confirmed') {
      const btn = el('button', 'Cancel', 'danger');
      btn.type = 'button';
      btn.dataset.id = b.id;
      action.append(btn);
    }
    tr.append(action);
    body.append(tr);
  });
}

function renderWeek() {
  const grid = document.getElementById('weekGrid');
  grid.replaceChildren();

  for (let i = 0; i < 5; i++) {
    const day = new Date(state.weekStart);
    day.setDate(day.getDate() + i);
    const key = TV.formatDate(day);

    const column = el('div', null, 'day');
    column.append(el('h3', `${TV.dayName(day)} ${key.slice(5)}`));

    const sessions = bookings
      .filter(b => b.date === key && b.status !== 'cancelled')
      .sort(sorters.date);

    if (sessions.length === 0) column.append(el('small', 'No sessions'));

    sessions.forEach(b => {
      const slot = el('div', null, 'slot ' + b.status);
      slot.append(el('strong', `${b.start} - ${b.end}`));
      slot.append(el('div', programmeName(b.programmeId)));
      slot.append(el('div', tutorName(b.tutorId)));
      column.append(slot);
    });
    grid.append(column);
  }
  document.getElementById('weekLabel').textContent = `Week of ${TV.formatDate(state.weekStart)}`;
}

function renderAvailability() {
  const body = document.getElementById('availabilityBody');
  body.replaceChildren();
  tutors.forEach(t => {
    const tr = el('tr');
    tr.append(el('td', t.name));
    tr.append(el('td', t.programmes.map(programmeName).join(', ')));
    const text = Object.entries(t.availability)
      .map(([day, slots]) => `${day.slice(0, 3)} ${slots.map(s => `${s.start}-${s.end}`).join(', ')}`)
      .join(' | ');
    tr.append(el('td', text));
    body.append(tr);
  });
}

function renderAll() {
  renderSummary();
  renderTable();
  renderWeek();
}

/* ---------- 5. Events ---------- */
document.getElementById('statusFilter').addEventListener('change', e => { state.status = e.target.value; renderTable(); });
document.getElementById('sortBy').addEventListener('change', e => { state.sortBy = e.target.value; renderTable(); });
document.getElementById('search').addEventListener('input', e => { state.search = e.target.value.trim(); renderTable(); });

document.getElementById('prevWeek').addEventListener('click', () => { state.weekStart.setDate(state.weekStart.getDate() - 7); renderWeek(); });
document.getElementById('nextWeek').addEventListener('click', () => { state.weekStart.setDate(state.weekStart.getDate() + 7); renderWeek(); });

document.getElementById('bookingBody').addEventListener('click', ev => {
  const btn = ev.target.closest('button[data-id]');
  if (!btn) return;
  const booking = byId(bookings, Number(btn.dataset.id));
  if (booking && confirm(`Cancel session #${booking.id} with ${tutorName(booking.tutorId)} on ${booking.date}?`)) {
    booking.status = 'cancelled';
    renderAll();
  }
});

/* ---------- 6. Add-session form: validation + business rules ---------- */
const plannerForm = document.getElementById('plannerForm');

programmes.forEach(p => {
  const o = el('option', p.name); o.value = p.id;
  document.getElementById('programme_id').append(o);
});
tutors.forEach(t => {
  const o = el('option', t.name); o.value = t.id;
  document.getElementById('tutor_id').append(o);
});

const planner = TV.attach('plannerForm', {
  programme_id: v => v ? '' : 'Select a programme.',
  tutor_id:     v => v ? '' : 'Select a tutor.',
  ...TV.bookingRules
});

plannerForm.addEventListener('submit', ev => {
  ev.preventDefault();
  if (planner.validateAll()) return;

  const fail = (id, message) => {
    const input = plannerForm.querySelector('#' + id);
    TV.setError(input, message);
    input.focus();
    document.getElementById('plannerMsg').textContent = '';
  };

  const candidate = {
    programmeId: Number(plannerForm.programme_id.value),
    tutorId:     Number(plannerForm.tutor_id.value),
    date:        plannerForm.session_date.value,
    start:       plannerForm.start_time.value,
    end:         plannerForm.end_time.value,
    status:      'confirmed'
  };
  const tutor = byId(tutors, candidate.tutorId);

  if (!tutor.programmes.includes(candidate.programmeId)) {
    return fail('tutor_id', `${tutor.name} does not teach ${programmeName(candidate.programmeId)}.`);
  }
  if (!tutorIsAvailable(tutor, candidate.date, candidate.start, candidate.end)) {
    const day = TV.dayName(TV.parseDate(candidate.date));
    return fail('start_time', `${tutor.name} is not available on ${day} from ${candidate.start} to ${candidate.end}.`);
  }
  const clash = findClash(candidate);
  if (clash) {
    return fail('start_time', `${tutor.name} already has a session from ${clash.start} to ${clash.end} on that date.`);
  }

  bookings.push({ id: nextId++, ...candidate });
  plannerForm.reset();
  planner.reset();
  document.getElementById('plannerMsg').textContent =
    `Session added with ${tutor.name} on ${candidate.date} at ${candidate.start}.`;

  state.weekStart = mondayOf(TV.parseDate(candidate.date));
  renderAll();
});

/* ---------- 7. First display ---------- */
renderAvailability();
renderAll();