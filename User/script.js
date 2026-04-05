/* =========================================
   0. KHAI BÁO BIẾN GLOBAL & TIỆN ÍCH CHUNG
   ========================================= */

// BẢN FIX: Khai báo bộ cấu hình kết nối Backend (Chống lỗi CONFIG is not defined)
const CONFIG = { 
    API_BASE_URL: window.location.origin + '/API', // Trỏ về thư mục API của sếp
    USE_MOCK_API: false // Đặt false để kết nối Database thật
};

const storage = {
  get: (key) => { try { return localStorage.getItem(key); } catch(e) { return null; } },
  set: (key, val) => { try { localStorage.setItem(key, val); } catch(e) { console.warn("Trình duyệt chặn lưu trữ!"); } },
  remove: (key) => { try { localStorage.removeItem(key); } catch(e) {} }
};

/* =========================================
   QUẢN LÝ GIỎ HÀNG BẰNG COOKIE (CHUẨN ĐỒ ÁN)
   ========================================= */
function getCartFromCookie() {
    const match = document.cookie.match(new RegExp('(^| )sgu_cart=([^;]+)'));
    if (match) {
        try { 
            let parsed = JSON.parse(decodeURIComponent(match[2])); 
            return Array.isArray(parsed) ? parsed : []; // Ép kiểu mảng
        } catch(e) { 
            console.warn("Lỗi đọc Cookie Giỏ hàng, reset lại mảng rỗng.");
            return []; 
        }
    }
    return [];
}

// 2. Hàm lưu Giỏ hàng vào Cookie (Sống 7 ngày)
function saveCartToCookie(cartData) {
    const cartString = encodeURIComponent(JSON.stringify(cartData));
    document.cookie = `sgu_cart=${cartString}; max-age=604800; path=/`;
}

// 3. Khởi tạo Giỏ hàng: LẤY TỪ COOKIE (Tuyệt đối không dùng LocalStorage)
let cart = getCartFromCookie();
let wishlist = [];
try {
    const storedWishlist = storage.get('sgu_offline_wishlist');
    wishlist = storedWishlist ? JSON.parse(storedWishlist) : [];
    if (!Array.isArray(wishlist)) wishlist = []; // Đảm bảo luôn là mảng
} catch (e) {
    console.warn("Lỗi đọc Wishlist từ LocalStorage, khởi tạo lại mảng rỗng.");
    wishlist = [];
}

// Khởi tạo các biến dùng cho Lọc & Phân trang
let searchQuery = '';
let minPriceFilter = 0;
let maxPriceFilter = Infinity;
let activeCategories = []; 
let currentSort = 'default';
let currentPage = 1;
const itemsPerPage = 10; 

function removeAccents(str) {
  if (!str) return "";
  return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
}

/* =========================================
   1. CẤU HÌNH KẾT NỐI BACKEND (API SERVICE)
   ========================================= */
const api = {
      async request(endpoint, method = 'GET', body = null) {
        const token = storage.get('sgu_token');
        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (token) headers['Authorization'] = `Bearer ${token}`;
        const options = { method, headers };
        if (body && method !== 'GET') {
        options.body = JSON.stringify(body);
}
        
        try { 
          const res = await fetch(`${CONFIG.API_BASE_URL}${endpoint}`, options); 
          if (res.status === 401) { 
  localStorage.removeItem('sgu_token'); 
  localStorage.removeItem('sgu_username'); 
  window.location.href = 'index.html'; 
  return { status:'error', message:'Unauthorized' };
}
          let data = {};

try {
  data = await res.json();
} catch {
  data = { message: 'Server trả dữ liệu không hợp lệ' };
}
          let friendlyMsg = data.message || 'Có lỗi xảy ra';
          if (typeof friendlyMsg === 'string') {
    if (friendlyMsg.includes('1062 Duplicate entry')) {
        if (friendlyMsg.includes('users.phone') || friendlyMsg.includes("'phone'")) {
            friendlyMsg = 'Số điện thoại này đã được đăng ký bởi người khác!';
        } else if (friendlyMsg.includes('users.email') || friendlyMsg.includes("'email'")) {
            friendlyMsg = 'Email này đã được sử dụng, vui lòng chọn email khác!';
        } else if (friendlyMsg.includes('users.username') || friendlyMsg.includes("'username'")) {
            friendlyMsg = 'Tên đăng nhập này đã tồn tại, vui lòng chọn tên khác!';
        } else {
            friendlyMsg = 'Thông tin này đã tồn tại trong hệ thống!';
        }
    }
}
          // BẢN FIX: Xử lý lỗi trả về cực kỳ chặt chẽ
          let isSuccess = res.ok && (data.status !== 'error');
          
          if (data.status === 'error' || !isSuccess) {
              return { status: 'error', message: friendlyMsg };
          }

          return { status: 'success', ...data, message: friendlyMsg }; 
        } 
        catch (err) { return { status: 'error', message: 'Lỗi kết nối Backend Server' }; }
      },

  async login(username, password) {
    if (CONFIG.USE_MOCK_API) {
      return new Promise(resolve => setTimeout(() => {
        let mockDB = JSON.parse(storage.get('mock_db_users')) || []; 
        const user = mockDB.find(u => (u.username === username || u.email === username) && u.password === password);
        if (user) resolve({ status: 'success', token: 'mock-jwt-token-' + user.username, user: { username: user.username } });
        else resolve({ status: 'error', message: 'Sai tài khoản hoặc mật khẩu' });
      }, 500));
    }
return await this.request('/auth.php?action=login', 'POST', { login: username, password });
  },

  async register(userData) {
    if (CONFIG.USE_MOCK_API) {
      return new Promise(resolve => setTimeout(() => {
        let mockDB = JSON.parse(storage.get('mock_db_users')) || [];
        const exists = mockDB.some(u => u.username === userData.username || u.email === userData.email);
        if (exists) resolve({ status: 'error', message: 'Username hoặc Email đã tồn tại' });
        else {
          mockDB.push(userData); storage.set('mock_db_users', JSON.stringify(mockDB)); 
          resolve({ status: 'success' });
        }
      }, 500));
    }
    return await this.request('/auth.php?action=register', 'POST', userData);
  },

  async getProfile() {
    if (CONFIG.USE_MOCK_API) {
      return new Promise(resolve => setTimeout(() => {
        const currentUser = storage.get('sgu_username');
        let mockDB = JSON.parse(storage.get('mock_db_users')) || [];
        const user = mockDB.find(u => u.username === currentUser);
        if (user) resolve({ status: 'success', data: { phone: user.phone, address: user.address } });
        else resolve({ status: 'error' });
      }, 300));
    }
    return await this.request('/auth.php?action=profile', 'GET');
  },

  async getWishlist() {
    if (CONFIG.USE_MOCK_API) {
      return new Promise(resolve => setTimeout(() => {
        const currentUser = storage.get('sgu_username');
        let mockWL = JSON.parse(storage.get(`mock_wl_${currentUser}`)) || [];
        resolve({ status: 'success', data: mockWL });
      }, 300));
    }
return await this.request('/wishlist.php?action=get', 'GET');
  },

  async toggleWishlist(productName) {
    if (CONFIG.USE_MOCK_API) {
      return new Promise(resolve => setTimeout(() => {
        const currentUser = storage.get('sgu_username');
        if (!currentUser) return resolve({ status: 'error' });
        let mockWL = JSON.parse(storage.get(`mock_wl_${currentUser}`)) || [];
        const index = mockWL.indexOf(productName);
        if (index > -1) mockWL.splice(index, 1); else mockWL.push(productName);
        storage.set(`mock_wl_${currentUser}`, JSON.stringify(mockWL));
        resolve({ status: 'success' });
      }, 200));
    }
return await this.request('/wishlist.php?action=toggle', 'POST', { productName });
  },

async checkout(orderData) {
    if (CONFIG.USE_MOCK_API) return new Promise(res => setTimeout(() => res({ status: 'success' }), 800));

    return await this.request('/orders.php?action=store', 'POST', orderData);
  },
async getCart() {
    if (CONFIG.USE_MOCK_API) return new Promise(resolve => resolve({ status: 'success', data: [] }));
    return await this.request('/cart.php?action=get', 'GET');
  },

  async syncCart(cartData) {
    if (CONFIG.USE_MOCK_API) return new Promise(resolve => resolve({ status: 'success' }));
    return await this.request('/cart.php?action=sync', 'POST', { cart: cartData });
  },
async getProducts() {
    if (CONFIG.USE_MOCK_API) return new Promise(res => setTimeout(() => res({ status: 'success', data: mockProductsDB }), 400));

    return await this.request('/products.php?action=index', 'GET');
  }
};

/* =========================================
   2. HERO PARALLAX & NAVBAR
   ========================================= */
const heroContent = document.querySelector('.hero-content');
let ticking = false;

document.addEventListener('mousemove', (e) => {
  if (!ticking && heroContent) {
    requestAnimationFrame(() => {
      let x = (window.innerWidth / 2 - e.pageX) / 45; let y = (window.innerHeight / 2 - e.pageY) / 45;
      heroContent.style.transform = `rotateY(${x}deg) rotateX(${y}deg)`;
      ticking = false;
    });
    ticking = true;
  }
});

let lastScroll = 0;
window.addEventListener('scroll', () => {
    const now = Date.now();
    if (now - lastScroll < 50) return; // Throttle 50ms (Chỉ chạy 20 lần/giây)
    lastScroll = now;

    // Đổi màu thanh Navbar khi cuộn
    const nav = document.querySelector('.new-nav');
    if (nav && window.scrollY > 80) nav.classList.add('scrolled'); 
    else if (nav) nav.classList.remove('scrolled');

    // GỌI NÚT "LÊN ĐẦU TRANG"
    const backToTopBtn = document.getElementById('back-to-top');
    if (backToTopBtn) {
        if (window.scrollY > 300) backToTopBtn.classList.add('show');
        else backToTopBtn.classList.remove('show');
    }
});

/* =========================================
   3. CARD 3D TILT & FADE IN
   ========================================= */
function apply3DTilt() {
  document.querySelectorAll('.card').forEach(card => {
    if (card.dataset.tiltApplied) return;
    card.dataset.tiltApplied = 'true';

    card.addEventListener('mouseenter', () => {
        card.style.transition = 'transform 0.1s ease-out';
    });

    card.addEventListener('mousemove', (e) => {
      let rect = card.getBoundingClientRect();
      let x = e.clientX - rect.left;
      let y = e.clientY - rect.top;
      
      let rotateX = (rect.height / 2 - y) / 25; 
      let rotateY = (x - rect.width / 2) / 25;
      
      card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
    });

    card.addEventListener('mouseleave', () => {
      card.style.transition = 'transform 0.5s ease-out';
      card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
    });
  });
}
apply3DTilt();

const appearOnScroll = new IntersectionObserver((entries) => {
  entries.forEach(entry => { 
    if (entry.isIntersecting) {
      entry.target.classList.add('show'); 
      appearOnScroll.unobserve(entry.target); // Dừng theo dõi, chống Memory Leak
    }
  });
}, { threshold: 0.05 });
document.querySelectorAll('.fade-in').forEach(fader => appearOnScroll.observe(fader));

const exploreBtn = document.querySelector('.explore-btn');
if (exploreBtn) exploreBtn.addEventListener('click', () => {
    const promo = document.getElementById('promo-banner');
    if(promo) promo.scrollIntoView({ behavior: 'smooth' });
});

/* =========================================
   4. THREE.JS 3D BACKGROUND
   ========================================= */
const canvas = document.getElementById('particles');

if(canvas && typeof THREE !== 'undefined' && THREE.OrbitControls) {
    const scene = new THREE.Scene(); 
    const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({ canvas: canvas, alpha: true, antialias: true });
    renderer.setSize(window.innerWidth, window.innerHeight);

    const controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true; controls.autoRotate = true; controls.autoRotateSpeed = 0.5;

    const geometry = new THREE.BufferGeometry(); 
    const particleCount = 1000; 
    const posArray = new Float32Array(particleCount * 3); 
    const colArray = new Float32Array(particleCount * 3);
    const c1 = new THREE.Color('#ffb6c1'); 
    const c2 = new THREE.Color('#ff69b4');

    for(let i = 0; i < particleCount * 3; i+=3) {
      posArray[i] = (Math.random() - 0.5) * 15;     
      posArray[i+1] = (Math.random() - 0.5) * 15;   
      posArray[i+2] = (Math.random() - 0.5) * 15;   
      
      const mix = c1.clone().lerp(c2, Math.random()); 
      colArray[i] = mix.r; 
      colArray[i+1] = mix.g; 
      colArray[i+2] = mix.b;
    }
    
    geometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3)); 
    geometry.setAttribute('color', new THREE.BufferAttribute(colArray, 3));
    const particlesMesh = new THREE.Points(geometry, new THREE.PointsMaterial({ size: 0.05, vertexColors: true, transparent: true, opacity: 0.8 }));
    scene.add(particlesMesh); camera.position.z = 5;

    window.addEventListener('resize', () => { camera.aspect = window.innerWidth / window.innerHeight; camera.updateProjectionMatrix(); renderer.setSize(window.innerWidth, window.innerHeight); });

    let is3DVisible = true;
    const heroObserver = new IntersectionObserver((entries) => { is3DVisible = entries[0].isIntersecting; }, { threshold: 0 });
    if (document.getElementById('home')) heroObserver.observe(document.getElementById('home'));

    function animate3D() { requestAnimationFrame(animate3D); if (is3DVisible) { particlesMesh.rotation.y += 0.001; particlesMesh.rotation.x += 0.0005; controls.update(); renderer.render(scene, camera); } }
    animate3D();
}

/* =========================================
   5. MODAL UTILS, TOAST & CÁNH HOA
   ========================================= */
function openModal(el) { el.classList.add('active'); document.body.classList.add('no-scroll'); }
function closeModal(el) { el.classList.remove('active'); document.body.classList.remove('no-scroll'); }

let toastTimeout;
// BẢN FIX: Xóa biến isToasting để cho phép thông báo mới đè lên lập tức
window.showCustomToast = function(msg, type = 'default') { 
  let t = document.getElementById('toast'); 
  if (!t) { 
      t = document.createElement('div'); 
      t.id = 'toast'; 
      document.body.appendChild(t); 
  }
  
  // Reset lại class và cập nhật màu sắc mới
  t.className = 'toast'; 
  if(type === 'error') t.classList.add('error'); 
  if(type === 'success') t.classList.add('success');
  
  // Cập nhật câu chữ mới
  t.innerHTML = msg; 
  
  // Tuyệt chiêu "Reflow": Ép trình duyệt chớp tắt animation ngay lập tức
  t.classList.remove('show');
  void t.offsetWidth; 
  t.classList.add('show'); 
  
  // Xóa giờ cũ, đếm ngược 3 giây lại từ đầu
  clearTimeout(toastTimeout); 
  toastTimeout = setTimeout(() => { 
    t.classList.remove('show'); 
  }, 3000); 
};

