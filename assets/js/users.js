// users.js

function showSection(section) {
  let content = document.getElementById("userContent");

  if (section === "list") {
    content.innerHTML = `
      <h2>Danh sách khách hàng</h2>
      <table class="table">
        <thead>
          <tr>
            <th>ID</th><th>Họ tên</th><th>Email</th><th>SĐT</th><th>Địa chỉ</th><th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>KH001</td><td>Nguyễn Văn A</td><td>a@gmail.com</td><td>0901234567</td><td>Quận 1</td>
            <td><a href="#">✏️ Sửa</a> | <a href="#">🗑️ Xóa</a></td>
          </tr>
        </tbody>
      </table>
    `;
  } else if (section === "add") {
    content.innerHTML = `
      <h2>Thêm khách hàng</h2>
      <form>
        <label>Họ tên: <input type="text"></label><br>
        <label>Email: <input type="email"></label><br>
        <label>SĐT: <input type="text"></label><br>
        <label>Địa chỉ: <input type="text"></label><br>
        <button type="submit">Lưu</button>
      </form>
    `;
  } else if (section === "edit") {
    content.innerHTML = `
      <h2>Sửa khách hàng</h2>
      <p>Chọn khách hàng cần sửa từ danh sách.</p>
    `;
  }
}
