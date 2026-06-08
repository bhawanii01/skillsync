/* =====================================================
   SkillSync – script.js  |  Client-Side Logic
   ===================================================== */

/* ---- Utility ---- */
const $ = id => document.getElementById(id);
const show = id => { const el = $(id); if(el) el.style.display = 'block'; };
const hide = id => { const el = $(id); if(el) el.style.display = 'none'; };

/* ---- Page Load Animations ---- */
document.addEventListener('DOMContentLoaded', () => {
  animateProgressBars();
  animateScoreRing();
  highlightActiveNav();
});

function animateProgressBars() {
  document.querySelectorAll('.progress-bar-fill').forEach(bar => {
    const target = bar.getAttribute('data-pct') || 0;
    bar.style.width = '0%';
    setTimeout(() => { bar.style.width = target + '%'; }, 200);
  });
}

function animateScoreRing() {
  const ring = document.querySelector('.score-ring');
  if (!ring) return;
  const score = parseInt(ring.getAttribute('data-score') || 0);
  ring.style.setProperty('--pct', 0);
  let current = 0;
  const timer = setInterval(() => {
    current += 2;
    ring.style.setProperty('--pct', current);
    if (current >= score) clearInterval(timer);
  }, 20);
}

function highlightActiveNav() {
  const path = window.location.pathname.split('/').pop();
  document.querySelectorAll('.sidebar-nav a').forEach(link => {
    if (link.getAttribute('href') === path) link.classList.add('active');
  });
}

/* ---- Skill Gap Analysis Engine ---- */
const SKILL_MATRIX = {
  "Web Developer": {
    core:    ["HTML", "CSS", "JavaScript", "React.js"],
    backend: ["PHP", "MySQL"],
    tools:   ["Git", "REST API"]
  },
  "Software Engineer": {
    core:    ["Data Structures", "Algorithms", "C++", "Java"],
    systems: ["DBMS", "Computer Networks"],
    tools:   ["OOP", "Git"]
  },
  "Data Analyst": {
    core:    ["Python", "SQL", "Excel", "Statistics"],
    tools:   ["Power BI", "Pandas"],
    soft:    ["Communication"]
  },
  "Full Stack Developer": {
    core:    ["React.js", "Node.js"],
    backend: ["MySQL", "MongoDB"],
    tools:   ["Git", "REST API"]
  },
  "Cloud Engineer": {
    core:    ["AWS", "Linux", "Docker"],
    systems: ["Computer Networks"],
    tools:   ["Git", "OOP"]
  },
  "UI/UX Designer": {
    core:    ["Figma"],
    tools:   ["CSS", "HTML"],
    soft:    ["Communication", "Problem Solving"]
  }
};

const WEIGHTS = { core: 0.50, backend: 0.25, tools: 0.15, systems: 0.10, soft: 0.10,
                  concepts: 0.15, devops: 0.15 };

/**
 * Compute career readiness score (0–100) given selected skills and target role.
 * Rule-based: category-weighted intersection.
 */
function computeReadinessScore(userSkills, role) {
  const matrix = SKILL_MATRIX[role];
  if (!matrix) return 0;
  const roleSkills = Object.values(matrix).flat();
  if (roleSkills.length === 0) return 0;
  const hasSkills = roleSkills.filter(s => userSkills.includes(s));
  return Math.round((hasSkills.length / roleSkills.length) * 100);
}

/**
 * Returns missing skills for a role given user's current skills.
 */
function getSkillGaps(userSkills, role) {
  const matrix = SKILL_MATRIX[role];
  if (!matrix) return [];
  const missing = [];
  Object.values(matrix).flat().forEach(skill => {
    if (!userSkills.includes(skill)) missing.push(skill);
  });
  return [...new Set(missing)];
}