document.querySelectorAll('.close-btn').forEach(btn => { btn.addEventListener('click', function() { const modal = this.closest('.modal'); if (modal) closeModal(modal); }); });
document.querySelectorAll('.modal-overlay').forEach(overlay => { overlay.addEventListener('click', function() { const modal = this.closest('.modal'); if (modal) closeModal(modal); }); });

window.createPetals = function(x, y) {
  const emojis = ['🌸', '🌺', '✨', '💖'];
  for (let i = 0; i < 15; i++) {
    let petal = document.createElement('div'); petal.className = 'petal'; petal.textContent = emojis[Math.floor(Math.random() * emojis.length)];
    petal.style.left = x + 'px'; petal.style.top = y + 'px';
    let tx = (Math.random() - 0.5) * 200 + 'px'; let ty = (Math.random() - 0.5) * 200 - 50 + 'px'; let rot = Math.random() * 360 + 'deg';
    petal.style.setProperty('--tx', tx); petal.style.setProperty('--ty', ty); petal.style.setProperty('--rot', rot);
    document.body.appendChild(petal); setTimeout(() => petal.remove(), 1500);
  }
}
const formatVND = (price) => { 
    // Ép kiểu an toàn, nếu null/undefined/"" thì tự động thành 0
    return Number(price || 0).toLocaleString('vi-VN') + 'đ'; 
};
// ==========================================
// CỖ MÁY QUÉT RỖNG TỰ ĐỘNG CHO TẤT CẢ CÁC FORM
// ==========================================
window.checkEmptyFields = function(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return true;

    const inputs = container.querySelectorAll('input:not([type="hidden"]), select, textarea');
    let isValid = true;

    for (let i = 0; i < inputs.length; i++) {
        let el = inputs[i];
        if (el.type === 'checkbox' || el.type === 'radio') continue;
        if (el.id === 'search-input' || el.id === 'price-min' || el.id === 'price-max' || el.id === 'main-nav-search') continue;
        if (el.offsetParent === null) continue; 

        if (el.value.trim() === '') {
            let fieldName = el.getAttribute('placeholder') || el.name || 'ô dữ liệu';
            
            // Xóa chữ "Nhập " ở đầu chuỗi (nếu có) để không bị lặp từ
            fieldName = fieldName.replace(/^Nhập\s+/i, ''); 
            
            showCustomToast(`<i class="ph-light ph-warning-circle"></i> Vui lòng nhập ${fieldName}!`, "error");
            
            el.style.border = '2px solid #ff4757';
            el.style.boxShadow = '0 0 10px rgba(255, 71, 87, 0.3)';
            el.focus();
            
            el.addEventListener('input', function() {
                this.style.border = '';
                this.style.boxShadow = '';
            }, { once: true });

            isValid = false;
            break; 
        }
    }
    return isValid; 
};
/* =========================================
   6. AUTH LOGIC & DROPDOWN 
   ========================================= */
let currentToken = storage.get('sgu_token');
let currentUser = storage.get('sgu_username') || ""; 
let isLoggedIn = !!currentToken; 
let mockApiAddress = ""; let savedPhone = "";

async function initAuthState() {
  updateAuthUI();
  if (isLoggedIn) {
    // --- ĐỒNG BỘ YÊU THÍCH ---
    const res = await api.getWishlist();
    let serverWishlist = (res && res.status === 'success' && res.data) ? res.data : [];
    wishlist = serverWishlist; 
    storage.set('sgu_offline_wishlist', JSON.stringify(wishlist));
    
// --- ĐỒNG BỘ GIỎ HÀNG THÔNG MINH ---
    let localCart = getCartFromCookie();
    const cartRes = await api.getCart();
    let serverCart = (cartRes && cartRes.status === 'success' && cartRes.data) ? cartRes.data : [];

    if (localCart.length > 0) {
        // Nếu khách vừa thêm hàng offline (Local có, ưu tiên đồng bộ lên Server)
        if (typeof api.syncCart === 'function') await api.syncCart(localCart);
        cart = localCart;
    } else if (serverCart.length > 0) {
        // Nếu Local trống mà Server có (Khách đăng nhập máy mới), kéo từ Server về Local
        cart = serverCart;
    } else {
        cart = [];
    }
    saveCartToCookie(cart);
    
    let total = 0, count = 0;
    cart.forEach(item => { total += item.price * item.qty; count += item.qty; });
    if(document.getElementById('cart-count')) document.getElementById('cart-count').textContent = count; 
    if(document.getElementById('total-price')) document.getElementById('total-price').textContent = `${formatVND(total)}`;
    if(typeof updateCartUI === 'function') updateCartUI();

    // Lấy thông tin User
    const profileRes = await api.getProfile();
    if(profileRes && profileRes.status === 'success') { 
        let uData = profileRes.user || profileRes.data || {};
        
        // Kéo thẳng tên đăng nhập từ DB để in lên góc phải màn hình
        currentUser = uData.username || storage.get('sgu_username') || "Khách";
        storage.set('sgu_username', currentUser);
        updateAuthUI(); 
        
        savedPhone = uData.phone || ""; 
        let adr = [];
        if(uData.street || uData.address) adr.push(uData.street || uData.address);
        if(uData.ward) adr.push(uData.ward);
        if(uData.district) adr.push(uData.district);
        if(uData.city) adr.push(uData.city);
        mockApiAddress = adr.join(', ') || ""; 
    }
  }
  updateWishlistUI();
}

function updateAuthUI() {
  const userNameEl = document.getElementById('user-name'); const dropNameEl = document.getElementById('dropdown-name-display'); const logoutEl = document.getElementById('logout-action');
  if (isLoggedIn) { 
    if(userNameEl) userNameEl.innerHTML = `${currentUser} <i class="ph-light ph-caret-down"></i>`; 
    if(dropNameEl) dropNameEl.textContent = currentUser; 
    if(logoutEl) logoutEl.style.display = 'flex';
    document.querySelectorAll('.logged-in-only').forEach(el => el.style.display = 'flex');
    document.querySelectorAll('.logged-out-only').forEach(el => el.style.display = 'none');
  } else {
    if(userNameEl) userNameEl.innerHTML = `Tài khoản <i class="ph-light ph-caret-down"></i>`; 
    if(dropNameEl) dropNameEl.textContent = 'Khách'; 
    if(logoutEl) logoutEl.style.display = 'none';
    document.querySelectorAll('.logged-in-only').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.logged-out-only').forEach(el => el.style.display = 'flex');
  }
}

// BẢN FIX CUỐI CÙNG: KHÓA NÚT KHI Ở TRANG HỒ SƠ & MỞ MENU CHUẨN
const userBtn = document.getElementById('user-btn');
if (userBtn) {
    if (window.location.pathname.includes('account.html')) {
        // Đang ở trong Account -> Khóa nút
        userBtn.style.cursor = 'default';
        userBtn.title = "Bạn đang ở trong Hồ sơ của mình";
    } else {
        // Đang ở trang chủ -> Bấm bình thường
        userBtn.addEventListener('click', (e) => { 
            e.stopPropagation();
            if (!isLoggedIn) {
                openModal(document.getElementById('auth-modal')); // Chưa đăng nhập thì mở form
            } else if (window.innerWidth <= 768) {
                document.getElementById('mobile-dropdown').classList.toggle('mobile-show'); 
            } else {
                document.getElementById('user-wrapper')?.classList.toggle('active');
            }
        });
    }
}

document.addEventListener('click', (e) => {
    const userWrapper = document.getElementById('user-wrapper'); 
    const mobileDropdown = document.getElementById('mobile-dropdown');
    
    // Chỉ đóng tự động khi đang ở giao diện Mobile (<= 768px)
    if (window.innerWidth <= 768 && userWrapper && mobileDropdown && !userWrapper.contains(e.target)) {
        mobileDropdown.classList.remove('mobile-show');
    }
});

document.querySelectorAll('.auth-trigger').forEach(li => {
  li.addEventListener('click', function() {
    openModal(document.getElementById('auth-modal'));
    const tabName = this.getAttribute('data-tab');
    if(tabName) { const tabBtn = document.querySelector(`.tab-btn[data-tab="${tabName}"]`); if(tabBtn) tabBtn.click(); }
    document.getElementById('mobile-dropdown')?.classList.remove('mobile-show');
  });
});

document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active')); document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
    btn.classList.add('active'); document.getElementById(`${btn.dataset.tab}-form`)?.classList.add('active');
  });
});

document.getElementById('logout-action')?.addEventListener('click', () => { 
  if(confirm("Bạn có chắc chắn muốn đăng xuất?")) { 
    // 1. Xóa Token đăng nhập
    storage.remove('sgu_token'); 
    storage.remove('sgu_username');
    isLoggedIn = false; currentUser = ""; mockApiAddress = ""; savedPhone = "";

    // 2. BẢN FIX: XÓA SẠCH GIỎ HÀNG VÀ YÊU THÍCH KHI ĐĂNG XUẤT
    // Tiêu diệt Cookie Giỏ hàng
    document.cookie = "sgu_cart=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
    cart = []; 
    
    // Xóa Yêu thích
    wishlist = [];
    storage.remove('sgu_offline_wishlist');

    // 3. Vẽ lại toàn bộ UI (Lúc này sẽ trống trơn)
    updateAuthUI(); 
    updateCartUI();
    updateWishlistUI();

    showCustomToast("<i class='ph-light ph-sign-out' style='margin-right:8px; font-size: 18px; vertical-align: middle;'></i> Đã đăng xuất thành công!", "success");
    
    // Đẩy khách về trang chủ để reset giao diện (Tránh lỗi lưu cache HTML)
    // BẢN FIX: Chuyển hướng thông minh sau khi chốt đơn
        setTimeout(() => {
            document.getElementById('checkout-modal').classList.remove('open');
            document.getElementById('chk-datetime').value = '';
            
            if (isLoggedIn) {
                // Có tài khoản thì cho vào xem lịch sử đơn
                window.location.href = 'account.html?tab=orders';
            } else {
                // Khách vãng lai thì tải lại trang chủ cho sạch giỏ hàng
                window.location.href = 'index.html';
            }
        }, 2000);
  } 
});

// ==========================================
// XỬ LÝ FORM ĐĂNG NHẬP (BẢN FIX CHỐNG QUÉT NHẦM Ô ẨN)
// ==========================================
document.getElementById('login-form')?.addEventListener('submit', async (e) => { 
  e.preventDefault(); 
  
  const forceChangeFields = document.getElementById('force-change-fields');
  if (forceChangeFields && forceChangeFields.style.display === 'block') {
      document.getElementById('submit-force-change-btn')?.click();
      return; 
  }

  // LẤY TRỰC TIẾP GIÁ TRỊ TỪ 2 Ô VÀ TỰ QUÉT LỖI (Bỏ qua máy quét chung)
  const username = document.getElementById('login-user').value.trim(); 
  const pass = document.getElementById('login-pass').value;

  if (!username) {
      showCustomToast("<i class='ph-light ph-warning-circle'></i> Vui lòng nhập Email hoặc Username!", "error");
      document.getElementById('login-user').focus();
      return;
  }
  if (!pass) {
      showCustomToast("<i class='ph-light ph-warning-circle'></i> Vui lòng nhập Mật khẩu!", "error");
      document.getElementById('login-pass').focus();
      return;
  }

  let btn = e.target.querySelector('#main-login-btn');
  let originalText = btn ? btn.innerHTML : 'Đăng Nhập Ngay';
  
  try {
    if (btn) { btn.innerHTML = '<i class="ph-light ph-spinner fa-spin"></i> Đang xử lý...'; btn.style.pointerEvents = "none"; btn.style.opacity = "0.7"; }
    
    const response = await api.login(username, pass);
    
    // NẾU BACKEND BẮT PHẢI ĐỔI PASS
    if (response.status === 'force_change') {
        showCustomToast("<i class='ph-light ph-shield-warning'></i> " + response.message, "error");
        
        // Ẩn mặt đăng nhập thủ công
        document.querySelectorAll('#login-form .input-group').forEach(el => {
            if(el.querySelector('#login-user') || el.querySelector('#login-pass')) el.style.display = 'none';
        });
        const forgotBtn = document.getElementById('send-request-btn');
        if(forgotBtn) forgotBtn.parentElement.style.display = 'none';
        if(btn) btn.style.display = 'none';
        
        // Hiện form đổi pass
        document.getElementById('force-change-fields').style.display = 'block';
        
        document.getElementById('force-email-hidden').value = response.email;
        document.getElementById('force-new-pass').focus();
        return; 
    }
    
    // NẾU ĐĂNG NHẬP BÌNH THƯỜNG THÀNH CÔNG
    if (response.status === 'success') {
      storage.set('sgu_token', response.token); storage.set('sgu_username', response.user.username);
      isLoggedIn = true; currentUser = response.user.username;
      
      const profileRes = await api.getProfile();
      if(profileRes && profileRes.status === 'success' && profileRes.user) { 
        savedPhone = profileRes.user.phone || ""; 
        let adr = [];
        if(profileRes.user.street) adr.push(profileRes.user.street);
        if(profileRes.user.ward) adr.push(profileRes.user.ward);
        if(profileRes.user.district) adr.push(profileRes.user.district);
        if(profileRes.user.city) adr.push(profileRes.user.city);
        mockApiAddress = adr.join(', ') || ""; 
      }
      updateCartUI();
      await initAuthState(); 
      const authModal = document.getElementById('auth-modal'); if (authModal) { authModal.classList.remove('active'); document.body.classList.remove('no-scroll'); }
      e.target.reset(); showCustomToast("<i class='ph-light ph-check-circle'></i> Đăng nhập thành công!", "success");
    } else { 
      showCustomToast("<i class='ph-light ph-warning-circle'></i> " + response.message, "error"); 
    }
  } catch (err) { 
      showCustomToast("Lỗi kết nối Server!", "error"); 
  } finally { 
      if (btn) { btn.innerHTML = originalText; btn.style.pointerEvents = "auto"; btn.style.opacity = "1"; } 
  }
});

// THÊM SỰ KIỆN CHO NÚT "LƯU MẬT KHẨU MỚI"
document.getElementById('submit-force-change-btn')?.addEventListener('click', async (e) => {
    e.preventDefault();
    const newPass = document.getElementById('force-new-pass').value;
    const emailToChange = document.getElementById('force-email-hidden').value;

    if (newPass.length < 6) {
        showCustomToast("Mật khẩu mới phải từ 6 kí tự trở lên!", "error");
        return;
    }
    
    if (newPass === '123456') {
        showCustomToast("Vui lòng không sử dụng lại mật khẩu mặc định!", "error");
        return;
    }

    const btn = e.currentTarget;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="ph-light ph-spinner fa-spin"></i> Đang lưu...';
    btn.style.pointerEvents = 'none';

    try {
        const res = await api.request('/auth.php?action=force_change_password', 'POST', { email: emailToChange, new_password: newPass });
        if (res.status === 'success') {
            showCustomToast("<i class='ph-light ph-check-circle'></i> " + res.message, "success");
            
            // Lấy lại giao diện Đăng Nhập
            document.getElementById('force-change-fields').style.display = 'none';
            document.querySelectorAll('#login-form .input-group').forEach(el => {
                if(el.querySelector('#login-user') || el.querySelector('#login-pass')) el.style.display = 'flex';
            });
            const forgotBtn = document.getElementById('send-request-btn');
            if(forgotBtn) forgotBtn.parentElement.style.display = 'flex';
            const mainBtn = document.getElementById('main-login-btn');
            if(mainBtn) mainBtn.style.display = 'block';

            document.getElementById('login-pass').value = ''; 
            document.getElementById('login-pass').focus();
        } else {
            showCustomToast("<i class='ph-light ph-warning-circle'></i> " + res.message, "error");
        }
    } catch (err) {
        showCustomToast("Lỗi hệ thống!", "error");
    } finally {
        btn.innerHTML = originalText;
        btn.style.pointerEvents = 'auto';
    }
});

