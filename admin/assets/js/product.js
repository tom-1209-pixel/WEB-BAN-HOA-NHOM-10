// assets/js/product.js

async function showSection(section) {
  const content = document.getElementById("productContent");
  if (section === "listProduct") {
    content.innerHTML = `
      <div class="list-container" style="background:#fff; padding:20px; border-radius:8px; border:1px solid #ddd; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0; color:#2c3e50; display:flex; align-items:center;">
                <span style="margin-right:10px;">📦</span> QUẢN LÝ SẢN PHẨM
            </h3>
            <div style="display:flex; gap:10px;">
                <input type="text" id="searchProduct" onkeyup="filterProductTable()" placeholder="Tìm tên sản phẩm..." 
                       style="padding:10px; border:1px solid #ccc; border-radius:4px; width:250px;">
            </div>
        </div>
        
        <div style="overflow-x: auto;">
          <table class="table" style="width:100%; border-collapse:collapse; border: 1px solid #eee;">
            <thead>
              <tr style="background:#f8f9fa; border-bottom: 2px solid #dee2e6;">
                <th style="padding:12px; text-align:center;">Mã sản phẩm</th>
                <th style="padding:12px; text-align:center;">Hình ảnh</th>
                <th style="padding:12px; text-align:left;">Tên sản phẩm</th>
                <th style="padding:12px; text-align:left;">Loại</th>
                <th style="padding:12px; text-align:center;">Trạng thái</th>
                <th style="padding:12px; text-align:center;">Thao tác</th>
              </tr>
            </thead>
            <tbody id="productTableBody">
              <tr><td colspan="6" style="text-align:center; padding:20px;">🔄 Đang tải dữ liệu...</td></tr>
            </tbody>
          </table>
        </div>
      </div>`;
    loadProducts();
  } else if (section === "addProduct") {
    renderProductForm();
  } else if (section === "addCategory") {
    renderCategoryForm();
  }
}

