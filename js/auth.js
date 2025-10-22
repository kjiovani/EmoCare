// =====================
// Konfigurasi nama file
// =====================
const PAGES = {
  LOGIN: 'login.html',
  REGISTER: 'register.html',
  HOME: 'home.php',   // ganti kalau dashboard-mu bernama lain
};

// =====================
// Util & penyimpanan
// =====================
const AUTH_KEY = 'auth_user';
const USERS_KEY = 'ec_users';

const getUsers = () => {
  try { return JSON.parse(localStorage.getItem(USERS_KEY) || '[]'); }
  catch { return []; }
};
const saveUsers = (list) => localStorage.setItem(USERS_KEY, JSON.stringify(list));
const setAuthUser = (u) => localStorage.setItem(AUTH_KEY, JSON.stringify({ email: u.email, username: u.username }));
const getAuthUser = () => { try { return JSON.parse(localStorage.getItem(AUTH_KEY)); } catch { return null; } };
const clearAuth = () => localStorage.removeItem(AUTH_KEY);
const isLoggedIn = () => !!getAuthUser();

const redirectTo = (page, replace = false) => replace ? location.replace(page) : location.assign(page);
const pageName = () => (location.pathname.split('/').pop() || '').toLowerCase();

function isGmail(email) {
  return typeof email === 'string' && email.trim().toLowerCase().endsWith('@gmail.com');
}
function hasSymbol(s) { return /[^A-Za-z0-9]/.test(s); }

// =====================
// Form detector (tidak ubah UI)
// =====================
function detectLoginForm() {
  return (
    document.querySelector('form[data-auth="login"]') ||
    document.querySelector('form[action*="login" i]') ||
    document.querySelector('#loginForm') ||
    document.querySelector('form') // fallback: form pertama di halaman
  );
}
function detectRegisterForm() {
  return (
    document.querySelector('form[data-auth="register"]') ||
    document.querySelector('form[action*="register" i]') ||
    document.querySelector('#registerForm') ||
    (document.querySelectorAll('form')[1] || null) // fallback: form kedua
  );
}
function pickInput(form, selectorList) {
  for (const sel of selectorList) {
    const el = form.querySelector(sel);
    if (el) return el;
  }
  return null;
}

// =====================
// Guard halaman
// =====================
function routeGuard() {
  const p = pageName();
  if (isLoggedIn()) {
    if (p === PAGES.LOGIN || p === PAGES.REGISTER) redirectTo(PAGES.HOME, true);
  } else {
    if (p === PAGES.HOME) redirectTo(PAGES.LOGIN, true);
  }
}

// =====================
// Attach handler tanpa mengubah tampilan
// =====================
function attachLoginHandler() {
  const form = detectLoginForm();
  const p = pageName();
  if (!form || p !== PAGES.LOGIN) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault(); // client-side only; tidak kirim ke server

    const emailEl = pickInput(form, ['input[type="email"]', 'input[name*="email" i]', '#email']);
    const passEl = pickInput(form, ['input[type="password"]', 'input[name*="pass" i]', '#password']);

    const email = (emailEl?.value || '').trim();
    const password = (passEl?.value || '').trim();

    if (!isGmail(email)) return alert('Gunakan email @gmail.com');
    if (password.length < 8) return alert('Password minimal 8 karakter');

    const users = getUsers();
    const user = users.find(u => u.email.toLowerCase() === email.toLowerCase());
    if (!user) return alert('Email belum terdaftar. Silakan daftar.');

    if (user.password !== password) return alert('Password salah. Coba lagi.');

    setAuthUser(user);
    setTimeout(() => redirectTo(PAGES.HOME), 300);
  });
}

function attachRegisterHandler() {
  const form = detectRegisterForm();
  const p = pageName();
  if (!form || p !== PAGES.REGISTER) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault(); // client-side only

    const userEl = pickInput(form, ['input[name*="user" i]', 'input[name*="nama" i]', 'input#username', '#regUsername']);
    const emailEl = pickInput(form, ['input[type="email"]', 'input[name*="email" i]', '#regEmail']);
    const passEl = pickInput(form, ['input[type="password"]', 'input[name*="pass" i]', '#regPassword']);

    const username = (userEl?.value || '').trim();
    const email = (emailEl?.value || '').trim();
    const password = (passEl?.value || '').trim();

    if (username.length < 8) return alert('Username minimal 8 huruf');
    if (!isGmail(email)) return alert('Gunakan email @gmail.com');
    if (password.length < 8 || !hasSymbol(password)) return alert('Password ≥ 8 & mengandung simbol');

    const users = getUsers();
    if (users.some(u => u.email.toLowerCase() === email.toLowerCase())) {
      return alert('Email sudah terdaftar. Silakan login.');
    }

    const newUser = { username, email, password };
    users.push(newUser);
    saveUsers(users);

    setAuthUser(newUser);             // auto-login
    setTimeout(() => redirectTo(PAGES.HOME), 300);
  });
}

function attachLogoutHandler() {
  const btn = document.querySelector('#logoutBtn,[data-auth="logout"]');
  if (!btn) return;
  btn.addEventListener('click', () => {
    clearAuth();
    redirectTo(PAGES.LOGIN, true);
  });
}

// =====================
// Init
// =====================
document.addEventListener('DOMContentLoaded', () => {
  routeGuard();
  attachLoginHandler();
  attachRegisterHandler();
  attachLogoutHandler();
});