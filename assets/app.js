/* Anwesenheitsliste – alles im Browser, Schreibzugriffe per fetch auf api.php */
(() => {
  'use strict';

  const boot  = window.__BOOT || { participants: [], trainings: [], attendance: [] };
  const TOKEN = window.__TOKEN || '';

  const S = {
    people:    boot.participants || [],
    trainings: boot.trainings || [],
    att:       new Map(),
    q:         '',
    filter:    'active',
    sort:      'first',
    dir:       1,
    view:      'grid',
    sessionId: null,
    scrollLeft: null,
  };
  (boot.attendance || []).forEach(([t, p, s]) => S.att.set(t + ':' + p, s));

  const app = document.getElementById('app');
  const $   = (sel, root = document) => root.querySelector(sel);

  const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

  /** Diakritika entfernen, damit «ruegg» auch «Rüegg» findet. */
  const fold = (s) => String(s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

  const key = (t, p) => t + ':' + p;
  const statusOf = (t, p) => S.att.get(key(t, p)) || '';
  const NEXT = { '': 'present', present: 'absent', absent: '' };

  /* ---------- Server ---------- */

  async function api(action, payload) {
    const res = await fetch('api.php?action=' + encodeURIComponent(action), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Token': TOKEN },
      body: JSON.stringify(payload || {}),
    });
    let data = {};
    try { data = await res.json(); } catch (e) { /* leere Antwort */ }
    if (!res.ok) throw new Error(data.error || 'Keine Verbindung zum Server.');
    return data;
  }

  let toastTimer = null;
  function toast(msg, bad) {
    const el = $('#toast');
    el.textContent = msg;
    el.classList.toggle('bad', !!bad);
    el.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('show'), bad ? 5000 : 2200);
  }

  /* ---------- Daten ---------- */

  function visible() {
    const q = fold(S.q.trim());
    let list = S.people.filter((p) => {
      if (S.filter === 'active' && !p.active) return false;
      if (S.filter === 'inactive' && p.active) return false;
      if (!q) return true;
      return fold(p.first_name + ' ' + p.last_name).includes(q);
    });
    const name = (p) => (p.first_name + ' ' + p.last_name).toLocaleLowerCase('de');
    list.sort((a, b) => {
      if (S.sort === 'year') {
        // Ohne Jahrgang immer am Schluss, unabhängig von der Richtung
        const na = a.birth_year == null, nb = b.birth_year == null;
        if (na !== nb) return na ? 1 : -1;
        if (!na && a.birth_year !== b.birth_year) return (a.birth_year - b.birth_year) * S.dir;
      }
      return name(a).localeCompare(name(b), 'de') * S.dir;
    });
    return list;
  }

  function presentCount(tid) {
    let n = 0;
    for (const p of S.people) if (statusOf(tid, p.id) === 'present') n++;
    return n;
  }

  const fmtDay = (iso) => {
    const [y, m, d] = iso.split('-');
    return d + '.' + m + '.';
  };
  const fmtFull = (iso) => {
    const dt = new Date(iso + 'T00:00:00');
    return dt.toLocaleDateString('de-CH', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' });
  };

  /* ---------- Rendering ---------- */

  function render() {
    if (S.view === 'session') renderSession();
    else renderGrid();
    const shown = visible().length;
    $('#count').textContent = shown + ' von ' + S.people.length + (S.people.length === 1 ? ' Person' : ' Personen');
  }

  function renderGrid() {
    const people = visible();

    if (!S.people.length) {
      app.innerHTML = '<p class="empty"><strong>Noch keine Teilnehmerinnen.</strong> ' +
        'Oben rechts über «Teilnehmerin» die erste Person erfassen.</p>';
      return;
    }

    const h = [];
    h.push('<div class="scroller" id="scroller"><table class="grid"><thead><tr>');
    h.push('<th class="c-first sortable' + (S.sort === 'first' ? ' on' : '') + '" data-sort="first">Vorname' +
      (S.sort === 'first' ? (S.dir > 0 ? ' ↑' : ' ↓') : '') + '</th>');
    h.push('<th class="c-last">Name</th>');
    h.push('<th class="c-year sortable' + (S.sort === 'year' ? ' on' : '') + '" data-sort="year">Jg' +
      (S.sort === 'year' ? (S.dir > 0 ? ' ↑' : ' ↓') : '') + '</th>');
    h.push('<th class="c-tools"></th>');

    let lastYear = '';
    for (const t of S.trainings) {
      const year = t.training_date.slice(0, 4);
      const showYear = year !== lastYear;
      lastYear = year;
      h.push('<th class="c-train" data-tid="' + t.id + '">' +
        '<button type="button" class="th-train" data-train="' + t.id + '" title="' +
        esc(fmtFull(t.training_date) + (t.label ? ' · ' + t.label : '')) + ' – erfassen">' +
        (showYear ? '<span class="m">' + year + '</span>' : '') +
        '<span class="d">' + fmtDay(t.training_date) + '</span>' +
        '<span class="n">' + presentCount(t.id) + '</span>' +
        (t.label ? '<span class="lbl">' + esc(t.label) + '</span>' : '') +
        '</button></th>');
    }
    h.push('<th class="c-add"><button type="button" id="addTrainingCol" title="Training hinzufügen">+</button></th>');
    h.push('</tr></thead><tbody>');

    if (!people.length) {
      h.push('<tr><td class="c-first"></td><td class="c-last"></td><td class="c-year"></td><td class="c-tools"></td>' +
        '<td colspan="' + (S.trainings.length + 1) + '" style="padding:.8rem;color:#6c6475">Keine Treffer.</td></tr>');
    }

    for (const p of people) {
      h.push('<tr' + (p.active ? '' : ' class="is-inactive"') + '>');
      h.push('<td class="c-first" title="' + esc(p.first_name) + '"><span class="t">' + esc(p.first_name) + '</span></td>');
      h.push('<td class="c-last" title="' + esc(p.last_name) + '"><span class="t">' + esc(p.last_name) + '</span></td>');
      h.push('<td class="c-year">' + (p.birth_year || '') + '</td>');
      h.push('<td class="c-tools">' +
        '<button type="button" class="icon" data-edit="' + p.id + '" title="Bearbeiten">&#9998;</button>' +
        '<a class="icon" href="vcf.php?id=' + p.id + '" title="Kontakt als .vcf">&#8623;</a>' +
        '</td>');
      for (const t of S.trainings) {
        const st = statusOf(t.id, p.id);
        h.push('<td class="dot-cell c-train"><button type="button" class="dot" data-s="' + st +
          '" data-t="' + t.id + '" data-p="' + p.id + '" aria-label="' +
          esc(p.first_name + ' ' + fmtDay(t.training_date)) + '"></button></td>');
      }
      h.push('<td class="c-add"></td></tr>');
    }
    h.push('</tbody></table></div>');
    app.innerHTML = h.join('');

    const sc = $('#scroller');
    sc.scrollLeft = S.scrollLeft === null ? sc.scrollWidth : S.scrollLeft;
    sc.addEventListener('scroll', () => { S.scrollLeft = sc.scrollLeft; }, { passive: true });
  }

  function renderSession() {
    const t = S.trainings.find((x) => x.id === S.sessionId);
    if (!t) { S.view = 'grid'; renderGrid(); return; }
    const people = visible();

    const h = [];
    h.push('<div class="session"><div class="session-head">');
    h.push('<button type="button" class="btn btn-quiet" data-back>← Liste</button>');
    h.push('<h2>' + esc(fmtFull(t.training_date)) + (t.label ? ' <span style="font-weight:400;color:#6c6475">' + esc(t.label) + '</span>' : '') + '</h2>');
    h.push('<span class="tally" id="tally">' + presentCount(t.id) + '</span>');
    h.push('</div><ul class="session-list">');
    for (const p of people) {
      h.push('<li><button type="button" class="session-row" data-s="' + statusOf(t.id, p.id) +
        '" data-t="' + t.id + '" data-p="' + p.id + '">' +
        '<span class="mark"></span><span class="who">' + esc(p.first_name + ' ' + p.last_name) + '</span>' +
        '<span class="jg">' + (p.birth_year || '') + '</span></button></li>');
    }
    if (!people.length) h.push('<li><p class="empty">Keine Treffer.</p></li>');
    h.push('</ul>');
    h.push('<p style="margin:1rem .2rem;color:#6c6475;font-size:.88rem">Tippen wechselt: grau → grün (anwesend) → rot (abwesend) → grau.</p>');
    h.push('<button type="button" class="btn" data-edit-train="' + t.id + '">Training bearbeiten</button>');
    h.push('</div>');
    app.innerHTML = h.join('');
  }

  /* ---------- Anwesenheit ---------- */

  async function cycle(el) {
    const tid = +el.dataset.t, pid = +el.dataset.p;
    const before = statusOf(tid, pid);
    const next = NEXT[before];

    if (next) S.att.set(key(tid, pid), next); else S.att.delete(key(tid, pid));
    el.dataset.s = next;
    el.classList.add('busy');
    updateTally(tid);

    try {
      await api('attendance.set', { training_id: tid, participant_id: pid, status: next });
    } catch (err) {
      if (before) S.att.set(key(tid, pid), before); else S.att.delete(key(tid, pid));
      el.dataset.s = before;
      updateTally(tid);
      toast(err.message, true);
    } finally {
      el.classList.remove('busy');
    }
  }

  function updateTally(tid) {
    const n = presentCount(tid);
    const head = app.querySelector('.th-train[data-train="' + tid + '"] .n');
    if (head) head.textContent = n;
    const tally = $('#tally');
    if (tally && S.sessionId === tid) tally.textContent = n;
  }

  /* ---------- Dialog Teilnehmerin ---------- */

  const dlgP = $('#dlgPerson');
  const dlgT = $('#dlgTraining');
  let editingPerson = null;
  let editingTraining = null;

  function openPerson(p) {
    editingPerson = p || null;
    $('#dlgPersonTitle').textContent = p ? 'Teilnehmerin bearbeiten' : 'Neue Teilnehmerin';
    $('#f_first').value    = p ? p.first_name : '';
    $('#f_last').value     = p ? p.last_name : '';
    $('#f_year').value     = p && p.birth_year ? p.birth_year : '';
    $('#f_gender').value   = p ? p.gender : 'f';
    $('#f_mobile').value   = p ? p.mobile : '';
    $('#f_email').value    = p ? p.email : '';
    $('#f_notes').value    = p ? (p.notes || '') : '';
    $('#f_active').checked = p ? !!p.active : true;
    $('#f_whatsapp').checked = p ? !!p.whatsapp : false;
    $('#btnDeletePerson').hidden = !p;
    dlgP.showModal();
    $('#f_first').focus();
  }

  $('#formPerson').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const payload = {
      id:         editingPerson ? editingPerson.id : 0,
      first_name: $('#f_first').value,
      last_name:  $('#f_last').value,
      birth_year: $('#f_year').value,
      gender:     $('#f_gender').value,
      mobile:     $('#f_mobile').value,
      email:      $('#f_email').value,
      notes:      $('#f_notes').value,
      active:     $('#f_active').checked,
      whatsapp:   $('#f_whatsapp').checked,
    };
    try {
      const res = await api('participant.save', payload);
      const saved = res.participant;
      const i = S.people.findIndex((x) => x.id === saved.id);
      if (i >= 0) S.people[i] = saved; else S.people.push(saved);
      dlgP.close();
      render();
      toast(saved.first_name + ' gespeichert.');
    } catch (err) {
      toast(err.message, true);
    }
  });

  $('#btnDeletePerson').addEventListener('click', async () => {
    if (!editingPerson) return;
    const name = (editingPerson.first_name + ' ' + editingPerson.last_name).trim();
    if (!confirm(name + ' endgültig löschen? Die Anwesenheiten dieser Person gehen mit verloren.')) return;
    try {
      await api('participant.delete', { id: editingPerson.id });
      const id = editingPerson.id;
      S.people = S.people.filter((x) => x.id !== id);
      for (const k of [...S.att.keys()]) if (k.endsWith(':' + id)) S.att.delete(k);
      dlgP.close();
      render();
      toast(name + ' gelöscht.');
    } catch (err) {
      toast(err.message, true);
    }
  });

  /* ---------- Dialog Training ---------- */

  function openTraining(t) {
    editingTraining = t || null;
    $('#dlgTrainingTitle').textContent = t ? 'Training bearbeiten' : 'Neues Training';
    $('#t_date').value  = t ? t.training_date : new Date().toISOString().slice(0, 10);
    $('#t_label').value = t ? t.label : '';
    $('#btnDeleteTraining').hidden = !t;
    dlgT.showModal();
    $('#t_date').focus();
  }

  $('#formTraining').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const isNew = !editingTraining;
    try {
      const res = await api('training.save', {
        id: editingTraining ? editingTraining.id : 0,
        training_date: $('#t_date').value,
        label: $('#t_label').value,
      });
      const saved = res.training;
      const i = S.trainings.findIndex((x) => x.id === saved.id);
      if (i >= 0) S.trainings[i] = saved; else S.trainings.push(saved);
      S.trainings.sort((a, b) => a.training_date.localeCompare(b.training_date) || a.id - b.id);
      dlgT.close();
      if (isNew) { S.view = 'session'; S.sessionId = saved.id; }
      S.scrollLeft = null;
      render();
    } catch (err) {
      toast(err.message, true);
    }
  });

  $('#btnDeleteTraining').addEventListener('click', async () => {
    if (!editingTraining) return;
    if (!confirm('Training vom ' + fmtFull(editingTraining.training_date) + ' samt Anwesenheiten löschen?')) return;
    try {
      await api('training.delete', { id: editingTraining.id });
      const id = editingTraining.id;
      S.trainings = S.trainings.filter((x) => x.id !== id);
      for (const k of [...S.att.keys()]) if (k.startsWith(id + ':')) S.att.delete(k);
      dlgT.close();
      if (S.sessionId === id) { S.view = 'grid'; S.sessionId = null; }
      render();
      toast('Training gelöscht.');
    } catch (err) {
      toast(err.message, true);
    }
  });

  /* ---------- Ereignisse ---------- */

  app.addEventListener('click', (ev) => {
    const dot = ev.target.closest('.dot, .session-row');
    if (dot) { cycle(dot); return; }

    const th = ev.target.closest('.th-train');
    if (th) { S.view = 'session'; S.sessionId = +th.dataset.train; render(); return; }

    const edit = ev.target.closest('[data-edit]');
    if (edit) {
      openPerson(S.people.find((x) => x.id === +edit.dataset.edit));
      return;
    }

    const editT = ev.target.closest('[data-edit-train]');
    if (editT) {
      openTraining(S.trainings.find((x) => x.id === +editT.dataset.editTrain));
      return;
    }

    if (ev.target.closest('[data-back]')) { S.view = 'grid'; S.sessionId = null; render(); return; }
    if (ev.target.closest('#addTrainingCol')) { openTraining(null); return; }

    const sort = ev.target.closest('[data-sort]');
    if (sort) {
      const v = sort.dataset.sort;
      if (S.sort === v) S.dir = -S.dir; else { S.sort = v; S.dir = 1; }
      render();
    }
  });

  $('#btnNewPerson').addEventListener('click', () => openPerson(null));
  $('#btnNewTraining').addEventListener('click', () => openTraining(null));

  let qTimer = null;
  $('#q').addEventListener('input', (ev) => {
    S.q = ev.target.value;
    clearTimeout(qTimer);
    qTimer = setTimeout(render, 90);
  });

  function segment(id, apply) {
    const seg = $(id);
    seg.addEventListener('click', (ev) => {
      const b = ev.target.closest('button[data-v]');
      if (!b) return;
      [...seg.querySelectorAll('button')].forEach((x) => x.classList.toggle('on', x === b));
      apply(b.dataset.v);
      render();
    });
  }
  segment('#segActive', (v) => { S.filter = v; });
  segment('#segSort', (v) => { if (S.sort === v) S.dir = -S.dir; else { S.sort = v; S.dir = 1; } });

  document.querySelectorAll('dialog [data-close]').forEach((b) =>
    b.addEventListener('click', () => b.closest('dialog').close()));

  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape' && S.view === 'session' && !dlgP.open && !dlgT.open) {
      S.view = 'grid'; S.sessionId = null; render();
    }
  });

  render();
})();
