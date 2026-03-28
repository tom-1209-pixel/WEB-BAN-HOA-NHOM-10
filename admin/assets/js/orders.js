document.addEventListener("DOMContentLoaded", () => {
  loadOrders();
});

async function loadOrders() {
  const fromDate = document.getElementById("filterFromDate").value;
  const toDate = document.getElementById("filterToDate").value;
  const status = document.getElementById("filterStatus").value;
  const sortWard = document.getElementById("sortWard").value;

  const url = `../API/orders.php?action=index&fromDate=${fromDate}&toDate=${toDate}&status=${status}&sortWard=${sortWard}`;

  try {
    const res = await fetch(url);
    if (!res.ok) throw new Error("Lỗi kết nối Server");

    const data = await res.json();
    const tbody = document.getElementById("orderTableBody");

    // 1. Kiểm tra nếu không có dữ liệu
    if (!data || data.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" style="padding: 40px; text-align: center; color: #7f8c8d;">
            <div style="font-size: 40px; margin-bottom: 10px;">📦</div>
            <p style="font-size: 16px; font-weight: bold;">Hiện chưa có đơn hàng nào phù hợp với bộ lọc.</p>
          </td>
        </tr>`;
      return;
    }

    // 2. Render dữ liệu đơn hàng
    tbody.innerHTML = data
      .map((order) => {
        let actionButtons = "";

        if (order.order_status === "pending") {
          // Nút Xác nhận (Màu xanh dương)
          actionButtons = `
            <button onclick="quickUpdateStatus('${order.order_id}', 'confirmed')" 
                style="padding:5px 10px; background:#3498db; color:white; border:none; border-radius:4px; cursor:pointer; margin-right:5px; font-size:12px;">
                Xác nhận
            </button>
          `;

          // Nút Hủy (Màu đỏ) - Chỉ hiện khi chưa thanh toán
          if (order.payment_status !== "paid") {
            actionButtons += `
                <button onclick="quickUpdateStatus('${order.order_id}', 'cancelled')" 
                    style="padding:5px 10px; background:#e74c3c; color:white; border:none; border-radius:4px; cursor:pointer; font-size:12px;">
                    Hủy
                </button>
            `;
          }
        } else if (order.order_status === "confirmed") {
          // Nút Đã giao (Màu xanh lá)
          actionButtons = `
            <button onclick="quickUpdateStatus('${order.order_id}', 'delivered')" 
                style="padding:5px 10px; background:#2ecc71; color:white; border:none; border-radius:4px; cursor:pointer; font-size:12px;">
                Đã giao
            </button>
          `;
        } else {
          // Trạng thái Đã giao hoặc Đã hủy
          actionButtons = `
            <span style="color: #7f8c8d; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                <i class="fas fa-check-circle"></i> Đã hoàn tất
            </span>
          `;
        }

        return `
            <tr style="border-bottom:1px solid #eee; text-align:center;">
                <td style="padding:12px;">
                    <a href="javascript:void(0)" onclick="viewOrderDetail('${order.order_id}')" 
                       style="font-weight:bold; color:#3498db; text-decoration:none;">
                       ${order.order_id}
                    </a>
                </td>
                <td>${new Date(order.order_date).toLocaleDateString("vi-VN")}</td>
                <td>${order.delivery_name}</td>
                <td>${order.delivery_ward}</td>
                <td style="color:#e74c3c; font-weight:bold;">${parseFloat(order.total_price).toLocaleString()}đ</td>
                <td>${renderStatusBadge(order.order_status)}</td>
                <td style="padding:10px;">${actionButtons}</td>
            </tr>`;
      })
      .join("");
  } catch (err) {
    console.error("Lỗi:", err);
    document.getElementById("orderTableBody").innerHTML = `
      <tr>
        <td colspan="7" style="padding: 40px; text-align: center; color: #e74c3c;">
          <p>⚠️ Không thể kết nối với máy chủ hoặc lỗi dữ liệu!</p>
        </td>
      </tr>`;
  }
}

function renderStatusBadge(status) {
  const config = {
    pending: { text: "Chưa xử lý", color: "#f39c12" },
    confirmed: { text: "Đã xác nhận", color: "#3498db" },
    delivered: { text: "Đã giao hàng", color: "#2ecc71" },
    cancelled: { text: "Đã hủy", color: "#e74c3c" },
  };
  const s = config[status] || config["pending"];
  return `<span style="background:${s.color}; color:white; padding:4px 8px; border-radius:12px; font-size:11px;">${s.text}</span>`;
}

// 1. Sửa hàm xem chi tiết
async function viewOrderDetail(id) {
  try {
    const res = await fetch(`../API/orders.php?action=get_detail&id=${id}`);
    const data = await res.json();

    // 1. Ẩn danh sách, Hiện vùng chi tiết
    document.getElementById("orderListSection").style.display = "none";
    const detailSection = document.getElementById("orderDetailSection");
    detailSection.style.display = "block";

    // 2. Đổ dữ liệu vào vùng chi tiết
    detailSection.innerHTML = `
        <div style="background:#fff; padding:20px; border-radius:8px; border:1px solid #ddd; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h4 style="color:#2c3e50; border-bottom:2px solid #3498db; padding-bottom:10px; margin-bottom:20px;">📦 CHI TIẾT ĐƠN HÀNG: ${id}</h4>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                <div style="line-height:1.6;">
                    <p><strong>Khách hàng:</strong> ${data.order.delivery_name}</p>
                    <p><strong>Số điện thoại:</strong> ${data.order.delivery_phone}</p>
                    <p><strong>Địa chỉ:</strong> ${data.order.delivery_address}</p>
                    <p><strong>Khu vực:</strong> ${data.order.delivery_ward}, ${data.order.delivery_city}</p>
                </div>
                <div style="line-height:1.6; border-left:1px solid #eee; padding-left:20px;">
                    <p><strong>Thanh toán:</strong> ${renderPaymentMethod(data.order.payment_method)}</p>
                    <p><strong>Tiền hàng:</strong> ${renderPaymentStatus(data.order.payment_status)}</p>
                    <p><strong>Trạng thái:</strong> ${renderStatusBadge(data.order.order_status)}</p>
                </div>
            </div>
            
            <table style="width:100%; border-collapse:collapse; margin-bottom:25px;">
                <thead>
                    <tr style="background:#f8f9fa; text-align:left; border-bottom:2px solid #eee;">
                        <th style="padding:12px;">Sản phẩm</th>
                        <th style="padding:12px; text-align:center;">Số lượng</th>
                        <th style="padding:12px; text-align:right;">Đơn giá</th>
                        <th style="padding:12px; text-align:right;">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.details
                      .map(
                        (d) => `
                        <tr style="border-bottom:1px solid #f9f9f9;">
                            <td style="padding:12px;"><strong>${d.product_name}</strong></td>
                            <td style="padding:12px; text-align:center;">${d.quantity}</td>
                            <td style="padding:12px; text-align:right;">${parseFloat(d.price).toLocaleString()}đ</td>
                            <td style="padding:12px; text-align:right; font-weight:bold;">${(d.quantity * d.price).toLocaleString()}đ</td>
                        </tr>
                    `,
                      )
                      .join("")}
                </tbody>
            </table>

            <div style="text-align: right; margin-bottom: 20px;">
                 <h3 style="color:#e74c3c;">Tổng cộng: ${parseFloat(data.order.total_price).toLocaleString()}đ</h3>
            </div>

            <button onclick="closeOrderDetail()" style="background:#95a5a6; color:white; border:none; padding:12px 25px; border-radius:4px; cursor:pointer; font-weight:bold; transition: 0.3s;">
                <i class="fas fa-arrow-left"></i> QUAY LẠI
            </button>
        </div>
    `;
    window.scrollTo(0, 0);
  } catch (err) {
    alert("Lỗi tải chi tiết đơn hàng!");
    console.error(err);
  }
}

// 2. Thêm hàm đóng chi tiết
function closeOrderDetail() {
  document.getElementById("orderDetailSection").style.display = "none";
  document.getElementById("orderListSection").style.display = "block";
}
// Hàm bổ trợ hiển thị Phương thức thanh toán
function renderPaymentMethod(method) {
  const methods = {
    cash: "💵 Tiền mặt (COD)",
    bank: "🏦 Chuyển khoản",
    online: "💳 Thanh toán Online",
  };
  return methods[method] || method;
}

// Hàm bổ trợ hiển thị Trạng thái thanh toán
function renderPaymentStatus(status) {
  if (status === "paid") {
    return `<span style="color:#27ae60; font-weight:bold;">✅ Đã thanh toán</span>`;
  }
  return `<span style="color:#e74c3c; font-weight:bold;">❌ Chưa thanh toán</span>`;
}

function renderPaymentMethod(method) {
  const map = { cash: "Tiền mặt", bank: "Chuyển khoản", online: "Ví điện tử" };
  return `<span style="color:#2980b9; font-weight:bold;">${map[method] || method}</span>`;
}

function renderPaymentStatus(status) {
  const config = {
    unpaid: { text: "Chưa thanh toán", color: "#e74c3c" },
    paid: { text: "Đã thanh toán", color: "#27ae60" },
    failed: { text: "Thanh toán lỗi", color: "#95a5a6" },
  };
  const s = config[status] || config["unpaid"];
  return `<span style="color:${s.color}; font-weight:bold;">${s.text}</span>`;
}
async function quickUpdateStatus(id, newStatus) {
  // Chuyển mã trạng thái thành tiếng Việt để hiển thị trong thông báo confirm
  const statusMap = {
    confirmed: "XÁC NHẬN đơn hàng",
    delivered: "đánh dấu ĐÃ GIAO",
    cancelled: "HỦY đơn hàng",
  };

  if (!confirm(`Bạn có chắc chắn muốn ${statusMap[newStatus]} ${id}?`)) return;

  try {
    const res = await fetch(`../API/orders.php?action=update_status`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id, status: newStatus }),
    });

    const result = await res.json();
    if (result.success) {
      // Thay vì dùng alert gây phiền, bạn có thể dùng console hoặc reload nhanh
      loadOrders();
    } else {
      alert("Lỗi: " + result.message);
    }
  } catch (err) {
    alert("Không thể kết nối máy chủ để cập nhật trạng thái!");
  }
}
function closeOrderDetail() {
  // 1. Ẩn vùng chi tiết
  document.getElementById("orderDetailSection").style.display = "none";

  // 2. Hiện lại vùng danh sách (Vùng này vẫn giữ nguyên các input và dữ liệu cũ)
  document.getElementById("orderListSection").style.display = "block";

  // Không dùng location.reload() ở đây!
}