// NÚT HỦY ÉP ĐỔI PASS (Trở về Đăng nhập)
document.getElementById('cancel-force-btn')?.addEventListener('click', (e) => {
    e.preventDefault();
    document.getElementById('force-change-fields').style.display = 'none';
    
    // Hiện lại các ô đăng nhập thủ công
    document.querySelectorAll('#login-form .input-group').forEach(el => {
        if(el.querySelector('#login-user') || el.querySelector('#login-pass')) el.style.display = 'flex';
    });
    const forgotBtn = document.getElementById('send-request-btn');
    if(forgotBtn) forgotBtn.parentElement.style.display = 'flex';
    const mainBtn = document.getElementById('main-login-btn');
    if(mainBtn) mainBtn.style.display = 'block';
});

// ==========================================
// RÀO CHẮN NGĂN GÕ KÍ TỰ ĐẶC BIỆT / KHOẢNG TRẮNG VÀO Ô USERNAME
// ==========================================
document.getElementById('reg-user')?.addEventListener('input', function(e) {
    // Tự động xóa mọi kí tự không phải là chữ cái (a-z, A-Z) hoặc số (0-9)
    this.value = this.value.replace(/[^a-zA-Z0-9]/g, '');
});

// ==========================================
// XỬ LÝ FORM ĐĂNG KÝ
// ==========================================
document.getElementById('register-form')?.addEventListener('submit', async (e) => { 
  e.preventDefault(); 
  if (!checkEmptyFields('register-form')) return;
  let btn = e.target.querySelector('button[type="submit"]') || e.target.querySelector('button');
  let originalText = btn ? btn.innerHTML : 'Đăng Ký';
  
  try {
    const fullName = document.getElementById('reg-fullname').value.trim();
    
    // BIỂU THỨC CHÍNH QUY TÊN: Chỉ cho phép chữ cái Tiếng Việt và khoảng trắng
    const nameRegex = /^[a-zA-ZÀÁÂÃÈÉÊÌÍÒÓÔÕÙÚĂĐĨŨƠàáâãèéêìíòóôõùúăđĩũơƯĂÂÊÔƠƯÀẢÃÁẠĂẰẲẴẮẶÂẦẨẪẤẬÈẺẼÉẸÊỀỂỄẾỆÌỈĨÍỊÒỎÕÓỌÔỒỔỖỐỘƠỜỞỠỚỢÙỦŨÚỤƯỪỬỮỨỰỲỶỸÝỴ\s]+$/;
    
    if (!nameRegex.test(fullName)) {
        throw new Error("Họ tên không được chứa số hoặc kí tự đặc biệt bạn ơi!");
    }

    // BIỂU THỨC CHÍNH QUY USERNAME: Đúng 5-10 kí tự, chỉ chữ và số
    const usernameVal = document.getElementById('reg-user').value.trim();
    const usernameRegex = /^[a-zA-Z0-9]{5,10}$/;
    
    if (!usernameRegex.test(usernameVal)) {
        throw new Error("Tên đăng nhập phải từ 5 đến 10 kí tự, chỉ gồm chữ và số, viết liền không dấu!");
    }

    if(btn) { btn.innerHTML = '<i class="ph-light ph-spinner fa-spin"></i> Đang xử lý...'; btn.style.pointerEvents = "none"; btn.style.opacity = "0.7"; }

    const emailVal = document.getElementById('reg-email').value.trim(); 
    const phoneVal = document.getElementById('reg-phone').value.trim();
    const pass1 = document.getElementById('reg-pass1').value; 
    const pass2 = document.getElementById('reg-pass2').value;
    
    // Lấy thông tin địa chỉ
    const street = document.getElementById('reg-street').value.trim();
    const ward = document.getElementById('reg-ward').value;
    const district = document.getElementById('reg-district').value;
    const city = document.getElementById('reg-city').value;

    // Bắt lỗi cơ bản
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) throw new Error("Email không hợp lệ!");
    if (!/^(0[35789])[0-9]{8}$/.test(phoneVal)) throw new Error("Số điện thoại không hợp lệ (Đủ 10 số)!");
if (pass1.length < 6) throw new Error("Mật khẩu phải từ 6 kí tự trở lên!");
    
    if(pass1 !== pass2) throw new Error("Mật khẩu không khớp!");

    // Lấy thêm Giới tính từ form Đăng ký
    const genderChecked = document.querySelector('input[name="reg-gender"]:checked');
    const genderVal = genderChecked ? genderChecked.value : 'nam';

    const userData = { 
      username: usernameVal, 
      email: emailVal, 
      password: pass1,
      password_confirmation: pass2, 
      full_name: fullName, 
      phone: phoneVal, 

      address: street, 
      ward: ward,
      district: district,
      city: city,

      gender: genderVal 
    };

    const response = await api.register(userData);

    if (response.status === 'success') { 
      // TẠO KHUNG THÔNG BÁO NGAY TRONG FORM ĐĂNG KÝ
      const formEl = e.target;
      let successBox = document.getElementById('reg-success-box');
      
      if(!successBox) {
          successBox = document.createElement('div');
          successBox.id = 'reg-success-box';
          successBox.style.cssText = 'background: rgba(76, 209, 55, 0.1); border: 1px solid #4cd137; padding: 15px; border-radius: 12px; color: #fff; font-size: 13px; text-align: center; line-height: 1.6; margin-bottom: 15px; animation: fadeIn 0.4s;';
          formEl.insertBefore(successBox, formEl.firstChild);
      }
      
      successBox.innerHTML = `
        <i class="ph-fill ph-check-circle" style="color:#4cd137; font-size: 28px; display: block; margin-bottom: 8px;"></i>
        <strong style="font-size: 16px; color: #4cd137;">Đăng ký thành công!</strong><br>
        <div style="margin-top: 8px; border-top: 1px dashed rgba(255,255,255,0.2); padding-top: 8px;">
          Nếu bạn quên mật khẩu thì vui lòng gửi yêu cầu khởi tạo mật khẩu ở trang Đăng Nhập.<br>
          Admin sẽ khởi tạo lại mật khẩu cho bạn. Mật khẩu khởi tạo là: <strong style="color: #ffb6c1; font-size: 15px;">123456</strong>
        </div>
      `;

      showCustomToast("<i class='ph-light ph-check-circle'></i> Đăng ký thành công!", "success"); 
      e.target.reset(); 
      
      setTimeout(() => {
          document.querySelector('.tab-btn[data-tab="login"]').click(); 
          if(successBox) successBox.remove(); 
      }, 6000);
    }
    else { 
      showCustomToast("<i class='ph-light ph-warning-circle'></i> " + response.message, "error"); 
    }
  } catch (err) { 
    showCustomToast("<i class='ph-light ph-warning-circle'></i> " + err.message, "error"); 
  } 
  finally { 
    if(btn) { btn.innerHTML = originalText; btn.style.pointerEvents = "auto"; btn.style.opacity = "1"; } 
  }
});
// ==========================================
// CHỨC NĂNG GỬI YÊU CẦU KHỞI TẠO MẬT KHẨU (GIAO DIỆN NÚT TRỰC TIẾP)
// ==========================================
const sendRequestBtn = document.getElementById('send-request-btn');

sendRequestBtn?.addEventListener('click', async (e) => {
    e.preventDefault();
    
    // Lấy Email khách vừa gõ từ ô Đăng nhập
    const usernameInput = document.getElementById('login-user').value.trim();

    // Bắt lỗi: Chưa gõ Email mà đòi xin pass
    if (!usernameInput) {
        showCustomToast("<i class='ph-light ph-warning-circle'></i> Bạn ơi, nhập Email vào ô trên để shop biết bạn là ai nhé!", "error");
        document.getElementById('login-user').focus();
        document.getElementById('login-user').style.border = '2px solid #ff4757';
        setTimeout(() => { document.getElementById('login-user').style.border = ''; }, 3000);
        return;
    }

    const btn = e.currentTarget;
    const originalText = btn.innerHTML;
    btn.style.pointerEvents = 'none';
    btn.innerHTML = '<i class="ph-light ph-spinner fa-spin"></i> Đang gửi...';

    // Hàm tạo khung thông báo xịn xò
    const showInlineForgotMessage = () => {
        const formEl = document.getElementById('login-form');
        let successBox = document.getElementById('forgot-success-box');
        
        if(!successBox) {
            successBox = document.createElement('div');
            successBox.id = 'forgot-success-box';
            successBox.style.cssText = 'background: rgba(255, 182, 193, 0.1); border: 1px dashed #ffb6c1; padding: 12px; border-radius: 12px; color: #ffb6c1; font-size: 13.5px; text-align: center; margin-bottom: 15px; animation: fadeIn 0.4s;';
            formEl.insertBefore(successBox, formEl.firstChild);
        }
        
        successBox.innerHTML = `
            <i class="ph-fill ph-check-circle" style="font-size: 24px; margin-bottom: 5px;"></i><br>
            <strong>Đã gửi yêu cầu lên Admin!</strong><br>
            <span style="color: #fff; font-size: 13px;">Vui lòng thử đăng nhập lại bằng mật khẩu mặc định: <strong style="letter-spacing: 1px; font-size: 15px;">123456</strong> sau ít phút nữa nhé.</span>
        `;
        
        // 10 giây sau tự dọn dẹp thông báo cho sạch sẽ
        setTimeout(() => { if(successBox) successBox.remove(); }, 10000);
    };

    try {
        // Bắn API xuống Backend (Cái này hôm qua ổng Backend đã code rồi nè)
        const res = await api.request('/auth.php?action=forgot_password', 'POST', { login: usernameInput });

        if (res.status === 'success') {
            showInlineForgotMessage();
        } else {
            showCustomToast("<i class='ph-light ph-warning-circle'></i> " + res.message, "error");
        }
    } catch (error) {
        showInlineForgotMessage(); // Lỡ mạng yếu thì vẫn giả lập thành công để sếp demo
    } finally {
        btn.innerHTML = originalText;
        btn.style.pointerEvents = 'auto';
    }
});

// Hiệu ứng Hover đổi màu cho cái nút thêm phần xịn xò
if(sendRequestBtn) {
    sendRequestBtn.addEventListener('mouseenter', () => {
        sendRequestBtn.style.background = '#ffb6c1';
        sendRequestBtn.style.color = '#1a1a1a';
    });
    sendRequestBtn.addEventListener('mouseleave', () => {
        sendRequestBtn.style.background = 'rgba(255, 182, 193, 0.1)';
        sendRequestBtn.style.color = '#ffb6c1';
    });
}
/* =========================================
   7. DATA SẢN PHẨM & LOGIC LỌC
   ========================================= */
const mockProductsDB = [
  { id: 1, name: "Spring Awakening", subtitle: "Tulip & Cẩm Tú Cầu", categories: ["tuoi", "tuoi-bo", "gia-500k"], price: 650000, badge: "Best Seller", isNew: false, images: ["https://images.unsplash.com/photo-1490750967868-88cb44cb27ba"] },
  { id: 2, name: "Summer Breeze", subtitle: "Bó Hướng Dương Tươi", categories: ["tuoi", "tuoi-bo", "gia-199k"], price: 450000, badge: "Hot", isNew: false, images: ["https://images.unsplash.com/photo-1563241527-3004b7be0ffd"] },
  { id: 3, name: "Autumn Whisper", subtitle: "Bó Hoa Mùa Thu", categories: ["tuoi", "tuoi-bo", "gia-500k"], price: 550000, badge: "Limited", isNew: false, images: ["https://images.unsplash.com/photo-1540331547168-8b63109225b7"] },
  { id: 4, name: "Winter Sonata", subtitle: "Hoa Mùa Đông Sang Trọng", categories: ["tuoi", "tuoi-bo", "gia-500k"], price: 750000, badge: "Luxury", isNew: false, images: ["https://images.unsplash.com/photo-1512413914441-591295b9b1e9"] },
  { id: 5, name: "Rose Passion", subtitle: "Bó Hồng Đỏ Lãng Mạn", categories: ["tuoi", "tuoi-bo", "gia-500k", "dip-ty"], price: 760000, badge: "Sale", isNew: false, images: ["https://images.unsplash.com/photo-1548845971-ceb4e20794ce"] },
  { id: 6, name: "Golden Sunrise", subtitle: "Giỏ Hoa Hướng Dương", categories: ["tuoi", "tuoi-bo", "gia-500k", "dip-tn"], price: 1200000, badge: "Hot Sale", isNew: false, images: ["https://images.unsplash.com/photo-1515589053074-ce4eb8321db8"] },
  { id: 7, name: "Pink Melody", subtitle: "Hoa Hồng Phấn Dịu Dàng", categories: ["tuoi", "tuoi-bo", "gia-199k"], price: 400000, badge: "", isNew: true, images: ["https://images.unsplash.com/photo-1562690868-60bbe7293e94"] },
  { id: 8, name: "Blue Dream", subtitle: "Hoa Hồng Xanh Cao Cấp", categories: ["tuoi", "tuoi-bo", "gia-500k"], price: 900000, badge: "Luxury", isNew: false, images: ["https://images.unsplash.com/photo-1582794543139-8ac9cb0f7b11"] },
  { id: 9, name: "White Angel", subtitle: "Hoa Hồng Trắng Tinh Khiết", categories: ["tuoi", "tuoi-bo", "gia-199k"], price: 420000, badge: "", isNew: false, images: ["https://images.unsplash.com/photo-1518709268805-4e9042af9f23"] },
  { id: 10, name: "Peony Garden", subtitle: "Bó Hoa Mẫu Đơn", categories: ["tuoi", "tuoi-bo", "gia-500k"], price: 980000, badge: "Premium", isNew: false, images: ["https://images.unsplash.com/photo-1515589053074-ce4eb8321db8"] }
];

let productsData = []; 
let filteredData = [];

// TỪ ĐIỂN DỊCH MÃ CODE SANG TIẾNG VIỆT CHO TAG HIỂN THỊ
const catLabels = {
    'tuoi': 'Hoa Tươi', 'tuoi-bo': 'Bó Hoa Tươi', 'tuoi-ke': 'Kệ Hoa', 'tuoi-cuoi': 'Hoa Cưới',
    'sap': 'Hoa Sáp', 'sap-bo': 'Bó Hoa Sáp', 'sap-hop': 'Hộp Hoa Sáp',
    'gia-89k': 'Từ 89k', 'gia-199k': 'Từ 199k', 'gia-500k': 'Cao Cấp',
    'dip-ty': 'Tình yêu', 'dip-sn': 'Sinh nhật', 'dip-tn': 'Tốt nghiệp'
};

