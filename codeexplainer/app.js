/* HoopFlow security explainer — rendering and interactions. */

const FIGURES = SECTIONS.filter((section) => section.num > 0);
const ALL_SECTIONS = [...FIGURES, ...FEATURES];
const CODE_STORE = new Map();
const STORAGE_KEY = 'hf-security-study-v1';
const NAV_STATE_KEY = 'hf-security-nav-collapsed';

const studied = loadStudied();
let quizAnswered = 0;
let quizCorrect = 0;

/* ---------- helpers ---------- */

function esc(value) {
  return String(value).replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  }[character]));
}

function searchText(section) {
  return [section.title, section.file, section.tag, section.tldr, section.group]
    .join(' ')
    .toLowerCase();
}

function loadStudied() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return new Set(Array.isArray(parsed) ? parsed : []);
  } catch {
    return new Set();
  }
}

function saveStudied() {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify([...studied]));
  } catch {
    /* private mode or file:// restrictions: progress just will not persist */
  }
}

/* ---------- syntax highlighting ---------- */

const TOKEN_RE = /(\/\/[^\n]*|#[^\n]*|\/\*[\s\S]*?\*\/)|('(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*")|(\{\{[\s\S]*?\}\}|@[a-zA-Z]+)|(\$[a-zA-Z_]\w*)|\b(abstract|and|array|as|bool|break|case|catch|class|const|continue|default|do|echo|else|elseif|empty|enum|extends|false|final|finally|fn|for|foreach|function|if|implements|in|instanceof|int|interface|isset|match|namespace|new|null|or|private|protected|public|readonly|return|static|string|switch|throw|trait|true|try|unset|use|var|void|while|yield)\b|\b(\d+(?:\.\d+)?)\b/g;

function highlight(code) {
  TOKEN_RE.lastIndex = 0;

  let out = '';
  let last = 0;
  let match;

  while ((match = TOKEN_RE.exec(code)) !== null) {
    out += esc(code.slice(last, match.index));

    let kind = 'num';
    if (match[1]) kind = 'comment';
    else if (match[2]) kind = 'string';
    else if (match[3]) kind = 'blade';
    else if (match[4]) kind = 'var';
    else if (match[5]) kind = 'kw';

    out += `<span class="tok-${kind}">${esc(match[0])}</span>`;
    last = match.index + match[0].length;
  }

  out += esc(code.slice(last));

  return out;
}

/* ---------- rendering ---------- */

function navLink(target, num, title, id = '', search = '') {
  const on = id !== '' && studied.has(id);

  return `<a class="nav-link${on ? ' is-studied' : ''}" href="#${target}" data-nav="${target}"${id ? ` data-id="${id}"` : ''}${search ? ` data-search="${esc(search)}"` : ''}>
    <span class="nav-num">${esc(num)}</span>
    <span class="nav-title">${esc(title)}</span>
    <span class="nav-check" aria-hidden="true">✓</span>
  </a>`;
}

function renderNav() {
  const groups = [];

  for (const section of FIGURES) {
    let group = groups.find((item) => item.name === section.group);

    if (!group) {
      group = { name: section.group, items: [] };
      groups.push(group);
    }

    group.items.push(section);
  }

  document.getElementById('nav').innerHTML = `
    <div class="nav-group">
      <p class="nav-group-label">Start here</p>
      ${navLink('pipeline-section', '00', 'The request pipeline')}
      ${navLink('attack-lab', '✳', 'Attack lab')}
    </div>
    ${groups.map((group) => `
      <div class="nav-group" data-group="${esc(group.name)}">
        <p class="nav-group-label">${esc(group.name)}</p>
        ${group.items.map((section) => navLink(
          `s-${section.id}`,
          String(section.num).padStart(2, '0'),
          section.title,
          section.id,
          searchText(section),
        )).join('')}
      </div>
    `).join('')}
    <div class="nav-group" data-group="Feature tour">
      <p class="nav-group-label">Feature tour</p>
      ${FEATURES.map((section) => navLink(
        `s-${section.id}`,
        section.label,
        section.title,
        section.id,
        searchText(section),
      )).join('')}
    </div>
    <div class="nav-group">
      <p class="nav-group-label">Practice</p>
      ${navLink('quiz-section', '?', 'Check yourself')}
      ${navLink('doc-section', '!', 'Document fixes')}
    </div>
  `;
}

function renderCode(block, sectionId, index) {
  const id = `code-${sectionId}-${index}`;
  const code = block.code.replace(/\n$/, '');
  const lines = code.split('\n');
  const collapsed = lines.length > 20;
  const place = block.label ? `${block.path} · ${block.label}` : block.path;
  const firstLine = Number.isInteger(block.startLine) ? block.startLine : 1;
  const lastLine = firstLine + lines.length - 1;

  CODE_STORE.set(id, block.code);

  return `
    <figure class="code-card">
      <figcaption class="code-head">
        <span class="code-dots" aria-hidden="true"><i></i><i></i><i></i></span>
        <span class="code-file" title="${esc(place)}">${esc(place)}</span>
        <span class="code-range">L${firstLine}–${lastLine}</span>
        <span class="code-lang">${esc(block.lang)}</span>
        <button class="copy-btn" type="button" data-copy="${id}">Copy</button>
      </figcaption>
      ${block.explain ? `<div class="code-explain"><span>What this snippet does</span><p>${esc(block.explain)}</p></div>` : ''}
      <div class="code-body${collapsed ? ' is-collapsed' : ''}" id="${id}-body">
        <pre class="gutter" aria-hidden="true">${lines.map((_, lineIndex) => firstLine + lineIndex).join('\n')}</pre>
        <pre class="code"><code>${highlight(code)}</code></pre>
      </div>
      ${collapsed ? `<button class="expand-btn" type="button" data-expand="${id}-body" data-lines="${lines.length}">Show all ${lines.length} lines</button>` : ''}
    </figure>
  `;
}

function sectionBody(section) {
  return `
    <ol class="steps">
      ${section.steps.map((step) => `<li>${esc(step)}</li>`).join('')}
    </ol>
    ${section.code.map((block, index) => renderCode(block, section.id, index)).join('')}
    <div class="why">
      <strong>Why it matters</strong>
      <p>${esc(section.why)}</p>
    </div>
    ${section.caveat ? `<p class="caveat">${esc(section.caveat)}</p>` : ''}
  `;
}

function renderSection(section) {
  const on = studied.has(section.id);
  const label = section.label ?? String(section.num).padStart(2, '0');

  return `
    <article class="section" id="s-${section.id}" data-spy data-search="${esc(searchText(section))}">
      <header class="section-head">
        <span class="section-num" aria-hidden="true">${label}</span>
        <div>
          <p class="eyebrow"><span class="dot"></span>${esc(section.tag)}</p>
          <h2>${esc(section.title)}</h2>
          <span class="section-file">${esc(section.file)}</span>
        </div>
        <button class="study-toggle" type="button" data-study="${section.id}" aria-pressed="${on}">
          ${on ? 'Studied ✓' : 'Mark studied'}
        </button>
      </header>
      <p class="tldr">${esc(section.tldr)}</p>
      ${sectionBody(section)}
    </article>
  `;
}

function renderSections() {
  document.getElementById('sections').innerHTML = FIGURES.map(renderSection).join('');
}

function renderFeatures() {
  document.getElementById('features').innerHTML = FEATURES.map(renderSection).join('');
}

function renderPipeline() {
  document.getElementById('pipeline').innerHTML = PIPELINE.map((node, index) => `
    <li class="pipe-node" data-node="${node.id}">
      <span class="pipe-dot" aria-hidden="true"></span>
      <div class="pipe-card">
        <span class="pipe-step">${String(index + 1).padStart(2, '0')}</span>
        <h3>${esc(node.label)}</h3>
        <p>${esc(node.detail)}</p>
      </div>
    </li>
  `).join('');
}

function renderPipelineIntro() {
  const section = SECTIONS.find((item) => item.id === 'pipeline');
  if (!section) return;

  const tldr = document.getElementById('pipelineTldr');
  const detail = document.getElementById('pipelineDetail');

  if (tldr) tldr.textContent = section.tldr;
  if (detail) detail.innerHTML = sectionBody(section);
}

function renderAttacks() {
  document.getElementById('attackButtons').innerHTML = ATTACKS.map((attack) => `
    <button class="attack-btn" type="button" data-attack="${attack.id}">
      ${esc(attack.label)}
      <span class="layer-count">${attack.nodes.length}</span>
    </button>
  `).join('');
}

function renderDocFixes() {
  document.getElementById('docFixes').innerHTML = DOC_FIXES
    .map((fix) => `<li>${esc(fix)}</li>`)
    .join('');
}

function renderQuiz() {
  quizAnswered = 0;
  quizCorrect = 0;

  document.getElementById('quizScore').textContent = `0 / ${QUIZ.length}`;
  document.getElementById('quiz').innerHTML = QUIZ.map((question, index) => `
    <div class="quiz-card" data-q="${index}">
      <p class="quiz-q">
        <span class="quiz-num">${String(index + 1).padStart(2, '0')}</span>
        ${esc(question.q)}
      </p>
      <div class="quiz-options">
        ${question.options.map((option, optionIndex) => `
          <button class="quiz-opt" type="button" data-opt="${optionIndex}">${esc(option)}</button>
        `).join('')}
      </div>
      <p class="quiz-why" hidden></p>
    </div>
  `).join('') + '<div class="quiz-result" id="quizResult" hidden></div>';
}

function updateStats() {
  const setText = (id, value) => {
    const element = document.getElementById(id);
    if (element) element.textContent = String(value);
  };

  setText('statChanges', FIGURES.length);
  setText('statStops', PIPELINE.length);
  setText('statFeatures', FEATURES.length);
  setText('statQuiz', QUIZ.length);
}

function updateProgress() {
  const total = ALL_SECTIONS.length;
  const done = ALL_SECTIONS.filter((section) => studied.has(section.id)).length;
  const text = document.getElementById('progressText');
  const fill = document.getElementById('progressFill');

  if (text) text.textContent = `${done} / ${total}`;
  if (fill) fill.style.width = `${total === 0 ? 0 : (done / total) * 100}%`;
}

/* ---------- interactions ---------- */

function selectAttack(id) {
  const attack = ATTACKS.find((item) => item.id === id);
  if (!attack) return;

  document.querySelectorAll('.pipe-node').forEach((node) => {
    const hot = attack.nodes.includes(node.dataset.node);
    node.classList.toggle('is-hot', hot);
    node.classList.toggle('is-dim', !hot);
  });

  document.querySelectorAll('.attack-btn').forEach((button) => {
    button.classList.toggle('is-active', button.dataset.attack === id);
  });

  const story = document.getElementById('attackStory');
  story.innerHTML = `
    <p class="attack-kicker">Attack</p>
    <h3>${esc(attack.label)}</h3>
    <p>${esc(attack.story)}</p>
  `;
}

function resetAttack() {
  document.querySelectorAll('.pipe-node').forEach((node) => {
    node.classList.remove('is-hot', 'is-dim');
  });

  document.querySelectorAll('.attack-btn').forEach((button) => {
    button.classList.remove('is-active');
  });

  document.getElementById('attackStory').innerHTML =
    '<p class="attack-placeholder">Choose a scenario above to light up the pipeline.</p>';
}

async function copyText(text) {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();

    let copied = false;
    try {
      copied = document.execCommand('copy');
    } catch {
      copied = false;
    }

    textarea.remove();
    return copied;
  }
}

