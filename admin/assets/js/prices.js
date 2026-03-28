// assets/js/prices.js

let currentImportPrice = 0;
let isProductSelected = false;

/**
 * 1. HÀM ĐIỀU HƯỚNG CHÍNH
 */
async function showSection(section) {
  const content = document.getElementById("priceContent");
  if (!content) return;

  if (section === "listPrice") {
    content.innerHTML = `
      <div class="list-container" style="background:#fff; padding:20px; border-radius:8px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0; color:#2c3e50;">📋 DANH SÁCH GIÁ & TỈ LỆ LỢI NHUẬN</h3>
            <input type="text" id="searchPrice" onkeyup="filterPriceTable()" 
                   placeholder="Tìm mã hoặc tên sản phẩm..." 
                   style="padding:10px; border:1px solid #ccc; border-radius:4px; width:250px;">
        </div>
        <div style="overflow-x: auto;">
          <table class="table" style="width:100%; border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f9fa;">
                <th style="padding:12px; border:1px solid #ddd;">Mã sản phẩm</th>
                <th style="padding:12px; border:1px solid #ddd;">Tên Sản Phẩm</th>
                <th style="padding:12px; border:1px solid #ddd; text-align:right;">Giá nhập bình quân</th>
                <th style="padding:12px; border:1px solid #ddd; text-align:center;">Tỉ lệ lợi Nhuận</th>
                <th style="padding:12px; border:1px solid #ddd; text-align:right;">Giá Bán</th>
                <th style="padding:12px; border:1px solid #ddd; text-align:center;">Thao Tác</th>
              </tr>
            </thead>
            <tbody id="priceListTableBody"></tbody>
          </table>
        </div>
      </div>`;
    renderPriceList();
  } else if (section === "addPrice") {
    content.innerHTML = `
      <div class="form-container" style="background:#fff; padding:25px; border-radius:8px; border:1px solid #ddd; max-width: 500px; margin: 20px auto;">
          <h3 style="text-align:center; color:#2196f3; margin-top:0;">📈 THIẾT LẬP GIÁ BÁN</h3>
          <hr style="border:0; border-top:1px solid #eee; margin-bottom:20px;">
          
          <div style="margin-bottom:15px; position:relative;"> 
              <label style="display:block; font-weight:bold; margin-bottom:5px;">Mã sản phẩm:</label>
              <input type="text" id="price_product_id" 
                     onkeyup="suggestProductForLookup(this.value, 'price_product_id')" 
                     placeholder="Nhập mã hoặc tên SP..." 
                     style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box;" 
                     autocomplete="off">
              <div id="lookupSuggestRes" style="display:none; position:absolute; top:70px; left:0; width:100%; background:#fff; border:1px solid #ddd; z-index:1000; max-height:200px; overflow-y:auto; box-shadow:0 4px 8px rgba(0,0,0,0.1);"></div>
          </div>

          <div id="costDisplay" style="display:none; padding:15px; background:#f0f7ff; border-left:4px solid #2196f3; border-radius:4px; margin-bottom:15px;">
              <p style="margin:0 0 5px 0;">Sản phẩm: <strong id="display_name">--</strong></p>
              <p style="margin:0;">Giá nhập bình quân: <strong id="display_cost" style="color:red;">0</strong> VNĐ</p>
          </div>

          <div style="margin-bottom:15px;">
              <label style="display:block; font-weight:bold; margin-bottom:5px;">Tỉ lệ lợi nhuận (%):</label>
              <input type="text" id="profit_rate" 
                     oninput="validateProfitInput(this); calculatePreviewPrice();" 
                     onkeypress="return isNumberKey(event, this)"
                     placeholder="Ví dụ: 20.5" 
                     style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; box-sizing: border-box;">
          </div>

          <div style="margin-bottom:20px; padding:15px; background:#e8f5e9; border-radius:4px; text-align:center; border: 1px dashed #4caf50;">
              <span style="font-size:14px; color:#666;">GIÁ BÁN DỰ KIẾN:</span><br>
              <strong id="preview_sale_price" style="font-size:26px; color:#2e7d32;">0 đ</strong>
          </div>

          <div style="display:flex; gap:10px;">
              <button onclick="saveNewPrice()" style="flex:1; padding:12px; background:#4caf50; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">LƯU GIÁ BÁN</button>
              <button onclick="showSection('listPrice')" style="flex:1; padding:12px; background:#666; color:white; border:none; border-radius:4px; cursor:pointer;">QUAY LẠI</button>
          </div>
      </div>`;
    isProductSelected = false;
    currentImportPrice = 0;
  } else if (section === "lookupPrice") {
    content.innerHTML = `
      <div class="list-container" style="background:#fff; padding:20px; border-radius:8px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0; color:#2c3e50;">🔍 TRA CỨU GIÁ THEO PHIẾU NHẬP</h3>
            <input type="text" id="searchLookup" onkeyup="filterLookupTable()" 
                   placeholder="Lọc mã phiếu, tên sản phẩm..." 
                   style="padding:10px; border:1px solid #ccc; border-radius:4px; width:280px;">
        </div>
        <div id="lookupResult">
            <p style="text-align:center; padding:20px;">⌛ Đang kết nối kho dữ liệu...</p>
        </div>
      </div>`;

    // Gọi hàm tải dữ liệu ngay
    loadAllImportPrices();
  }
}

