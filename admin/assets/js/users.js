// assets/js/users.js

function showSection(section) {
  const content = document.getElementById("userContent");
  if (!content) return;

  if (section === "listUser") {
    content.innerHTML = `
      <div class="list-container" style="background:#fff; padding:20px; border-radius:8px; border:1px solid #ddd; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0; color:#2c3e50; display:flex; align-items:center;">
                <span style="margin-right:10px;">👥</span> DANH SÁCH NGƯỜI DÙNG
            </h3>
            <div style="display:flex; gap:10px;">
                  <input type="text" id="searchUser" onkeyup="filterUserTable()" placeholder="Tìm theo email..." 
                        style="padding:10px; border:1px solid #ccc; border-radius:4px; width:250px;">
            </div>
        </div>
        
        <div style="overflow-x: auto;">
          <table class="table" style="width:100%; border-collapse:collapse; border: 1px solid #eee; min-width: 1100px;">
            <thead>
              <tr style="background:#f8f9fa; border-bottom: 2px solid #dee2e6;">
                <th style="padding:12px; text-align:left;">Họ tên</th>
    <th style="padding:12px; text-align:left;">Email</th>
    <th style="padding:12px; text-align:center;">SĐT</th>
    <th style="padding:12px; text-align:left;">Địa chỉ</th>
    <th style="padding:12px; text-align:left;">Quận/Huyện</th> <th style="padding:12px; text-align:left;">Phường/Xã</th>
    <th style="padding:12px; text-align:left;">Thành phố</th>
                <th style="padding:12px; text-align:center; width: 130px;">Vai trò</th>
                <th style="padding:12px; text-align:center;">Ngày tạo</th>
                <th style="padding:12px; text-align:center;">Trạng thái</th>
                <th style="padding:12px; text-align:center;">Thao tác</th>
              </tr>
            </thead>
            <tbody id="userTableBody">
              <tr><td colspan="10" style="text-align:center; padding:20px;">🔄 Đang tải dữ liệu...</td></tr>
            </tbody>
          </table>
        </div>
      </div>`;
    loadUserList();
  } else if (section === "addUser") {
    content.innerHTML = `
      <div class="form-container" style="max-width: 700px; margin: 20px auto; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 8px;">
        <h3 style="text-align: center; margin-bottom: 20px;">➕ Thêm tài khoản mới</h3>
        <form id="addAccountForm" novalidate style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
          <div class="form-group">
            <label>Họ tên:</label>
            <input type="text" id="full_name" placeholder="Nguyễn Văn A" style="width:100%; padding:8px;">
            <span id="err_full_name" style="color: red; font-size: 12px; display: block; height: 15px;"></span>
          </div>
          <div class="form-group">
            <label>Email:</label>
            <input type="email" id="email" placeholder="abc@gmail.com" style="width:100%; padding:8px;">
            <span id="err_email" style="color: red; font-size: 12px; display: block; height: 15px;"></span>
          </div>
          
          <div class="form-group">
            <label>Mật khẩu:</label>
            <div style="position: relative; display: flex; align-items: center;">
                <input type="password" id="password" placeholder="Ít nhất 6 ký tự" 
                       onkeypress="return event.charCode != 32" 
                       style="width: 100%; padding: 8px; padding-right: 35px; box-sizing: border-box;">
                <span onclick="togglePassword('password')" 
                      style="position: absolute; right: 10px; cursor: pointer; font-size: 18px; z-index: 100; user-select: none;">👁️</span>
            </div>
            <span id="err_password" style="color: red; font-size: 12px; display: block; height: 15px;"></span>
          </div>

          <div class="form-group">
            <label>Xác nhận MK:</label>
            <div style="position: relative; display: flex; align-items: center;">
                <input type="password" id="confirmPassword" placeholder="Nhập lại mật khẩu" 
                       onkeypress="return event.charCode != 32" 
                       style="width: 100%; padding: 8px; padding-right: 35px; box-sizing: border-box;">
                <span onclick="togglePassword('confirmPassword')" 
                      style="position: absolute; right: 10px; cursor: pointer; font-size: 18px; z-index: 100; user-select: none;">👁️</span>
            </div>
            <span id="err_confirmPassword" style="color: red; font-size: 12px; display: block; height: 15px;"></span>
          </div>

          <div class="form-group">
            <label>SĐT:</label>
            <input type="tel" id="phone" placeholder="10 chữ số" style="width:100%; padding:8px;">
            <span id="err_phone" style="color: red; font-size: 12px; display: block; height: 15px;"></span>
          </div>
          <div class="form-group">
            <label>Vai trò:</label>
            <select id="role" style="width:100%; padding:8px;">
              <option value="">-- Chọn vai trò --</option>
              <option value="customer">Khách hàng</option>
              <option value="staff">Nhân viên</option>
            </select>
            <span id="err_role" style="color: red; font-size: 12px; display: block; height: 15px;"></span>
          </div>
          <div class="form-group" style="grid-column: span 2;">
            <label>Địa chỉ:</label>
            <input type="text" id="address" placeholder="Số nhà, tên đường" style="width:100%; padding:8px;">
            <span id="err_address" style="color: red; font-size: 12px; display: block; height: 15px;"></span>
          </div>
        <div class="form-group">
  <label>Thành phố:</label>
  <select id="city" onchange="loadDistricts(this.value)" style="width:100%; padding:8px;">
    <option value="">-- Đang tải dữ liệu... --</option>
  </select>
  <span id="err_city" style="color: red; font-size: 12px; display: block; height: 15px;"></span>
</div>

<div class="form-group">
  <label>Quận/Huyện:</label>
  <select id="district" onchange="loadWards(this.value)" style="width:100%; padding:8px;">
    <option value="">-- Chọn Thành phố trước --</option>
  </select>
  <span id="err_district" style="color: red; font-size: 12px; display: block; height: 15px;"></span>
</div>

<div class="form-group">
  <label>Phường/Xã:</label>
  <select id="ward" style="width:100%; padding:8px;">
    <option value="">-- Chọn Quận/Huyện trước --</option>
  </select>
  <span id="err_ward" style="color: red; font-size: 12px; display: block; height: 15px;"></span>
</div>
          <div style="grid-column: span 2; text-align: center; margin-top: 20px;">
            <button type="submit" id="btnSubmit" style="background: #4caf50; color: white; padding: 10px 25px; border: none; border-radius: 4px; cursor: pointer;">Thêm tài khoản</button>
            <button type="button" onclick="showSection('listUser')" style="background: #999; color: white; padding: 10px 25px; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">Hủy bỏ</button>
          </div>
        </form>
      </div>`;
    setupRealtimeValidation();
    loadProvinces();
  }
}

