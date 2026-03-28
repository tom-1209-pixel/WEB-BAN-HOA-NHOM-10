let selectedProduct = null;
let importItems = []; // Lưu danh sách sản phẩm đang chọn

/**
 * 1. GIAO DIỆN DANH SÁCH PHIẾU NHẬP
 */
async function renderImportSection() {
  const content = document.getElementById("productContent");
  content.innerHTML = `
        <table class="table">
            <thead>
                <tr>
                    <th style="text-align:center;">Mã phiếu</th>
                    <th style="text-align:center;">Lần nhập</th> <th style="text-align:center;">Sản phẩm</th>
                    <th style="text-align:center;">Giá nhập</th>
                    <th style="text-align:center;">Số lượng</th>
                    <th style="text-align:center;">Ngày nhập</th>
                    <th style="text-align:center;">Trạng thái</th>
                    <th style="text-align:center;">Thao tác</th>
                </tr>
            </thead>
            <tbody id="importTableBody">
                <tr><td colspan="8" style="text-align:center;">Đang tải dữ liệu...</td></tr>
            </tbody>
        </table>`;
  loadImportList();
}
/**
 * 2. TẢI DỮ LIỆU DANH SÁCH
 */
async function loadImportList() {
  try {
    const res = await fetch("../api/imports.php?action=list");
    const data = await res.json();
    const tbody = document.getElementById("importTableBody");

    if (!data || data.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;">Chưa có dữ liệu.</td></tr>`;
      return;
    }

    tbody.innerHTML = data
      .map(
        (t) => `
            <tr>
                <td style="text-align:center;"><strong>${t.receipt_id}</strong></td>
                <td style="text-align:center;">Lần ${t.import_times || 1}</td>
                <td style="text-align:center;">${t.product_name}</td>
                <td style="text-align:center; font-weight:bold; color:#e91e63;">
                    ${new Intl.NumberFormat("vi-VN").format(t.import_price)} VNĐ
                </td>
                <td style="text-align:center;">${t.quantity}</td>
                <td style="text-align:center;">${t.import_date}</td>
                <td style="text-align:center;">
                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; 
                        ${t.status === "completed" ? "background: #e8f5e9; color: #2e7d32;" : "background: #fff3e0; color: #ef6c00;"}">
                        ${t.status === "completed" ? "Đã nhập kho" : "Phiếu tạm"}
                    </span>
                </td>
                <td style="text-align:center;">
                    ${
                      t.status === "draft"
                        ? `<button onclick="editImport('${t.receipt_id}')" 
                                style="background: #ff9800; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; margin-right: 5px; font-size: 13px;">
                                Sửa
                           </button>
                           <button onclick="completeImport('${t.receipt_id}')" 
                                style="background: #4caf50; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 13px;">
                                Nhập kho
                           </button>`
                        : `<span style="color:#888; font-weight: bold;">✔️ Hoàn tất</span>`
                    }
                </td>
            </tr>`,
      )
      .join("");
  } catch (e) {
    console.error("Lỗi tải danh sách:", e);
  }
}
/**
 * 3. GIAO DIỆN FORM TẠO/SỬA PHIẾU
 */
