document.addEventListener("DOMContentLoaded", function () {
  // ====== CHỐNG VÀO ADMIN KHI CHƯA LOGIN ======
  if (
    !localStorage.getItem("admin_login") &&
    !window.location.pathname.includes("login.html")
  ) {
    window.location.href = "./login.html";
    return;
  }

  // ====== HIỂN THỊ TÊN ADMIN (NẾU CÓ) ======
  const adminNameEl = document.getElementById("adminName");
  if (adminNameEl) {
    adminNameEl.innerText = localStorage.getItem("admin_name") || "Admin";
  }

  // ====== LOGIN LOGIC ======
  const loginForm = document.getElementById("loginForm");

  if (loginForm) {
    loginForm.addEventListener("submit", function (e) {
      e.preventDefault();

      const usernameInput = document.getElementById("username");
      const passwordInput = document.getElementById("password");
      const errorMsg = document.getElementById("errorMsg");

      const username = usernameInput ? usernameInput.value.trim() : "";
      const password = passwordInput ? passwordInput.value.trim() : "";

      if (!username || !password) {
        if (errorMsg) errorMsg.innerText = "Vui lòng nhập đầy đủ thông tin";
        return;
      }

      if (username === "admin" && password === "123") {
        localStorage.setItem("admin_login", "true");
        localStorage.setItem("admin_name", "Admin");
        window.location.href = "./index.html";
      } else {
        if (errorMsg) errorMsg.innerText = "Sai tên đăng nhập hoặc mật khẩu";
      }
    });
  }
});