/* ---- Resume Strength Checker (Rule-Based) ---- */
const RESUME_RULES = [
  { key: 'email',        pattern: /[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i, weight: 10, label: 'Valid email address found' },
  { key: 'phone',        pattern: /\b\d{10}\b|\+91[-\s]?\d{10}/,            weight: 8,  label: 'Phone number present' },
  { key: 'linkedin',     pattern: /linkedin\.com\/in\//i,                    weight: 8,  label: 'LinkedIn profile linked' },
  { key: 'github',       pattern: /github\.com\//i,                          weight: 7,  label: 'GitHub profile linked' },
  { key: 'objective',    pattern: /objective|summary|profile/i,              weight: 8,  label: 'Objective/Summary section present' },
  { key: 'education',    pattern: /b\.?tech|btech|bachelor|b\.?e\.|degree/i, weight: 10, label: 'Education details found' },
  { key: 'skills',       pattern: /skills|technologies|technical/i,          weight: 10, label: 'Skills section present' },
  { key: 'projects',     pattern: /project|built|developed|implemented/i,    weight: 12, label: 'Project experience found' },
  { key: 'internship',   pattern: /internship|intern|training|experience/i,  weight: 10, label: 'Internship/Experience mentioned' },
  { key: 'achievements', pattern: /achievement|award|honor|certificate/i,    weight: 7,  label: 'Achievements section present' },
  { key: 'keywords',     pattern: /python|java|c\+\+|javascript|sql|ml/i,    weight: 10, label: 'Technical keywords detected' }
];

function checkResumeStrength(resumeText) {
  let score = 0, passed = [], failed = [];
  RESUME_RULES.forEach(rule => {
    if (rule.pattern.test(resumeText)) {
      score += rule.weight;
      passed.push(rule.label);
    } else {
      failed.push(rule.label.replace(' present','').replace(' found','').replace(' mentioned','') + ' — missing');
    }
  });
  const maxScore = RESUME_RULES.reduce((a, r) => a + r.weight, 0);
  return { score: Math.round((score / maxScore) * 100), passed, failed };
}

/* ---- Career Roadmap Generator ---- */
const ROADMAPS = {
  "Web Developer": [
    { phase: "Foundation (Month 1-2)",   skills: ["HTML5", "CSS3", "Responsive Design"], status: "start" },
    { phase: "JavaScript Core (Month 3)", skills: ["JS ES6+", "DOM Manipulation", "Fetch API"], status: "start" },
    { phase: "Frontend Framework (Month 4-5)", skills: ["React.js", "Component Design", "State Mgmt"], status: "lock" },
    { phase: "Backend Basics (Month 6)",  skills: ["Node.js", "Express", "REST APIs"], status: "lock" },
    { phase: "Database (Month 7)",        skills: ["MySQL", "MongoDB Basics", "ORM"], status: "lock" },
    { phase: "Deployment & Portfolio (Month 8)", skills: ["Git/GitHub", "Netlify/Vercel", "Portfolio Site"], status: "lock" }
  ],
  "Data Analyst": [
    { phase: "Statistics & Excel (Month 1-2)",  skills: ["Descriptive Stats", "Pivot Tables", "Excel Charts"], status: "start" },
    { phase: "Python for Data (Month 3-4)",     skills: ["NumPy", "Pandas", "Matplotlib"], status: "start" },
    { phase: "SQL & Databases (Month 5)",       skills: ["SQL Queries", "Joins", "Aggregations"], status: "lock" },
    { phase: "Visualization (Month 6)",         skills: ["Power BI", "Tableau Basics", "Seaborn"], status: "lock" },
    { phase: "Projects & Portfolio (Month 7-8)",skills: ["Kaggle Datasets", "Case Studies", "Dashboard"], status: "lock" }
  ]
};

function generateRoadmap(role, userSkills) {
  const steps = ROADMAPS[role] || ROADMAPS["Web Developer"];
  return steps.map(step => {
    const acquired = step.skills.filter(s => userSkills.some(us => us.toLowerCase().includes(s.toLowerCase().split(' ')[0]))).length;
    const status = acquired === step.skills.length ? 'done' : (acquired > 0 ? 'active' : step.status);
    return { ...step, status, acquired };
  });
}

/* ---- Aptitude Timer ---- */
let quizTimer = null, quizSeconds = 0;

function startQuizTimer(elementId, totalSec) {
  let remaining = totalSec;
  const el = $(elementId);
  if (!el) return;
  quizTimer = setInterval(() => {
    remaining--;
    const m = Math.floor(remaining / 60).toString().padStart(2,'0');
    const s = (remaining % 60).toString().padStart(2,'0');
    el.textContent = m + ':' + s;
    if (remaining <= 60) el.style.color = '#ef4444';
    if (remaining <= 0) { clearInterval(quizTimer); autoSubmitQuiz(); }
  }, 1000);
}

function autoSubmitQuiz() {
  const form = document.querySelector('#quiz-form');
  if (form) form.submit();
}

/* ---- Skill Selection UI ---- */
function toggleSkill(el, skill) {
  el.classList.toggle('selected');
  const isSelected = el.classList.contains('selected');
  if (isSelected) {
    el.style.background = '#4f46e5';
    el.style.color = '#fff';
    el.style.borderColor = '#4f46e5';
  } else {
    el.style.background = '';
    el.style.color = '';
    el.style.borderColor = '';
  }
  updateSelectedSkillsInput();
}

function updateSelectedSkillsInput() {
  const selected = Array.from(document.querySelectorAll('.skill-btn.selected'))
                        .map(b => b.getAttribute('data-skill'));
  const input = $('selected_skills_input');
  if (input) input.value = JSON.stringify(selected);
}

/* ---- Live Score Preview ---- */
function liveScorePreview() {
  const roleEl = $('target_role');
  if (!roleEl) return;
  roleEl.addEventListener('change', updateLiveScore);
  document.querySelectorAll('.skill-btn').forEach(btn => {
    btn.addEventListener('click', updateLiveScore);
  });
}

function updateLiveScore() {
  const role = $('target_role')?.value;
  if (!role) return;
  const selected = Array.from(document.querySelectorAll('.skill-btn.selected'))
                        .map(b => b.getAttribute('data-skill'));
  const score = computeReadinessScore(selected, role);
  const scoreEl = $('live_score');
  if (scoreEl) {
    scoreEl.textContent = score;
    scoreEl.style.color = score >= 70 ? '#10b981' : score >= 40 ? '#f59e0b' : '#ef4444';
  }
}

/* ---- File Upload Preview ---- */
function initFileUpload(inputId, zoneId) {
  const input = $(inputId), zone = $(zoneId);
  if (!input || !zone) return;
  zone.addEventListener('click', () => input.click());
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor = '#4f46e5'; });
  zone.addEventListener('dragleave', () => zone.style.borderColor = '');
  zone.addEventListener('drop', e => {
    e.preventDefault();
    input.files = e.dataTransfer.files;
    showFileName(e.dataTransfer.files[0], zoneId);
  });
  input.addEventListener('change', () => showFileName(input.files[0], zoneId));
}

function showFileName(file, zoneId) {
  const zone = $(zoneId);
  if (!file || !zone) return;
  zone.querySelector('.upload-text').textContent = '✅ ' + file.name + ' (' + (file.size/1024).toFixed(1) + ' KB)';
  zone.style.borderColor = '#10b981';
  zone.style.background = '#f0fdf4';
}

/* ---- Toast Notifications ---- */
function showToast(msg, type = 'success') {
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.textContent = msg;
  t.style.cssText = `position:fixed;bottom:24px;right:24px;padding:12px 20px;border-radius:8px;
    color:#fff;font-size:14px;z-index:9999;
    background:${type==='success'?'#10b981':type==='error'?'#ef4444':'#f59e0b'};
    box-shadow:0 4px 16px rgba(0,0,0,.15);animation:slideIn .3s ease`;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

/* ---- Admin: Confirm Delete ---- */
function confirmDelete(action, id) {
  if (confirm('Are you sure you want to delete this record?')) {
    window.location.href = action + '?id=' + id + '&confirm=1';
  }
}

/* ---- Form Validation ---- */
function validateRegistrationForm() {
  const name     = $('reg_name')?.value.trim();
  const email    = $('reg_email')?.value.trim();
  const password = $('reg_password')?.value;
  const confirm  = $('reg_confirm')?.value;
  if (!name || name.length < 2) { showToast('Please enter your full name.', 'error'); return false; }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showToast('Invalid email address.', 'error'); return false; }
  if (password.length < 6) { showToast('Password must be at least 6 characters.', 'error'); return false; }
  if (password !== confirm) { showToast('Passwords do not match.', 'error'); return false; }
  return true;
}

/* ---- Init calls on specific pages ---- */
liveScorePreview();
initFileUpload('resume_file', 'upload_zone');