function showQuizResult() {
  const result = document.getElementById('quizResult');
  if (!result) return;

  const percent = Math.round((quizCorrect / QUIZ.length) * 100);
  let message = 'Go back to the pipeline and the session guards, then run it again.';

  if (percent === 100) message = 'Clean sweep. You can defend the whole document.';
  else if (percent >= 70) message = 'Solid. Re-read the caveats on the ones you missed.';

  result.hidden = false;
  result.innerHTML = `
    <strong>${quizCorrect} / ${QUIZ.length} correct (${percent}%)</strong>
    <p>${message}</p>
    <button class="btn btn-sm" type="button" data-quiz-restart>Try again</button>
  `;
}

/* ---------- init ---------- */

function initSearch() {
  const input = document.getElementById('search');
  const count = document.getElementById('searchCount');
  const links = [...document.querySelectorAll('.nav-link[data-search]')];
  const sections = [...document.querySelectorAll('.section[data-search]')];

  input.addEventListener('input', () => {
    const query = input.value.trim().toLowerCase();
    let matches = 0;

    links.forEach((link) => {
      const hit = query === '' || link.dataset.search.includes(query);
      link.classList.toggle('is-hidden', !hit);
      if (hit) matches += 1;
    });

    sections.forEach((section) => {
      const hit = query === '' || section.dataset.search.includes(query);
      section.classList.toggle('is-hidden', !hit);
    });

    document.querySelectorAll('.nav-group').forEach((group) => {
      const figureLinks = group.querySelectorAll('.nav-link[data-search]');
      if (figureLinks.length === 0) return;

      const anyVisible = [...figureLinks].some((link) => !link.classList.contains('is-hidden'));
      group.classList.toggle('is-hidden', !anyVisible);
    });

    count.textContent = query === '' ? '' : `${matches} match${matches === 1 ? '' : 'es'}`;
  });
}

