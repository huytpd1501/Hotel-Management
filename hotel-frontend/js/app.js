
const BASE = "http://localhost:8000/hotel/hotel-api-php/src/controllers";

async function apiGet(entity) {
  const res = await fetch(`${BASE}/${entity}Controller.php`);
  return res.json();
}
async function apiPost(entity, body) {
  const res = await fetch(`${BASE}/${entity}Controller.php`, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(body)
  });
  return res.json();
}
async function apiPut(entity, id, body) {
  const res = await fetch(`${BASE}/${entity}Controller.php?id=${id}`, {
    method: 'PUT',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(body)
  });
  return res.json();
}
async function apiDelete(entity, id) {
  const res = await fetch(`${BASE}/${entity}Controller.php?id=${id}`, { method: 'DELETE' });
  return res.json();
}

async function loadRooms() {
  const data = await apiGet('Room');
  const tbody = document.getElementById('roomTableBody');
  if(!tbody) return;
  tbody.innerHTML = '';
  data.forEach((r, i) => {
    const tr = document.createElement('tr');
    tr.className = 'border-b hover:bg-gray-50';
    tr.innerHTML = `
      <td class="py-3 px-4">${i+1}</td>
      <td class="py-3 px-4">${r.room_number}</td>
      <td class="py-3 px-4">${r.room_type}</td>
      <td class="py-3 px-4">${r.status}</td>
      <td class="py-3 px-4">${Number(r.price_per_night).toLocaleString()}₫</td>
      <td class="py-3 px-4 text-right">
        <button class="btn-edit mr-2 text-blue-600" data-id="${r.room_id}">Edit</button>
        <button class="btn-del text-red-600" data-id="${r.room_id}">Delete</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
  document.querySelectorAll('.btn-edit').forEach(btn=>{
    btn.onclick = async ()=> {
      const id = btn.dataset.id;
      const item = (await apiGet('Room')).find(x=>String(x.room_id)===String(id));
      if(!item) return alert('Not found');
      document.getElementById('room_id').value = item.room_id;
      document.getElementById('room_number').value = item.room_number;
      document.getElementById('room_type').value = item.room_type;
      document.getElementById('price_per_night').value = item.price_per_night;
      document.getElementById('status').value = item.status;
      showModal('roomModal');
    };
  });
  document.querySelectorAll('.btn-del').forEach(btn=>{
    btn.onclick = async ()=> {
      if(!confirm('Bạn có muốn xóa phòng này?')) return;
      const id = btn.dataset.id;
      await apiDelete('Room', id);
      await loadRooms();
    };
  });
}

function showModal(id) { document.getElementById(id).classList.remove('hidden'); }
function hideModal(id) { document.getElementById(id).classList.add('hidden'); }

async function saveRoom(e) {
  e.preventDefault();
  const id = document.getElementById('room_id').value;
  const payload = {
    room_number: document.getElementById('room_number').value,
    room_type: document.getElementById('room_type').value,
    status: document.getElementById('status').value,
    price_per_night: Number(document.getElementById('price_per_night').value),
    description: document.getElementById('description').value
  };
  if(id) {
    await apiPut('Room', id, payload);
  } else {
    await apiPost('Room', payload);
  }
  hideModal('roomModal');
  await loadRooms();
}

document.addEventListener('DOMContentLoaded', ()=> {
  if(document.getElementById('roomCount')) {
    apiGet('Room').then(d=>document.getElementById('roomCount').textContent = d.length);
    apiGet('Customer').then(d=>document.getElementById('customerCount').textContent = d.length);
    apiGet('Booking').then(d=>document.getElementById('bookingCount').textContent = d.length);
  }
  if(document.getElementById('roomTableBody')) {
    loadRooms();
    document.getElementById('btnNewRoom').onclick = ()=> {
      document.getElementById('room_id').value = '';
      document.getElementById('roomForm').reset();
      showModal('roomModal');
    };
    document.getElementById('roomForm').onsubmit = saveRoom;
    document.getElementById('roomModalClose').onclick = ()=> hideModal('roomModal');
    document.getElementById('roomModalCloseBottom').onclick = ()=> hideModal('roomModal');
  }
});