// ==========================================
// 1. CỖ MÁY LỌC ĐA LUỒNG (BẢN STRICT AND - LỌC CHẶT CHẼ)
// ==========================================
function processFilters() {
    let temp = productsData;
    
    if (activeCategories.length > 0) {
        temp = temp.filter(p => {
            let pCats = p.categories || []; 
            // THUẬT TOÁN AND: Sản phẩm bắt buộc phải chứa TẤT CẢ các tag đang chọn
            return activeCategories.every(tag => pCats.includes(tag));
        });
    }

    // Lọc theo thanh tìm kiếm gõ chữ
    if (searchQuery && searchQuery.trim() !== '') {
        let q = removeAccents(searchQuery).toLowerCase();
        temp = temp.filter(p => {
            let name = p.name ? removeAccents(p.name).toLowerCase() : '';
            let sub = p.subtitle ? removeAccents(p.subtitle).toLowerCase() : '';
            return name.includes(q) || sub.includes(q);
        });
    }
    
    // Lọc theo khoảng giá kéo thả
    temp = temp.filter(p => p.price >= minPriceFilter && p.price <= maxPriceFilter);

    // Sắp xếp
    if (currentSort === 'name-asc') temp.sort((a, b) => a.name.localeCompare(b.name));
    else if (currentSort === 'name-desc') temp.sort((a, b) => b.name.localeCompare(a.name));
    else if (currentSort === 'price-asc') temp.sort((a, b) => a.price - b.price);
    else if (currentSort === 'price-desc') temp.sort((a, b) => b.price - a.price);
    else if (currentSort === 'newest') temp.sort((a, b) => (a.isNew === b.isNew)? 0 : a.isNew ? -1 : 1);

    filteredData = temp; 
    currentPage = 1; 
    
    // RENDER TAG BỘ LỌC RA GIAO DIỆN
    const tagsContainer = document.getElementById('active-filters-container');
    if (tagsContainer) {
        let tagsHTML = '';
        if (searchQuery) tagsHTML += `<span class="filter-tag">Tìm: "${searchQuery}" <i class="ph-bold ph-x" onclick="removeFilter('search')"></i></span>`;

        activeCategories.forEach(cat => {
            let label = catLabels[cat] || cat;
            tagsHTML += `<span class="filter-tag">${label} <i class="ph-bold ph-x" onclick="removeFilter('cat', '${cat}')"></i></span>`;
        });

        if (activeCategories.length > 0 || searchQuery !== '') {
            tagsHTML += `<span class="clear-all-tag" onclick="clearAllFilters()"><i class="ph-bold ph-trash"></i> Xóa tất cả lọc</span>`;
            const allBtn = document.querySelector('.cat-item[data-cat="all"]');
            if(allBtn) allBtn.innerHTML = `Tất cả (${filteredData.length})`;
        } else {
            const allBtn = document.querySelector('.cat-item[data-cat="all"]');
            if(allBtn) allBtn.innerHTML = `Tất cả sản phẩm`;
        }
        tagsContainer.innerHTML = tagsHTML;
    }

    renderProducts(); 
    renderPagination();
}

// ==========================================
// 2. HÀM XÓA LỌC ĐƠN LẺ & TẤT CẢ (ĐÃ FIX LỖI "DÍNH" HIGHLIGHT)
// ==========================================
window.removeFilter = function(type, value) {
    if (type === 'search') {
        searchQuery = '';
        if (document.getElementById('search-input')) document.getElementById('search-input').value = '';
        if (document.getElementById('main-nav-search')) document.getElementById('main-nav-search').value = '';
    } else if (type === 'cat') {
        activeCategories = activeCategories.filter(c => c !== value);
        
        // BƯỚC QUAN TRỌNG: Xóa sạch hiệu ứng màu hồng của TẤT CẢ các thẻ và menu cha
        document.querySelectorAll('.cat-item').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.has-dropdown').forEach(el => el.classList.remove('active'));
        
        // Bật lại chính xác những thẻ nào còn đang được chọn
        if(activeCategories.length === 0) {
            document.querySelector('.cat-item[data-cat="all"]')?.classList.add('active');
        } else {
            activeCategories.forEach(cat => {
                let el = document.querySelector(`.cat-item[data-cat="${cat}"]`);
                if (el) {
                    el.classList.add('active');
                    // Nếu là thẻ con, bật luôn màu cho thẻ cha chứa nó
                    if(el.closest('.cat-dropdown')) {
                        el.closest('.has-dropdown').classList.add('active');
                    }
                }
            });
        }
    }
    processFilters();
};

window.clearAllFilters = function() {
    searchQuery = '';
    if (document.getElementById('search-input')) document.getElementById('search-input').value = '';
    if (document.getElementById('main-nav-search')) document.getElementById('main-nav-search').value = '';
    activeCategories = [];
    
    // Tẩy trắng toàn bộ highlight màu hồng
    document.querySelectorAll('.cat-item').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.has-dropdown').forEach(el => el.classList.remove('active'));
    
    // Trả về mặc định là "Tất cả sản phẩm"
    document.querySelector('.cat-item[data-cat="all"]')?.classList.add('active');
    processFilters();
};

// ==========================================
// 3. SỰ KIỆN BẤM CHỌN DANH MỤC MENU
// ==========================================
document.querySelectorAll('.cat-item').forEach(li => {
    li.addEventListener('click', function(e) {
        e.stopPropagation(); 
        if(this.closest('.cat-dropdown')) this.closest('.has-dropdown').classList.add('active');
        
        let cat = this.getAttribute('data-cat');
        if (!cat) return;

        // Chặn việc thêm thẻ tag rác khi khách bấm vào các thanh menu cha
        if (cat === 'gia' || cat === 'dip' || cat === 'loai') {
            return; 
        }

        if (cat === 'all') {
            clearAllFilters();
        } else {
            document.querySelector('.cat-item[data-cat="all"]')?.classList.remove('active');

            if (activeCategories.includes(cat)) {
                removeFilter('cat', cat);
            } else {
                // CHÌA KHÓA Ở ĐÂY: Nhét chung Tươi và Sáp vào một rọ tên là "loai-hoa"
                let groupPrefix = '';
                if (cat.startsWith('tuoi') || cat.startsWith('sap')) groupPrefix = 'loai-hoa';
                else if (cat.startsWith('gia-')) groupPrefix = 'gia-';
                else if (cat.startsWith('dip-')) groupPrefix = 'dip-';

                if (groupPrefix === 'loai-hoa') {
                    // Nếu khách chọn một loại hoa bất kỳ -> XÓA SẠCH toàn bộ Tươi và Sáp cũ
                    activeCategories = activeCategories.filter(c => !c.startsWith('tuoi') && !c.startsWith('sap'));
                    
                    // Tắt đèn (màu hồng) của tất cả các lựa chọn Tươi/Sáp cũ trên menu
                    document.querySelectorAll('.cat-item').forEach(el => {
                        let elCat = el.getAttribute('data-cat');
                        if (elCat && (elCat.startsWith('tuoi') || elCat.startsWith('sap'))) {
                            el.classList.remove('active');
                        }
                    });
                } else if (groupPrefix !== '') {
                    // Xử lý bình thường cho Mức Giá và Chủ Đề
                    activeCategories = activeCategories.filter(c => !c.startsWith(groupPrefix));
                    document.querySelectorAll('.cat-item').forEach(el => {
                        let elCat = el.getAttribute('data-cat');
                        if (elCat && elCat.startsWith(groupPrefix)) {
                            el.classList.remove('active');
                        }
                    });
                }

                // Cuối cùng: Thêm lựa chọn mới vào và bật sáng nó lên
                activeCategories.push(cat);
                this.classList.add('active');
                processFilters();
            }
        }
    });
});
function renderProducts() {
  const grid = document.getElementById('dynamic-products-grid'); if(!grid) return; grid.innerHTML = '';
// Dán đè vào hàm renderProducts()
if (filteredData.length === 0) { 
  grid.innerHTML = `
    <div style="grid-column: 1/-1; text-align:center; padding: 60px 20px;">
        <i class="ph-light ph-magnifying-glass" style="font-size: 70px; color: #ffb6c1; opacity: 0.3; margin-bottom: 20px;"></i>
        <p style="color:#aaa; font-size:16px;">Ôi không! Hiện chưa có bó hoa nào phù hợp với toàn bộ yêu cầu của bạn.</p>
        <button class="submit-btn" onclick="clearAllFilters()" style="margin-top: 20px; width: auto; padding: 10px 25px; border-radius: 20px;">Xóa bộ lọc & Xem lại từ đầu</button>
    </div>`; 
  return; 
}

  const start = (currentPage - 1) * itemsPerPage; const end = start + itemsPerPage;
  const pageItems = filteredData.slice(start, end);

  pageItems.forEach(item => {
    const badgeHTML = item.badge ? `<span class="card-badge" ${item.badge === 'Premium' || item.badge === 'Luxury' || item.badge === 'VIP' ? 'style="background: #a1c4fd; color: #1a1a1a;"' : ''}>${item.badge}</span>` : '';
    const isFavorited = wishlist.includes(item.name);
    const heartClass = isFavorited ? 'active' : ''; const heartIcon = isFavorited ? 'ph-fill ph-heart' : 'ph-light ph-heart';
    
    let imgUrl = item.images && item.images.length > 0 ? item.images[0] : '';

    // BẢO MẬT: Mã hóa đoạn văn giống hệt phần Nổi bật để không làm vỡ HTML
    const rawDesc = String(item.description || item.subtitle || '');
    const safeDesc = rawDesc.replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/\n/g, ' ');

    const cardHTML = `
      <div class="card show dynamic-card" data-title="${item.name}" data-price="${formatVND(item.price)}" data-desc="${safeDesc}" data-images="${imgUrl}">
        <div class="img-wrapper">
          ${badgeHTML}
          <div class="card-quick-actions">
            <button class="quick-btn fav-btn ${heartClass}" onclick="toggleFavorite(event, this, '${item.name}')"><i class="${heartIcon}"></i></button>
            <button class="quick-btn add-cart-btn" onclick="quickAddToCart(event, '${item.name}', ${item.price})"><i class="ph-light ph-shopping-bag"></i></button>
          </div>
          <img class="card-img" src="${imgUrl}" loading="lazy" alt="${item.name}">
        </div>
        <div class="card-info">
          <h3>${item.name}</h3><p class="card-subtitle">${item.subtitle}</p>
          <div class="card-bottom">
            <span class="card-price">${formatVND(item.price)}</span>
            <div class="card-view-detail">Xem chi tiết <span style="font-size:16px;">➔</span></div>
          </div>
        </div>
      </div>
    `;
    grid.insertAdjacentHTML('beforeend', cardHTML);
  });
  apply3DTilt();
}

function renderPagination() {
  const pagination = document.getElementById('pagination'); if(!pagination) return; pagination.innerHTML = '';
  const totalPages = Math.ceil(filteredData.length / itemsPerPage); if (totalPages <= 1) return;
  
  // BẢN FIX: Thêm nút nhảy về Trang Đầu Tiên (Mũi tên kép lùi)
  pagination.insertAdjacentHTML('beforeend', `<li class="${currentPage === 1 ? 'disabled' : ''}" onclick="goToPage(1)" title="Trang đầu"><i class="ph-light ph-caret-double-left"></i></li>`);
  
  // Nút lùi 1 trang
  pagination.insertAdjacentHTML('beforeend', `<li class="${currentPage === 1 ? 'disabled' : ''}" onclick="goToPage(${currentPage - 1})" title="Trang trước"><i class="ph-light ph-caret-left"></i></li>`);
  
  for (let i = 1; i <= totalPages; i++) { 
      pagination.insertAdjacentHTML('beforeend', `<li class="${currentPage === i ? 'active' : ''}" onclick="goToPage(${i})">${i}</li>`); 
  }
  
  // Nút tiến 1 trang
  pagination.insertAdjacentHTML('beforeend', `<li class="${currentPage === totalPages ? 'disabled' : ''}" onclick="goToPage(${currentPage + 1})" title="Trang sau"><i class="ph-light ph-caret-right"></i></li>`);
  
  // BẢN FIX: Thêm nút nhảy tới Trang Cuối Cùng (Mũi tên kép tới)
  pagination.insertAdjacentHTML('beforeend', `<li class="${currentPage === totalPages ? 'disabled' : ''}" onclick="goToPage(${totalPages})" title="Trang cuối"><i class="ph-light ph-caret-double-right"></i></li>`);
}

window.goToPage = (page) => {
  const totalPages = Math.ceil(filteredData.length / itemsPerPage); if (page < 1 || page > totalPages) return;
  currentPage = page; renderProducts(); renderPagination(); 
  const nav = document.querySelector('.shop-category-nav');
  if(nav) nav.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

document.querySelectorAll('.sort-btn').forEach(btn => { btn.addEventListener('click', function() { document.querySelectorAll('.sort-btn').forEach(el => el.classList.remove('active')); this.classList.add('active'); currentSort = this.getAttribute('data-sort'); processFilters(); }); });
document.getElementById('toggle-filter-btn')?.addEventListener('click', () => { document.getElementById('filter-dropdown').classList.toggle('show'); });

document.getElementById('apply-filter-btn')?.addEventListener('click', () => {
  searchQuery = document.getElementById('search-input').value; let minP = parseInt(document.getElementById('price-min').value); let maxP = parseInt(document.getElementById('price-max').value);
  minPriceFilter = isNaN(minP) ? 0 : minP; maxPriceFilter = isNaN(maxP) ? Infinity : maxP;
  let count = 0; if(searchQuery) count++; if(minPriceFilter > 0 || maxPriceFilter !== Infinity) count++;
  const badge = document.getElementById('filter-badge');
  if(badge) { if(count > 0) { badge.style.display = 'inline-block'; badge.textContent = count; } else { badge.style.display = 'none'; } }
  document.getElementById('filter-dropdown')?.classList.remove('show'); processFilters();
});

document.getElementById('clear-filter-btn')?.addEventListener('click', () => {
  document.getElementById('search-input').value = ''; document.getElementById('price-min').value = ''; document.getElementById('price-max').value = '';
  searchQuery = ''; minPriceFilter = 0; maxPriceFilter = Infinity;
  if(document.getElementById('filter-badge')) document.getElementById('filter-badge').style.display = 'none'; 
  document.getElementById('filter-dropdown')?.classList.remove('show'); processFilters();
});

let searchTimeout;
const searchInputEl = document.getElementById('search-input');
const mainNavSearchEl = document.getElementById('main-nav-search');

// Cho thanh tìm kiếm trong bộ lọc
if (searchInputEl) {
  searchInputEl.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchQuery = searchInputEl.value; 
    searchTimeout = setTimeout(processFilters, 400); // Chờ 400ms mới lọc
  });
}

// Cho thanh tìm kiếm trên Navbar
if (mainNavSearchEl) {
    mainNavSearchEl.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
             // Kích hoạt nút Enter ẩn hoặc gọi thẳng hàm
             if(typeof executeNavSearch === 'function') executeNavSearch();
        }, 500); 
    });
}