function openMenu() {
  document.getElementById('sidebar').classList.add('is-open');
  document.getElementById('scrim').hidden = false;
  document.getElementById('menuBtn').setAttribute('aria-expanded', 'true');
  document.body.style.overflow = 'hidden';
}

function closeMenu() {
  document.getElementById('sidebar').classList.remove('is-open');
  document.getElementById('scrim').hidden = true;
  document.getElementById('menuBtn').setAttribute('aria-expanded', 'false');
  document.body.style.overflow = '';
}

function setNavCollapsed(collapsed) {
  const shell = document.querySelector('.shell');
  const toggle = document.getElementById('navToggle');
  const label = collapsed ? 'Show navigation' : 'Hide navigation';

  if (!shell || !toggle) return;

  shell.classList.toggle('is-nav-collapsed', collapsed);
  toggle.setAttribute('aria-expanded', String(!collapsed));
  toggle.setAttribute('aria-label', label);
  toggle.title = label;

  try {
    localStorage.setItem(NAV_STATE_KEY, collapsed ? '1' : '0');
  } catch {
    /* private mode: the choice just will not persist */
  }
}

function initNavToggle() {
  const shell = document.querySelector('.shell');
  const toggle = document.getElementById('navToggle');

  if (!shell || !toggle) return;

  let collapsed = false;

  try {
    collapsed = localStorage.getItem(NAV_STATE_KEY) === '1';
  } catch {
    collapsed = false;
  }

  setNavCollapsed(collapsed);

  toggle.addEventListener('click', () => {
    setNavCollapsed(!shell.classList.contains('is-nav-collapsed'));
  });
}

