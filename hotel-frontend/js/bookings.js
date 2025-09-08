// =================== CONFIG ===================
const API_BASE     = "http://localhost:8000/hotel/hotel-api-php/src/controllers";
const API_BOOKING  = `${API_BASE}/BookingController.php`;
const API_CUSTOMER = `${API_BASE}/CustomerController.php`;
const API_ROOM     = `${API_BASE}/RoomController.php`;
const API_SERVICE  = `${API_BASE}/ServiceController.php`;

// ============ JSON-FETCH AN TOÀN (CHỐNG HTML) ============
async function jfetch(url, opt = {}) {
  const res = await fetch(url + (url.includes("?") ? "&" : "?") + "_=" + Date.now(), {
    cache: "no-store",
    headers: { "Content-Type": "application/json", ...(opt.headers || {}) },
    ...opt
  });
  const raw = await res.text();
  let data;
  try { data = JSON.parse(raw); }
  catch { throw new Error("Server trả non-JSON:\n" + raw.slice(0, 400)); }
  if (!res.ok) throw new Error(data?.message || `HTTP ${res.status}`);
  return data;
}

// =================== STATE & HELPERS ===================
let servicesState = []; // [{service_id, name, unit_price, quantity}]
let allServices   = [];

const $  = (s, r=document) => r.querySelector(s);
const $$ = (s, r=document) => Array.from(r.querySelectorAll(s));
function openModal(){ $('#bookingModal').classList.replace('hidden','flex'); }
function closeModal(){ $('#bookingModal').classList.replace('flex','hidden'); }
function ymd(d){ return d.toISOString().slice(0,10); }

// =================== LOAD DROPDOWNS ===================
async function loadCustomers(){
  const data = await jfetch(API_CUSTOMER);
  $('#customer_id').innerHTML = (data||[]).map(c => `<option value="${c.customer_id}">${c.full_name}</option>`).join("");
}
async function loadRooms(){
  const data = await jfetch(`${API_ROOM}?status=AVAILABLE`);
  $('#room_id').innerHTML = (data||[]).map(r => `<option value="${r.room_id}">${r.room_number} - ${r.room_type}</option>`).join("");
}
async function loadServices(){
  allServices = await jfetch(API_SERVICE).catch(()=>[]);
  $('#service_select').innerHTML = (allServices||[])
    .map(s => `<option value="${s.service_id}" data-price="${s.unit_price}">${s.service_name}</option>`)
    .join("");
}

// =================== DỊCH VỤ (THÊM TỪNG CÁI) ===================
function renderChosenServices(){
  const box = $('#serviceChosen');
  if (!servicesState.length) {
    box.innerHTML = `<div class="text-sm text-gray-500">Chưa có dịch vụ nào.</div>`;
    return;
  }
  box.innerHTML = servicesState.map(s => `
    <div class="flex items-center justify-between border rounded p-2">
      <div>
        <div class="font-medium">${s.name}</div>
        <div class="text-xs text-gray-500">Số lượng: ${s.quantity}</div>
      </div>
      <button type="button" class="px-2 py-1 rounded bg-red-600 text-white" data-remove="${s.service_id}">Xóa</button>
    </div>
  `).join("");
}
function addSelectedService(){
  const sel = $('#service_select');
  const qty = Number($('#service_qty').value || 0);
  if (!sel.value || qty <= 0) { alert('Chọn dịch vụ và số lượng > 0'); return; }
  const sid = sel.value;
  const name = sel.selectedOptions[0].textContent.trim();
  const unit_price = Number(sel.selectedOptions[0].dataset.price || 0);
  const exist = servicesState.find(x => String(x.service_id) === String(sid));
  if (exist) exist.quantity += qty;
  else servicesState.push({ service_id: sid, name, unit_price, quantity: qty });
  $('#service_qty').value = 1;
  renderChosenServices();
}
document.addEventListener('click', (ev)=>{
  const btn = ev.target.closest('[data-remove]');
  if (!btn) return;
  const sid = btn.getAttribute('data-remove');
  servicesState = servicesState.filter(x => String(x.service_id) !== String(sid));
  renderChosenServices();
});