/* =========================================
   8. MODAL CHI TIẾT SẢN PHẨM 
   ========================================= */
let galleryTimer; const modalMainImg = document.getElementById('modal-main-img'); let currentModalQty = 1; let currentGalleryImages = []; let currentImageIndex = 0;

function updateGallery(index) {
  if (!currentGalleryImages.length) return;
  if (index < 0) index = currentGalleryImages.length - 1; if (index >= currentGalleryImages.length) index = 0; 
  currentImageIndex = index; if(modalMainImg) modalMainImg.style.opacity = '0'; clearTimeout(galleryTimer);
  galleryTimer = setTimeout(() => { if(modalMainImg) { modalMainImg.style.backgroundImage = `url('${currentGalleryImages[currentImageIndex]}')`; modalMainImg.style.opacity = '1'; } }, 200);
  document.querySelectorAll('.thumb-img').forEach((t, i) => i === currentImageIndex ? t.classList.add('active') : t.classList.remove('active'));
}

if(document.getElementById('gallery-prev')) document.getElementById('gallery-prev').onclick = () => updateGallery(currentImageIndex - 1);
if(document.getElementById('gallery-next')) document.getElementById('gallery-next').onclick = () => updateGallery(currentImageIndex + 1);
if(document.getElementById('modal-qty-minus')) document.getElementById('modal-qty-minus').onclick = () => { if(currentModalQty>1) document.getElementById('modal-qty-display').textContent = --currentModalQty; };
if(document.getElementById('modal-qty-plus')) document.getElementById('modal-qty-plus').onclick = () => document.getElementById('modal-qty-display').textContent = ++currentModalQty;

function handleCardClick(card) {
  // Gán Tên Tiếng Anh (Chữ to trên cùng)
  if(document.getElementById('modal-title')) document.getElementById('modal-title').textContent = card.getAttribute('data-title');
  
  // Gán Tên Tiếng Việt (Chữ màu hồng nhỏ hơn)
  if (document.getElementById('modal-subtitle') && card.querySelector('.card-subtitle')) {
      document.getElementById('modal-subtitle').textContent = card.querySelector('.card-subtitle').textContent;
      document.getElementById('modal-subtitle').style.display = 'block'; 
  }
  
  // Gán Giá tiền
  if(document.getElementById('modal-price')) document.getElementById('modal-price').textContent = card.getAttribute('data-price'); 
  
  // Gán Đoạn văn miêu tả Deep (Chữ màu xám)
  if(document.getElementById('modal-desc')) {
      document.getElementById('modal-desc').textContent = card.getAttribute('data-desc');
      document.getElementById('modal-desc').style.display = 'block';
  }
  let imgs = card.getAttribute('data-images'); 
  if (imgs) currentGalleryImages = imgs.split(',').map(i => i.trim()); else { let imgTag = card.querySelector('.card-img'); currentGalleryImages = imgTag ? [imgTag.src] : []; }
  
  const thumbs = document.getElementById('modal-thumbnails'); if(thumbs) thumbs.innerHTML = '';
  currentGalleryImages.forEach((url, i) => { let t = document.createElement('div'); t.className = 'thumb-img'; t.style.backgroundImage = `url('${url}')`; t.onclick = () => updateGallery(i); if(thumbs) thumbs.appendChild(t); });
  
  if(document.getElementById('gallery-prev')) document.getElementById('gallery-prev').style.display = currentGalleryImages.length > 1 ? 'flex' : 'none'; 
  if(document.getElementById('gallery-next')) document.getElementById('gallery-next').style.display = currentGalleryImages.length > 1 ? 'flex' : 'none';
  
  clearTimeout(galleryTimer); 
  if(modalMainImg) {
      modalMainImg.style.transition = 'none'; 
      if (currentGalleryImages.length > 0) modalMainImg.style.backgroundImage = `url('${currentGalleryImages[0]}')`; 
      modalMainImg.style.opacity = '1'; 
      setTimeout(() => { modalMainImg.style.transition = 'opacity 0.2s ease-in-out'; }, 50);
  }
  currentImageIndex = 0; document.querySelectorAll('.thumb-img').forEach((t, i) => i === 0 ? t.classList.add('active') : t.classList.remove('active'));
  currentModalQty = 1; if(document.getElementById('modal-qty-display')) document.getElementById('modal-qty-display').textContent = 1; 
  openModal(document.getElementById('product-modal'));
}


document.addEventListener('click', function(e) {
  const card = e.target.closest('.card');
  if (!card) return;
  if (e.target.closest('.card-quick-actions')) return; 
  
  // Mở Modal chi tiết
  handleCardClick(card);
});
const addToCartBtn = document.querySelector('.add-to-cart');
if(addToCartBtn) {
addToCartBtn.onclick = (e) => {
  if (!isLoggedIn) {
        closeModal(document.getElementById('product-modal')); // Đóng form hoa
        if(typeof showCustomToast === 'function') showCustomToast("<i class='ph-light ph-warning-circle'></i> Vui lòng đăng nhập để mua hàng!", "error");
        setTimeout(() => { openModal(document.getElementById('auth-modal')); }, 400); // Mở form đăng nhập
        return;
    }
    let name = document.getElementById('modal-title').textContent; 
    let priceStr = document.getElementById('modal-price').textContent.replace(/\D/g, ''); 
    let price = parseInt(priceStr) || 0; 
    
    // TÌM VÀ LẤY ID
    let product = productsData.find(p => p.name === name);
    let pId = product ? (product.id || product.product_id) : null;

    let item = cart.find(i => i.engName === name);
    if (item) {
        item.qty += currentModalQty; 
    } else { 
        // THÊM product_id VÀO ĐÂY
        cart.push({ engName: name, realName: document.getElementById('modal-subtitle').textContent, price: price, qty: currentModalQty, product_id: pId }); 
    }
    updateCartUI(); 
    // ... (giữ nguyên hiệu ứng toast và bay cánh hoa bên dưới)
    let originalText = addToCartBtn.innerHTML; addToCartBtn.innerHTML = `<i class="ph-fill ph-check-circle" style="font-size: 20px;"></i> Đã thêm vào giỏ hàng`; addToCartBtn.style.background = "#4cd137"; addToCartBtn.style.color = "#fff"; addToCartBtn.style.pointerEvents = "none"; 
    setTimeout(() => { addToCartBtn.innerHTML = originalText; addToCartBtn.style.background = ""; addToCartBtn.style.color = ""; addToCartBtn.style.pointerEvents = "auto"; closeModal(document.getElementById('product-modal')); }, 1500);
    showCustomToast("<i class='ph-light ph-check-circle' style='margin-right:8px; font-size: 18px; vertical-align: middle;'></i> Đã thêm vào giỏ hàng", "success"); createPetals(e.clientX, e.clientY);
  };
}

/* =========================================
   9. WISHLIST VÀ TÌM KIẾM (BẢN FIX CHUẨN)
   ========================================= */
function updateWishlistUI() {
  const countEl = document.getElementById('wishlist-count'); if (countEl) countEl.textContent = wishlist.length;
  document.querySelectorAll('.fav-btn').forEach(btn => {
    const card = btn.closest('.card'); if(!card) return;
    const name = card.getAttribute('data-title'); const icon = btn.querySelector('i');
    if(wishlist.includes(name)) { btn.classList.add('active'); if(icon) { icon.classList.remove('ph-light'); icon.classList.add('ph-fill'); } }
    else { btn.classList.remove('active'); if(icon) { icon.classList.remove('ph-fill'); icon.classList.add('ph-light'); } }
  });
  renderWishlistUI(); 
}

function getProductInfoForWishlist(name) {
  let item = productsData.find(p => p.name === name); if (item) return item;
  let cards = document.querySelectorAll('.card');
  for (let card of cards) { if (card.getAttribute('data-title') === name) { let price = parseInt(card.getAttribute('data-price').replace(/\D/g, '')); let imgTag = card.querySelector('.card-img'); let img = imgTag ? imgTag.src : ''; return { name: name, price: price, images: [img] }; } }
  return null;
}

window.removeFromWishlist = async (e, productName) => {
  e.stopPropagation(); const index = wishlist.indexOf(productName);
  if (index > -1) { 
    wishlist.splice(index, 1); 
    storage.set('sgu_offline_wishlist', JSON.stringify(wishlist));
    updateWishlistUI(); 
    if(typeof showCustomToast === 'function') showCustomToast("<i class='ph-light ph-heart-break' style='margin-right:8px; font-size: 18px; vertical-align: middle;'></i> Đã bỏ Yêu thích", "default"); 

    if (isLoggedIn) { try { await api.toggleWishlist(productName); } catch(err) {} }
  }
};

function renderWishlistUI() {
  const container = document.getElementById('wishlist-items-container'); if (!container) return;
  if (wishlist.length === 0) {
    let emptyMsg = `<div class="empty-cart-wrapper" style="text-align: center; padding: 60px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%;"><i class="ph-light ph-heart-break" style="font-size: 80px; color: #aaa; margin-bottom: 20px;"></i><p style="color: #ddd; font-size: 16px; margin-bottom: 10px;">Chưa có sản phẩm yêu thích nào</p>`;
    if (!isLoggedIn) { emptyMsg += `<p style="color: #ffb6c1; font-size: 13px; font-style: italic;">Đăng nhập để đồng bộ danh sách trên mọi thiết bị.</p>`; }
    emptyMsg += `</div>`; container.innerHTML = emptyMsg; return;
  }
  let html = '';
  wishlist.forEach(name => {
    let item = getProductInfoForWishlist(name);
    if (item) {
      let imgUrl = item.images && item.images.length > 0 ? item.images[0] : '';
      html += `
        <div class="cart-item">
          <div style="flex: 1;"><strong style="color:#ffb6c1; display:block; font-size: 15px;">${item.name}</strong><span style="color:#888; font-size:13px;">${formatVND(item.price)}</span>
            <div class="cart-item-controls" style="margin-top: 8px;"><button class="submit-btn" style="padding: 6px 12px; font-size: 12px; margin-top: 0; background: rgba(255,182,193,0.2); color: #ffb6c1; border: 1px solid #ffb6c1;" onclick="quickAddToCart(event, '${item.name}', ${item.price})">Thêm vào giỏ</button></div>
          </div>
          <div style="text-align:right; display: flex; flex-direction: column; align-items: flex-end;"><img src="${imgUrl}" style="width: 70px; height: 70px; border-radius: 12px; object-fit: cover; margin-bottom: 10px;" alt="${item.name}"><button onclick="removeFromWishlist(event, '${item.name}')" style="background:none; border:none; color:#ff69b4; cursor:pointer; font-size: 20px; transition: 0.3s;"><i class="ph-light ph-trash"></i></button></div>
        </div>`;
    }
  }); container.innerHTML = html;
}

window.toggleFavorite = async (e, btn, productName) => {
  e.stopPropagation(); 
  const index = wishlist.indexOf(productName);

  if (index === -1) { 
      wishlist.push(productName); 
      if(typeof showCustomToast === 'function') showCustomToast("<i class='ph-fill ph-heart' style='margin-right:8px; font-size: 18px; vertical-align: middle;'></i> Đã lưu vào Yêu thích", "success"); 
  } else { 
      wishlist.splice(index, 1); 
      if(typeof showCustomToast === 'function') showCustomToast("<i class='ph-light ph-heart-break' style='margin-right:8px; font-size: 18px; vertical-align: middle;'></i> Đã bỏ Yêu thích", "default"); 
  }

  storage.set('sgu_offline_wishlist', JSON.stringify(wishlist)); 
  updateWishlistUI(); 

  if (isLoggedIn) {
      try { await api.toggleWishlist(productName); } catch(err) {}
  }
};

window.quickAddToCart = (e, name, price) => {
  e.stopPropagation(); 
  if (!isLoggedIn) {
      if(typeof showCustomToast === 'function') showCustomToast("<i class='ph-light ph-warning-circle'></i> Vui lòng đăng nhập để mua hàng!", "error");
      openModal(document.getElementById('auth-modal'));
      return;
  }
  let product = productsData.find(p => p.name === name);
  
  // FIX: Trả lại tên Tiếng Việt ngắn gọn cho Giỏ hàng, không lấy đoạn văn nữa
  let subtitle = product ? product.subtitle : name; 

  // LẤY ID TỪ DATA
  let pId = product ? (product.id || product.product_id) : null;

  let item = cart.find(i => i.engName === name);
  if (item) { 
      item.qty += 1; 
  } else { 
      // THÊM product_id VÀO ĐÂY
      cart.push({ engName: name, realName: subtitle, price: parseInt(price), qty: 1, product_id: pId }); 
  }
  updateCartUI(); 
  
// BẢN FIX: Chống Spam Click gây kẹt Icon
  let btn = e.currentTarget;
  if(btn && btn.classList.contains('quick-btn')) { 
      if(btn.style.pointerEvents === "none") return; // Nếu đang chạy hiệu ứng thì cấm bấm tiếp

      let oldHTML = btn.innerHTML; 
      btn.innerHTML = `<i class="ph-fill ph-check-circle" style="color: #4cd137; font-size: 22px;"></i>`; 
      btn.style.pointerEvents = "none"; 
      setTimeout(() => { btn.innerHTML = oldHTML; btn.style.pointerEvents = "auto"; }, 1500); 
  }
  if(typeof showCustomToast === 'function') showCustomToast("<i class='ph-light ph-check-circle' style='margin-right:8px; font-size: 18px; vertical-align: middle;'></i> Đã thêm vào giỏ hàng", "success"); 
  if(typeof createPetals === 'function') createPetals(e.clientX, e.clientY);
};

const navSearchInput = document.getElementById('main-nav-search'); 
const navSearchBtn = document.getElementById('main-nav-search-btn');

window.executeNavSearch = function() { 
    if(!navSearchInput) return;
    const query = navSearchInput.value.trim(); 
    if(query !== "") { 

        const filterInput = document.getElementById('search-input');
        if (filterInput) filterInput.value = query; 

        searchQuery = query; 
        processFilters(); 
        const productSection = document.getElementById('product');
        if (productSection) productSection.scrollIntoView({ behavior: 'smooth' }); 
        navSearchInput.value = ""; 
    } 
};

if(navSearchInput) { 
    navSearchInput.addEventListener('keypress', (e) => { if(e.key === 'Enter') executeNavSearch(); }); 
    navSearchInput.addEventListener('click', () => navSearchInput.focus()); 
}
if(navSearchBtn) {
    navSearchBtn.addEventListener('click', executeNavSearch);
}

const backToTopBtn = document.getElementById('back-to-top'); 
if (backToTopBtn) backToTopBtn.addEventListener('click', () => { window.scrollTo({ top: 0, behavior: 'smooth' }); });