function initMenu() {
  document.getElementById('menuBtn').addEventListener('click', openMenu);
  document.getElementById('drawerClose').addEventListener('click', closeMenu);
  document.getElementById('scrim').addEventListener('click', closeMenu);

  document.getElementById('nav').addEventListener('click', (event) => {
    if (event.target.closest('.nav-link') && window.innerWidth <= 1080) closeMenu();
  });
}

function initScrollSpy() {
  if (!('IntersectionObserver' in window)) return;

  const links = [...document.querySelectorAll('.nav-link[data-nav]')];
  const targets = [...document.querySelectorAll('[data-spy]')];
  const pipeline = document.getElementById('pipeline-section');

  const setActive = (id) => {
    links.forEach((link) => {
      link.classList.toggle('is-active', link.dataset.nav === id);
    });
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) setActive(entry.target.id);
    });
  }, { rootMargin: '-15% 0px -70% 0px', threshold: 0 });

  targets.forEach((target) => observer.observe(target));

  setActive('pipeline-section');

  window.addEventListener('scroll', () => {
    if (pipeline && window.scrollY < pipeline.offsetTop - 140) {
      setActive('pipeline-section');
    }
  }, { passive: true });
}

function initKeyboard() {
  document.addEventListener('keydown', (event) => {
    const typing = event.target instanceof HTMLElement
      && (event.target.tagName === 'INPUT'
        || event.target.tagName === 'TEXTAREA'
        || event.target.isContentEditable);

    if (event.key === '/' && !typing) {
      event.preventDefault();
      document.getElementById('search').focus();
      return;
    }

    if (event.key === 'Escape') {
      const search = document.getElementById('search');

      if (document.activeElement === search && search.value !== '') {
        search.value = '';
        search.dispatchEvent(new Event('input'));
        return;
      }

      closeMenu();
    }
  });
}