// Hàm mới để lấy toàn bộ danh sách khi vừa nhấn nút
async function loadAllImportPrices() {
  const resultDiv = document.getElementById("lookupResult");
  try {
    const response = await fetch(
      `../API/prices.php?action=lookup_import&product_name=`,
    );
    const data = await response.json();

    if (!data.details || data.details.length === 0) {
      resultDiv.innerHTML =
        "<p style='text-align:center; padding:20px; color:#666;'>Chưa có lịch sử nhập hàng nào được ghi nhận.</p>";
      return;
    }

    let html = `
      <table class="table" style="width:100%; border-collapse:collapse;">
        <thead>
          <tr style="background:#f8f9fa;">
          <th style="padding:12px; border:1px solid #ddd ;">Mã phiếu</th>
          <th style="padding:12px; border:1px solid #ddd;">Tên sản phẩm</th>
          <th style="padding:12px; border:1px solid #ddd;">Giá vốn</th>
          <th style="padding:12px; border:1px solid #ddd;">% Lợi nhuận</th>
          <th style="padding:12px; border:1px solid #ddd;">Ngày nhập</th>
            <th style="padding:12px; border:1px solid #ddd;">Giá bán gợi ý</th>
          </tr>
        </thead>
        <tbody id="lookupTableBody">`;

    html += data.details
      .map((item) => {
        const cost = parseFloat(item.import_price) || 0;
        const profit = parseFloat(item.profit_percent) || 0;
        const salePrice = Math.round(cost * (1 + profit / 100));
        return `
        <tr>
        <td style="padding:10px; border:1px solid #ddd; text-align:center; font-weight:bold;">${item.receipt_id}</td>
        <td style="padding:10px; border:1px solid #ddd;">${item.product_name}</td>
        <td style="padding:10px; border:1px solid #ddd; text-align:right;">${cost.toLocaleString()}đ</td>
        <td style="padding:10px; border:1px solid #ddd; text-align:center;">${profit}%</td>
        <td style="padding:10px; border:1px solid #ddd; text-align:center;">${item.import_date}</td>
          <td style="padding:10px; border:1px solid #ddd; text-align:right; font-weight:bold; color:#27ae60;">
            ${salePrice.toLocaleString()}đ
          </td>
        </tr>`;
      })
      .join("");

    html += "</tbody></table>";
    resultDiv.innerHTML = html;
  } catch (err) {
    resultDiv.innerHTML =
      "<p style='color:red;'>Lỗi tải dữ liệu. Vui lòng kiểm tra lại kết nối API!</p>";
  }
}
/**
 * 2. HÀM XỬ LÝ DỮ LIỆU DANH SÁCH
 */