function renderImportForm() {
  selectedProduct = null;
  importItems = [];
  const content = document.getElementById("productContent");
  content.innerHTML = `
    <div class="form-container" style="background:#fff; padding:25px; border-radius:8px; border:1px solid #ddd; max-width: 600px; margin: auto;">
        <h3 id="formTitle" style="margin-bottom:20px; color: #333; text-align: center;">➕ THÔNG TIN PHIẾU NHẬP</h3>
        
        <div style="margin-bottom: 15px;">
            <label style="display:block; margin-bottom:5px; font-weight:bold;">Ngày nhập:</label>
            <input type="date" id="import_date" value="${new Date().toISOString().split("T")[0]}" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display:block; margin-bottom:5px; font-weight:bold;">Mã phiếu nhập:</label>
            <input type="text" id="receipt_id" placeholder="VD: NHAP-01" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
        </div>
       <div style="flex:1;">
    <label style="display:block; margin-bottom:5px; font-weight:bold;">Lần nhập hàng:</label>
    <input type="text" 
        id="import_times" 
        placeholder="VD: 1" 
        oninput="this.value = this.value.replace(/[^0-9]/g, ''); if(this.value.startsWith('0')) this.value = '';"
        style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
</div>

        <hr style="margin: 25px 0; border: 0; border-top: 2px dashed #eee;">
        <p style="margin-bottom:15px; color: #666; font-style: italic; text-align: center; font-weight: 500;">
            Vui lòng chọn sản phẩm và nhập thông tin bên dưới
        </p>

        <div style="margin-bottom: 15px; position: relative;">
            <label style="display:block; margin-bottom:5px; font-weight:bold;">Sản phẩm nhập:</label>
            <input type="text" id="searchProdInput" placeholder="Tìm tên hoặc mã hoa..." oninput="searchProductInDB(this.value)" autocomplete="off" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
            <div id="searchRes" style="display:none; position:absolute; left:0; right:0; background:#fff; border:1px solid #ccc; z-index:1000; max-height:180px; overflow-y:auto;"></div>
        </div>

        <div id="selectedInfo" style="margin-bottom: 15px; padding: 10px; background: #e8f5e9; border-radius: 4px; display:none; text-align: center;">
            ✅ Đã chọn: <span id="prodNameDisplay" style="font-weight:bold;"></span>
        </div>

        <div style="display:flex; gap:10px; margin-bottom: 15px;">
            <div style="flex:1;">
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Giá nhập (VNĐ):</label>
                <input type="text" id="import_price" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div style="flex:1;">
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Số lượng:</label>
                <input type="text" id="quantity" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
            </div>
        </div>

        <div style="display: flex; justify-content: center; margin-bottom: 20px;">
            <button onclick="addItemToImportList()" style="padding:10px 25px; background:#2196f3; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">+ THÊM VÀO DANH SÁCH SẢN PHẨM</button>
        </div>

        <div id="itemsTableContainer"></div>

        <div style="display:flex; gap:15px; justify-content: center; margin-top: 25px; border-top: 1px solid #eee; padding-top: 20px;">
            <button onclick="renderImportSection()" style="padding:12px 30px; background:#666; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">HỦY BỎ</button>
            <button id="btnSave" onclick="saveSingleImport()" style="padding:12px 30px; background:#4caf50; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">TẠO PHIẾU NHẬP</button>
        </div>
    </div>`;
}

/**
 * 4. TÌM KIẾM SẢN PHẨM TRONG CƠ SỞ DỮ LIỆU
 */
async function searchProductInDB(keyword) {
  const resBox = document.getElementById("searchRes");
  const selectedInfo = document.getElementById("selectedInfo");

  if (!keyword || keyword.trim().length < 1) {
    resBox.innerHTML = "";
    resBox.style.display = "none";
    selectedProduct = null;
    if (selectedInfo) {
      selectedInfo.style.display = "none";
    }
    return;
  }

  try {
    const response = await fetch("../api/products.php?action=index");
    const products = await response.json();
    const filtered = products.filter(
      (p) =>
        p.product_name.toLowerCase().includes(keyword.toLowerCase()) ||
        p.product_id.toLowerCase().includes(keyword.toLowerCase()),
    );

    if (filtered.length > 0) {
      resBox.innerHTML = filtered
        .map(
          (p) => `
            <div onclick="selectProductFromDB('${p.product_id}', '${p.product_name}')" style="padding:10px; border-bottom:1px solid #eee; cursor:pointer;">
                ${p.product_id} - ${p.product_name}
            </div>`,
        )
        .join("");
      resBox.style.display = "block";
    } else {
      resBox.innerHTML =
        '<div style="padding:10px; color:#888;">Không tìm thấy sản phẩm</div>';
      resBox.style.display = "block";
    }
  } catch (e) {
    console.error("Lỗi tìm kiếm:", e);
  }
}

function selectProductFromDB(id, name) {
  selectedProduct = { product_id: id, product_name: name };
  const searchInput = document.getElementById("searchProdInput");
  if (searchInput) searchInput.value = name;

  document.getElementById("prodNameDisplay").innerText = id + " - " + name;
  document.getElementById("selectedInfo").style.display = "block";
  document.getElementById("searchRes").style.display = "none";
}

/**
 * 5. THÊM VÀO DANH SÁCH TẠM
 */