// Các hàm bổ trợ giữ nguyên logic của bạn nhưng tối ưu hóa hiển thị
function togglePassword(id) {
  const input = document.getElementById(id);
  const icon = event.target;
  if (input) {
    if (input.type === "password") {
      input.type = "text";
      icon.innerText = "🙈";
    } else {
      input.type = "password";
      icon.innerText = "👁️";
    }
  }
}

function setupRealtimeValidation() {
  const fields = [
    "full_name",
    "email",
    "password",
    "confirmPassword",
    "phone",
    "address",
    "role",
    "city",
    "district", // THÊM DÒNG NÀY
    "ward",
  ];
  const form = document.getElementById("addAccountForm");
  if (!form) return;

  fields.forEach((fieldId) => {
    const input = document.getElementById(fieldId);
    if (input) {
      input.addEventListener("input", () => validateField(fieldId));
      input.addEventListener("blur", () => validateField(fieldId));
    }
  });

  form.onsubmit = function (e) {
    e.preventDefault();
    let isAllValid = true;
    fields.forEach((f) => {
      if (!validateField(f)) isAllValid = false;
    });
    if (isAllValid) handleCreateAccount();
  };
}

function validateField(id) {
  const input = document.getElementById(id);
  const errorSpan = document.getElementById("err_" + id);
  if (!input || !errorSpan) return false;

  const value = input.value.trim();
  let errorMsg = "";

  if (value === "" && id !== "confirmPassword") {
    errorMsg = "Vui lòng điền đầy đủ thông tin";
  } else {
    switch (id) {
      case "full_name":
        input.value = input.value
          .toLowerCase()
          .split(" ")
          .map((s) => s.charAt(0).toUpperCase() + s.slice(1))
          .join(" ");
        if (
          !/^[a-zA-ZÀÁÂÃÈÉÊÌÍÒÓÔÕÙÚĂĐĨŨƠàáâãèéêìíòóôõùúăđĩũơƯĂẠẢẤẦẨẪẬẮẰẲẴẶẸẺẼỀỀỂưăạảấầẩẫậắằẳẵặẹẻẽềềểỄỆỈỊỌỎỐỒỔỖỘỚỜỞỠỢỤỦỨỪễệỉịọỏốồổỗộớờởỡợụủứừỬỮỰỲỴÝỶỸửữựỳỵỷỹ\s]+$/.test(
            value,
          )
        )
          errorMsg = "Họ tên chỉ được chứa chữ cái.";
        else if (value.length < 3) errorMsg = "Tối thiểu 3 ký tự.";
        break;
      case "email":
        const emailRegex = /^[a-zA-Z][a-zA-Z0-9]*@[a-zA-Z]+\.[a-zA-Z]{2,}$/;
        if (!emailRegex.test(value)) errorMsg = "Email sai định dạng.";
        break;
      case "password":
        if (input.value.includes(" "))
          errorMsg = "Mật khẩu không được chứa dấu cách.";
        else if (input.value.length < 6) errorMsg = "Ít nhất 6 ký tự.";
        break;
      case "confirmPassword":
        if (input.value !== document.getElementById("password").value)
          errorMsg = "Mật khẩu không khớp.";
        break;
      case "phone":
        if (!/^0(3|5|7|8|9)[0-9]{8}$/.test(value))
          errorMsg = "SĐT không hợp lệ.";
        break;
    }
  }

  errorSpan.innerText = errorMsg;
  input.style.borderColor = errorMsg ? "red" : "#ccc";
  return errorMsg === "";
}