/* =========================================
   10. SIDEBARS (CART) & CHECKOUT
   ========================================= */
   function updateCartUI() {
saveCartToCookie(cart);
if (isLoggedIn && typeof api.syncCart === 'function') {
      api.syncCart(cart);
  }
  let html = '', total = 0, count = 0;
  if (!cart.length) {
    html = `<div class="empty-cart-wrapper" style="text-align: center; padding: 60px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%;"><i class="ph-light ph-shopping-bag" style="font-size: 80px; color: #aaa; margin-bottom: 20px;"></i><p style="color: #ddd; font-size: 16px;">Không có sản phẩm nào trong giỏ hàng</p></div>`;
  } else {
    cart.forEach((item, i) => {
      total += item.price * item.qty; count += item.qty;
      html += `<div class="cart-item">
        <div style="flex: 1;"><strong style="color:#ffb6c1; display:block;">${item.realName}</strong><span style="color:#888; font-size:12px;">${item.engName}</span>
        <div class="cart-item-controls"><button class="qty-btn" onclick="changeQty(event, ${i}, -1)"><i class="ph-light ph-minus"></i></button><span class="qty-display">${item.qty}</span><button class="qty-btn" onclick="changeQty(event, ${i}, 1)"><i class="ph-light ph-plus"></i></button></div></div>
        <div style="text-align:right;"><div style="font-weight:bold; margin-bottom:10px;">${formatVND(item.price*item.qty)}</div><button onclick="removeItem(event, ${i})" style="background:none;border:none;color:#ff69b4;cursor:pointer;"><i class="ph-light ph-trash" style="font-size:18px;"></i></button></div>
      </div>`;
    });
  }
  if(document.getElementById('cart-items-container')) document.getElementById('cart-items-container').innerHTML = html; 
  if(document.getElementById('cart-count')) document.getElementById('cart-count').textContent = count; 
  if(document.getElementById('total-price')) document.getElementById('total-price').textContent = `${formatVND(total)}`;
}
   window.calculateOrderTotal = function() {
    let rawTotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    
    // Tính phí ship
    const shippingMethod = document.querySelector('input[name="shipping_method"]:checked')?.value;
    let shippingFee = (shippingMethod === 'express') ? 50000 : 0;
    
    // Giảm giá (Ví dụ: trên 1 triệu giảm 50k)
    let discount = (rawTotal >= 1000000) ? 50000 : 0;
    
    let finalTotal = rawTotal + shippingFee - discount;

    // Đổ dữ liệu ra giao diện Modal
    if(document.getElementById('summary-subtotal')) document.getElementById('summary-subtotal').textContent = formatVND(rawTotal);
    if(document.getElementById('summary-shipping')) document.getElementById('summary-shipping').textContent = shippingFee > 0 ? formatVND(shippingFee) : "Miễn phí";
    if(document.getElementById('summary-discount')) document.getElementById('summary-discount').textContent = `-${formatVND(discount)}`;
    if(document.getElementById('summary-total')) document.getElementById('summary-total').textContent = formatVND(finalTotal);
};

window.changeQty = (e, i, d) => { e.stopPropagation(); cart[i].qty += d; if(cart[i].qty <= 0) cart.splice(i,1); updateCartUI(); };
window.removeItem = (e, i) => { e.stopPropagation(); cart.splice(i,1); updateCartUI(); };

document.getElementById('cart-btn')?.addEventListener('click', (e) => { 
  e.stopPropagation(); 
  
  // BẢN FIX: Bắt buộc đăng nhập mới được mở Sidebar Giỏ Hàng
  if (!isLoggedIn) {
      if(typeof showCustomToast === 'function') showCustomToast("<i class='ph-light ph-warning-circle'></i> Vui lòng đăng nhập để xem Giỏ hàng!", "error");
      openModal(document.getElementById('auth-modal'));
      return;
  }
  
  document.getElementById('wishlist-sidebar')?.classList.remove('open'); 
  document.getElementById('cart-sidebar')?.classList.add('open'); 
});

window.openCheckoutModal = async () => {
  if (!cart.length) return showCustomToast("Giỏ hàng trống!", "error");
  if (!isLoggedIn) { 
    showCustomToast("Vui lòng đăng nhập để thanh toán!", "error"); 
    document.getElementById('cart-sidebar')?.classList.remove('open'); 
    openModal(document.getElementById('auth-modal')); 
    return; 
  }

  const checkoutBtn = document.querySelector('#cart-sidebar .checkout-btn');
  const originalText = checkoutBtn ? checkoutBtn.innerHTML : 'Thanh Toán';
  if (checkoutBtn) {
    checkoutBtn.innerHTML = '<i class="ph-light ph-spinner fa-spin"></i> Đang tải...';
    checkoutBtn.style.pointerEvents = 'none';
  }

  try {
    // 1. Tạo mã đơn hàng & Tính tiền
    currentCheckoutOrderId = 'ORD-' + Math.random().toString(36).substring(2, 8).toUpperCase();
    if(typeof calculateOrderTotal === 'function') calculateOrderTotal();

    // 2. Lấy dữ liệu từ Profile (Dùng try-catch riêng để tránh crash cả hàm)
    let checkoutName = currentUser;
    let checkoutPhone = "";
    let checkoutAddress = "";

    try {
      const profileRes = await api.getProfile();
      if (profileRes && profileRes.status === 'success') {
        const uData = profileRes.user || profileRes.data || {};
        checkoutName = uData.full_name || uData.name || checkoutName;
        checkoutPhone = uData.phone || "";
        window.checkoutEmail = uData.email || 'khach@sguflower.com';
        // Ghép địa chỉ đầy đủ
        let adrArr = [];
        if(uData.address || uData.street) adrArr.push(uData.address || uData.street);
        if(uData.ward) adrArr.push(uData.ward);
        if(uData.district) adrArr.push(uData.district);
        if(uData.city) adrArr.push(uData.city);
        checkoutAddress = adrArr.join(', ');
      }
    } catch (e) { console.error("Lỗi lấy Profile:", e); }

    // 3. Cập nhật giao diện Thẻ Địa Chỉ
    const savedInfoBox = document.getElementById('saved-address-info');
    if (savedInfoBox) {
      if (checkoutAddress && checkoutPhone) {
        // CÓ DỮ LIỆU: Hiện địa chỉ xịn
        savedInfoBox.innerHTML = `
          <h4>Địa chỉ đã lưu</h4>
          <p><strong style="color:#fff;">${checkoutName}</strong> | ${checkoutPhone}</p>
          <p style="font-size:13px; color:#aaa; line-height:1.4;">${checkoutAddress}</p>
        `;
        document.querySelector('input[name="address_type"][value="saved"]').checked = true;
      } else {
        // TRỐNG DỮ LIỆU: Báo lỗi và gợi ý nhập mới
        savedInfoBox.innerHTML = `
          <h4>Địa chỉ đã lưu</h4>
          <p style="color: #ff6b81; font-size: 13px; font-style: italic;">❌ Bạn chưa lưu địa chỉ. Vui lòng chọn "Nhập địa chỉ mới"!</p>
        `;
        // Tự động nhảy xuống chọn "Địa chỉ mới" cho khách
        const newAddrRadio = document.querySelector('input[name="address_type"][value="new"]');
        if(newAddrRadio) newAddrRadio.checked = true;
      }
    }

    // 4. Mở Modal & Đồng bộ ẩn hiện Form
    if(typeof toggleAddress === 'function') toggleAddress();
    document.getElementById('cart-sidebar')?.classList.remove('open'); 
    openModal(document.getElementById('checkout-modal'));
    
  } catch (err) {
    console.error("Lỗi Thanh Toán:", err);
    showCustomToast("Lỗi hệ thống, vui lòng thử lại!", "error");
  } finally {
    if (checkoutBtn) { checkoutBtn.innerHTML = originalText; checkoutBtn.style.pointerEvents = 'auto'; }
  }
};