function addItemToImportList() {
  const priceInput = document.getElementById("import_price");
  const qtyInput = document.getElementById("quantity");
  const price = parseFloat(priceInput.value);
  const qty = parseInt(qtyInput.value);

  if (!selectedProduct || isNaN(price) || isNaN(qty) || qty <= 0) {
    alert("Vui lòng chọn sản phẩm và nhập giá/số lượng hợp lệ!");
    return;
  }

  importItems.push({
    product_id: selectedProduct.product_id,
    product_name: selectedProduct.product_name,
    import_price: price,
    quantity: qty,
  });

  priceInput.value = "";
  qtyInput.value = "";
  document.getElementById("searchProdInput").value = "";
  document.getElementById("selectedInfo").style.display = "none";
  selectedProduct = null;

  renderItemsTable();
}

/**
 * 6. VẼ BẢNG SẢN PHẨM TẠM
 */
function renderItemsTable() {
  const container = document.getElementById("itemsTableContainer");
  if (!container) return;

  if (importItems.length === 0) {
    container.innerHTML = "";
    return;
  }

  container.innerHTML = `
    <table class="table" style="font-size: 13px; margin-top: 15px;">
        <thead>
            <tr style="background: #f9f9f9;">
                <th>Sản phẩm</th>
                <th>Giá</th>
                <th>SL</th>
                <th>Xóa</th>
            </tr>
        </thead>
        <tbody>
            ${importItems
              .map(
                (item, index) => `
                <tr>
                    <td>${item.product_name}</td>
                    <td>${new Intl.NumberFormat().format(item.import_price)}</td>
                    <td>${item.quantity}</td>
                    <td><button onclick="importItems.splice(${index},1); renderItemsTable();" style="color:red; background:none; border:none; cursor:pointer;">❌</button></td>
                </tr>
            `,
              )
              .join("")}
        </tbody>
    </table>`;
}

/**
 * 7. LƯU PHIẾU
 */
/**
 * 8. LƯU PHIẾU (TẠO MỚI HOẶC CẬP NHẬT)
 */