function loadProducts() {
  fetch("../API/products.php?action=index")
    .then((res) => res.json())
    .then((data) => {
      const tbody = document.getElementById("productTableBody");
      if (!data || data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:20px;">Chưa có sản phẩm nào.</td></tr>`;
        return;
      }

      const html = data
        .map((item) => {
          // Logic hiển thị loại sản phẩm
          const categoryDisplay =
            item.cat_status === "inactive"
              ? `${item.category_name || "N/A"} <br><small style="color:#e74c3c; font-weight:bold;">(Loại bị khóa)</small>`
              : item.category_name || "N/A";
let imageTag = item.image ? `<img src="${item.image}" style="width:80px; height:80px; object-fit:cover; border-radius:4px; border:1px solid #eee;">` : "";
          return `
          <tr style="border-bottom: 1px solid #eee;">
            <td style="text-align:center; padding:12px; font-weight:bold; color:#555;">${item.product_id}</td>
            <td style="text-align:center; padding:5px; width:120px; height:60px;">
    ${imageTag}
</td>
            <td style="padding:12px; font-weight:500; color:#2c3e50;">${item.product_name}</td>
            <td style="padding:12px; color:#666;">${categoryDisplay}</td>
            <td style="text-align:center; padding:12px;">
                <span style="background:${item.status === "selling" ? "#e8f5e9" : "#ffebee"}; 
                             color:${item.status === "selling" ? "#2e7d32" : "#c62828"}; 
                             padding:4px 10px; border-radius:20px; font-weight:bold; font-size:12px; display:inline-block; min-width:100px;">
                    ${item.status === "selling" ? "Đang bán" : "Tạm ẩn"}
                </span>
            </td>
            <td style="text-align:center; padding:12px;">
                <div style="display:flex; gap:8px; justify-content:center;">
                    <button onclick="editProduct('${item.product_id}')" 
                            style="padding:6px 20px; background:#f39c12; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px;">
                         Sửa
                    </button>
                    <button onclick="deleteProduct('${item.product_id}')" 
                            style="padding:6px 20px; background:#e74c3c; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px;">
                         Xóa
                    </button>
                </div>
            </td>
          </tr>`;
        })
        .join("");

      tbody.innerHTML = html;
    })
    .catch((err) => {
      console.error(err);
      document.getElementById("productTableBody").innerHTML =
        `<tr><td colspan="6" style="text-align:center; color:red; padding:20px;">Lỗi kết nối API!</td></tr>`;
    });
}
async function renderProductForm(data = null) {
  const res = await fetch("../API/categories.php?action=index");
  const categories = (await res.json()).data || [];
  const isEdit = !!data;
  const units = ["Bó", "Giỏ", "Cành", "Bình", "Chậu", "Hộp", "Kệ"];

  document.getElementById("productContent").innerHTML = `
    <div class="form-container" style="max-width: 850px; margin: auto; padding: 25px; background: #fff; border: 1px solid #ddd; border-radius: 8px;">
      <h3 style="text-align: center; margin-bottom: 20px;">${isEdit ? "CẬP NHẬT SẢN PHẨM" : "THÊM SẢN PHẨM MỚI"}</h3>
      
      <form id="mainForm" enctype="multipart/form-data" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            
            <div>
                <label style="font-weight:bold;">Mã sản phẩm (*)</label>
                <input type="text" name="product_id" id="product_id" value="${data?.product_id || ""}" 
                       ${isEdit ? "readonly" : "required"} 
                       placeholder="Ví dụ: P01"
                       oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')"
                       style="width:100%; padding:10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; ${isEdit ? "background-color: #f9f9f9; cursor: not-allowed;" : ""}">
            </div>

            <div>
                <label style="font-weight:bold;">Tên sản phẩm (*)</label>
                <input type="text" name="product_name" id="product_name" value="${data?.product_name || ""}" required 
                       placeholder="Ví dụ: Hoa Hồng Đà Lạt" 
                       style="width:100%; padding:10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
            </div>

            <div>
                <label style="font-weight:bold;">Loại hoa (*)</label>
                <select name="category_id" required style="width:100%; padding:10px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="">-- Chọn loại hoa --</option>
                    ${categories.map((c) => `<option value="${c.category_id}" ${data?.category_id == c.category_id ? "selected" : ""}>${c.category_name}</option>`).join("")}
                </select>
            </div>

            <div>
                <label style="font-weight:bold;">Đơn vị tính (*)</label>
                <select name="unit" required style="width:100%; padding:10px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="">-- Chọn đơn vị --</option>
                    ${units.map((u) => `<option value="${u}" ${data?.unit === u ? "selected" : ""}>${u}</option>`).join("")}
                </select>
            </div>

            <div>
                <label style="font-weight:bold;">Số lượng ban đầu (*)</label>
                <input type="text" name="stock_qty" id="stock_qty" value="${data?.stock_qty || ""}" required 
                       placeholder="Nhập số nguyên dương > 0"
                       oninput="this.value = this.value.replace(/[^0-9]/g, ''); if(this.value.startsWith('0')) this.value = this.value.replace(/^0+/, '');"
                       style="width:100%; padding:10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
            </div>

            <div>
                <label style="font-weight:bold;">Tỉ lệ lợi nhuận (%) (*)</label>
                <input type="text" name="profit_percent" id="profit_percent" value="${data?.profit_percent || ""}" required 
                       placeholder="Ví dụ: 15.5"
                       oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\\..*)\\./g, '$1'); if(this.value.startsWith('0') && this.value[1] !== '.') this.value = this.value.replace(/^0+/, '');"
                       style="width:100%; padding:10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
            </div>

            <div>
                <label style="font-weight:bold;">Nhà cung cấp (*)</label>
                <input type="text" name="supplier" id="supplier" value="${data?.supplier || ""}" required 
                       placeholder="Ví dụ: Đại Lý Hoa Tươi" 
                       style="width:100%; padding:10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
            </div>

            <div>
                <label style="font-weight:bold;">Hiện trạng (*)</label>
                <select name="status" required style="width:100%; padding:10px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="selling" ${data?.status == "selling" ? "selected" : ""}>Hiển thị (Đang bán)</option>
                    <option value="hidden" ${data?.status == "hidden" ? "selected" : ""}>Tạm ẩn (Không bán)</option>
                </select>
            </div>

            <div style="grid-column: span 2">
                <label style="font-weight:bold;">Mô tả sản phẩm (*)</label>
                <textarea name="description" required placeholder="Mô tả chi tiết đặc điểm hoa..." style="width:100%; height:80px; padding:10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">${data?.description || ""}</textarea>
            </div>

            <div style="grid-column: span 2">
                <label style="font-weight:bold;">Hình ảnh sản phẩm (*)</label><br>
                ${
                  isEdit && data.image
                    ? `
                    <div id="imagePreviewContainer" style="margin-bottom:10px; border: 1px solid #ddd; padding: 10px; display: inline-block; border-radius: 4px;">
                        <img src="${data.image}" width="80" id="currentImg" onerror="this.src='../assets/images/no-image.png'"><br>
                        <div style="margin-top: 8px; font-size: 13px;">
                            <label style="color: #2196F3; cursor: pointer;">
                                <input type="radio" name="image_option" value="keep" checked onclick="document.getElementById('fileInput').style.display='block'"> Sửa/Giữ hình
                            </label>
                            <label style="color: #f44336; cursor: pointer; margin-left: 15px;">
                                <input type="radio" name="image_option" value="delete" onclick="document.getElementById('fileInput').style.display='none'"> Bỏ hình
                            </label>
                        </div>
                    </div>
                `
                    : ""
                }
                <input type="file" name="image" id="fileInput" 
       ${isEdit ? "" : "required"} 
       accept="image/*" 
       style="width:100%; margin-top: 5px;">
            </div>

            <div style="grid-column: span 2; text-align: center; margin-top: 15px; display: flex; justify-content: center; gap: 15px;">
                <button type="button" onclick="showSection('listProduct')" style="background: #6c757d; color: white; padding: 12px 30px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
                    QUAY LẠI
                </button>
                <button type="submit" style="background: #4caf50; color: white; padding: 12px 30px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
                    ${isEdit ? "CẬP NHẬT THAY ĐỔI" : "THÊM SẢN PHẨM"}
                </button>
            </div>
      </form>
    </div>`;

  document.getElementById("mainForm").onsubmit = async (e) => {
    e.preventDefault();

    // 1. Kiểm tra logic dữ liệu trước khi hiện Confirm
    const rawQty = document.getElementById("stock_qty").value;
    const rawProfit = document.getElementById("profit_percent").value;

    if (!rawQty || parseInt(rawQty) <= 0) {
      alert("Lỗi: Số lượng ban đầu phải là số nguyên lớn hơn 0!");
      document.getElementById("stock_qty").focus();
      return;
    }

    if (!rawProfit || parseFloat(rawProfit) <= 0 || rawProfit.endsWith(".")) {
      alert("Lỗi: Tỉ lệ lợi nhuận phải là số thực lớn hơn 0!");
      document.getElementById("profit_percent").focus();
      return;
    }

    // 2. Xác nhận hành động
    const confirmMsg = isEdit
      ? "Xác nhận cập nhật thay đổi?"
      : "Xác nhận thêm sản phẩm mới?";
    if (!confirm(confirmMsg)) return;

    const formData = new FormData(e.target);

    if (isEdit) {
      const imageOption = document.querySelector(
        'input[name="image_option"]:checked',
      )?.value;
      if (imageOption === "delete") {
        formData.append("delete_image", "1");
      }
    }

    const action = isEdit ? `update&id=${data.product_id}` : "store";

    try {
      const req = await fetch(`../API/products.php?action=${action}`, {
        method: "POST",
        body: formData,
      });
      const result = await req.json();
      alert(result.message);
      if (result.success) showSection("listProduct");
    } catch (err) {
      alert("Lỗi kết nối server.");
    }
  };

  // Chuẩn hóa viết hoa chữ cái đầu cho tên sản phẩm và nhà cung cấp
  ["product_name", "supplier"].forEach((id) => {
    const el = document.getElementById(id);
    if (el) {
      el.oninput = function () {
        this.value = this.value
          .toLowerCase()
          .replace(/(^|\s)([a-zà-ỹ\u0111])/g, (m) => m.toUpperCase());
      };
    }
  });
}