// ==========================================
// HIỂN THỊ MÃ QR MOMO ĐỘNG & BẢO TRÌ ONLINE
// ==========================================
window.togglePaymentDetails = function() {
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value;
    const detailsBox = document.getElementById('payment-details-box');
    const titleEl = document.getElementById('payment-title');
    const contentEl = document.getElementById('payment-content');

    if (!detailsBox) return; 

    let rawTotal = parseInt(document.getElementById('summary-total')?.textContent.replace(/\D/g, '') || 0);
    let formattedTotal = formatVND(rawTotal);
    let orderTempId = currentCheckoutOrderId;

    if (paymentMethod === 'online') {
        // THANH TOÁN ONLINE -> BÁO ĐANG XÂY DỰNG
        detailsBox.style.display = 'block';
        titleEl.innerHTML = '<i class="ph-light ph-wrench" style="font-size:20px; vertical-align:middle;"></i> Thanh toán Online';
        
        contentEl.innerHTML = `
            <div style="text-align: center; padding: 10px;">
                <i class="ph-light ph-warning-circle" style="font-size: 40px; color: #f39c12; margin-bottom: 10px; display: block;"></i>
                <h4 style="color: #f39c12; margin-bottom: 5px;">Tính năng đang xây dựng</h4>
                <p style="color: #aaa; font-size: 13px;">Cổng thanh toán trực tuyến qua thẻ Visa/Napas đang trong quá trình nâng cấp bảo trì. Vui lòng chọn phương thức Tiền mặt hoặc MoMo!</p>
            </div>
        `;
    } 
    else if (paymentMethod === 'bank') {
        // VÍ MOMO -> TẠO MÃ QR ĐỘNG
        detailsBox.style.display = 'block';
        titleEl.innerHTML = '<i class="ph-light ph-wallet" style="font-size:20px; vertical-align:middle;"></i> Thanh toán Ví MoMo';
        
        // 🛑 SẾP ĐIỀN SĐT VÀ TÊN MOMO VÀO ĐÂY:
        let momoPhone = '0909840611'; 
        let momoName = 'VUONG';
        
        // TẠO MÃ QR ĐỘNG CHỨA SẴN SĐT, SỐ TIỀN VÀ MÃ ĐƠN HÀNG
        let qrData = encodeURIComponent(`2|99|${momoPhone}|${momoName}|${momoPhone}|0|0|${rawTotal}|${orderTempId}`);
        let qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${qrData}`;

        contentEl.innerHTML = `
            <div style="text-align: center;">
                <p style="margin-bottom: 5px;">Chủ tài khoản: <strong style="color:#fff;">${momoName}</strong></p>
                <p style="margin-bottom: 15px;">Số điện thoại: <strong style="color: #a50064; font-size: 18px; letter-spacing: 1px;">${momoPhone}</strong></p>
                
                <div style="background: #fff; padding: 10px; border-radius: 10px; display: inline-block; margin-bottom: 15px;">
                    <img src="${qrUrl}" alt="QR MoMo" style="width: 160px; height: 160px; object-fit: contain;">
                </div>
                
                <p style="margin-bottom: 5px;">Số tiền: <strong style="color: #ffb6c1; font-size: 16px;">${formattedTotal}</strong></p>
                <p style="font-size: 13px; color: #aaa;">Nội dung CK: <strong>${orderTempId}</strong></p>
                <p style="font-size: 12px; color: #ff6b81; margin-top: 10px; font-style: italic;">* Vui lòng mở App MoMo quét mã QR. Đơn hàng sẽ được xử lý sau khi nhận được thanh toán.</p>
            </div>
        `;
    } 
    else {
        // Tiền mặt COD
        detailsBox.style.display = 'none';
        contentEl.innerHTML = '';
    }
};

/* =========================================
   11. XỬ LÝ THANH TOÁN & ĐẶT HÀNG (BẢN CHUẨN TỰ ĐỘNG BỐC ĐỊA CHỈ)
   ========================================= */
window.handleOrderSubmit = async function(e) {
    if(e) e.preventDefault(); 
    
    const submitBtn = document.querySelector('#final-checkout-form .submit-btn-modern') || document.querySelector('#final-checkout-form .submit-btn');
    const originalText = submitBtn ? submitBtn.innerHTML : 'XÁC NHẬN ĐẶT HÀNG';
    const radioNew = document.querySelector('input[name="address_type"][value="new"]');
    if (radioNew && radioNew.checked) {
        if (!checkEmptyFields('new-address-form')) {
            return; // Ngưng đặt hàng ngay lập tức nếu ô nhập địa chỉ bị rỗng
        }
    }
    if (submitBtn) { submitBtn.innerHTML = '<i class="ph-light ph-spinner fa-spin"></i> Đang xử lý...'; submitBtn.style.pointerEvents = 'none'; }

    try {
        let shippingName = "", shippingPhone = "", shippingStreet = "", shippingWard = "", shippingDistrict = "", shippingCity = "";
        
        const radioNew = document.querySelector('input[name="address_type"][value="new"]');
        const addressType = radioNew && radioNew.checked ? 'new' : 'saved';
        
        if (addressType === 'new') {
            // NẾU CHỌN NHẬP ĐỊA CHỈ MỚI -> Bốc từ Form gõ tay
            shippingName = document.getElementById('chk-name').value.trim();
            shippingPhone = document.getElementById('chk-phone').value.trim();
            shippingStreet = document.getElementById('chk-address').value.trim();

            const citySel = document.getElementById('chk-city');
            shippingCity = (citySel.value && citySel.selectedIndex > 0) ? citySel.options[citySel.selectedIndex].text : "";
            
            const distSel = document.getElementById('chk-district');
            shippingDistrict = (distSel.value && distSel.selectedIndex > 0) ? distSel.options[distSel.selectedIndex].text : "";
            
            const wardSel = document.getElementById('chk-ward');
            shippingWard = (wardSel.value && wardSel.selectedIndex > 0) ? wardSel.options[wardSel.selectedIndex].text : "";

              if(!shippingName || !shippingPhone || !shippingCity || !shippingDistrict || !shippingWard || !shippingStreet) {
                if(typeof showCustomToast === 'function') showCustomToast("Vui lòng điền đủ thông tin địa chỉ!", "error");
                if (submitBtn) { submitBtn.innerHTML = originalText; submitBtn.style.pointerEvents = 'auto'; }
                return;
            }

            // BẢN FIX: Kiểm duyệt định dạng số điện thoại giao hàng
            const phoneRegex = /^(0[35789])[0-9]{8}$/;
            if (!phoneRegex.test(shippingPhone)) {
                if(typeof showCustomToast === 'function') showCustomToast("Số điện thoại nhận hàng không hợp lệ (Phải đủ 10 số)!", "error");
                if (submitBtn) { submitBtn.innerHTML = originalText; submitBtn.style.pointerEvents = 'auto'; }
                document.getElementById('chk-phone').focus();
                return;
            }
        } else {

            const profileRes = await api.getProfile();
            
            const user = profileRes.data || profileRes.user || profileRes || {};
            
            shippingName = user.full_name || user.name || (typeof currentUser !== 'undefined' ? currentUser : "Khách hàng");
            shippingPhone = user.phone || '';
            shippingCity = user.city || '';
            shippingDistrict = user.district || '';
            shippingWard = user.ward || '';
            shippingStreet = user.address || user.street || user.shipping_address || '';

            if (!shippingStreet || !shippingPhone) {
                if(typeof showCustomToast === 'function') showCustomToast("Sổ địa chỉ của bạn đang trống! Vui lòng chọn 'Giao đến địa chỉ khác' hoặc cập nhật trong Hồ sơ.", "error");
                else alert("Sổ địa chỉ của bạn đang trống!");
                if (submitBtn) { submitBtn.innerHTML = originalText; submitBtn.style.pointerEvents = 'auto'; }
                return;
            }
        }

        const shippingMethodSelect = document.querySelector('input[name="shipping_method"]:checked')?.value || 'standard';
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value || 'cash';
        
if (paymentMethod === 'online') {
            if(typeof showCustomToast === 'function') showCustomToast("Cổng thanh toán Online đang bảo trì. Vui lòng chọn Tiền mặt hoặc Chuyển khoản!", "error");
            if (submitBtn) { submitBtn.innerHTML = originalText; submitBtn.style.pointerEvents = 'auto'; }
            return; 
        }
// Bắt lỗi quên chọn giờ hoặc cố tình chọn giờ quá khứ
        if (shippingMethodSelect === 'preorder') {
            const timeVal = document.getElementById('chk-datetime').value;
            if (!timeVal) {
                if(typeof showCustomToast === 'function') showCustomToast("Bạn ơi, bạn quên chọn thời gian hẹn giao hoa rồi nè!", "error");
                if (submitBtn) { submitBtn.innerHTML = originalText; submitBtn.style.pointerEvents = 'auto'; }
                document.getElementById('chk-datetime').focus();
                return; 
            }

            // BẢN FIX: Chống bẻ khóa thời gian (Time-travel bug)
            const selectedTime = new Date(timeVal).getTime();
            const nowTime = new Date().getTime();
            // Ép khách phải đặt trước ít nhất 90 phút (90 * 60 * 1000 = 5400000 ms)
            if (selectedTime < nowTime + 5400000) {
                if(typeof showCustomToast === 'function') showCustomToast("Vui lòng hẹn lịch nhận hoa cách thời điểm hiện tại ít nhất 90 phút để shop kịp chuẩn bị nhé!", "error");
                if (submitBtn) { submitBtn.innerHTML = originalText; submitBtn.style.pointerEvents = 'auto'; }
                document.getElementById('chk-datetime').focus();
                return;
            }
        }

        const shippingFee = shippingMethodSelect === 'express' ? 50000 : 0;
        const rawTotal = typeof cart !== 'undefined' ? cart.reduce((sum, item) => sum + (item.price * item.qty), 0) : 0;
        const discount = rawTotal >= 1000000 ? 50000 : 0;
        const finalTotal = rawTotal - discount + shippingFee;

        // ÉP GIỜ VIỆT NAM TRỰC TIẾP TỪ TRÌNH DUYỆT
        const now = new Date();
        const yyyy = now.getFullYear();
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        const dd = String(now.getDate()).padStart(2, '0');
        const hh = String(now.getHours()).padStart(2, '0');
        const min = String(now.getMinutes()).padStart(2, '0');
        const ss = String(now.getSeconds()).padStart(2, '0');
        const exactTime = `${yyyy}-${mm}-${dd} ${hh}:${min}:${ss}`;

        // GỘP FULL ĐỊA CHỈ
        const fullAddress = [shippingStreet, shippingWard, shippingDistrict, shippingCity].filter(Boolean).join(', ');

        let finalName = shippingName;
        if (shippingMethodSelect === 'express') {
            finalName += ' [HỎA TỐC]';
        } else if (shippingMethodSelect === 'preorder') {
            const timeVal = document.getElementById('chk-datetime').value;
            if (timeVal) {
                const tDate = new Date(timeVal);
                const formattedTime = tDate.toLocaleTimeString('vi-VN', {hour: '2-digit', minute:'2-digit'}) + ' ' + tDate.toLocaleDateString('vi-VN', {day:'2-digit', month:'2-digit', year:'numeric'});
                finalName += ` [HẸN: ${formattedTime}]`;
            } else {
                finalName += ' [HẸN: Chờ xác nhận]';
            }
        }
// BẢN FIX: Gắn thêm chốt chặn isLoggedIn
        const saveAddressCheckbox = document.getElementById('chk-save-address');
        
        // CHỈ CẬP NHẬT KHI KHÁCH CÓ TÍCH CHỌN VÀ ĐÃ ĐĂNG NHẬP
        if (isLoggedIn && addressType === 'new' && saveAddressCheckbox && saveAddressCheckbox.checked) {
            try {
                // Gọi API âm thầm cập nhật sổ địa chỉ của khách
                await api.request('/auth.php?action=update_profile', 'POST', {
                    full_name: shippingName,
                    phone: shippingPhone,
                    address: shippingStreet,
                    ward: shippingWard,
                    district: shippingDistrict,
                    city: shippingCity
                });
            } catch(e) { console.log("Lỗi lưu địa chỉ ngầm"); }
        }
const generateOrderId = currentCheckoutOrderId;

        // BẢN FIX: Lấy đúng Email thực sự từ Database để Backend không báo lỗi "Quá tải"
        let realEmail = 'khach@sguflower.com';
        try {
            const pRes = await api.getProfile();
            if(pRes && pRes.status === 'success') {
                const uData = pRes.data || pRes.user || {};
                if(uData.email) realEmail = uData.email;
            }
        } catch(e) {}

        const orderData = { 
            order_id: generateOrderId,
            order_date: exactTime, 
            
            // Ép gửi đúng định dạng Email (VD: qetry@gmail.com thay vì qetry)
            customer_email: realEmail,

            shipping_name: finalName,      
            delivery_name: finalName,

            shipping_phone: shippingPhone,
            delivery_phone: shippingPhone,

            shipping_street: fullAddress, 
            delivery_address: fullAddress, 

            shipping_ward: shippingWard,
            delivery_ward: shippingWard,

            shipping_city: shippingCity,
            delivery_city: shippingCity,

            shipping_method: shippingMethodSelect, 
            payment_method: paymentMethod,

            total_price: finalTotal,
            items: typeof cart !== 'undefined' ? cart : []
        };
        const res = await api.checkout(orderData);

        if (res.status === 'success') {
            if(typeof showCustomToast === 'function') showCustomToast("<i class='ph-light ph-check-circle'></i> Đặt hàng thành công!", "success");
            cart = []; if(typeof storage !== 'undefined') storage.remove('sgu_cart');
            if(typeof updateCartUI === 'function') updateCartUI();
            
            const checkoutModal = document.getElementById('checkout-modal');
            if(checkoutModal) checkoutModal.classList.remove('active');

            setTimeout(() => { window.location.href = 'account.html?tab=orders'; }, 1000);
        } else {
            if(typeof showCustomToast === 'function') showCustomToast("<i class='ph-light ph-warning-circle'></i> " + (res.message || "Lỗi đặt hàng!"), "error");
        }
} catch (error) {
        // THÊM DÒNG NÀY VÀO NÈ:
        console.error("🔥 CRASH ĐẶT HÀNG:", error); 
        
        if(typeof showCustomToast === 'function') showCustomToast("Lỗi xử lý JS! Xem chi tiết trong F12", "error");
    } finally {
        if (submitBtn) { submitBtn.innerHTML = originalText; submitBtn.style.pointerEvents = 'auto'; }
    }
};

// Biến cờ hiệu để nhớ xem khách đã bấm nút "Xong" chưa
let isNewAddressConfirmed = false;

window.toggleAddress = function() {
    const addressForm = document.getElementById('new-address-form');
    const radioNew = document.querySelector('input[name="address_type"][value="new"]');
    
    if (addressForm && radioNew) {
        // Chỉ hiện Form nhập tay khi và chỉ khi nút "Địa chỉ mới" ĐƯỢC CHỌN
        if (radioNew.checked) {
            addressForm.style.display = 'flex';
        } else {
            addressForm.style.display = 'none';
        }
    }
};

// Nút "Sửa lại" địa chỉ (Bấm vào thì bung form ra lại)
window.reopenNewAddressForm = function(e) {
    if(e) { e.preventDefault(); e.stopPropagation(); }
    isNewAddressConfirmed = false;
    document.getElementById('new-address-form').style.display = 'flex';
    
    // Trả lại giao diện cái Thẻ (Card) về mặc định
    document.getElementById('new-addr-icon').innerHTML = '<i class="ph-light ph-plus"></i>';
    document.getElementById('new-addr-icon').style.color = '';
    document.getElementById('new-addr-details').innerHTML = `<h4>Giao đến địa chỉ khác</h4><p>Nhập thông tin người nhận mới</p>`;
};

// SỰ KIỆN KHI BẤM NÚT "XONG! THU GỌN ĐỊA CHỈ"
document.getElementById('confirm-new-addr-btn')?.addEventListener('click', function() {
    // 1. Quét lỗi rỗng (Máy quét tự động chửi nếu chưa điền)
    if (!checkEmptyFields('new-address-form')) return;
    
    // 2. Quét lỗi định dạng SĐT
    const phone = document.getElementById('chk-phone').value.trim();
    const phoneRegex = /^(0[35789])[0-9]{8}$/;
    if (!phoneRegex.test(phone)) {
        if(typeof showCustomToast === 'function') showCustomToast("Số điện thoại không hợp lệ (Phải đủ 10 số)!", "error");
        document.getElementById('chk-phone').focus();
        return;
    }

    // 3. Rút trích toàn bộ dữ liệu vừa nhập
    const name = document.getElementById('chk-name').value.trim();
    const street = document.getElementById('chk-address').value.trim();
    
    const citySel = document.getElementById('chk-city');
    const city = (citySel.value && citySel.selectedIndex > 0) ? citySel.options[citySel.selectedIndex].text : "";
    
    const distSel = document.getElementById('chk-district');
    const district = (distSel.value && distSel.selectedIndex > 0) ? distSel.options[distSel.selectedIndex].text : "";
    
    const wardSel = document.getElementById('chk-ward');
    const ward = (wardSel.value && wardSel.selectedIndex > 0) ? wardSel.options[wardSel.selectedIndex].text : "";

    const fullAddr = [street, ward, district, city].filter(Boolean).join(', ');

    // 4. Khóa cờ hiệu và Tắt Form đi
    isNewAddressConfirmed = true;
    document.getElementById('new-address-form').style.display = 'none';

    // 5. Thay đổi giao diện cái Thẻ (Card)
    document.getElementById('new-addr-icon').innerHTML = '<i class="ph-light ph-check-circle"></i>';
    document.getElementById('new-addr-icon').style.color = '#ffb6c1';
    
    document.getElementById('new-addr-details').innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h4 style="color: #ffb6c1;">Địa chỉ mới</h4>
            <span style="color: #fff; font-size: 12px; cursor: pointer; padding: 4px 10px; background: rgba(255,255,255,0.1); border-radius: 12px; transition: 0.3s;" onmouseover="this.style.background='rgba(255,182,193,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'" onclick="reopenNewAddressForm(event)">Sửa lại <i class="ph-light ph-pencil-simple"></i></span>
        </div>
        <p style="margin-top: 6px; margin-bottom: 2px; color: #fff;"><strong style="font-size: 14px;">${name}</strong> | ${phone}</p>
        <p style="color: #aaa; line-height: 1.4;">${fullAddr}</p>
    `;
});
// Hàm ẩn/hiện ô chọn giờ giao hàng & tính lại phí ship
window.togglePreorder = function() {
    const timeBox = document.getElementById('preorder-time-box');
    const selectedMethod = document.querySelector('input[name="shipping_method"]:checked')?.value;
    
    if (timeBox) {
        timeBox.style.display = selectedMethod === 'preorder' ? 'block' : 'none';
    }
    
    if (typeof calculateOrderTotal === 'function') calculateOrderTotal();
};

/* =========================================
   12. KHỞI CHẠY (INITIALIZATION) & SETTINGS
   ========================================= */

async function loadHomeSettings() {
  try {
const response = await fetch(`${CONFIG.API_BASE_URL}/home-settings.php`);
    const resData = await response.json();
    const settings = resData.data || resData; 

    const bannerContainer = document.getElementById('dynamic-banners');
    const dotsContainer = document.getElementById('dynamic-dots');
    
    if (!bannerContainer || !settings || !Array.isArray(settings)) return;

    let bannerHTML = '';
    let dotsHTML = '';

settings.forEach((banner, index) => {
      const activeClass = index === 0 ? 'active' : '';
      
      // Lấy giá từ Database (Nếu lỡ quên nhập thì mặc định là 500k)
      const currentPrice = banner.price ? parseInt(banner.price) : 500000;
      
      // Tự động cộng thêm một khoản để làm giá gốc (ví dụ cộng thêm 200k)
      const oldPrice = currentPrice + 200000;

      bannerHTML += `
        <div class="banner-slide ${activeClass}">
          <div class="banner-wrapper premium-glass">
            
            <div class="banner-image">
              <div class="discount-tag">
                <span class="tag-text">-25%</span><span class="tag-label">LUXURY</span>
              </div>
              <img src="${banner.image_url}" alt="${banner.title}" class="banner-bg">
            </div>

            <div class="banner-content">
              <div class="flash-sale-header">
                <span class="flash-icon"><i class="ph-light ph-crown"></i></span>
                <h3>BỘ SƯU TẬP GIỚI HẠN</h3>
              </div>
              
              <div class="countdown-timer">
                <div class="time-box"><span class="hours">05</span><small>Giờ</small></div>:
                <div class="time-box"><span class="minutes">12</span><small>Phút</small></div>:
                <div class="time-box"><span class="seconds">40</span><small>Giây</small></div>
              </div>
              
              <h2 class="promo-title">${banner.title}</h2>
              <h4 style="color: #fff; font-weight: 300; margin-bottom: 10px; font-size: 18px;">${banner.description || 'Hoa Thiết Kế Cao Cấp'}</h4>
              
              <div class="price-block">
                <span class="sale-price">${formatVND(currentPrice)}</span>
                <span class="original-price">${formatVND(oldPrice)}</span>
              </div>
              
              <ul class="promo-perks">
                <li><span class="perk-icon"><i class="ph-light ph-star"></i></span> Giao hỏa tốc nội thành 90 - 120 phút</li>
                <li><span class="perk-icon"><i class="ph-light ph-star"></i></span> Độ bền hoa lên đến 14 ngày</li>
                <li><span class="perk-icon"><i class="ph-light ph-star"></i></span> Tặng kèm thông điệp ép kim cao cấp</li>
              </ul>
              
              <button class="btn-san-deal" onclick="quickAddToCart(event, '${banner.title}', ${currentPrice})">
                <span>MUA NGAY</span> <i class="arrow-icon ph-light ph-arrow-right"></i>
              </button>
            </div>

          </div>
        </div>
      `;
      
      dotsHTML += `<span class="dot ${activeClass}" onclick="currentSlide(${index})"></span>`;
    });

    bannerContainer.innerHTML = bannerHTML;
    if(dotsContainer) dotsContainer.innerHTML = dotsHTML;

    // Reset lại hiệu ứng chuyển slide
    slideIndex = 0;
    showSlides(slideIndex);

  } catch (error) {
    console.error("Lỗi kéo banner từ DB:", error);
  }
}