async function renderPriceList() {
  const tbody = document.getElementById("priceListTableBody");
  if (!tbody) return;

  try {
    const response = await fetch("../API/prices.php?action=get_all_prices");
    let data = await response.json();

    if (!data || data.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:20px;">Chưa có sản phẩm nào.</td></tr>`;
      return;
    }

    // Sắp xếp theo mã số tăng dần
    data.sort(
      (a, b) =>
        (parseInt(a.product_id.replace(/\D/g, "")) || 0) -
        (parseInt(b.product_id.replace(/\D/g, "")) || 0),
    );

    tbody.innerHTML = data
      .map((item) => {
        const importPrice = parseFloat(item.import_price) || 0;
        const salePrice = parseFloat(item.sale_price) || 0;
        const profitRate = parseFloat(item.profit_percent) || 0;

        return `
        <tr>
            <td style="text-align:center; padding:12px; font-weight:bold;">${item.product_id}</td>
            <td style="padding:12px;">${item.product_name}</td>
            <td style="text-align:right; padding:12px;">${new Intl.NumberFormat().format(importPrice)} đ</td>
            <td style="text-align:center; padding:12px;">
                <span style="background:#e3f2fd; color:#1976d2; padding:4px 10px; border-radius:20px; font-weight:bold; font-size:12px;">
                    ${profitRate}%
                </span>
            </td>
            <td style="text-align:right; padding:12px; font-weight:bold; color:#2e7d32;">
                ${salePrice > 0 ? new Intl.NumberFormat().format(salePrice) + " đ" : "Chưa đặt giá"}
            </td>
            <td style="text-align:center; padding:12px;">
                <button onclick="editPriceDirectly('${item.product_id}', '${profitRate}')" 
                        style="padding:6px 12px; background:#f39c12; color:white; border:none; border-radius:4px; cursor:pointer; font-size:12px;">
                    Sửa tỉ lệ lợi nhuận
                </button>
            </td>
        </tr>`;
      })
      .join("");
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="6" style="color:red; text-align:center;">Lỗi kết nối API!</td></tr>`;
  }
}

/**
 * 3. HÀM TRA CỨU PHIẾU NHẬP (5 CỘT)
 */
async function checkPriceLookup() {
  const pName = document.getElementById("lookupPName").value.trim();
  const resultDiv = document.getElementById("lookupResult");

  if (!pName) {
    alert("Vui lòng nhập tên hoặc mã sản phẩm!");
    return;
  }

  resultDiv.innerHTML = "<p>⌛ Đang lấy dữ liệu...</p>";

  try {
    const response = await fetch(
      `../API/prices.php?action=lookup_import&product_name=${encodeURIComponent(pName)}`,
    );
    const data = await response.json();

    // Kiểm tra nếu không có mảng details hoặc mảng rỗng
    if (!data.details || data.details.length === 0) {
      resultDiv.innerHTML = `<p style="color:red; padding:10px; background:#fff1f0; border:1px solid #ffa39e;">
                ❌ Không tìm thấy lịch sử nhập hàng cho sản phẩm này.
            </p>`;
      return;
    }

    let html = `
            <table class="table" style="width:100%; margin-top:15px; border-collapse: collapse;">
                <thead>
                    <tr style="background:#fafafa;">
                        <th style="border:1px solid #f0f0f0; padding:12px;">Mã SP</th>
                        <th style="border:1px solid #f0f0f0; padding:12px;">Tên sản phẩm</th>
                        <th style="border:1px solid #f0f0f0; padding:12px;">Giá vốn nhập</th>
                        <th style="border:1px solid #f0f0f0; padding:12px;">% Lợi nhuận</th>
                        <th style="border:1px solid #f0f0f0; padding:12px;">Giá bán gợi ý</th>
                    </tr>
                </thead>
                <tbody>
        `;

    html += data.details
      .map((item) => {
        const cost = parseFloat(item.import_price) || 0;
        const profit = parseFloat(item.profit_percent) || 0;
        const salePrice = Math.round(cost * (1 + profit / 100));

        return `
                <tr>
                    <td style="border:1px solid #f0f0f0; padding:10px; text-align:center;">${item.product_id}</td>
                    <td style="border:1px solid #f0f0f0; padding:10px;">${item.product_name}</td>
                    <td style="border:1px solid #f0f0f0; padding:10px; text-align:right;">${cost.toLocaleString()}đ</td>
                    <td style="border:1px solid #f0f0f0; padding:10px; text-align:center;">${profit}%</td>
                    <td style="border:1px solid #f0f0f0; padding:10px; text-align:right; font-weight:bold; color:#27ae60;">
                        ${salePrice.toLocaleString()}đ
                    </td>
                </tr>
            `;
      })
      .join("");

    html += "</tbody></table>";
    resultDiv.innerHTML = html;
  } catch (err) {
    console.error(err);
    resultDiv.innerHTML = "<p style='color:red;'>Lỗi kết nối máy chủ!</p>";
  }
}
/**
 * 4. LOGIC TÌM KIẾM & GỢI Ý
 */
async function suggestProductForLookup(key, targetInputId) {
  const resBox = document.getElementById("lookupSuggestRes");
  if (!resBox) return;

  if (!key.trim()) {
    resBox.style.display = "none";
    if (targetInputId === "price_product_id") {
      document.getElementById("costDisplay").style.display = "none";
      currentImportPrice = 0;
      calculatePreviewPrice();
    }
    return;
  }

  try {
    const res = await fetch(
      `../API/prices.php?action=search_products&key=${encodeURIComponent(key)}`,
    );
    const data = await res.json();

    if (data && data.length > 0) {
      resBox.innerHTML = data
        .map(
          (p) => `
        <div onclick="selectProductFromSuggest('${p.product_id}', '${p.product_name}', '${targetInputId}')" 
             style="padding:10px; cursor:pointer; border-bottom:1px solid #eee;"
             onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='#fff'">
            <strong style="color:#2196f3;">${p.product_id}</strong> - ${p.product_name}
        </div>`,
        )
        .join("");
      resBox.style.display = "block";
    } else {
      resBox.innerHTML = `<div style="padding:10px; color:#999;">Không tìm thấy</div>`;
      resBox.style.display = "block";
    }
  } catch (e) {
    console.error(e);
  }
}

function selectProductFromSuggest(id, name, targetInputId) {
  const input = document.getElementById(targetInputId);
  if (input) {
    input.value = targetInputId === "price_product_id" ? id : name;
    isProductSelected = true;
  }
  document.getElementById("lookupSuggestRes").style.display = "none";
  if (targetInputId === "price_product_id") fetchProductCost();
  else checkPriceLookup();
}

/**
 * 5. CÁC HÀM TÍNH TOÁN & LƯU
 */
async function fetchProductCost() {
  const pid = document.getElementById("price_product_id").value.trim();
  if (!pid) return;

  try {
    const res = await fetch(
      `../API/prices.php?action=get_cost&product_id=${pid}`,
    );
    const data = await res.json();

    if (data.error) {
      alert("Sản phẩm không tồn tại!");
      isProductSelected = false;
    } else {
      isProductSelected = true;
      currentImportPrice = parseFloat(data.import_price) || 0;
      document.getElementById("display_name").innerText = data.product_name;
      document.getElementById("display_cost").innerText =
        new Intl.NumberFormat().format(currentImportPrice);
      document.getElementById("costDisplay").style.display = "block";
      calculatePreviewPrice();
    }
  } catch (e) {
    console.error(e);
  }
}

function calculatePreviewPrice() {
  const rate = parseFloat(document.getElementById("profit_rate").value) || 0;
  if (currentImportPrice <= 0) {
    document.getElementById("preview_sale_price").innerText = "0 đ";
    return;
  }
  const salePrice = Math.round(currentImportPrice * (1 + rate / 100));
  document.getElementById("preview_sale_price").innerText =
    new Intl.NumberFormat().format(salePrice) + " đ";
}

async function saveNewPrice() {
  if (
    !confirm(
      "Bạn có chắc chắn muốn lưu thay đổi giá bán cho sản phẩm này không?",
    )
  ) {
    return;
  }
  const pid = document.getElementById("price_product_id").value;
  const rate = document.getElementById("profit_rate").value;
  if (!isProductSelected) return alert("Vui lòng chọn sản phẩm hợp lệ!");

  try {
    const res = await fetch(`../API/prices.php?action=upsert`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        product_id: pid,
        profit_rate: parseFloat(rate),
        import_price: currentImportPrice,
      }),
    });
    const result = await res.json();
    alert(result.message);
    if (result.success) showSection("listPrice");
  } catch (e) {
    alert("Lỗi lưu dữ liệu!");
  }
}

/**
 * 6. HÀM SỬA NHANH & LỌC
 */
function editPriceDirectly(pid, currentProfit) {
  showSection("addPrice");
  isProductSelected = true;
  setTimeout(() => {
    const input = document.getElementById("price_product_id");
    const profitInput = document.getElementById("profit_rate");
    if (input) {
      input.value = pid;
      input.readOnly = true;
      input.style.background = "#f5f5f5";
      if (profitInput) profitInput.value = currentProfit;
      fetchProductCost(); // Tự động lấy giá vốn và tính toán lại Preview
    }
  }, 100);
}

function filterPriceTable() {
  const input = document.getElementById("searchPrice").value.toUpperCase();
  const rows = document
    .getElementById("priceListTableBody")
    .getElementsByTagName("tr");
  for (let row of rows) {
    const id = row.getElementsByTagName("td")[0].textContent.toUpperCase();
    const name = row.getElementsByTagName("td")[1].textContent.toUpperCase();
    row.style.display =
      id.includes(input) || name.includes(input) ? "" : "none";
  }
}
function filterLookupTable() {
  const input = document.getElementById("searchLookup").value.toUpperCase();
  const rows = document.getElementById("lookupTableBody").getElementsByTagName("tr");
  for (let row of rows) {
    const text = row.textContent.toUpperCase();
    row.style.display = text.includes(input) ? "" : "none";
  }
}

// Chặn ký tự lạ & Validate Input
function isNumberKey(evt, element) {
  var charCode = evt.which ? evt.which : evt.keyCode;
  if (charCode <= 31) return true;
  if (charCode === 46) return element.value.indexOf(".") === -1;
  return charCode >= 48 && charCode <= 57;
}

function validateProfitInput(element) {
  let value = element.value.replace(/[^0-9.]/g, "");
  const parts = value.split(".");
  if (parts.length > 2) value = parts[0] + "." + parts.slice(1).join("");
  element.value = value;
}

// Đóng gợi ý khi click ra ngoài
document.addEventListener("click", function (e) {
  const resBox = document.getElementById("lookupSuggestRes");
  if (
    resBox &&
    e.target.id !== "lookupPName" &&
    e.target.id !== "price_product_id" &&
    !resBox.contains(e.target)
  ) {
    resBox.style.display = "none";
  }
});
