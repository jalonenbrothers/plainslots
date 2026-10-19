/* PlainSlots — vanilla JS enhancements only. Core flow works with JS off. */
(function () {
  'use strict';

  // ---- landing: add/remove slot rows (JS appends; no-JS gets 3 rows + server keeps extras)
  var createForm = document.getElementById('create-form');
  var slotRows = document.getElementById('slot-rows');
  if (createForm && slotRows) {
    var addBtn = document.getElementById('add-slot');
    addBtn.addEventListener('click', function () {
      if (slotRows.querySelectorAll('.slot-input').length >= 50) return;
      var input = document.createElement('input');
      input.className = 'slot-input';
      input.name = 'slots[]';
      input.type = 'text';
      input.maxLength = 80;
      slotRows.appendChild(input);
      input.focus();
    });

    // JS path: fetch POST /api/create.php → redirect to /p/{slug}?new=1&edit_slug=…
    createForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var fd = new FormData(createForm);
      var slots = [];
      fd.getAll('slots[]').forEach(function (s) { s = String(s).trim(); if (s) slots.push(s); });
      fetch('/api/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title: String(fd.get('title') || ''),
          description: String(fd.get('description') || ''),
          creator: String(fd.get('creator') || ''),
          slots: slots
        })
      }).then(function (r) { return r.json(); }).then(function (j) {
        if (j && j.ok) {
          // edit_slug travels via session (one-time box), never via URL
          location.assign('/p/' + encodeURIComponent(j.slug) + '?new=1');
        } else {
          alert((j && j.error) ? j.error : 'Could not create the poll.');
        }
      }).catch(function () { createForm.submit(); }); // network fail → no-JS form post
    });
  }

  // ---- poll page: vote via fetch, remember name, prefill
  var voteForm = document.querySelector('#vote form');
  if (voteForm) {
    var nameInput = document.getElementById('v-name');
    try {
      var last = localStorage.getItem('plainslots:name');
      if (last && nameInput && !nameInput.value) nameInput.value = last;
    } catch (e) { /* private mode etc. */ }
    voteForm.addEventListener('submit', function (ev) {
      var name = nameInput ? nameInput.value.trim() : '';
      try { if (name) localStorage.setItem('plainslots:name', name); } catch (e) {}
      // default: let the form POST normally (no-JS core path, works everywhere).
    });
  }

  // ---- copy buttons (clipboard API, fallback = select on focus)
  document.querySelectorAll('.copy-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var src = document.getElementById(btn.getAttribute('data-copy'));
      if (!src) return;
      var done = function () { var t = btn.textContent; btn.textContent = 'Copied!'; setTimeout(function () { btn.textContent = t; }, 1500); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(src.value).then(done).catch(function () { src.select(); done(); });
      } else {
        src.focus(); src.select(); done();
      }
    });
  });

  // ---- admin: remove slot rows, confirm() on delete
  var adminRows = document.getElementById('admin-slot-rows');
  if (adminRows) {
    adminRows.addEventListener('click', function (ev) {
      var btn = ev.target.closest('.remove-slot');
      if (btn) btn.closest('.slot-row').remove();
    });
    var addAdmin = document.getElementById('add-admin-slot');
    if (addAdmin) addAdmin.addEventListener('click', function () {
      var wrap = document.createElement('div');
      wrap.className = 'slot-row';
      var input = document.createElement('input');
      input.className = 'slot-input';
      input.name = 'slots[]';
      input.type = 'text';
      input.maxLength = 80;
      var rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'linklike remove-slot';
      rm.title = 'Remove this slot';
      rm.textContent = '✕';
      wrap.appendChild(input); wrap.appendChild(rm);
      adminRows.appendChild(wrap);
      input.focus();
    });
  }
  var delForm = document.getElementById('delete-form');
  if (delForm) {
    delForm.addEventListener('submit', function (ev) {
      if (!window.confirm('Really delete this poll and all its votes? This cannot be undone.')) {
        ev.preventDefault();
      }
    });
  }
})();