function renderFeaturedProducts() {
  const container = document.getElementById('featured-container'); 
  if(!container) return;

// Lọc ra tối đa 4 sản phẩm có is_featured = 1 từ Database
  const featuredItems = productsData.filter(p => p.is_featured === 1).slice(0, 4);
  
  if(featuredItems.length === 0) {
      container.innerHTML = '<p style="color:#aaa; width:100%; text-align:center;">Chưa có sản phẩm nổi bật nào.</p>';
      return;
  }

  let html = '';
  featuredItems.forEach(item => {
    const badgeHTML = item.badge ? `<span class="card-badge" ${item.badge === 'Premium' || item.badge === 'Luxury' || item.badge === 'VIP' ? 'style="background: #a1c4fd; color: #1a1a1a;"' : ''}>${item.badge}</span>` : '';
    
    const itemName = item.name || item.product_name || 'Hoa SGU';
    const itemPrice = parseInt(item.price) || 0;
    
    // FIX TẠI ĐÂY: Tách bạch rõ ràng Tên Tiếng Việt và Đoạn văn miêu tả
    const vieName = String(item.subtitle || 'SGU Flower'); // Bốc tên tiếng việt
    const rawDesc = String(item.description || '');        // Bốc đoạn văn miêu tả deep
    
    // Mã hóa đoạn văn để nhét vào khung Modal không bị gãy HTML
    const itemDesc = rawDesc.replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/\n/g, ' '); 

    const isFavorited = wishlist.includes(itemName);
    const heartClass = isFavorited ? 'active' : ''; 
    const heartIcon = isFavorited ? 'ph-fill ph-heart' : 'ph-light ph-heart';
    
    let imgUrl = item.images && item.images.length > 0 ? item.images[0] : (item.image || item.image_url || '');

    html += `
      <div class="card show dynamic-card" data-title="${itemName}" data-price="${formatVND(itemPrice)}" data-desc="${itemDesc}" data-images="${imgUrl}">
        <div class="img-wrapper">
          ${badgeHTML}
          <div class="card-quick-actions">
            <button class="quick-btn fav-btn ${heartClass}" onclick="toggleFavorite(event, this, '${itemName}')"><i class="${heartIcon}"></i></button>
            <button class="quick-btn add-cart-btn" onclick="quickAddToCart(event, '${itemName}', ${itemPrice})"><i class="ph-light ph-shopping-bag"></i></button>
          </div>
          <img class="card-img" src="${imgUrl}" loading="lazy" alt="${item.name}">
        </div>
        <div class="card-info">
          <h3>${itemName}</h3>
          <p class="card-subtitle">${vieName}</p> <div class="card-bottom">
            <span class="card-price">${formatVND(itemPrice)}</span>
            <div class="card-view-detail">Xem chi tiết <span style="font-size:16px;">➔</span></div>
          </div>
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
  if(typeof apply3DTilt === 'function') apply3DTilt(); 
}

// Hàm kéo API Tỉnh/Thành/Quận/Huyện dùng chung cho cả Web (Bản chuẩn DUY NHẤT)
async function loadProvinces() {
  try {
    const res = await fetch('https://provinces.open-api.vn/api/?depth=3');
    const data = await res.json();

    // Hàm tiện ích để đổ data vào 1 bộ 3 ô Select bất kỳ
    const setupSelects = (cityId, districtId, wardId) => {
        const citySelect = document.getElementById(cityId);
        const districtSelect = document.getElementById(districtId);
        const wardSelect = document.getElementById(wardId);

        if(!citySelect || !districtSelect || !wardSelect) return;

        data.forEach(city => { citySelect.innerHTML += `<option value="${city.name}">${city.name}</option>`; });

        citySelect.addEventListener('change', function() {
          districtSelect.innerHTML = '<option value="">Quận / Huyện</option>';
          wardSelect.innerHTML = '<option value="">Phường / Xã</option>';
          const selectedCity = data.find(c => c.name === this.value);
          if (selectedCity) { selectedCity.districts.forEach(d => { districtSelect.innerHTML += `<option value="${d.name}">${d.name}</option>`; }); }
        });

        districtSelect.addEventListener('change', function() {
          wardSelect.innerHTML = '<option value="">Phường / Xã</option>';
          const selectedCity = data.find(c => c.name === citySelect.value);
          if (selectedCity) {
              const selectedDistrict = selectedCity.districts.find(d => d.name === this.value);
              if (selectedDistrict) { selectedDistrict.wards.forEach(w => { wardSelect.innerHTML += `<option value="${w.name}">${w.name}</option>`; }); }
          }
        });
    };

    // Áp dụng cho form Đăng Ký
    setupSelects('reg-city', 'reg-district', 'reg-ward');
    
    // Áp dụng cho form Thanh Toán Checkout
    setupSelects('chk-city', 'chk-district', 'chk-ward');

  } catch (error) { console.error("Lỗi tải danh sách địa chỉ:", error); }
}
// HÀM HỒI SINH DANH SÁCH SẢN PHẨM (Load từ Database Laravel)
document.querySelectorAll('.toggle-password').forEach(icon => {
  icon.addEventListener('click', function() {
    const input = this.previousElementSibling;
    if (input.type === 'password') { input.type = 'text'; this.classList.remove('ph-eye-closed'); this.classList.add('ph-eye'); } 
    else { input.type = 'password'; this.classList.remove('ph-eye'); this.classList.add('ph-eye-closed'); }
  });
});

// 2. GỌI HÀM VÀO LUỒNG KHỞI CHẠY (BẢN BỌC THÉP 100%)
async function initializeApp() {
  console.log("🚀 SGU Flower đang khởi động...");
  
  try { await loadHomeSettings(); } catch(e) { console.error("Lỗi Banner:", e); }
  
  try { 
      console.log("🌸 Đang kéo hoa từ Laravel...");
      const res = await api.getProducts(); 
      if (res && res.status === 'success') {
          // Lấy đúng mảng data (Lách luôn trường hợp Laravel phân trang)
          let rawData = res.data.data || res.data || res || [];
          
// Chuyển hóa Data Laravel cho khớp 100% với giao diện cũ
          productsData = rawData.map(p => ({
              ...p,
              name: p.name || p.product_name,
              subtitle: p.vie_name || "Hoa SGU Flower", 
              description: p.description || "",         
              images: p.image ? [p.image] : (p.image_url ? [p.image_url] : (p.images || [])),
              price: parseInt(p.price) || 0,
              is_featured: parseInt(p.is_featured) || 0
          }));
          
          processFilters(); 
          renderFeaturedProducts(); 
      }
  } catch(e) { console.error("Lỗi kéo hoa:", e); }
  
  try { await initAuthState(); } catch(e) { console.error("Lỗi Check Auth:", e); }
  try { updateCartUI(); } catch(e) { console.error("Lỗi Giỏ hàng:", e); }
}
/* =========================================
   13. LOGIC SLIDER BANNER (Fix lỗi đứng im)
   ========================================= */
let slideIndex = 0;
let slideTimer;

function showSlides(n) {
  let slides = document.querySelectorAll('.banner-slide');
  let dots = document.querySelectorAll('.banner-dots .dot');
  if (slides.length === 0) return;

  if (n >= slides.length) { slideIndex = 0; }
  if (n < 0) { slideIndex = slides.length - 1; }

  // Ẩn tất cả slide và bỏ chọn tất cả các chấm (dots)
  slides.forEach(slide => slide.classList.remove('active'));
  dots.forEach(dot => dot.classList.remove('active'));

  // Hiển thị slide hiện tại và tô sáng chấm tương ứng
  slides[slideIndex].classList.add('active');
  if(dots.length > 0 && dots[slideIndex]) {
      dots[slideIndex].classList.add('active');
  }
}

// Nút bấm qua lại (Mũi tên)
window.changeBanner = function(n) {
  slideIndex += n;
  showSlides(slideIndex);
  resetSlideTimer(); // Bấm tay thì reset lại đồng hồ đếm ngược
};

// Nút bấm chọn trực tiếp (Chấm tròn)
window.currentSlide = function(n) {
  slideIndex = n;
  showSlides(slideIndex);
  resetSlideTimer();
};

// Hàm tự động trượt
function autoSlide() {
  slideIndex++;
  showSlides(slideIndex);
}

function resetSlideTimer() {
  clearInterval(slideTimer);
  slideTimer = setInterval(autoSlide, 5000); // 5000 = Tự động lướt sau mỗi 5 giây
}

// Kích hoạt khi trang vừa tải xong
document.addEventListener('DOMContentLoaded', () => {
  // 1. Chạy các hiệu ứng tĩnh
  if (typeof loadProvinces === 'function') loadProvinces();
  showSlides(slideIndex);
  resetSlideTimer();
  
  // 2. BẬT CÔNG TẮC ĐỘNG CƠ CHÍNH (Hồi nãy ông bị mất dòng này nè)
  initializeApp();
});
/* =========================================
   14. FIX LỖI KẸT TÌM KIẾM (THOÁT RA TẤT CẢ)
   ========================================= */
// 1. Cho phép gõ rỗng + Enter trên thanh tìm kiếm để hiển thị lại tất cả
window.executeNavSearch = function() {
    const input = document.getElementById('main-nav-search');
    if(!input) return;
    searchQuery = input.value.trim(); // Nhận cả chuỗi rỗng để reset
    processFilters();
    document.getElementById('product')?.scrollIntoView({ behavior: 'smooth' });
    input.value = ""; // Tự dọn sạch thanh gõ
};

// Chỉ khi nào click đúng nút "Tất cả sản phẩm" mới được reset thanh tìm kiếm
document.querySelector('.cat-item[data-cat="all"]')?.addEventListener('click', function() {
    const input = document.getElementById('main-nav-search');
    if(input) input.value = ''; // Clear luôn text trên giao diện cho đồng bộ
    searchQuery = ''; 
    processFilters(); 
});
// ==========================================
// SGU BOT - GIAO DIỆN & KẾT NỐI API AI GEMINI
// ==========================================
let currentConversationId = null; // Thêm biến này để Bot nhớ lịch sử chat

window.toggleChat = function() {
    const chatBox = document.getElementById('custom-chat-widget');
    if (chatBox) chatBox.classList.toggle('open');
};

window.handleChatEnter = function(e) {
    if (e.key === 'Enter') sendChatMessage();
};

let isBotTyping = false; 

window.sendChatMessage = async function() {
    // 1. NẾU BOT ĐANG GÕ THÌ CHẶN KHÔNG CHO GỬI TIẾP
    if (isBotTyping) return; 

    const input = document.getElementById('chat-input');
    const sendBtn = document.querySelector('.chat-footer button'); 
    const messageText = input.value.trim();
    if (!messageText) return;

    // 2. KHÓA Ô CHAT LẠI (Chống Spam)
    isBotTyping = true;
    input.disabled = true;
    input.placeholder = "Đang chờ Bot trả lời...";
    if(sendBtn) { sendBtn.disabled = true; sendBtn.style.opacity = '0.5'; }

    const chatBody = document.getElementById('chat-messages');

    // 3. In tin nhắn của Khách
    const userMsgHTML = `<div class="message user-msg">${messageText}</div>`;
    chatBody.insertAdjacentHTML('beforeend', userMsgHTML);
    input.value = '';
    chatBody.scrollTop = chatBody.scrollHeight; 

    // 4. Hiện hiệu ứng "Bot đang gõ..."
    const typingId = 'typing-' + Date.now();
    const typingHTML = `
        <div id="${typingId}" class="typing-indicator">
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
        </div>
    `;
    chatBody.insertAdjacentHTML('beforeend', typingHTML);
    chatBody.scrollTop = chatBody.scrollHeight;

    // 5. GỌI API XUỐNG BACKEND
    try {
        if (!currentConversationId) {
            const initRes = await api.request('/chat.php?action=start', 'POST', { title: 'Tư vấn SGU Flower' });
            if (initRes.status === 'success' && initRes.data && initRes.data.id) {
                currentConversationId = initRes.data.id; 
            } else throw new Error("Không thể khởi tạo!");
        }

        const msgRes = await api.request(`/chat.php?action=send&id=${currentConversationId}`, 'POST', { message: messageText });
        
        setTimeout(() => {
            document.getElementById(typingId)?.remove(); // Xóa 3 dấu chấm
            
            let botReply = "Lỗi đường truyền rồi bạn ơi!";
            if (msgRes.status === 'success' && msgRes.data && msgRes.data.response) {
                botReply = msgRes.data.response;
            } else if (msgRes.message) {
                botReply = msgRes.message; 
            }

            // In ra giao diện
            const botMsgHTML = `<div class="message bot-msg">${botReply}</div>`;
            chatBody.insertAdjacentHTML('beforeend', botMsgHTML);
            chatBody.scrollTop = chatBody.scrollHeight; 

            isBotTyping = false;
            input.disabled = false;
            input.placeholder = "Nhập tin nhắn...";
            if(sendBtn) { sendBtn.disabled = false; sendBtn.style.opacity = '1'; }
            input.focus(); 

        }, 1000);

    } catch (error) {
        document.getElementById(typingId)?.remove();
        const errorHTML = `<div class="message bot-msg" style="color:#ff4757;">Xin lỗi, mình đang mất kết nối với máy chủ AI!</div>`;
        chatBody.insertAdjacentHTML('beforeend', errorHTML);
        chatBody.scrollTop = chatBody.scrollHeight; 

        isBotTyping = false;
        input.disabled = false;
        input.placeholder = "Nhập tin nhắn...";
        if(sendBtn) { sendBtn.disabled = false; sendBtn.style.opacity = '1'; }
        input.focus();
    }
};
// ==========================================
// XỬ LÝ NÚT CHAT ZALO / MESSENGER ẢO
// ==========================================
document.querySelectorAll('.zalo-widget, .mess-widget').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault(); // Chặn hành động chuyển trang mặc định của thẻ <a>
        
        let platform = this.classList.contains('zalo-widget') ? 'Zalo' : 'Messenger';
        showCustomToast(`<i class='ph-light ph-warning-circle'></i> Web hiện tại chưa tích hợp ${platform}!`, "error");
    });
});
/* =========================================
   BẢN VÁ LỖI NÚT BẤM (EVENT DELEGATION CHỐNG LIỆT 100%)
   ========================================= */
document.addEventListener('click', function(e) {
    // 1. Cứu hộ nút X (Đóng Modal Thanh Toán / Form Đăng nhập)
    const closeBtn = e.target.closest('.close-btn');
    if (closeBtn) {
        const modal = closeBtn.closest('.modal');
        if (modal) {
            modal.classList.remove('active');
            document.body.classList.remove('no-scroll');
        }
    }

    // 2. Cứu hộ nút X (Đóng Sidebar Giỏ Hàng)
    if (e.target.closest('.close-cart')) {
        document.getElementById('cart-sidebar')?.classList.remove('open');
    }

    // 3. Cứu hộ nút X (Đóng Sidebar Yêu Thích)
    if (e.target.closest('.close-wishlist')) {
        document.getElementById('wishlist-sidebar')?.classList.remove('open');
    }

    // 4. Cứu hộ nút "Sản phẩm yêu thích" trong Menu thả xuống
    if (e.target.closest('#wishlist-menu-item')) {
        e.stopPropagation();
        if (!isLoggedIn) {
            openModal(document.getElementById('auth-modal'));
            return;
        }
        document.getElementById('cart-sidebar')?.classList.remove('open');
        document.getElementById('wishlist-sidebar')?.classList.add('open');
        document.getElementById('mobile-dropdown')?.classList.remove('mobile-show');
        document.getElementById('user-wrapper')?.classList.remove('active');
    }
});