function loadUserList() {
  const tbody = document.getElementById("userTableBody");
  if (!tbody) return;

  fetch("../API/users.php?action=index")
    .then((res) => res.json())
    .then((data) => {
      if (!data || data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" style="text-align:center; padding:20px;">📭 Chưa có người dùng nào trong hệ thống.</td></tr>`;
        return;
      }

      let html = "";
      data.forEach((user) => {
        // Xử lý text hiển thị cho Vai trò
        const roleText =
          user.role === "admin"
            ? "Quản trị viên"
            : user.role === "staff"
              ? "Nhân viên"
              : "Khách hàng";

        // Style cho nhãn Vai trò (tương tự nhãn Lợi nhuận bên Prices)
        const roleBadge = `<span style="background:#e3f2fd; color:#1976d2; padding:4px 8px; border-radius:4px; font-weight:bold; font-size:12px;">${roleText}</span>`;

        html += `
          <tr style="border-bottom: 1px solid #eee;">
            <td style="padding:12px; font-weight:500; color:#2c3e50;">${user.full_name || "---"}</td>
            <td style="padding:12px; color:#666;">${user.email}</td>
            <td style="text-align:center; padding:12px;">${user.phone || "---"}</td>
            <td style="padding:12px;">${user.address || "---"}</td>
            <td style="padding:12px;">${user.district || "---"}</td>
            <td style="padding:12px;">${user.ward || "---"}</td>
            <td style="padding:12px;">${user.city || "---"}</td> 
            <td style="text-align:center; padding:12px;">${roleBadge}</td>
            <td style="text-align:center; padding:12px; font-size:13px; color:#888;">${user.created_at || "---"}</td>
            
            <td style="text-align:center; padding:12px;">
                <span style="background:${user.status === "active" ? "#e8f5e9" : "#ffebee"}; 
                             color:${user.status === "active" ? "#2e7d32" : "#c62828"}; 
                             padding:4px 10px; border-radius:20px; font-weight:bold; font-size:12px; display:inline-block; min-width:80px;">
                    ${user.status === "active" ? "Hoạt động" : "Bị khóa"}
                </span>
            </td>

            <td style="text-align:center; padding:12px;">
                <button onclick="changeStatus('${user.email}')" 
                        style="padding:6px 12px; background:${user.status === "active" ? "#f39c12" : "#2ecc71"}; 
                               color:white; border:none; border-radius:4px; cursor:pointer; font-size:12px; transition: 0.3s;">
                    ${user.status === "active" ? "Khóa" : "Mở"}
                </button>
            </td>
          </tr>`;
      });
      tbody.innerHTML = html;
    })
    .catch((err) => {
      console.error("Lỗi tải danh sách:", err);
      tbody.innerHTML = `<tr><td colspan="10" style="text-align:center; color:red; padding:20px;">Lỗi kết nối dữ liệu!</td></tr>`;
    });
}
function handleCreateAccount() {
  if (!confirm("Bạn có chắc chắn muốn lưu thông tin tài khoản này không?"))
    return;

  const citySelect = document.getElementById("city");
  const districtSelect = document.getElementById("district"); // Thêm dòng này

  const cityName =
    citySelect.options[citySelect.selectedIndex].getAttribute("data-name");
  // Lấy tên Quận/Huyện từ thuộc tính data-name
  const districtName =
    districtSelect.options[districtSelect.selectedIndex].getAttribute(
      "data-name",
    );

  const data = {
    full_name: document.getElementById("full_name").value.trim(),
    email: document.getElementById("email").value.trim(),
    password: document.getElementById("password").value,
    phone: document.getElementById("phone").value.trim(),
    address: document.getElementById("address").value.trim(),
    role: document.getElementById("role").value,
    city: cityName,
    district: districtName, // Bây giờ biến này đã hợp lệ
    ward: document.getElementById("ward").value,
  };
  const btn = document.getElementById("btnSubmit");
  btn.disabled = true;
  btn.innerText = "Đang xử lý...";
  fetch("../API/users.php?action=store", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
  })
    .then((res) => res.json())
    .then((res) => {
      alert(res.message);
      if (res.success) showSection("listUser");
      else {
        btn.disabled = false;
        btn.innerText = "Lưu tài khoản";
      }
    });
}

