// ====== Invoices – Tạo nhanh không nhập tổng ======
const API_INVOICE = "InvoiceController.php";
const API_CUSTOMER = "CustomerController.php";
const API_BOOKING  = "BookingController.php";

async function loadCustomersToSelect(){
  const cs = await api(API_CUSTOMER);
  document.getElementById("iv_customer").innerHTML =
    cs.map(c=>`<option value="${c.customer_id}">${c.full_name} (#${c.customer_id})</option>`).join("");
  await reloadBookingsByCustomer();
}

async function reloadBookingsByCustomer(){
  const cid = document.getElementById("iv_customer").value;
  // Lấy tất cả booking rồi lọc theo customer (hoặc bạn làm một API GET /booking?customer_id=..)
  const all = await api(API_BOOKING);
  const list = all.filter(b=>String(b.customer_id)===String(cid));
  const sel = document.getElementById("iv_booking");
  sel.innerHTML = list.map(b=>`<option value="${b.booking_id}">Booking #${b.booking_id} – Phòng ${b.room_id}</option>`).join("");
}

async function previewInvoice(){
  const bid = document.getElementById("iv_booking").value;
  if(!bid){ document.getElementById("iv_preview").innerHTML="<em>Chưa có booking</em>"; return; }
  const detail = await api(`InvoiceController.php?action=generate&booking_id=${bid}`);
  if(detail.error){
    document.getElementById("iv_preview").innerHTML = `<span class="text-red-600">${detail.error}</span>`;
    return;
  }
  document.getElementById("iv_preview").innerHTML = `
    <div class="grid grid-cols-2 gap-2">
      <div><b>Khách:</b> ${detail.customer}</div>
      <div><b>Phòng:</b> ${detail.room}</div>
      <div><b>Số đêm:</b> ${detail.nights}</div>
      <div><b>Tiền phòng:</b> ${fmtMoney(detail.room_cost)}</div>
    </div>
    <div class="mt-2">
      <b>Dịch vụ:</b>
      <ul class="list-disc ml-6">
        ${detail.services.length? detail.services.map(s=>`<li>${s.service_name} × ${s.quantity} = ${fmtMoney(s.quantity*s.unit_price)}</li>`).join("") : "<li>Không sử dụng</li>"}
      </ul>
      <div class="mt-2"><b>Tổng dịch vụ:</b> ${fmtMoney(detail.service_total)}</div>
      <div class="mt-2 text-lg font-bold">TỔNG CỘNG: ${fmtMoney(detail.total_amount)}</div>
    </div>
  `;
}

async function createInvoiceAndPrint(){
  const bid = document.getElementById("iv_booking").value;
  const status = document.getElementById("iv_status").value;

  // POST tạo invoice – server tự tính total + lấy staff từ session
  await api(API_INVOICE, {
    method:"POST",
    body: JSON.stringify({ booking_id: bid, payment_status: status })
  });
  toast("Đã tạo hóa đơn");

  // mở modal in hiện có của bạn
  closeModal("invoiceQuickModal");
  await printInvoice(bid); // dùng hàm printInvoice bạn đã có
  // reload danh sách
  if(typeof loadInvoices==="function") loadInvoices();
}

// Bind sự kiện khi trang invoices.html mở
document.addEventListener("DOMContentLoaded", async ()=>{
  const btnNew = document.getElementById("btnNewInvoice");
  if(btnNew){
    btnNew.addEventListener("click", async ()=>{
      openModal("invoiceQuickModal");
      await loadCustomersToSelect();
      await previewInvoice();
    });
  }
  document.getElementById("iv_customer").addEventListener("change", async ()=>{
    await reloadBookingsByCustomer();
    await previewInvoice();
  });
  document.getElementById("iv_booking").addEventListener("change", previewInvoice);
  document.getElementById("btnPreviewInvoice").addEventListener("click", previewInvoice);
  document.getElementById("btnCreateInvoice").addEventListener("click", createInvoiceAndPrint);
});
