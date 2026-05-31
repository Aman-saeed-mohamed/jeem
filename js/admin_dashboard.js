/* 
   Admin Dashboard Logic 
*/

// --- Mock Data ---
let users = [
    { id: "USR-001", name: "Ahmed Ali", email: "ahmed@example.com", role: "Customer", status: "active" },
    { id: "USR-002", name: "Fatima Noor", email: "fatima@example.com", role: "Manager", status: "active" },
    { id: "USR-003", name: "Omar Hassan", email: "omar@example.com", role: "Customer", status: "suspended" }
];

let shops = [
    { id: "SHP-101", name: "Electro World", owner: "Fatima Noor", sales: "$45,200", status: "verified" },
    { id: "SHP-102", name: "Fashion Hub", owner: "Zayd Amin", sales: "$12,300", status: "pending" },
    { id: "SHP-103", name: "Home Essentials", owner: "Sara Sami", sales: "$89,000", status: "verified" },
    { id: "SHP-104", name: "Gizmo Store", owner: "Ali Kareem", sales: "$2,100", status: "banned" }
];

// --- Initialization ---
document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initChart();
    initModals();
    
    // Render
    renderUsers();
    renderShops();
    updateStats();
});

// --- Routing ---
function initNavigation() {
    const navItems = document.querySelectorAll('.nav-links .nav-item[data-target]');
    const sections = document.querySelectorAll('.page-section');

    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            navItems.forEach(nav => nav.classList.remove('active'));
            sections.forEach(sec => sec.classList.remove('active'));
            
            item.classList.add('active');
            const targetId = item.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');
        });
    });
}

// --- Home / Analytics ---
function updateStats() {
    document.getElementById('totalUsersStat').innerText = users.length;
    document.getElementById('totalShopsStat').innerText = shops.filter(s => s.status !== 'banned').length;
}

function initChart() {
    const ctx = document.getElementById('growthChart');
    if(!ctx) return;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['October', 'November', 'December', 'January', 'February', 'March'],
            datasets: [{
                label: 'Platform Revenue ($)',
                data: [150000, 200000, 250000, 180000, 300000, 420000],
                backgroundColor: '#177f9e',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: '#eee' } },
                x: { grid: { display: false } }
            }
        }
    });
}

// --- Manage Users ---
function renderUsers() {
    const tbody = document.getElementById('usersTableBody');
    tbody.innerHTML = '';
    
    users.forEach(u => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>${u.id}</strong></td>
            <td>${u.name}</td>
            <td>${u.email}</td>
            <td>${u.role}</td>
            <td><span class="status-badge status-${u.status}">${u.status}</span></td>
            <td>
                <div class="table-actions">
                    <button class="btn btn-outline" onclick="editUser('${u.id}')" title="Edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="btn btn-danger" onclick="deleteUser('${u.id}')" title="Delete"><i class="fa-solid fa-trash"></i></button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
    updateStats();
}

function editUser(id) {
    const user = users.find(u => u.id === id);
    if(user) {
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUserName').value = user.name;
        document.getElementById('editUserEmail').value = user.email;
        document.getElementById('editUserStatus').value = user.status;
        document.getElementById('editUserModal').classList.add('active');
    }
}

function deleteUser(id) {
    if(confirm("Are you sure you want to completely delete this user?")) {
        users = users.filter(u => u.id !== id);
        renderUsers();
        showToast("User deleted successfully.", "success");
    }
}

// --- Manage Shops ---
function renderShops() {
    const tbody = document.getElementById('shopsTableBody');
    tbody.innerHTML = '';
    
    shops.forEach(s => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>${s.id}</strong></td>
            <td>${s.name}</td>
            <td>${s.owner}</td>
            <td>${s.sales}</td>
            <td><span class="status-badge status-${s.status}">${s.status}</span></td>
            <td>
                <div class="table-actions">
                    <button class="btn btn-outline" onclick="editShop('${s.id}')" title="Edit Status"><i class="fa-solid fa-pen"></i></button>
                    <button class="btn btn-danger" onclick="deleteShop('${s.id}')" title="Delete"><i class="fa-solid fa-trash"></i></button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
    updateStats();
}

function editShop(id) {
    const shop = shops.find(s => s.id === id);
    if(shop) {
        document.getElementById('editShopId').value = shop.id;
        document.getElementById('editShopName').value = shop.name;
        document.getElementById('editShopStatus').value = shop.status;
        document.getElementById('editShopModal').classList.add('active');
    }
}

function deleteShop(id) {
    if(confirm("Are you sure you want to permanently delete this shop?")) {
        shops = shops.filter(s => s.id !== id);
        renderShops();
        showToast("Shop deleted successfully.", "success");
    }
}

// --- Modals Setup ---
function initModals() {
    const userModal = document.getElementById('editUserModal');
    const shopModal = document.getElementById('editShopModal');
    
    // Close Btns
    document.querySelectorAll('.close-btn, .cancel-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            userModal.classList.remove('active');
            shopModal.classList.remove('active');
        });
    });

    // Form Submits
    document.getElementById('editUserForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const id = document.getElementById('editUserId').value;
        const index = users.findIndex(u => u.id === id);
        if(index !== -1) {
            users[index].name = document.getElementById('editUserName').value;
            users[index].email = document.getElementById('editUserEmail').value;
            users[index].status = document.getElementById('editUserStatus').value;
            renderUsers();
            userModal.classList.remove('active');
            showToast("User updated successfully.", "success");
        }
    });

    document.getElementById('editShopForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const id = document.getElementById('editShopId').value;
        const index = shops.findIndex(s => s.id === id);
        if(index !== -1) {
            shops[index].name = document.getElementById('editShopName').value;
            shops[index].status = document.getElementById('editShopStatus').value;
            renderShops();
            shopModal.classList.remove('active');
            showToast("Shop updated successfully.", "success");
        }
    });
}

// --- Utils ---
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-circle-exclamation';
    
    toast.innerHTML = `
        <i class="fa-solid ${icon}"></i>
        <div>${message}</div>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s forwards reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