async function saveSingleImport(isUpdate = false) {
  const rid = document.getElementById("receipt_id").value.trim();
  const timesRaw = document.getElementById("import_times").value.trim();
  const date = document.getElementById("import_date").value;

  // Chuyển đổi sang số để kiểm tra
  const times = parseInt(timesRaw);

  // Ràng buộc: phải có giá trị và phải lớn hơn 0
  if (!rid || isNaN(times) || times <= 0) {
    alert(
      "Vui lòng nhập đầy đủ Mã phiếu và Lần nhập (phải là số nguyên dương)!",
    );
    return;
  }

  if (importItems.length === 0) {
    alert("Danh sách sản phẩm phải có ít nhất một sản phẩm!");
    return;
  }

  const payload = {
    receipt_id: rid,
    import_times: times, // Gửi số nguyên đã xử lý
    import_date: date,
    items: importItems,
  };

  // ... (phần fetch bên dưới giữ nguyên) ...
  const action = isUpdate ? "update" : "store";
  try {
    const res = await fetch(`../api/imports.php?action=${action}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const result = await res.json();
    if (result.success) {
      alert(result.message);
      renderImportSection();
    } else {
      alert("Lỗi: " + result.message);
    }
  } catch (e) {
    alert("Lỗi kết nối máy chủ!");
  }
}
/**
 * 8. SỬA PHIẾU
 */
async function editImport(id) {
  try {
    const res = await fetch(`../api/imports.php?action=get_ticket&id=${id}`);
    const data = await res.json();
    renderImportForm(); // Vẽ lại form trống

    document.getElementById("formTitle").innerText = "✏️ CHỈNH SỬA PHIẾU NHẬP";

    // Đổ dữ liệu vào các ô input
    document.getElementById("receipt_id").value = data.info.receipt_id;
    document.getElementById("receipt_id").readOnly = true; // Không cho sửa mã phiếu
    document.getElementById("import_date").value = data.info.import_date;

    // QUAN TRỌNG: Đổ dữ liệu Lần nhập vào đây để không bị lỗi validate khi lưu
    document.getElementById("import_times").value = data.info.import_times || 1;

    // Load danh sách sản phẩm
    importItems = data.items.map((item) => ({
      product_id: item.product_id,
      product_name: item.product_name,
      import_price: item.import_price,
      quantity: item.quantity,
    }));

    renderItemsTable();

    const btnSave = document.getElementById("btnSave");
    btnSave.innerText = "CẬP NHẬT PHIẾU";
    btnSave.style.background = "#ff9800";
    btnSave.setAttribute("onclick", `saveSingleImport(true)`);
  } catch (e) {
    alert("Lỗi khi lấy thông tin phiếu!");
  }
}
/**
 * 9. GIAO DIỆN TÌM KIẾM ĐỂ SỬA (Đã cập nhật Autocomplete)
 */
function renderEditSearchSection() {
  const content = document.getElementById("productContent");
  content.innerHTML = `
    <div class="form-container" style="background:#fff; padding:40px; border-radius:8px; border:1px solid #ddd; max-width: 500px; margin: 30px auto; text-align:center;">
        <h3 style="margin-bottom:15px; color: #ff9800;">🔍 TÌM PHIẾU NHẬP ĐỂ SỬA</h3>
        
        <div style="position: relative; margin-bottom: 25px;">
            <input type="text" id="editSearchId" placeholder="Nhập mã phiếu..." 
                oninput="searchReceiptInDB(this.value)" autocomplete="off"
                style="width:100%; padding:15px; border:2px solid #ff9800; border-radius:6px; font-size:18px; text-align:center; outline:none;">
            <div id="receiptSearchRes" style="display:none; position:absolute; left:0; right:0; background:#fff; border:1px solid #ccc; z-index:1000; max-height:180px; overflow-y:auto; text-align:left;"></div>
        </div>

        <div style="display:flex; gap:15px; justify-content: center;">
            <button onclick="renderImportSection()" style="padding:12px 30px; background:#666; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">HỦY BỎ</button>
            <button onclick="processEditSearch()" style="padding:12px 30px; background:#ff9800; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">TÌM KIẾM</button>
        </div>
        <div id="editSearchMsg" style="margin-top:20px; font-weight:bold;"></div>
    </div>`;
}

/**
 * Hàm tìm gợi ý mã phiếu
 */
async function searchReceiptInDB(keyword) {
  const resBox = document.getElementById("receiptSearchRes");
  if (!keyword || keyword.trim().length < 1) {
    resBox.innerHTML = "";
    resBox.style.display = "none";
    return;
  }

  try {
    const res = await fetch("../api/imports.php?action=list");
    const data = await res.json();

    // Lọc các mã phiếu theo từ khóa (chỉ hiện mã phiếu độc nhất)
    const uniqueReceipts = Array.from(
      new Set(data.map((item) => item.receipt_id)),
    );
    const filtered = uniqueReceipts.filter((id) =>
      id.toLowerCase().includes(keyword.toLowerCase()),
    );

    if (filtered.length > 0) {
      resBox.innerHTML = filtered
        .map(
          (id) => `
            <div onclick="selectReceiptForEdit('${id}')" style="padding:12px; border-bottom:1px solid #eee; cursor:pointer; color:#333;">
                Mã phiếu: <strong>${id}</strong>
            </div>`,
        )
        .join("");
      resBox.style.display = "block";
    } else {
      resBox.innerHTML =
        '<div style="padding:10px; color:#888;">Không tìm thấy mã phiếu</div>';
      resBox.style.display = "block";
    }
  } catch (e) {
    console.error("Lỗi tìm phiếu:", e);
  }
}

function selectReceiptForEdit(id) {
  document.getElementById("editSearchId").value = id;
  document.getElementById("receiptSearchRes").style.display = "none";
  // Tự động tìm kiếm luôn sau khi chọn
  processEditSearch();
}

async function processEditSearch() {
  const id = document.getElementById("editSearchId").value.trim();
  const msg = document.getElementById("editSearchMsg");
  if (!id) {
    msg.innerHTML = "⚠️ Vui lòng nhập mã phiếu!";
    return;
  }

  try {
    const res = await fetch(`../api/imports.php?action=get_ticket&id=${id}`);
    const data = await res.json();
    if (!data.info || !data.info.receipt_id) {
      msg.innerHTML = `<span style="color:red;">❌ Không tồn tại mã phiếu: ${id}</span>`;
    } else if (data.info.status === "completed") {
      msg.innerHTML = `<span style="color:blue;">🚫 Phiếu đã nhập kho, không được sửa!</span>`;
    } else {
      editImport(id);
    }
  } catch (e) {
    msg.innerHTML = "Lỗi kết nối!";
  }
}

/**
 * 10. HOÀN THÀNH NHẬP KHO
 */
async function completeImport(id) {
  if (!confirm("Xác nhận hoàn thành nhập kho?")) return;
  try {
    const res = await fetch(`../api/imports.php?action=complete&id=${id}`);
    const result = await res.json();
    alert(result.message);
    if (result.success) renderImportSection();
  } catch (e) {
    alert("Lỗi hệ thống!");
  }
}
