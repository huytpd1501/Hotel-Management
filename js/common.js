// hotel-frontend/js/common.js  (ES5-safe)

// Thiết lập API base: có thể override bằng window.API_BASE trên từng trang
var API_BASE = (window.API_BASE) || (location.origin + '/hotel/hotel-api-php/src/controllers');

// Helper fetch JSON luôn kèm cookie session
function api(path, opt) {
  opt = opt || {};
  var url = /^https?:\/\//i.test(path) ? path : (API_BASE + '/' + path);
  var hasBody = typeof opt.body !== 'undefined' && opt.body !== null;
  var body = (typeof opt.body === 'string') ? opt.body : (hasBody ? JSON.stringify(opt.body) : undefined);

  var init = {
    method: opt.method || (hasBody ? 'POST' : 'GET'),
    headers: Object.assign({ 'Content-Type': 'application/json' }, (opt.headers || {})),
    credentials: 'include'
  };
  if (typeof body !== 'undefined') init.body = body;

  return fetch(url, init).then(function(res){
    var ct = res.headers.get('content-type') || '';
    var parse = ct.indexOf('application/json') !== -1 ? res.json() : res.text();
    return parse.then(function(data){
      if (!res.ok) {
        var msg = (typeof data === 'string') ? data : (data && (data.message || data.error)) || 'Request error';
        throw new Error(msg);
      }
      return data;
    });
  });
}

// Auth endpoints (dùng đúng AuthController.php của bạn)
var Auth = {
  me: function(){ return api('AuthController.php?action=me'); },
  login: function(username, password){
    return api('AuthController.php?action=login', { method:'POST', body: { username: username, password: password } });
  },
  logout: function(){ return api('AuthController.php?action=logout', { method:'POST' }); }
};

// Format tiền
function fmtMoney(v){
  try { return Number(v || 0).toLocaleString('vi-VN') + '₫'; } catch(e){ return v; }
}

// Render “Admin | Đăng xuất”
function renderHeaderUser(user){
  var slot = document.querySelector('#header-user');
  if(!slot) return;
  var name = (user && (user.full_name || user.username)) ? (user.full_name || user.username) : 'Admin';

  // Dùng string thường thay vì template literal
  slot.innerHTML =
    '<span class="text-sm text-gray-700"><b>' + name + '</b></span>' +
    '<span class="mx-2 text-gray-400">|</span>' +
    '<button id="btnLogout" class="text-sm text-red-600 hover:underline">Đăng xuất</button>';

  var btn = document.getElementById('btnLogout');
  if (btn) btn.onclick = function(){
    Auth.logout().catch(function(){}).finally(function(){
      try { localStorage.removeItem('auth_user'); } catch(e){}
      location.href = 'login.html';
    });
  };
}

// Chặn chưa login → đẩy về login.html
function requireAuth(){
  return Auth.me().then(function(user){
    if (!user) throw new Error('not logged in');
    try { localStorage.setItem('auth_user', JSON.stringify(user)); } catch(e){}
    renderHeaderUser(user);
    return user;
  }).catch(function(err){
    var here = (location.pathname.split('/').pop() || 'index.html');
    if (here !== 'login.html') {
      location.href = 'login.html?next=' + encodeURIComponent(here);
    }
    throw err;
  });
}

// Nếu đã có user cache thì hiện header ngay (đỡ giật)
document.addEventListener('DOMContentLoaded', function(){
  try {
    var raw = localStorage.getItem('auth_user');
    if (raw) renderHeaderUser(JSON.parse(raw));
  } catch(e){}
});