function editProduct(id) {
  fetch(`../API/products.php?action=get_item&id=${id}`)
    .then((res) => res.json())
    .then((data) => renderProductForm(data));
}

async function deleteProduct(id) {
  // THÊM XÁC NHẬN Ở ĐÂY
  if (
    !confirm(
      `Bạn có chắc chắn muốn xóa sản phẩm có mã: ${id} không?\nHành động này không thể hoàn tác!`,
    )
  ) {
    return;
  }

  try {
    const res = await fetch(`../API/products.php?action=delete&id=${id}`);
    const result = await res.json();
    alert(result.message);
    if (result.success) showSection("listProduct");
  } catch (err) {
    alert("Lỗi kết nối khi xóa sản phẩm.");
  }
}
// assets/js/product.js

async function renderCategoryForm() {
  document.getElementById("productContent").innerHTML = `
        <div class="form-container" style="max-width: 500px; margin: auto; padding: 25px; background: #fff; border: 1px solid #ddd; border-radius: 8px;">
            <h3 style="text-align: center; margin-bottom: 20px;">THÊM LOẠI SẢN PHẨM MỚI</h3>
            <form id="catForm">
                <div style="margin-bottom: 15px;">
                    <label style="font-weight:bold;">Mã loại sản phẩm (*)</label>
                    <input type="text" id="catId" placeholder="Ví dụ: CAT01, CAT02..." required 
                           oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')"
                           style="width:100%; padding:10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="font-weight:bold;">Tên loại sản phẩm (*)</label>
                    <input type="text" id="catName" placeholder="Ví dụ: Hoa Chúc Mừng, Hoa Khai Trương..." required 
                           style="width:100%; padding:10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
                </div>
                
<div style="margin-bottom: 15px;">
    <label style="font-weight:bold;">Trạng thái (*)</label>
    <select id="catStatus" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
        <option value="active">Hoạt động</option>
        <option value="inactive">Ngưng hoạt động</option>
    </select>
</div>
                <div style="margin-bottom: 15px;">
                    <label style="font-weight:bold;">Mô tả loại</label>
                    <textarea id="catDesc" placeholder="Nhập mô tả ngắn gọn về nhóm sản phẩm này..." 
                              style="width:100%; height:100px; padding:10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;"></textarea>
                </div>
                <div style="display: flex; justify-content: center; gap: 15px; margin-top: 10px;">
                    <button type="button" onclick="showSection('listProduct')" 
                            style="background: #6c757d; color: white; padding: 12px 30px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
                        QUAY LẠI
                    </button>
                    <button type="submit" 
                            style="background: #4caf50; color: white; padding: 12px 30px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
                        THÊM LOẠI SẢN PHẨM
                    </button>
                </div>
            </form>
        </div>`;

  // Logic viết hoa chữ cái đầu cho Tên loại
  const catNameInput = document.getElementById("catName");
  catNameInput.oninput = function () {
    this.value = this.value
      .toLowerCase()
      .replace(/(^|\s)([a-zà-ỹ\u0111])/g, (m) => m.toUpperCase());
  };

  document.getElementById("catForm").onsubmit = async (e) => {
    e.preventDefault();
    if (!confirm("Xác nhận tạo thêm loại sản phẩm mới này?")) return;

    const payload = {
      category_id: document.getElementById("catId").value.trim(),
      category_name: document.getElementById("catName").value.trim(),
      description: document.getElementById("catDesc").value.trim(),
      status: document.getElementById("catStatus").value, // THÊM DÒNG NÀY
    };

    // Kiểm tra nhanh phía client
    if (!payload.category_id || !payload.category_name) {
      alert("Vui lòng nhập đầy đủ các trường bắt buộc (*)");
      return;
    }

    try {
      const req = await fetch("../API/categories.php?action=store", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const result = await req.json();
      alert(result.message);

      if (result.success) {
        showSection("listProduct");
      }
    } catch (err) {
      alert("Lỗi kết nối server khi lưu loại sản phẩm.");
    }
  };
}
function filterProductTable() {
  const input = document.getElementById("searchProduct").value.toUpperCase();
  const rows = document
    .getElementById("productTableBody")
    .getElementsByTagName("tr");

  for (let row of rows) {
    const idCol = row.getElementsByTagName("td")[0]; // Mã SP
    const nameCol = row.getElementsByTagName("td")[2]; // Tên SP

    if (idCol && nameCol) {
      const idText = idCol.textContent || idCol.innerText;
      const nameText = nameCol.textContent || nameCol.innerText;

      // Tìm được cả trong mã và tên
      if (
        idText.toUpperCase().includes(input) ||
        nameText.toUpperCase().includes(input)
      ) {
        row.style.display = "";
      } else {
        row.style.display = "none";
      }
    }
  }
}