// =================== LIST TABLE ===================
async function loadBookings(){
  const data = await jfetch(API_BOOKING);
  const tb = $('#bookingTableBody');
  tb.innerHTML = (data||[]).map((b,i)=>`
    <tr class="border-b">
      <td class="px-4 py-2 text-left">${i+1}</td>
      <td class="px-4 py-2 text-left">${b.customer_name ?? b.customer_id}</td>
      <td class="px-4 py-2 text-left">${b.room_number ?? b.room_id}</td>
      <td class="px-4 py-2 text-left">${b.checkin_date}</td>
      <td class="px-4 py-2 text-left">${b.checkout_date}</td>
      <td class="px-4 py-2 text-left">${b.status}</td>
      <td class="px-4 py-2 text-right">
        <button onclick="openEdit(${b.booking_id})" class="bg-yellow-400 px-3 py-1 rounded mr-2">Sửa</button>
        <button onclick="deleteBooking(${b.booking_id})" class="bg-red-600 text-white px-3 py-1 rounded">Xóa</button>
      </td>
    </tr>
  `).join("");
}

// =================== OPEN/CLOSE MODAL ===================
function openNew(){
  $('#modalTitle').textContent = "Thêm Booking";
  $('#booking_id').value = "";
  const today = new Date();
  $('#checkin_date').value = ymd(today);
  $('#checkout_date').value = ymd(new Date(today.getTime()+86400000));
  $('#status_booking').value = "CONFIRMED";
  servicesState = [];
  renderChosenServices();
  openModal();
}
function closeBookingModal(){ closeModal(); }

// =================== EDIT ===================
async function openEdit(id){
  const b = await jfetch(`${API_BOOKING}?action=getById&id=${id}`);
  $('#modalTitle').textContent = "Sửa Booking";
  $('#booking_id').value = b.booking_id;
  $('#customer_id').value = b.customer_id;
  $('#room_id').value = b.room_id;
  $('#checkin_date').value = b.checkin_date;
  $('#checkout_date').value = b.checkout_date;
  $('#status_booking').value = b.status;

  servicesState = (b.services || []).map(s => ({
    service_id: s.service_id,
    name: s.service_name || ("Dịch vụ #" + s.service_id),
    unit_price: Number(s.unit_price || 0),
    quantity: Number(s.quantity || 1)
  }));
  renderChosenServices();
  openModal();
}
window.openEdit = openEdit;

// =================== SUBMIT ===================
document.getElementById("bookingForm").addEventListener("submit", async (e)=>{
  e.preventDefault();
  const id = $('#booking_id').value;
  const payload = {
    customer_id  : $('#customer_id').value,
    room_id      : $('#room_id').value,
    checkin_date : $('#checkin_date').value,
    checkout_date: $('#checkout_date').value,
    status       : $('#status_booking').value
  };
  if (!payload.customer_id || !payload.room_id || !payload.checkin_date || !payload.checkout_date) {
    alert("Vui lòng nhập đủ thông tin"); return;
  }
  if (new Date(payload.checkin_date) >= new Date(payload.checkout_date)) {
    alert("Ngày trả phải sau ngày nhận"); return;
  }
  if (!id && servicesState.length){
    payload.services = servicesState.map(({service_id, quantity}) => ({service_id, quantity}));
  }

  try{
    let out;
    if (id) {
      out = await jfetch(`${API_BOOKING}?action=update&id=${id}`, { method:"PUT", body: JSON.stringify(payload) });
    } else {
      out = await jfetch(`${API_BOOKING}?action=create`, { method:"POST", body: JSON.stringify(payload) });
    }
    if (!out.success) throw new Error(out.message || "Không lưu được");

    closeModal();
    await loadBookings();   // cập nhật bảng ngay
  }catch(err){
    alert(err.message);     // sẽ in snippet HTML nếu backend trả non-JSON
  }
});

// =================== DELETE ===================
async function deleteBooking(id){
  if (!confirm("Xóa booking này?")) return;
  try{
    const out = await jfetch(`${API_BOOKING}?action=delete&id=${id}`, { method:"DELETE" });
    if (!out.success) throw new Error(out.message || "Xóa thất bại");
    await loadBookings();
  }catch(err){ alert(err.message); }
}
window.deleteBooking = deleteBooking;

// =================== INIT ===================
document.addEventListener("DOMContentLoaded", async ()=>{
  await Promise.all([loadCustomers(), loadRooms(), loadServices()]);
  await loadBookings();
  document.getElementById("btnNewBooking").addEventListener("click", openNew);
  document.getElementById("btnAddService").addEventListener("click", addSelectedService);
  document.getElementById("btnCancel").addEventListener("click", closeBookingModal);
  document.getElementById("btnCloseModal").addEventListener("click", closeBookingModal);
});