function changeStatus(email) {
  if (
    !confirm(
      `Bạn có chắc chắn muốn thay đổi trạng thái (Khóa/Mở) cho tài khoản ${email} không?`,
    )
  ) {
    return; // Nếu chọn Cancel thì không thực hiện lệnh fetch
  }
  fetch(`../API/users.php?action=toggle&email=${email}`).then(() =>
    loadUserList(),
  );
}
function filterUserTable() {
  const input = document.getElementById("searchUser").value.toUpperCase();
  const rows = document
    .getElementById("userTableBody")
    .getElementsByTagName("tr");

  for (let row of rows) {
    const cells = row.getElementsByTagName("td");

    // Kiểm tra nếu dòng có ít nhất 2 cột (tránh dòng 'Đang tải' hoặc 'Trống' chỉ có 1 cột)
    if (cells.length > 1) {
      const emailCol = cells[1]; // Cột Email là index 1
      const text = (emailCol.textContent || emailCol.innerText).toUpperCase();

      if (text.indexOf(input) > -1) {
        row.style.display = "";
      } else {
        row.style.display = "none";
      }
    }
  }
}
/**
 * Tải danh sách Tỉnh/Thành phố từ API
 */
async function loadProvinces() {
  const citySelect = document.getElementById("city");
  if (!citySelect) return;

  try {
    const res = await fetch("https://provinces.open-api.vn/api/v1/p/");
    const provinces = await res.json();

    citySelect.innerHTML = '<option value="">-- Chọn Thành phố --</option>';
    provinces.forEach((p) => {
      // Lưu Tên tỉnh vào data-name để dễ lấy khi lưu tài khoản
      citySelect.innerHTML += `<option value="${p.code}" data-name="${p.name}">${p.name}</option>`;
    });
  } catch (err) {
    console.error("Lỗi tải tỉnh thành:", err);
    citySelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
  }
}

/**
 * Tải danh sách Phường/Xã dựa trên mã Thành phố đã chọn
 * Sử dụng API v2 để lấy thẳng danh sách Phường/Xã thuộc Tỉnh
 *
 */
async function loadDistricts(provinceCode) {
  const districtSelect = document.getElementById("district");
  const wardSelect = document.getElementById("ward");

  districtSelect.innerHTML = '<option value="">🔄 Đang tải...</option>';
  wardSelect.innerHTML =
    '<option value="">-- Chọn Quận/Huyện trước --</option>';

  try {
    const res = await fetch(
      `https://provinces.open-api.vn/api/v1/p/${provinceCode}?depth=2`,
    );
    const data = await res.json();

    districtSelect.innerHTML =
      '<option value="">-- Chọn Quận/Huyện --</option>';
    data.districts.forEach((d) => {
      districtSelect.innerHTML += `<option value="${d.code}" data-name="${d.name}">${d.name}</option>`;
    });
  } catch (err) {
    districtSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
  }
}
async function loadWards(districtCode) {
  const wardSelect = document.getElementById("ward");
  wardSelect.innerHTML = '<option value="">🔄 Đang tải...</option>';

  try {
    const res = await fetch(
      `https://provinces.open-api.vn/api/v1/d/${districtCode}?depth=2`,
    );
    const data = await res.json();

    wardSelect.innerHTML = '<option value="">-- Chọn Phường/Xã --</option>';
    data.wards.forEach((w) => {
      wardSelect.innerHTML += `<option value="${w.name}">${w.name}</option>`;
    });
  } catch (err) {
    wardSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
  }
}