function initDelegates() {
  document.getElementById('attackReset').addEventListener('click', resetAttack);

  document.addEventListener('click', async (event) => {
    const copyButton = event.target.closest('[data-copy]');
    if (copyButton) {
      const text = CODE_STORE.get(copyButton.dataset.copy);
      if (!text) return;

      const copied = await copyText(text);
      copyButton.textContent = copied ? 'Copied' : 'Failed';
      copyButton.classList.toggle('is-copied', copied);

      window.setTimeout(() => {
        copyButton.textContent = 'Copy';
        copyButton.classList.remove('is-copied');
      }, 1200);

      return;
    }

    const expandButton = event.target.closest('[data-expand]');
    if (expandButton) {
      const body = document.getElementById(expandButton.dataset.expand);
      if (!body) return;

      const collapsed = body.classList.toggle('is-collapsed');
      expandButton.textContent = collapsed
        ? `Show all ${expandButton.dataset.lines} lines`
        : 'Collapse';

      return;
    }

    const studyButton = event.target.closest('[data-study]');
    if (studyButton) {
      const id = studyButton.dataset.study;

      if (studied.has(id)) studied.delete(id);
      else studied.add(id);

      saveStudied();

      const on = studied.has(id);
      studyButton.setAttribute('aria-pressed', String(on));
      studyButton.textContent = on ? 'Studied ✓' : 'Mark studied';

      document.querySelectorAll(`.nav-link[data-id="${id}"]`).forEach((link) => {
        link.classList.toggle('is-studied', on);
      });

      updateProgress();
      return;
    }

    const attackButton = event.target.closest('[data-attack]');
    if (attackButton) {
      selectAttack(attackButton.dataset.attack);
      return;
    }

    const quizOption = event.target.closest('.quiz-opt');
    if (quizOption) {
      const card = quizOption.closest('.quiz-card');
      if (!card || card.dataset.done === '1') return;

      const question = QUIZ[Number(card.dataset.q)];
      const chosen = Number(quizOption.dataset.opt);

      card.dataset.done = '1';

      card.querySelectorAll('.quiz-opt').forEach((button, index) => {
        button.disabled = true;
        if (index === question.answer) button.classList.add('is-correct');
        if (index === chosen && chosen !== question.answer) button.classList.add('is-wrong');
      });

      const why = card.querySelector('.quiz-why');
      why.textContent = question.why;
      why.hidden = false;

      quizAnswered += 1;
      if (chosen === question.answer) quizCorrect += 1;

      document.getElementById('quizScore').textContent = `${quizCorrect} / ${QUIZ.length}`;

      if (quizAnswered === QUIZ.length) showQuizResult();
      return;
    }

    if (event.target.closest('[data-quiz-restart]')) {
      renderQuiz();
    }
  });
}

/* ---------- boot ---------- */

renderNav();
renderSections();
renderFeatures();
renderPipeline();
renderPipelineIntro();
renderAttacks();
renderQuiz();
renderDocFixes();
updateStats();
updateProgress();
initSearch();
initNavToggle();
initMenu();
initScrollSpy();
initKeyboard();
initDelegates();
